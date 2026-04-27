<?php

namespace App\Http\Controllers;

use App\Models\PlatformSetting;
use App\Services\AdminAuditTrailService;
use App\Services\Scoring360ExcelImporter;
use App\Support\Scoring360Defaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminScoringParametersController extends Controller
{
    public function __construct(
        private readonly Scoring360ExcelImporter $excelImporter,
        private readonly AdminAuditTrailService $auditTrail
    ) {}

    public function index(Request $request): View
    {
        $setting = PlatformSetting::query()->firstOrCreate(
            ['key' => 'scoring_360'],
            ['value' => Scoring360Defaults::defaults(), 'updated_by' => $request->user()?->id]
        );

        return view('admin.scoring-parameters', [
            'setting' => $setting,
            'config' => (array) ($setting->value ?? Scoring360Defaults::defaults()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = PlatformSetting::query()->firstOrCreate(
            ['key' => 'scoring_360'],
            ['value' => Scoring360Defaults::defaults(), 'updated_by' => $request->user()?->id]
        );

        $data = $request->validate([
            'coefficients.strong' => ['required', 'numeric', 'min:0', 'max:2'],
            'coefficients.medium' => ['required', 'numeric', 'min:0', 'max:2'],
            'coefficients.weak' => ['required', 'numeric', 'min:0', 'max:2'],

            'bank.thresholds.dscr.strong' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.dscr.medium' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.interest_coverage.strong' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.interest_coverage.medium' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.current_ratio.strong' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.current_ratio.medium' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.debt_asset.strong' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.debt_asset.medium' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.bfr_days.strong' => ['required', 'numeric', 'min:0'],
            'bank.thresholds.bfr_days.medium' => ['required', 'numeric', 'min:0'],

            'bank.weights.dscr' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.weights.interest_coverage' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.weights.current_ratio' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.weights.debt_asset' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.weights.bfr_days' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.decision.strong_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'bank.decision.medium_min' => ['required', 'numeric', 'min:0', 'max:100'],

            'investor.thresholds.revenue_growth.strong' => ['required', 'numeric'],
            'investor.thresholds.revenue_growth.medium' => ['required', 'numeric'],
            'investor.thresholds.ebitda_margin.strong' => ['required', 'numeric'],
            'investor.thresholds.ebitda_margin.medium' => ['required', 'numeric'],
            'investor.thresholds.roe.strong' => ['required', 'numeric'],
            'investor.thresholds.roe.medium' => ['required', 'numeric'],
            'investor.thresholds.fcf_margin.strong' => ['required', 'numeric'],
            'investor.thresholds.fcf_margin.medium' => ['required', 'numeric'],
            'investor.thresholds.asset_turnover.strong' => ['required', 'numeric', 'min:0'],
            'investor.thresholds.asset_turnover.medium' => ['required', 'numeric', 'min:0'],

            'investor.weights.revenue_growth' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.weights.ebitda_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.weights.roe' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.weights.fcf_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.weights.asset_turnover' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.decision.strong_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'investor.decision.medium_min' => ['required', 'numeric', 'min:0', 'max:100'],

            'internal.thresholds.net_margin.strong' => ['required', 'numeric'],
            'internal.thresholds.net_margin.medium' => ['required', 'numeric'],
            'internal.thresholds.quick_ratio.strong' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.quick_ratio.medium' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.receivable_days.strong' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.receivable_days.medium' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.inventory_days.strong' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.inventory_days.medium' => ['required', 'numeric', 'min:0'],
            'internal.thresholds.ebitda_growth.strong' => ['required', 'numeric'],
            'internal.thresholds.ebitda_growth.medium' => ['required', 'numeric'],

            'internal.weights.net_margin' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.weights.quick_ratio' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.weights.receivable_days' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.weights.inventory_days' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.weights.ebitda_growth' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.decision.strong_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'internal.decision.medium_min' => ['required', 'numeric', 'min:0', 'max:100'],

            'composite.weights.bank' => ['required', 'numeric', 'min:0', 'max:100'],
            'composite.weights.investor' => ['required', 'numeric', 'min:0', 'max:100'],
            'composite.weights.internal' => ['required', 'numeric', 'min:0', 'max:100'],
            'composite.decision.strong_min' => ['required', 'numeric', 'min:0', 'max:100'],
            'composite.decision.medium_min' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->assertWeightsSums($data);

        $current = (array) ($setting->value ?? Scoring360Defaults::defaults());
        $next = $current;

        $next['coefficients'] = $data['coefficients'];

        foreach (['bank', 'investor', 'internal'] as $block) {
            $next[$block] = array_merge((array) ($next[$block] ?? []), [
                'thresholds' => array_replace_recursive((array) ($next[$block]['thresholds'] ?? []), (array) ($data[$block]['thresholds'] ?? [])),
                'weights' => array_replace((array) ($next[$block]['weights'] ?? []), (array) ($data[$block]['weights'] ?? [])),
                'decision' => array_replace((array) ($next[$block]['decision'] ?? []), [
                    'strong_min' => $data[$block]['decision']['strong_min'] ?? null,
                    'medium_min' => $data[$block]['decision']['medium_min'] ?? null,
                ]),
            ]);
        }

        $next['composite'] = array_merge((array) ($next['composite'] ?? []), [
            'weights' => array_replace((array) ($next['composite']['weights'] ?? []), (array) ($data['composite']['weights'] ?? [])),
            'decision' => array_replace((array) ($next['composite']['decision'] ?? []), [
                'strong_min' => $data['composite']['decision']['strong_min'] ?? null,
                'medium_min' => $data['composite']['decision']['medium_min'] ?? null,
            ]),
        ]);

        $next['meta'] = array_merge((array) ($next['meta'] ?? []), [
            'updated_at' => now()->toIso8601String(),
        ]);

        $before = (array) ($setting->value ?? []);
        $setting->update([
            'value' => $next,
            'updated_by' => $request->user()?->id,
        ]);
        $this->auditTrail->log(
            'scoring.update',
            PlatformSetting::class,
            $setting->id,
            $request->user()?->id,
            $before,
            (array) ($setting->fresh()?->value ?? []),
            null,
            $request
        );

        return back()->with('status', 'Paramètres de scoring enregistrés.');
    }

    public function import(Request $request): RedirectResponse
    {
        $file = $request->validate([
            'excel' => ['required', 'file', 'mimes:xlsx'],
        ])['excel'];

        /** @var \Illuminate\Http\UploadedFile $file */
        $config = $this->excelImporter->import($file->getRealPath(), $file->getClientOriginalName());

        $setting = PlatformSetting::query()->firstOrCreate(
            ['key' => 'scoring_360'],
            ['value' => Scoring360Defaults::defaults(), 'updated_by' => $request->user()?->id]
        );

        $before = (array) ($setting->value ?? []);
        $setting->update([
            'value' => $config,
            'updated_by' => $request->user()?->id,
        ]);
        $this->auditTrail->log(
            'scoring.import',
            PlatformSetting::class,
            $setting->id,
            $request->user()?->id,
            $before,
            (array) ($setting->fresh()?->value ?? []),
            ['source_file' => $file->getClientOriginalName()],
            $request
        );

        return back()->with('status', 'Paramètres importés depuis le fichier Excel.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertWeightsSums(array $data): void
    {
        $sumBank = array_sum(array_map('floatval', (array) ($data['bank']['weights'] ?? [])));
        $sumInvestor = array_sum(array_map('floatval', (array) ($data['investor']['weights'] ?? [])));
        $sumInternal = array_sum(array_map('floatval', (array) ($data['internal']['weights'] ?? [])));
        $sumComposite = array_sum(array_map('floatval', (array) ($data['composite']['weights'] ?? [])));

        $errors = [];
        if (abs($sumBank - 100.0) > 0.001) {
            $errors['bank.weights'] = "La somme des poids Banque doit être 100 (actuel : {$sumBank}).";
        }
        if (abs($sumInvestor - 100.0) > 0.001) {
            $errors['investor.weights'] = "La somme des poids Investisseur doit être 100 (actuel : {$sumInvestor}).";
        }
        if (abs($sumInternal - 100.0) > 0.001) {
            $errors['internal.weights'] = "La somme des poids Interne doit être 100 (actuel : {$sumInternal}).";
        }
        if (abs($sumComposite - 100.0) > 0.001) {
            $errors['composite.weights'] = "La somme des poids Composite doit être 100 (actuel : {$sumComposite}).";
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}

