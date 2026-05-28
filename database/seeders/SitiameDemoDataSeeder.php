<?php

namespace Database\Seeders;

use App\Models\AccountingEntry;
use App\Models\AppNotification;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\UserPremiumService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de démonstration : fiche entreprise (FIRD) et écritures de CA pour le premier utilisateur.
 */
class SitiameDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->orderBy('id')->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Démo Sitiame Capitale',
                'email' => 'demo@sitiame-capitale.local',
                'password' => Hash::make('DemoSitiame2026!'),
                'email_verified_at' => now(),
            ]);
            $this->command?->info('Compte démo créé : demo@sitiame-capitale.local / DemoSitiame2026!');
        }

        $today = Carbon::today();

        $user->forceFill([
            'company_name' => 'Sitiame Capitale SARL',
            'company_designation' => 'Sitiame Capitale — Solutions de gestion financière et comptable',
            'company_sigle' => 'SC',
            'company_tax_id' => 'CI-ABJ-2026-F-00012345',
            'sector' => 'Technologies financières et logiciels de gestion',
            'address' => 'Plateau, avenue Chardy',
            'city' => 'Abidjan',
            'phone' => '+225 07 00 00 00 00',
            'rccm' => 'CI-ABJ-01-2026-B-12345',
            'company_directory_number' => 'RE-CI-889900',
            'social_security_number' => 'CNPS-778899',
            'importer_code' => 'IMP-001122',
            'primary_activity_code' => '6201Z',
            'postal_code' => '01 BP',
            'po_box' => '1234',
            'company_fax' => '+225 20 00 00 00',
            'full_geographic_address' => 'Immeuble Horizon, 3e étage, Plateau, Abidjan, Côte d’Ivoire',
            'fiscal_year_end_date' => $today->copy()->month(12)->day(31),
            'fiscal_year_duration_months' => 12,
            'accounting_period_from' => $today->copy()->startOfYear(),
            'accounting_period_to' => $today->copy()->endOfYear(),
            'accounts_effective_closing_date' => $today->copy()->month(3)->day(15),
            'previous_fiscal_year_end' => $today->copy()->subYear()->month(12)->day(31),
            'previous_fiscal_year_duration_months' => 12,
            'main_activity_description' => 'Édition de logiciels de gestion de trésorerie et de comptabilité pour PME ; accompagnement et formation.',
            'production_capacity_utilization_pct' => 72.5,
            'contact_person_name' => 'Direction générale',
            'contact_person_title' => 'Gérant',
            'contact_person_address' => 'Même adresse que le siège social',
            'accountant_type' => 'firm',
            'accountant_name' => 'Cabinet Expertise & Conseils',
            'accountant_address' => 'Cocody Riviera, Abidjan',
            'accountant_phone' => '+225 05 11 22 33 44',
            'company_auditors' => [
                ['name' => 'Audit Côte d’Ivoire', 'address' => 'Marcory, Abidjan'],
            ],
            'certified_financial_statements' => 'yes_no_reserves',
            'approved_by_general_assembly' => 'yes',
            'financial_statements_signatory_name' => 'Jean Dupont',
            'signatory_qualification' => 'Gérant',
            'financial_statements_signature_date' => $today->copy()->month(6)->day(30),
            'company_bank_accounts' => [
                ['bank' => 'Banque Atlantique', 'account_number' => 'CI93 XXXX XXXX XXXX XXXX XXXX XX'],
                ['bank' => 'SGBCI', 'account_number' => 'CI07 XXXX XXXX XXXX XXXX XXXX XX'],
            ],
        ])->save();

        $demoRefPrefix = 'DEMO-CA-';

        $already = AccountingEntry::where('user_id', $user->id)
            ->where('document_reference', 'like', $demoRefPrefix.'%')
            ->exists();

        if (! $already) {
            for ($i = 11; $i >= 0; $i--) {
                $mStart = $today->copy()->subMonths($i)->startOfMonth();
                $mEnd = $mStart->copy()->endOfMonth();
                $amount = 2_800_000 + ($i * 95_000) + (($user->id % 7) * 10_000);

                AccountingEntry::create([
                    'user_id' => $user->id,
                    'date' => $mEnd->toDateString(),
                    'document_type' => 'Vente',
                    'document_reference' => $demoRefPrefix.$mStart->format('Ym'),
                    'description' => '[Démo] Chiffre d\'affaires mensuel (classe 7)',
                    'debit_account' => '411 Clients',
                    'credit_account' => '701 Ventes de marchandises',
                    'amount' => $amount,
                    'ocr_status' => 'verified',
                    'ocr_verified_at' => now(),
                ]);
            }
        }

        if (TreasuryTransaction::where('user_id', $user->id)->where('reference', 'like', 'DEMO-TX-%')->doesntExist()) {
            $base = $today->copy()->subMonths(2)->startOfMonth();
            TreasuryTransaction::create([
                'user_id' => $user->id,
                'type' => 'encaissement',
                'transaction_type' => 'Virement client',
                'amount' => 1_500_000,
                'description' => '[Démo] Encaissement clients',
                'transaction_date' => $base->copy()->day(5),
                'reference' => 'DEMO-TX-ENC-1',
                'status' => 'effectue',
            ]);
            TreasuryTransaction::create([
                'user_id' => $user->id,
                'type' => 'decaissement',
                'transaction_type' => 'Charges',
                'amount' => 420_000,
                'description' => '[Démo] Paiement fournisseur',
                'transaction_date' => $base->copy()->day(12),
                'reference' => 'DEMO-TX-DEC-1',
                'status' => 'effectue',
            ]);
        }

        if (! AppNotification::where('user_id', $user->id)->where('title', 'like', 'Bienvenue%')->exists()) {
            AppNotification::create([
                'user_id' => $user->id,
                'title' => 'Bienvenue sur '.config('app.name'),
                'body' => 'Consultez le centre d’aide ou écrivez au support depuis le menu Aide & support.',
                'type' => 'info',
                'action_url' => route('support.index'),
            ]);
        }

        $this->command?->info('Données démo Sitiame Capitale appliquées pour l’utilisateur #'.$user->id.'.');

        // Accès volet « Administration » si l’e-mail correspond à PLATFORM_ADMIN_EMAIL (.env).
        $platformAdminEmail = env('PLATFORM_ADMIN_EMAIL');
        if (is_string($platformAdminEmail) && $platformAdminEmail !== '' && $user->email === $platformAdminEmail) {
            $user->forceFill(['is_platform_admin' => true])->save();
            app(UserPremiumService::class)->ensurePlatformAdminPremium($user->fresh());
            $this->command?->info('Compte administrateur plateforme : '.$user->email.' (PLATFORM_ADMIN_EMAIL).');
        }

        if ($user->is_platform_admin) {
            app(UserPremiumService::class)->ensurePlatformAdminPremium($user->fresh());
        }
    }
}
