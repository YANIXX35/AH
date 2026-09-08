<?php

namespace App\Http\Controllers;

use App\Models\FinancingDossier;
use App\Models\User;
use App\Support\ClientWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinancingDossierController extends Controller
{
    public function store(Request $request, User $company): RedirectResponse
    {
        if (! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }

        $dossier = FinancingDossier::createDraftFor($company, $request->user()->id);

        return redirect()->route('analyst.financement.step', [$dossier, 'demande']);
    }

    public function showStep(FinancingDossier $dossier, string $step): View
    {
        $this->authorizeDossier($dossier);
        $this->ensureValidStep($step);

        return view('financial-analyst.financing-dossier.wizard', [
            'dossier' => $dossier,
            'step' => $step,
            'prefilledFields' => $dossier->prefilledFields(),
        ]);
    }

    public function updateStep(Request $request, FinancingDossier $dossier, string $step): RedirectResponse
    {
        $this->authorizeDossier($dossier);
        $this->ensureValidStep($step);

        if ($step === 'demande') {
            $validated = $request->validate([
                'financing_type' => ['required', 'string', 'max:64'],
                'financing_purpose' => ['required', 'string', 'max:64'],
                'amount_requested' => ['required', 'numeric', 'min:1'],
                'desired_term_months' => ['required', 'integer', 'min:1'],
                'grace_period_months' => ['nullable', 'integer', 'min:0'],
                'repayment_frequency' => ['required', 'string', 'max:32'],
                'promoter_contribution' => ['required', 'numeric', 'min:0'],
                'desired_disbursement_date' => ['nullable', 'date'],
                'financing_summary' => ['required', 'string'],
                'repayment_source' => ['required', 'string'],
            ]);

            $dossier->update([
                'financing_type' => $validated['financing_type'],
                'financing_purpose' => $validated['financing_purpose'],
                'amount_requested' => $validated['amount_requested'],
                'desired_term_months' => $validated['desired_term_months'],
                'grace_period_months' => $validated['grace_period_months'] ?? null,
                'repayment_frequency' => $validated['repayment_frequency'],
                'promoter_contribution' => $validated['promoter_contribution'],
                'desired_disbursement_date' => $validated['desired_disbursement_date'] ?? null,
                'financing_summary_data' => [
                    'financing_summary' => $validated['financing_summary'],
                    'repayment_source' => $validated['repayment_source'],
                ],
            ]);
        } elseif ($step === 'entreprise') {
            $validated = $request->validate([
                'legal_name' => ['required', 'string', 'max:255'],
                'trade_name' => ['nullable', 'string', 'max:255'],
                'legal_form' => ['required', 'string', 'max:64'],
                'rccm_number' => ['required', 'string', 'max:64'],
                'taxpayer_number' => ['required', 'string', 'max:64'],
                'incorporation_date' => ['required', 'date'],
                'registered_office' => ['required', 'string', 'max:255'],
                'city_country' => ['required', 'string', 'max:255'],
                'business_sector' => ['required', 'string', 'max:64'],
                'main_activity' => ['required', 'string', 'max:255'],
                'employee_count' => ['nullable', 'integer', 'min:0'],
                'website' => ['nullable', 'string', 'max:255'],
                'company_history' => ['required', 'string'],
                'share_capital' => ['required', 'numeric', 'min:0'],
                'major_shareholders' => ['required', 'string', 'max:255'],
                'beneficial_owners' => ['required', 'string', 'max:255'],
                'authorized_representative' => ['required', 'string', 'max:255'],
            ]);

            $dossier->update($validated);
        }

        return redirect()->route('analyst.financement.step', [$dossier, $this->nextStepKey($step)])
            ->with('status', 'Étape enregistrée.');
    }

    private function authorizeDossier(FinancingDossier $dossier): void
    {
        $company = $dossier->company;
        if (! $company || ! ClientWorkspace::isAssignableClient($company)) {
            abort(404);
        }
    }

    private function ensureValidStep(string $step): void
    {
        if (! in_array($step, array_column(FinancingDossier::STEPS, 'key'), true)) {
            abort(404);
        }
    }

    private function nextStepKey(string $step): string
    {
        $keys = array_column(FinancingDossier::STEPS, 'key');
        $index = array_search($step, $keys, true);

        return $keys[$index + 1] ?? $step;
    }
}
