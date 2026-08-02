<?php

namespace App\Console\Commands;

use App\Models\EnterpriseLicense;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rattache les comptes entreprise existants à des licences (migration / données historiques).
 */
class EnterpriseBackfillLicensesCommand extends Command
{
    protected $signature = 'enterprise:backfill-licenses
                            {--dry-run : Afficher le plan sans écrire en base}
                            {--without-nif : Créer une licence par compte entreprise sans NIF (sinon ignorés)}';

    protected $description = 'Crée des licences et lie chaque groupe d’entreprise (NIF) ; optionnellement les comptes sans NIF.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $withoutNif = (bool) $this->option('without-nif');

        $creatorId = User::query()
            ->where('is_platform_admin', true)
            ->orderBy('id')
            ->value('id');

        if ($creatorId === null) {
            $creatorId = User::query()->orderBy('id')->value('id');
        }

        if ($creatorId === null) {
            $this->error('Aucun utilisateur en base : impossible de définir created_by_user_id pour les licences.');

            return self::FAILURE;
        }

        $seatCap = (int) config('licensing.enterprise_max_users_per_license', 3);

        $this->info($dryRun ? 'Mode simulation (aucune écriture).' : 'Écriture en base activée.');
        $this->newLine();

        $createdLicenses = 0;
        $updatedUsers = 0;
        $skippedNoNif = 0;

        // 1) Comptes entreprise avec NIF : une licence par NIF normalisé
        $clientsWithNif = User::query()
            ->clients()
            ->whereNotNull('company_tax_id')
            ->where('company_tax_id', '!=', '')
            ->get(['id', 'company_tax_id', 'company_name', 'enterprise_license_id']);

        $byNif = $clientsWithNif->groupBy(fn (User $u) => EnterpriseLicense::normalizeCompanyTaxId($u->company_tax_id));

        foreach ($byNif as $nifNorm => $group) {
            /** @var Collection<int, User> $group */
            if ($nifNorm === '') {
                continue;
            }

            $userIds = $group->pluck('id')->all();
            $count = $group->count();

            $existingLicenseIds = $group->pluck('enterprise_license_id')->filter()->unique()->values();

            if ($existingLicenseIds->count() > 1) {
                $this->warn("NIF {$nifNorm} : plusieurs licences différentes déjà présentes — intervention manuelle requise. Ignoré.");

                continue;
            }

            if ($existingLicenseIds->count() === 1) {
                $licenseId = (int) $existingLicenseIds->first();
                $license = EnterpriseLicense::query()->find($licenseId);
                if ($license === null) {
                    $this->warn("NIF {$nifNorm} : licence id {$licenseId} introuvable. Ignoré.");

                    continue;
                }

                $toAttach = $group->whereNull('enterprise_license_id');
                if ($toAttach->isEmpty()) {
                    $this->line("NIF {$nifNorm} : déjà entièrement rattaché à la licence {$license->license_key}.");

                    continue;
                }

                $needed = $license->seatsUsed() + $toAttach->count();
                if ($needed > $license->max_seats) {
                    $newMax = max($needed, $seatCap);
                    $this->line("NIF {$nifNorm} : extension des sièges {$license->max_seats} → {$newMax} pour la licence existante.");
                    if (! $dryRun) {
                        $license->update(['max_seats' => $newMax]);
                    }
                }

                if (! $dryRun) {
                    User::query()->whereIn('id', $toAttach->pluck('id'))->update(['enterprise_license_id' => $license->id]);
                    $license->syncPrimaryWorkspaceUser();
                }
                $updatedUsers += $toAttach->count();
                $this->info("NIF {$nifNorm} : {$toAttach->count()} compte(s) rattaché(s) à la licence existante {$license->license_key}.");

                continue;
            }

            // Aucune licence : en créer une
            $maxSeats = max($seatCap, $count);
            $label = 'Rattrapage auto — '.$nifNorm;

            if ($dryRun) {
                $this->info("[simulation] NIF {$nifNorm} : créer licence « {$label} », max_seats={$maxSeats}, lier {$count} utilisateur(s).");
                $createdLicenses++;
                $updatedUsers += $count;

                continue;
            }

            DB::transaction(function () use ($creatorId, $nifNorm, $maxSeats, $label, $userIds, &$createdLicenses, &$updatedUsers) {
                $license = EnterpriseLicense::query()->create([
                    'license_key' => EnterpriseLicense::generateUniqueKey(),
                    'assigned_company_tax_id' => $nifNorm,
                    'label' => $label,
                    'max_seats' => $maxSeats,
                    'notes' => 'Créée par la commande enterprise:backfill-licenses',
                    'created_by_user_id' => $creatorId,
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);
                $createdLicenses++;
                User::query()->whereIn('id', $userIds)->update(['enterprise_license_id' => $license->id]);
                $license->syncPrimaryWorkspaceUser();
                $updatedUsers += count($userIds);
            });

            $this->info("NIF {$nifNorm} : licence créée, {$count} utilisateur(s) liés.");
        }

        // 2) Sans NIF
        $withoutNifUsers = User::query()
            ->clients()
            ->where(function ($q) {
                $q->whereNull('company_tax_id')->orWhere('company_tax_id', '');
            })
            ->whereNull('enterprise_license_id')
            ->get(['id', 'email', 'company_name']);

        if ($withoutNifUsers->isEmpty()) {
            $this->newLine();
            $this->line('Aucun compte entreprise sans NIF à traiter.');
        } elseif (! $withoutNif) {
            $skippedNoNif = $withoutNifUsers->count();
            $this->newLine();
            $this->warn("{$skippedNoNif} compte(s) entreprise sans NIF ignoré(s). Relancez avec --without-nif pour leur créer une licence individuelle chacun.");
        } else {
            foreach ($withoutNifUsers as $u) {
                if ($dryRun) {
                    $this->line("[simulation] Sans NIF user #{$u->id} ({$u->email}) : créer licence dédiée, 1 siège.");
                    $createdLicenses++;
                    $updatedUsers++;

                    continue;
                }

                DB::transaction(function () use ($creatorId, $seatCap, $u, &$createdLicenses, &$updatedUsers) {
                    $license = EnterpriseLicense::query()->create([
                        'license_key' => EnterpriseLicense::generateUniqueKey(),
                        'assigned_company_tax_id' => null,
                        'label' => 'Rattrapage auto — sans NIF (user '.$u->id.')',
                        'max_seats' => $seatCap,
                        'notes' => 'Compte sans NIF — enterprise:backfill-licenses --without-nif',
                        'created_by_user_id' => $creatorId,
                        'expires_at' => null,
                        'revoked_at' => null,
                    ]);
                    $createdLicenses++;
                    $u->update(['enterprise_license_id' => $license->id]);
                    $license->syncPrimaryWorkspaceUser();
                    $updatedUsers++;
                });
                $this->info("User #{$u->id} ({$u->email}) : licence individuelle créée.");
            }
        }

        $this->newLine();
        $this->table(
            ['Licences créées', 'Rattachements utilisateurs', 'Ignorés (sans NIF, sans option)'],
            [[$createdLicenses, $updatedUsers, $skippedNoNif]]
        );

        return self::SUCCESS;
    }
}
