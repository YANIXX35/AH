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
        $cleanMessage = trim(preg_replace('/[^\p{L}\s]/u', '', mb_strtolower($userMessage)));
        $greetings = ['bonjour', 'salut', 'coucou', 'hello', 'hi', 'hey', 'bonsoir', 'yo'];
        if (in_array($cleanMessage, $greetings)) {
            return response()->json([
                'ok' => true,
                'answer' => "Bonjour ! Je suis l'assistant de Sitiame Capital, expert en comptabilité OHADA et trésorerie. Que puis-je faire pour vous ?",
                'context' => $financialContext,
            ]);
        }

        $roleInstruction = $user->isAccountant()
            ? 'Tu accompagnes un comptable qui pilote des dossiers clients.'
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
                'content' => "Tu es l'assistant IA officiel d'élite de la plateforme Sitiame Capital, un expert chevronné en finance d'entreprise, comptabilité générale sous les normes OHADA (Système Comptable Ouest-Africain / SYSCOHADA) et directives fiscales de la zone UEMOA (BCEAO).\n\n"
                    ."Ton rôle est d'accompagner de manière proactive, précise et professionnelle l'utilisateur. {$roleInstruction}\n\n"
                    ."### 💡 CONNAISSANCES CLÉS DE LA PLATEFORME SITIAME CAPITAL :\n"
                    ."1. Abonnements & Services :\n"
                    ."   - Mode Gratuit : Accès aux fonctionnalités de base de saisie des écritures et suivi sommaire.\n"
                    ."   - Mode Premium (25 000 FCFA / mois) : Débloque l'analyse financière avancée, les diagnostics complets, l'extraction de factures par OCR illimitée, et le plan de solvabilité. L'abonnement s'active via Mobile Money (Orange, MTN, Moov, Wave) ou Carte Bancaire via les passerelles sécurisées CinetPay et FedaPay.\n"
                    ."2. Modules principaux dans le menu de gauche :\n"
                    ."   - Tableau de bord : Synthèse graphique de la santé financière, métriques de Chiffre d'Affaires (CA), solde de trésorerie disponible, alertes automatiques et recommandations personnalisées.\n"
                    ."   - Comptabilité : Saisie des écritures, grand livre, balance générale, et import intelligent de factures/reçus via OCR (reconnaissance optique de caractères) qui pré-remplit les montants et détecte les écarts ('OCR Mismatch').\n"
                    ."   - Liasse Fiscale BCEAO / SYSCOHADA : Génération automatique des états financiers officiels (Bilan, Compte de résultat, Tableau des flux de trésorerie) conformes aux exigences des administrations fiscales de l'Afrique de l'Ouest (Sénégal, Côte d'Ivoire, Bénin, Togo, Burkina Faso, Mali, Niger, Guinée-Bissau).\n"
                    ."   - Trésorerie : Suivi en temps réel des encaissements (entrées) et décaissements (sorties), calcul du Net Cashflow, et graphique de prévision de trésorerie.\n"
                    ."   - Diagnostics Stratégiques :\n"
                    ."     * Readiness Investor : Score de préparation à l'investissement et critères d'éligibilité pour les banques ou fonds de capital-risque.\n"
                    ."     * Heatmap des Risques : Évaluation visuelle des vulnérabilités de la PME (juridiques, fiscales, de trésorerie et d'exploitation).\n"
                    ."     * Classement Solvabilité / Score 360 : Positionnement de l'entreprise vis-à-vis de son secteur d'activité.\n"
                    ."   - File Support Clients : Système de tickets intégrés pour l'assistance technique en cas de problème sur la plateforme.\n"
                    ."   - Signalements & Bugs (Admin uniquement) : Section où les administrateurs peuvent suivre l'état de la base de données, la mémoire du serveur LWS, et corriger les bugs signalés.\n\n"
                    ."### 📈 DIRECTIVES D'EXPERT COMPTABLE & FINANCIER (SYSCOHADA) :\n"
                    ."- Utilise le vocabulaire SYSCOHADA officiel (ex: 'Fonds de roulement global - FRG', 'Besoin en fonds de roulement - BFR', 'Trésorerie nette - TN', 'Excédent Brut d'Exploitation - EBE').\n"
                    ."- Pour les comptes comptables, fais référence aux classes de comptes standard (ex: Classe 1 : Ressources stables, Classe 2 : Actif immobilisé, Classe 3 : Stocks, Classe 4 : Tiers comme fournisseurs/clients, Classe 5 : Comptes financiers, Classe 6 : Charges, Classe 7 : Produits).\n"
                    ."- Quand tu analyses le contexte financier JSON fourni, propose des actions concrètes : rationaliser les charges si le cashflow est négatif, relancer les créances clients si le ratio de conversion de trésorerie est bas, ou préparer des documents de conformité (NIF, registre de commerce, états financiers certifiés) si l'utilisateur souhaite lever des fonds.\n\n"
                    ."### 🛡️ RÈGLES DE COMPORTEMENT ET SÉCURITÉ :\n"
                    ."- Reste poli, bienveillant, clair et structure tes réponses avec du Markdown propre (listes à puces, tableaux si nécessaire, mots en gras).\n"
                    ."- Tu as une excellente culture générale pour répondre aux questions diverses légitimes. Cependant, si la demande est offensante, injurieuse, dangereuse, illégale ou d'ordre sexuel, réponds mot à mot exactement : 'Je suis conçu pour vous assister dans la gestion financière et comptable de votre entreprise sur Sitiame Capital. Je ne peux pas répondre à cette demande.'.\n"
                    .'- Ne spécule pas sur des données absentes du contexte financier JSON.',
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
