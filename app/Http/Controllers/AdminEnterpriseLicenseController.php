<?php

namespace App\Http\Controllers;

use App\Models\EnterpriseLicense;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminEnterpriseLicenseController extends Controller
{
    /**
     * Liste des licences et formulaire de création.
     */
    public function index(Request $request): View
    {
        $licenses = EnterpriseLicense::query()
            ->with('createdBy:id,name,email')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $enterprises = $this->enterprisesForAssignSelect();

        $licensesAssignable = EnterpriseLicense::query()
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get(['id', 'license_key', 'label', 'max_seats', 'assigned_company_tax_id']);

        return view('admin.licenses', [
            'licenses' => $licenses,
            'licensesAssignable' => $licensesAssignable,
            'defaultMaxSeats' => (int) config('licensing.enterprise_max_users_per_license', 3),
            'enterprises' => $enterprises,
        ]);
    }

    /**
     * Entreprises clientes distinctes (NIF renseigné) pour attribution de licence.
     *
     * @return \Illuminate\Support\Collection<int, object{tax_norm: string, company_name: string|null, users_count: int}>
     */
    protected function enterprisesForAssignSelect()
    {
        $rows = User::query()
            ->clients()
            ->whereNotNull('company_tax_id')
            ->where('company_tax_id', '!=', '')
            ->get(['company_tax_id', 'company_name']);

        return $rows
            ->groupBy(fn (User $u) => EnterpriseLicense::normalizeCompanyTaxId($u->company_tax_id))
            ->map(function ($group, string $taxNorm) {
                /** @var \Illuminate\Support\Collection<int, User> $group */
                $first = $group->first();

                return (object) [
                    'tax_norm' => $taxNorm,
                    'company_name' => $first?->company_name,
                    'users_count' => $group->count(),
                ];
            })
            ->values()
            ->sortBy(fn ($e) => $e->company_name ?? $e->tax_norm);
    }

    /**
     * Rattache une licence à une entreprise existante (tous les comptes « entreprise » avec le même NIF).
     */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enterprise_license_id' => ['required', 'integer', 'exists:enterprise_licenses,id'],
            'company_tax_id' => ['required', 'string', 'max:255'],
        ]);

        $taxNorm = EnterpriseLicense::normalizeCompanyTaxId($data['company_tax_id']);
        if ($taxNorm === '') {
            return back()->withErrors(['company_tax_id' => 'Identifiant fiscal invalide ou vide après normalisation.']);
        }

        return DB::transaction(function () use ($data, $taxNorm) {
            /** @var EnterpriseLicense $license */
            $license = EnterpriseLicense::query()->lockForUpdate()->findOrFail($data['enterprise_license_id']);

            if (! $license->isUsable()) {
                return back()->withErrors(['enterprise_license_id' => 'Cette licence n’est pas utilisable (révoquée ou expirée).']);
            }

            if ($license->isLockedToCompany() && $license->assigned_company_tax_id !== $taxNorm) {
                return back()->withErrors(['enterprise_license_id' => 'Cette licence est déjà réservée à une autre entreprise (NIF différent).']);
            }

            $otherActive = EnterpriseLicense::query()
                ->where('assigned_company_tax_id', $taxNorm)
                ->where('id', '!=', $license->id)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($otherActive) {
                return back()->withErrors(['company_tax_id' => 'Une autre licence active est déjà attribuée à cette entreprise (même NIF).']);
            }

            $userIds = User::query()
                ->clients()
                ->whereNotNull('company_tax_id')
                ->where('company_tax_id', '!=', '')
                ->get(['id', 'company_tax_id', 'enterprise_license_id'])
                ->filter(fn (User $u) => EnterpriseLicense::normalizeCompanyTaxId($u->company_tax_id) === $taxNorm)
                ->pluck('id');

            if ($userIds->isEmpty()) {
                return back()->withErrors(['company_tax_id' => 'Aucun compte entreprise trouvé pour ce NIF.']);
            }

            if ($userIds->count() > $license->max_seats) {
                return back()->withErrors([
                    'company_tax_id' => 'Cette entreprise compte '.$userIds->count().' utilisateur(s), ce qui dépasse les '.$license->max_seats.' sièges de la licence.',
                ]);
            }

            $conflict = User::query()
                ->whereIn('id', $userIds)
                ->whereNotNull('enterprise_license_id')
                ->where('enterprise_license_id', '!=', $license->id)
                ->exists();

            if ($conflict) {
                return back()->withErrors([
                    'company_tax_id' => 'Au moins un utilisateur de cette entreprise est déjà rattaché à une autre licence. Retirez l’ancienne attribution avant de continuer.',
                ]);
            }

            User::query()->whereIn('id', $userIds)->update(['enterprise_license_id' => $license->id]);

            if (! $license->isLockedToCompany()) {
                $license->assigned_company_tax_id = $taxNorm;
                $license->save();
            }
            $license->syncPrimaryWorkspaceUser();

            return redirect()
                ->route('admin.licenses.index')
                ->with('status', 'Licence attribuée : '.$userIds->count().' compte(s) entreprise mis à jour pour le NIF '.$taxNorm.'.');
        });
    }

    /**
     * Génère une nouvelle licence (clé unique).
     */
    public function store(Request $request): RedirectResponse
    {
        $seatCap = (int) config('licensing.enterprise_max_users_per_license', 3);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'max_seats' => ['nullable', 'integer', 'min:1', 'max:'.$seatCap],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $maxSeats = (int) ($data['max_seats'] ?? $seatCap);

        EnterpriseLicense::query()->create([
            'license_key' => EnterpriseLicense::generateUniqueKey(),
            'label' => $data['label'] ?? null,
            'notes' => $data['notes'] ?? null,
            'max_seats' => $maxSeats,
            'created_by_user_id' => $request->user()->id,
            'expires_at' => ! empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);

        return redirect()
            ->route('admin.licenses.index')
            ->with('status', 'Licence créée. Communiquez la clé à l’entreprise (copier depuis le tableau).');
    }

    /**
     * Révoque une licence (plus aucune nouvelle inscription possible).
     */
    public function revoke(Request $request, EnterpriseLicense $enterprise_license): RedirectResponse
    {
        if ($enterprise_license->revoked_at !== null) {
            return back()->with('status', 'Cette licence était déjà révoquée.');
        }

        $enterprise_license->update(['revoked_at' => now()]);

        return redirect()
            ->route('admin.licenses.index')
            ->with('status', 'Licence révoquée. Les comptes déjà créés conservent l’accès ; aucune nouvelle inscription avec cette clé.');
    }
}
