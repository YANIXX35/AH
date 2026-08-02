<?php

namespace App\Http\Controllers;

use App\Domain\Treasury\MobileMoney\MobileMoneyReconciliationService;
use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\MobileMoneyStatementImport;
use App\Models\MobileMoneyTransaction;
use App\Models\PlanComptableAccount;
use App\Models\TreasuryTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MobileMoneyReconciliationController extends Controller
{
    use UsesClientWorkspace;

    public function __construct(private readonly MobileMoneyReconciliationService $reconciliationService) {}

    public function index(): View
    {
        $userIds = $this->workspaceDataUserIds();

        $imports = MobileMoneyStatementImport::whereIn('user_id', $userIds)
            ->latest()
            ->paginate(10);

        $treasuryAccounts = PlanComptableAccount::where('user_id', $this->workspaceUserId())
            ->where('prefix', 'like', '5%')
            ->orderBy('prefix')
            ->get();

        return view('treasury.mobile-money.import', [
            'imports' => $imports,
            'treasuryAccounts' => $treasuryAccounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'operator' => ['required', 'in:wave,orange_money,mtn_momo,moov_money'],
            'treasury_account_code' => ['nullable', 'string', 'max:20'],
            'consent' => ['required', 'accepted'],
        ], [
            'consent.required' => 'Le consentement au traitement des données du relevé est obligatoire pour importer.',
            'consent.accepted' => 'Le consentement au traitement des données du relevé est obligatoire pour importer.',
        ]);

        $import = $this->reconciliationService->importStatement(
            $request->file('statement'),
            $validated['operator'],
            $this->workspaceUserId(),
            auth()->id(),
            $validated['treasury_account_code'] ?? null,
            $request->ip()
        );

        return redirect()->route('treasury.mobile-money.review', $import)
            ->with('success', sprintf(
                '%d transaction(s) importée(s), %d rapprochée(s) automatiquement.',
                $import->rows_imported,
                $import->rows_matched
            ));
    }

    public function review(MobileMoneyStatementImport $import): View
    {
        $this->authorizeImport($import);

        $transactions = $import->transactions()
            ->orderByDesc('occurred_at')
            ->get()
            ->map(function (MobileMoneyTransaction $transaction) {
                if ($transaction->status === 'pending') {
                    $transaction->setRelation('candidates', $this->reconciliationService->findCandidates($transaction));
                }

                return $transaction;
            });

        return view('treasury.mobile-money.review', [
            'import' => $import,
            'transactions' => $transactions,
        ]);
    }

    public function matchTransaction(Request $request, MobileMoneyTransaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $validated = $request->validate([
            'treasury_transaction_id' => ['required', 'integer'],
        ]);

        $treasuryTransaction = TreasuryTransaction::whereIn('user_id', $this->workspaceDataUserIds())
            ->findOrFail($validated['treasury_transaction_id']);

        $this->reconciliationService->linkToTreasuryTransaction($transaction, $treasuryTransaction);

        return back()->with('success', 'Transaction rapprochée.');
    }

    public function createTreasuryTransaction(MobileMoneyTransaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $this->reconciliationService->createTreasuryTransactionFromMobileMoney($transaction, auth()->id());

        return back()->with('success', 'Mouvement de trésorerie créé à partir de la ligne Mobile Money.');
    }

    public function ignoreTransaction(MobileMoneyTransaction $transaction): RedirectResponse
    {
        $this->authorizeTransaction($transaction);

        $this->reconciliationService->ignore($transaction);

        return back()->with('success', 'Ligne ignorée.');
    }

    public function purgePersonalData(MobileMoneyStatementImport $import): RedirectResponse
    {
        $this->authorizeImport($import);

        $count = $this->reconciliationService->purgePersonalData($import);

        return back()->with('success', sprintf(
            'Données personnelles (nom, numéro, ligne brute) effacées sur %d transaction(s). Les montants, dates et écritures liées sont conservés pour la piste d\'audit.',
            $count
        ));
    }

    private function authorizeImport(MobileMoneyStatementImport $import): void
    {
        abort_unless(in_array($import->user_id, $this->workspaceDataUserIds(), true), 403);
    }

    private function authorizeTransaction(MobileMoneyTransaction $transaction): void
    {
        abort_unless(in_array($transaction->user_id, $this->workspaceDataUserIds(), true), 403);
    }
}
