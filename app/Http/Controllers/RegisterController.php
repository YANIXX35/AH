<?php

namespace App\Http\Controllers;

use App\Mail\AccountCreatedMail;
use App\Models\BillingPlan;
use App\Models\BillingSubscription;
use App\Models\EnterpriseLicense;
use App\Models\KycDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /**
     * Inscription entreprise ; rattachement optionnel à une licence multi-utilisateurs.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => [
                Rule::requiredIf(fn () => $request->filled('license_key')),
                'nullable',
                'string',
                'max:255',
            ],
            'company_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx', 'max:5120'],
            'sector' => ['nullable', 'string', 'max:255'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:5120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'license_key' => ['nullable', 'string', 'max:64'],
        ]);

        $licenseKeyInput = $validated['license_key'] ?? null;

        if ($request->hasFile('company_logo')) {
            $validated['company_logo'] = $request->file('company_logo')->store('company-logos', 'public');
        }

        if ($request->hasFile('trade_register')) {
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }

        unset($validated['trade_register'], $validated['license_key']);

        $user = DB::transaction(function () use ($validated, $licenseKeyInput) {
            $enterpriseLicenseId = null;
            $license = null;

            if (! empty($licenseKeyInput)) {
                $normalizedKey = EnterpriseLicense::normalizeKey($licenseKeyInput);
                /** @var EnterpriseLicense|null $license */
                $license = EnterpriseLicense::query()
                    ->where('license_key', $normalizedKey)
                    ->lockForUpdate()
                    ->first();

                if ($license === null) {
                    throw ValidationException::withMessages([
                        'license_key' => 'Clé de licence inconnue. Vérifiez la saisie ou contactez l’administrateur.',
                    ]);
                }
                if (! $license->isUsable()) {
                    throw ValidationException::withMessages([
                        'license_key' => 'Cette licence n’est plus valide (révoquée ou expirée).',
                    ]);
                }

                $taxNorm = EnterpriseLicense::normalizeCompanyTaxId($validated['company_tax_id'] ?? null);

                if (! $license->isLockedToCompany()) {
                    if ($taxNorm === '') {
                        throw ValidationException::withMessages([
                            'company_tax_id' => 'Le NIF / identifiant fiscal est obligatoire pour activer une licence (une licence = une seule entreprise).',
                        ]);
                    }
                    $license->assigned_company_tax_id = $taxNorm;
                    $license->save();
                } elseif (! $license->acceptsCompanyTaxId($validated['company_tax_id'] ?? null)) {
                    throw ValidationException::withMessages([
                        'license_key' => 'Cette licence est déjà attribuée à une autre entreprise ; le NIF saisi ne correspond pas.',
                    ]);
                }

                if (! $license->hasAvailableSeat()) {
                    throw ValidationException::withMessages([
                        'license_key' => 'Nombre maximum d’utilisateurs atteint pour cette licence ('.$license->max_seats.').',
                    ]);
                }

                $enterpriseLicenseId = $license->id;
            }

            $created = User::create([
                ...$validated,
                'password' => Hash::make($validated['password']),
                'enterprise_license_id' => $enterpriseLicenseId,
                'kyc_status' => 'submitted',
                'kyc_submitted_at' => now(),
            ]);

            // Chaque nouvel inscrit dispose de sa propre donnée d'abonnement.
            $freePlan = BillingPlan::query()->where('slug', 'free-trial')->first();
            if ($freePlan !== null) {
                BillingSubscription::query()->create([
                    'user_id' => $created->id,
                    'billing_plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                    'next_billing_at' => now(),
                    'auto_renew' => true,
                    'meta' => [
                        'source' => 'registration',
                        'requires_payment_for_accounting' => true,
                    ],
                ]);
            }

            $kycDocuments = [];
            if (! empty($validated['trade_register_file'])) {
                $kycDocuments[] = [
                    'document_type' => 'trade_register',
                    'stored_path' => (string) $validated['trade_register_file'],
                    'original_name' => 'Registre de commerce',
                ];
            }
            if (! empty($validated['company_logo'])) {
                $kycDocuments[] = [
                    'document_type' => 'company_logo',
                    'stored_path' => (string) $validated['company_logo'],
                    'original_name' => 'Logo entreprise',
                ];
            }
            foreach ($kycDocuments as $doc) {
                KycDocument::query()->create([
                    'user_id' => $created->id,
                    'document_type' => $doc['document_type'],
                    'stored_path' => $doc['stored_path'],
                    'original_name' => $doc['original_name'],
                    'status' => 'pending',
                    'submitted_at' => now(),
                ]);
            }

            if ($license !== null) {
                $license->syncPrimaryWorkspaceUser();
            }

            return $created;
        });

        Auth::login($user);
        $request->session()->regenerate();

        try {
            Mail::to($user->email)->send(new AccountCreatedMail($user));
            Log::info('account_created_mail_sent', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Throwable $e) {
            Log::warning('account_created_mail_failed', [
                'email' => $user->email,
                'message' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('payments.sandbox')
            ->with('status', 'Inscription réussie. Pour activer la fonctionnalité Comptabilité, veuillez effectuer le paiement de l’abonnement Enterprise Premium (15 000 FCFA).');
    }
}
