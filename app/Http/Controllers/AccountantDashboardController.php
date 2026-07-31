<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\HuggingFaceOpsAssistantService;
use App\Support\ClientWorkspace;
use App\Support\OcrStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AccountantDashboardController extends Controller
{
    public function __construct(
        private readonly HuggingFaceOpsAssistantService $hfAssistant
    ) {}

    /**
     * Tableau de bord cabinet : vue transverse sur les dossiers clients.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $cacheKey = 'accountant.dashboard.aggregates.' . ($user?->id ?? 0);

        $aggregates = Cache::remember($cacheKey, now()->addMinutes(3), function () use ($user) {
            $clientQuery = User::query()->clients();
            if ($user && ! $user->is_platform_admin) {
                $clientQuery->where('created_by_user_id', $user->id);
            }
            $clientIds = $clientQuery->pluck('id');

            $countQuery = User::query()->clients();
            if ($user && ! $user->is_platform_admin) {
                $countQuery->where('created_by_user_id', $user->id);
            }

            $recentQuery = User::query()->clients();
            if ($user && ! $user->is_platform_admin) {
                $recentQuery->where('created_by_user_id', $user->id);
            }

            return [
                'clientCount' => $countQuery->count(),
                'entriesTotal' => AccountingEntry::query()->whereIn('user_id', $clientIds)->count(),
                'documentsPending' => AccountingDocument::query()
                    ->whereIn('user_id', $clientIds)
                    ->whereIn('status', ['pending', 'pending_validation', 'ocr_failed'])
                    ->count(),
                'ocrStressEntries' => AccountingEntry::query()
                    ->whereIn('user_id', $clientIds)
                    ->whereIn('ocr_status', [OcrStatus::FAILED, OcrStatus::MISMATCH, OcrStatus::LEGACY_MISMATCHED])
                    ->count(),
                'treasuryVolume' => (float) TreasuryTransaction::query()
                    ->whereIn('user_id', $clientIds)
                    ->where('status', 'effectue')
                    ->sum('amount'),
                'recentClients' => $recentQuery
                    ->latest()
                    ->limit(10)
                    ->get(['id', 'name', 'email', 'company_name', 'created_at']),
                'inconsistencies' => $this->buildFinancialInconsistencies($clientIds->all()),
            ];
        });

        $clientCount = $aggregates['clientCount'];
        $entriesTotal = $aggregates['entriesTotal'];
        $documentsPending = $aggregates['documentsPending'];
        $ocrStressEntries = $aggregates['ocrStressEntries'];
        $treasuryVolume = $aggregates['treasuryVolume'];
        $recentClients = $aggregates['recentClients'];
        $inconsistencies = $aggregates['inconsistencies'];

        $workspaceTarget = ClientWorkspace::isViewingClient() ? ClientWorkspace::workspaceTarget() : null;
        $workspaceLabel = '';
        if ($workspaceTarget) {
            $workspaceLabel = (string) ($workspaceTarget->company_name ?: $workspaceTarget->name ?: $workspaceTarget->email);
        }

        // La recommandation IA est chargée en tâche de fond par le JS de la vue
        // (route accountant.dashboard.ai.live) : la calculer ici aussi bloquerait
        // l'affichage de la page pendant jusqu'à 45s (timeout de l'appel HuggingFace).
        return view('accountant.dashboard', [
            'clientCount' => $clientCount,
            'entriesTotal' => $entriesTotal,
            'documentsPending' => $documentsPending,
            'ocrStressEntries' => $ocrStressEntries,
            'treasuryVolume' => $treasuryVolume,
            'recentClients' => $recentClients,
            'accountingWorkspaceOpen' => ClientWorkspace::isViewingClient(),
            'accountingWorkspaceLabel' => $workspaceLabel,
            'aiInconsistencies' => $inconsistencies,
        ]);
    }

    public function liveInsights(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = User::query()->clients();
        if ($user && ! $user->is_platform_admin) {
            $query->where('created_by_user_id', $user->id);
        }
        $clientIds = $query->pluck('id')->all();
        $inconsistencies = $this->buildFinancialInconsistencies($clientIds);
        $liveInsight = $this->buildLiveInsight($clientIds, $inconsistencies);

        return response()->json([
            'ok' => true,
            'generated_at' => now()->toIso8601String(),
            'live_insight' => $liveInsight,
            'inconsistencies' => $inconsistencies,
        ]);
    }

    /**
     * Suivi transversal des portefeuilles commerciaux pour le cabinet comptable.
     */
    public function commercialsTracking(Request $request): View
    {
        $commercials = User::query()
            ->where('role_key', 'commercial')
            ->with(['createdClients' => function ($q) {
                $q->latest();
            }])
            ->get();

        $referredClients = User::query()
            ->whereNotNull('created_by_user_id')
            ->whereHas('creator', function ($q) {
                $q->where('role_key', 'commercial');
            })
            ->with('creator')
            ->latest()
            ->get();

        $totalCommercials = $commercials->count();
        $totalClientsReferred = $referredClients->count();

        $activeTrials = $referredClients->filter(function ($client) {
            return $client->is_premium && $client->premium_ends_at && $client->premium_ends_at->isFuture();
        })->count();

        $expiredTrials = $referredClients->filter(function ($client) {
            return !$client->is_premium || ($client->premium_ends_at && $client->premium_ends_at->isPast());
        })->count();

        $selectedCommercialId = $request->query('commercial_id');
        $selectedCommercial = $selectedCommercialId
            ? $commercials->firstWhere('id', (int) $selectedCommercialId)
            : null;

        return view('accountant.commercials-tracking', [
            'commercials' => $commercials,
            'referredClients' => $referredClients,
            'totalCommercials' => $totalCommercials,
            'totalClientsReferred' => $totalClientsReferred,
            'activeTrials' => $activeTrials,
            'expiredTrials' => $expiredTrials,
            'selectedCommercial' => $selectedCommercial,
        ]);
    }

    /**
     * @param array<int, int> $clientIds
     * @return array<int, array<string, string>>
     */
    private function buildFinancialInconsistencies(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [[
                'severity' => 'success',
                'title' => 'Aucun dossier client actif',
                'detail' => "Le cabinet n'a pas encore de dossier client a analyser.",
                'proposal' => "Ouvrir un dossier client pour activer l'analyse IA.",
            ]];
        }

        $items = [];

        $entriesWithoutDoc = AccountingEntry::query()
            ->whereIn('user_id', $clientIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->whereNull('document_id')
            ->where(function ($q) {
                $q->whereNull('attachment_path')->orWhere('attachment_path', '');
            })
            ->count();
        if ($entriesWithoutDoc > 0) {
            $items[] = [
                'severity' => $entriesWithoutDoc >= 15 ? 'danger' : 'warning',
                'title' => 'Pieces manquantes dans les dossiers',
                'detail' => $entriesWithoutDoc.' ecriture(s) sans justificatif sur 30 jours.',
                'proposal' => 'Prioriser les 10 dossiers les plus exposes puis regulariser les pieces.',
            ];
        }

        $ocrMismatch = AccountingEntry::query()
            ->whereIn('user_id', $clientIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->where('ocr_status', 'mismatch')
            ->count();
        if ($ocrMismatch > 0) {
            $items[] = [
                'severity' => $ocrMismatch >= 10 ? 'danger' : 'warning',
                'title' => 'Ecritures OCR incoherentes',
                'detail' => $ocrMismatch.' ecriture(s) avec mismatch OCR.',
                'proposal' => 'Mettre en revue manuelle les ecritures OCR critiques avec delai 48h.',
            ];
        }

        $treasuryNoRef = TreasuryTransaction::query()
            ->whereIn('user_id', $clientIds)
            ->where('status', 'effectue')
            ->where('transaction_date', '>=', now()->subDays(90)->toDateString())
            ->where(function ($q) {
                $q->whereNull('reference')->orWhere('reference', '');
            })
            ->count();
        if ($treasuryNoRef > 0) {
            $items[] = [
                'severity' => $treasuryNoRef >= 20 ? 'danger' : 'warning',
                'title' => 'Flux de tresorerie sans reference',
                'detail' => $treasuryNoRef.' mouvement(s) sans reference unique.',
                'proposal' => 'Imposer une reference obligatoire avant validation des flux.',
            ];
        }

        $invalidAmounts = TreasuryTransaction::query()
            ->whereIn('user_id', $clientIds)
            ->where('status', 'effectue')
            ->where('transaction_date', '>=', now()->subDays(90)->toDateString())
            ->where('amount', '<=', 0)
            ->count();
        if ($invalidAmounts > 0) {
            $items[] = [
                'severity' => 'danger',
                'title' => 'Montants de tresorerie invalides',
                'detail' => $invalidAmounts.' mouvement(s) avec montant inferieur ou egal a zero.',
                'proposal' => 'Corriger les mouvements invalides et verrouiller ce cas en saisie.',
            ];
        }

        if (empty($items)) {
            return [[
                'severity' => 'success',
                'title' => 'Aucune incoherence majeure detectee',
                'detail' => 'La qualite comptable et tresorerie est stable sur les dossiers actifs.',
                'proposal' => 'Maintenir une revue hebdomadaire cabinet par portefeuille.',
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
     * @param array<int, int> $clientIds
     * @param array<int, array<string, string>> $inconsistencies
     */
    private function buildLiveInsight(array $clientIds, array $inconsistencies): string
    {
        $fallback = "**Priorite immediate :** reduire les incoherences comptables sur les dossiers clients critiques.\n"
            ."**Comment faire :**\n"
            ."1) Prioriser les dossiers avec OCR mismatch et pieces manquantes.\n"
            ."2) Assigner les corrections par collaborateur avec SLA 48h.\n"
            ."3) Controler les references de tresorerie et la coherence des montants.\n"
            ."4) Presenter un suivi hebdomadaire avec KPI par portefeuille.\n"
            ."**KPI de suivi :** anomalies ouvertes, delai moyen de correction, flux sans reference, evolution CA clients.\n"
            ."**Impact attendu :** meilleure fiabilite dossiers et meilleure performance business client.";

        if (empty($clientIds) || (string) config('services.huggingface.token', '') === '') {
            return $fallback;
        }

        $turnoverMonth = (float) AccountingEntry::query()
            ->whereIn('user_id', $clientIds)
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum(DB::raw("
                CASE
                    WHEN LTRIM(credit_account) LIKE '7%' THEN amount
                    WHEN LTRIM(debit_account) LIKE '7%' THEN -amount
                    ELSE 0
                END
            "));
        $treasuryVolume = (float) TreasuryTransaction::query()
            ->whereIn('user_id', $clientIds)
            ->where('status', 'effectue')
            ->sum('amount');

        $messages = [
            [
                'role' => 'system',
                'content' => "Tu es un copilote financier pour cabinet comptable. Reponds en francais, actionnable et concis avec: Priorite immediate, Comment faire (4 etapes), KPI de suivi, Impact attendu.",
            ],
            [
                'role' => 'system',
                'content' => 'Contexte JSON: '.json_encode([
                    'client_count' => count($clientIds),
                    'turnover_month' => $turnoverMonth,
                    'treasury_volume' => $treasuryVolume,
                    'inconsistencies' => $inconsistencies,
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'user',
                'content' => "Analyse les incoherences des dossiers clients et propose un plan pour ameliorer leur chiffre d'affaires.",
            ],
        ];

        $result = $this->hfAssistant->chat($messages);
        if (! ($result['ok'] ?? false)) {
            return $fallback;
        }

        $answer = trim((string) ($result['answer'] ?? ''));
        return $answer !== '' ? $answer : $fallback;
    }

    public function parseCompanyDocument(Request $request, \App\Services\CompanyDocumentParserService $parser): JsonResponse
    {
        $request->validate([
            'document' => ['required', 'file', 'max:10240', 'mimes:docx,doc,xlsx,xls,csv,pdf,txt'],
        ]);

        $file = $request->file('document');
        $extracted = $parser->parse($file);

        return response()->json([
            'ok' => true,
            'filename' => $file->getClientOriginalName(),
            'fields_found_count' => count($extracted),
            'extracted' => $extracted,
            'message' => count($extracted) > 0
                ? count($extracted) . ' champ(s) identifié(s) et pré-rempli(s) automatiquement.'
                : 'Aucun champ reconnu automatiquement. Vous pouvez remplir les informations manuellement.',
        ]);
    }
}
