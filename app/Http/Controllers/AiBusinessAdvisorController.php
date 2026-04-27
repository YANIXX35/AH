<?php

namespace App\Http\Controllers;

use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use App\Services\HuggingFaceOpsAssistantService;
use App\Support\ClientWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AiBusinessAdvisorController extends Controller
{
    public function __construct(
        private readonly HuggingFaceOpsAssistantService $hfAssistant
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:2500'],
            'history' => ['nullable', 'array', 'max:12'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:2500'],
        ]);

        $user = $request->user();
        $scopeUserIds = ClientWorkspace::dataScopeUserIds($user);
        if (empty($scopeUserIds)) {
            $scopeUserIds = [(int) $user->id];
        }

        $financialContext = $this->buildFinancialContext($scopeUserIds);
        $roleInstruction = $user->isAccountant()
            ? "Tu accompagnes un comptable qui pilote des dossiers clients."
            : "Tu accompagnes un dirigeant d'entreprise.";

        $history = collect((array) ($data['history'] ?? []))
            ->map(fn ($row) => [
                'role' => (string) ($row['role'] ?? 'user'),
                'content' => (string) ($row['content'] ?? ''),
            ])
            ->filter(fn ($row) => $row['content'] !== '')
            ->take(-8)
            ->values()
            ->all();

        $messages = [
            [
                'role' => 'system',
                'content' => "Tu es un expert comptable et financier virtuel. {$roleInstruction} Réponds en français, clair et actionnable. Quand tu proposes une amélioration du chiffre d'affaires, donne toujours COMMENT FAIRE avec étapes concrètes, délai, KPI de suivi et impact attendu. N'invente pas de données absentes.",
            ],
            [
                'role' => 'system',
                'content' => 'Contexte financier JSON: '.json_encode($financialContext, JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($history as $row) {
            $messages[] = $row;
        }
        $messages[] = [
            'role' => 'user',
            'content' => (string) $data['message'],
        ];

        $result = $this->hfAssistant->chat($messages);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Erreur IA inconnue.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'answer' => (string) $result['answer'],
            'context' => $financialContext,
        ]);
    }

    /**
     * @param  list<int>  $scopeUserIds
     * @return array<string, mixed>
     */
    private function buildFinancialContext(array $scopeUserIds): array
    {
        $now = now();
        $last30 = $now->copy()->subDays(30);
        $last90 = $now->copy()->subDays(90);

        $entryCount30 = 0;
        $entryAmount30 = 0.0;
        $ocrMismatch30 = 0;
        if (Schema::hasTable('accounting_entries')) {
            $entries30 = AccountingEntry::query()
                ->whereIn('user_id', $scopeUserIds)
                ->where('created_at', '>=', $last30);

            $entryCount30 = (int) (clone $entries30)->count();
            $entryAmount30 = (float) (clone $entries30)->sum('amount');
            $ocrMismatch30 = (int) (clone $entries30)->where('ocr_status', 'mismatch')->count();
        }

        $inflow90 = 0.0;
        $outflow90 = 0.0;
        if (Schema::hasTable('treasury_transactions')) {
            $base90 = TreasuryTransaction::query()
                ->whereIn('user_id', $scopeUserIds)
                ->where('created_at', '>=', $last90)
                ->where('status', 'effectue');

            $inflow90 = (float) (clone $base90)->where('type', 'encaissement')->sum('amount');
            $outflow90 = (float) (clone $base90)->where('type', 'decaissement')->sum('amount');
        }

        $netCashFlow90 = round($inflow90 - $outflow90, 2);
        $cashConversionRatio = $entryAmount30 > 0
            ? round(($inflow90 / max($entryAmount30, 1)) * 100, 2)
            : 0.0;

        return [
            'scope_user_ids' => $scopeUserIds,
            'accounting' => [
                'entries_30d_count' => $entryCount30,
                'entries_30d_total_amount' => round($entryAmount30, 2),
                'ocr_mismatch_30d_count' => $ocrMismatch30,
            ],
            'treasury' => [
                'inflow_90d' => round($inflow90, 2),
                'outflow_90d' => round($outflow90, 2),
                'net_cashflow_90d' => $netCashFlow90,
                'cash_conversion_ratio_pct' => $cashConversionRatio,
            ],
            'financial_signal' => [
                'cashflow_trend' => $netCashFlow90 >= 0 ? 'positive' : 'negative',
                'accounting_coverage' => $entryCount30 >= 10 ? 'good' : 'low',
            ],
        ];
    }
}

