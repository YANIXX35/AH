<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\User;
use App\Support\ClientWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Gestionnaire de fichiers OCR pour le cabinet comptable — vue multi-clients
 * des documents comptables (factures scannées/importées) séparant ceux dont
 * l'OCR a échoué de ceux correctement lus. Sans rapport avec
 * AccountantClientController::documents() (gestion des documents KYC :
 * attestation DFE/NIF, registre de commerce).
 */
class AccountantDocumentController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'failed' ? 'failed' : 'success';
        $authUser = $request->user();

        $clientIds = User::query()->clients()
            ->when($authUser && ! $authUser->is_platform_admin, fn ($q) => $q->where('created_by_user_id', $authUser->id))
            ->pluck('id');

        $documents = AccountingDocument::query()
            ->whereIn('user_id', $clientIds)
            ->when($tab === 'failed', fn ($q) => $q->where('status', 'ocr_failed'))
            ->when($tab === 'success', fn ($q) => $q->where('status', '!=', 'ocr_failed'))
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        return view('accountant.files-index', [
            'groups' => $this->groupByClient($documents),
            'tab' => $tab,
            'failedCount' => AccountingDocument::whereIn('user_id', $clientIds)->where('status', 'ocr_failed')->count(),
            'successCount' => AccountingDocument::whereIn('user_id', $clientIds)->where('status', '!=', 'ocr_failed')->count(),
        ]);
    }

    /**
     * Ouvre le document ciblé pour validation. Un comptable ne peut consulter
     * les données d'un client que si son dossier est "ouvert"
     * (ClientWorkspace) — on bascule donc le dossier avant de rediriger,
     * plutôt qu'un lien direct qui échouerait faute de contexte.
     */
    public function open(Request $request, AccountingDocument $document): RedirectResponse
    {
        $authUser = $request->user();
        $client = $document->user;

        if (! $client || ! ClientWorkspace::isAssignableClient($client)) {
            abort(404);
        }
        if ($authUser && ! $authUser->is_platform_admin && $client->created_by_user_id !== $authUser->id) {
            abort(403, 'Vous n’avez pas accès à ce document.');
        }

        ClientWorkspace::setWorkspaceUserId((int) $client->id);

        return redirect()->route('accounting.documents.validate', $document);
    }

    private function groupByClient(Collection $documents): Collection
    {
        return $documents
            ->groupBy('user_id')
            ->map(function ($docs) {
                $client = $docs->first()->user;

                return [
                    'client_label' => $client?->company_name ?: $client?->name ?: ('Client #'.$docs->first()->user_id),
                    'client' => $client,
                    'documents' => $docs->sortByDesc('created_at')->values(),
                ];
            })
            ->sortBy('client_label')
            ->values();
    }
}
