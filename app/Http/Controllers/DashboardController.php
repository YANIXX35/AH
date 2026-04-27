<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use App\Services\DashboardMetricsService;
use App\Services\HuggingFaceOpsAssistantService;
use App\Support\ClientWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardMetricsService $dashboardMetricsService,
        private readonly HuggingFaceOpsAssistantService $hfAssistant
    ) {}

    public function index(Request $request): View
    {
        $userIds = ClientWorkspace::dataScopeUserIds($request->user());
        $metrics = $this->dashboardMetricsService->build($userIds);
        $inconsistencies = $this->buildFinancialInconsistencies($userIds);
        $liveInsight = $this->buildDashboardLiveInsight($metrics, $inconsistencies);

        return view('dashboard', array_merge($metrics, [
            'aiLiveInsight' => $liveInsight,
            'aiInconsistencies' => $inconsistencies,
        ]));
    }

    public function liveInsights(Request $request): JsonResponse
    {
        $userIds = ClientWorkspace::dataScopeUserIds($request->user());
        $metrics = $this->dashboardMetricsService->build($userIds);
        $inconsistencies = $this->buildFinancialInconsistencies($userIds);
        $liveInsight = $this->buildDashboardLiveInsight($metrics, $inconsistencies);

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'live_insight' => $liveInsight,
            'inconsistencies' => $inconsistencies,
        ]);
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array<string, string>>
     */
    private function buildFinancialInconsistencies(array $userIds): array
    {
        $items = [];

        $entriesWithoutDoc = AccountingEntry::query()
            ->whereIn('user_id', $userIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->whereNull('document_id')
            ->where(function ($q) {
                $q->whereNull('attachment_path')->orWhere('attachment_path', '');
            })
            ->count();
        if ($entriesWithoutDoc > 0) {
            $items[] = [
                'severity' => $entriesWithoutDoc >= 6 ? 'danger' : 'warning',
                'title' => 'Ecritures sans piece justificative',
                'detail' => $entriesWithoutDoc.' ecriture(s) sans document sur 30 jours.',
                'proposal' => 'Ajouter les pieces manquantes et valider avant cloture mensuelle.',
            ];
        }

        $ocrMismatch = AccountingEntry::query()
            ->whereIn('user_id', $userIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->where('ocr_status', 'mismatch')
            ->count();
        if ($ocrMismatch > 0) {
            $items[] = [
                'severity' => $ocrMismatch >= 5 ? 'danger' : 'warning',
                'title' => 'Incoherences OCR',
                'detail' => $ocrMismatch.' ecriture(s) avec incoherence OCR.',
                'proposal' => 'Faire une revue manuelle des montants OCR puis corriger les ecritures.',
            ];
        }

        $treasuryNoRef = TreasuryTransaction::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'effectue')
            ->where('transaction_date', '>=', now()->subDays(90)->toDateString())
            ->where(function ($q) {
                $q->whereNull('reference')->orWhere('reference', '');
            })
            ->count();
        if ($treasuryNoRef > 0) {
            $items[] = [
                'severity' => $treasuryNoRef >= 10 ? 'danger' : 'warning',
                'title' => 'Mouvements de tresorerie sans reference',
                'detail' => $treasuryNoRef.' mouvement(s) effectues sans reference.',
                'proposal' => 'Renseigner une reference unique pour chaque mouvement de tresorerie.',
            ];
        }

        $invalidAmounts = TreasuryTransaction::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'effectue')
            ->where('transaction_date', '>=', now()->subDays(90)->toDateString())
            ->where('amount', '<=', 0)
            ->count();
        if ($invalidAmounts > 0) {
            $items[] = [
                'severity' => 'danger',
                'title' => 'Montants invalides en tresorerie',
                'detail' => $invalidAmounts.' mouvement(s) avec montant <= 0.',
                'proposal' => 'Corriger les mouvements invalides et renforcer les controles de saisie.',
            ];
        }

        if (empty($items)) {
            return [[
                'severity' => 'success',
                'title' => 'Aucune incoherence majeure',
                'detail' => 'Comptabilite et tresorerie sont coherentes sur les periodes recentes.',
                'proposal' => 'Maintenir un controle hebdomadaire pour conserver ce niveau.',
            ]];
        }

        return collect($items)
            ->sortByDesc(fn (array $item) => match ($item['severity']) {
                'danger' => 3,
                'warning' => 2,
                default => 1,
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $metrics
     * @param array<int, array<string, string>> $inconsistencies
     */
    private function buildDashboardLiveInsight(array $metrics, array $inconsistencies): string
    {
        $fallback = "**Priorite immediate :** securiser les flux de tresorerie et la qualite comptable.\n"
            ."**Comment faire :**\n"
            ."1) Corriger en priorite les incoherences critiques detectees.\n"
            ."2) Verifier les references de paiement et les pieces justificatives manquantes.\n"
            ."3) Suivre l'impact sur le cashflow, le CA et les delais de cloture.\n"
            ."4) Mettre en place un rituel hebdomadaire de controle financier.\n"
            ."**KPI de suivi :** anomalies ouvertes, cashflow net mensuel, CA mensuel, ratio OCR coherent.\n"
            ."**Impact attendu :** meilleure fiabilite financiere et progression du chiffre d'affaires.";

        if ((string) config('services.huggingface.token', '') === '') {
            return $fallback;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => "Tu es un assistant financier en direct pour dashboard PME. Reponds en francais, format compact: Priorite immediate, Comment faire (4 etapes), KPI de suivi, Impact attendu.",
            ],
            [
                'role' => 'system',
                'content' => 'Contexte JSON: '.json_encode([
                    'turnover_month' => $metrics['turnoverMonth'] ?? 0,
                    'turnover_year' => $metrics['turnoverYear'] ?? 0,
                    'solde_actuel' => $metrics['soldeActuel'] ?? 0,
                    'pending_documents' => $metrics['pendingDocumentsCount'] ?? 0,
                    'accounting_entries' => $metrics['accountingEntriesCount'] ?? 0,
                    'inconsistencies' => $inconsistencies,
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'user',
                'content' => "Analyse la comptabilite et la tresorerie et propose un plan concret pour augmenter le chiffre d'affaires.",
            ],
        ];

        $result = $this->hfAssistant->chat($messages);
        if (! ($result['ok'] ?? false)) {
            return $fallback;
        }

        $answer = trim((string) ($result['answer'] ?? ''));

        return $answer !== '' ? $answer : $fallback;
    }
}
