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

        // 1. Intercepter les salutations simples pour un accueil personnalisé immédiat
        $userMessage = trim((string) $data['message']);
        $cleanMessage = preg_replace('/[^\p{L}\s]/u', '', mb_strtolower($userMessage));
        $greetings = ['bonjour', 'salut', 'coucou', 'hello', 'hi', 'hey', 'bonsoir', 'yo'];
        if (in_array($cleanMessage, $greetings)) {
            return response()->json([
                'ok' => true,
                'answer' => "Bonjour ! Je suis l'assistant de Sitiame Capital et que puis-je faire pour vous ?",
                'context' => $financialContext,
            ]);
        }

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
                'content' => "Tu es l'assistant IA officiel de l'application Sitiame Capital. {$roleInstruction} Réponds en français de manière claire et actionnable.\n\n"
                    . "Voici la structure et les pages clés de l'application :\n"
                    . "- Tableau de bord : Synthèse de la santé financière, métriques de CA et cashflow, alertes et propositions en temps réel.\n"
                    . "- Comptabilité : Gestion des écritures, saisie et import de justificatifs avec extraction automatique par OCR, plan comptable OHADA et génération de la liasse fiscale BCEAO.\n"
                    . "- Trésorerie : Balance, suivi des soldes, encaissements/décaissements et net cashflow.\n"
                    . "- Diagnostics : readiness investor (éligibilité à l'investissement), heatmap des risques et scoring financier à 360°.\n"
                    . "- Équipe et Profil : Gestion des collaborateurs de l'entreprise et abonnements (Gratuit, Premium activable via CinetPay/FedaPay).\n"
                    . "- Factures & Support : Historique des factures de la plateforme et messagerie de tickets support technique.\n\n"
                    . "Tu possèdes une excellente culture générale pour répondre à des questions en dehors de l'application. Cependant, si l'utilisateur pose une question bizarre, inappropriée, offensante ou complètement délirante, refuse poliment d'y répondre en disant exactement : 'Je suis conçu pour vous assister dans la gestion financière et comptable de votre entreprise sur Sitiame Capital. Je ne peux pas répondre à cette demande.'. N'invente pas de données absentes du contexte financier.",
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
            'content' => $userMessage,
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

