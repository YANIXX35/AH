<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyFirdController extends Controller
{
    use UsesClientWorkspace;

    /**
     * Formulaire FIRD (fiche d’identification entreprise) — aligné sur le modèle administratif courant.
     */
    public function edit(Request $request)
    {
        $user = User::query()->findOrFail($this->workspaceUserId());

        return view('profile-company-fird', [
            'user' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = User::query()->findOrFail($this->workspaceUserId());

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'company_designation' => ['nullable', 'string', 'max:512'],
            'company_sigle' => ['nullable', 'string', 'max:255'],
            'company_tax_id' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'fiscal_year_end_date' => ['nullable', 'date'],
            'fiscal_year_duration_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'accounting_period_from' => ['nullable', 'date'],
            'accounting_period_to' => ['nullable', 'date', 'after_or_equal:accounting_period_from'],
            'accounts_effective_closing_date' => ['nullable', 'date'],
            'previous_fiscal_year_end' => ['nullable', 'date'],
            'previous_fiscal_year_duration_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'rccm' => ['nullable', 'string', 'max:255'],
            'company_directory_number' => ['nullable', 'string', 'max:128'],
            'social_security_number' => ['nullable', 'string', 'max:128'],
            'importer_code' => ['nullable', 'string', 'max:128'],
            'primary_activity_code' => ['nullable', 'string', 'max:32'],
            'company_designation' => ['nullable', 'string', 'max:512'],
            'company_fax' => ['nullable', 'string', 'max:64'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'po_box' => ['nullable', 'string', 'max:64'],
            'full_geographic_address' => ['nullable', 'string', 'max:2000'],
            'main_activity_description' => ['nullable', 'string', 'max:5000'],
            'production_capacity_utilization_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_person_name' => ['nullable', 'string', 'max:255'],
            'contact_person_address' => ['nullable', 'string', 'max:2000'],
            'contact_person_title' => ['nullable', 'string', 'max:255'],
            'accountant_type' => ['nullable', 'in:none,internal,firm'],
            'accountant_name' => ['nullable', 'string', 'max:512'],
            'accountant_address' => ['nullable', 'string', 'max:2000'],
            'accountant_phone' => ['nullable', 'string', 'max:64'],
            'auditor_name' => ['nullable', 'array', 'max:5'],
            'auditor_name.*' => ['nullable', 'string', 'max:512'],
            'auditor_address' => ['nullable', 'array', 'max:5'],
            'auditor_address.*' => ['nullable', 'string', 'max:2000'],
            'certified_financial_statements' => ['nullable', 'in:not_subject,no_refusal,yes_reserves,yes_no_reserves'],
            'approved_by_general_assembly' => ['nullable', 'in:not_subject,no,yes'],
            'financial_statements_signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_qualification' => ['nullable', 'string', 'max:255'],
            'financial_statements_signature_date' => ['nullable', 'date'],
            'bank_name' => ['nullable', 'array', 'max:8'],
            'bank_name.*' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'array', 'max:8'],
            'bank_account_number.*' => ['nullable', 'string', 'max:128'],
            'trade_register' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $auditors = [];
        $names = $validated['auditor_name'] ?? [];
        $addresses = $validated['auditor_address'] ?? [];
        for ($i = 0; $i < 5; $i++) {
            $n = trim((string) ($names[$i] ?? ''));
            $a = trim((string) ($addresses[$i] ?? ''));
            if ($n !== '' || $a !== '') {
                $auditors[] = ['name' => $n, 'address' => $a];
            }
        }

        $banks = [];
        $bankNames = $validated['bank_name'] ?? [];
        $bankNums = $validated['bank_account_number'] ?? [];
        for ($i = 0; $i < 8; $i++) {
            $bn = trim((string) ($bankNames[$i] ?? ''));
            $ba = trim((string) ($bankNums[$i] ?? ''));
            if ($bn !== '' || $ba !== '') {
                $banks[] = ['bank' => $bn, 'account_number' => $ba];
            }
        }

        unset($validated['auditor_name'], $validated['auditor_address'], $validated['bank_name'], $validated['bank_account_number']);

        $validated['company_auditors'] = $auditors;
        $validated['company_bank_accounts'] = $banks;

        if ($request->hasFile('trade_register')) {
            if ($user->trade_register_file) {
                Storage::disk('public')->delete($user->trade_register_file);
            }
            $validated['trade_register_file'] = $request->file('trade_register')->store('trade-registers', 'public');
        }
        unset($validated['trade_register']);

        $user->update($validated);

        return redirect()
            ->route('profile.company.fird')
            ->with('status', 'Fiche entreprise (FIRD) enregistrée. Ces informations seront utilisées pour les mises à jour des états et documents.');
    }
}
