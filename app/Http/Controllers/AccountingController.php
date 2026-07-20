<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\AccountingMonthClosure;
use App\Models\PlanComptableAccount;
use App\Models\PlanComptableImport;
use App\Models\TreasuryTransaction;
use App\Services\OcrPipelineService;
use App\Services\OcrService;
use App\Services\TreasuryAudit;
use App\Support\OcrStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;

class AccountingController extends Controller
{
    use UsesClientWorkspace;

    public function index(Request $request)
    {
        $search = trim($request->query('q', ''));
        $documentType = trim($request->query('document_type', ''));
        $account = trim($request->query('account', ''));
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $prefillDocument = $this->resolvePrefillDocument($request);

        $entriesQuery = AccountingEntry::with('document:id,original_name,stored_path')
            ->whereIn('user_id', $this->workspaceDataUserIds())
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('document_reference', 'like', "%{$search}%")
                        ->orWhere('document_type', 'like', "%{$search}%")
                        ->orWhere('debit_account', 'like', "%{$search}%")
                        ->orWhere('credit_account', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%");
                });
            })
            ->when($documentType, function ($query, $documentType) {
                $query->where('document_type', $documentType);
            })
            ->when($account, function ($query, $account) {
                $query->where(function ($query) use ($account) {
                    $query->where('debit_account', 'like', "%{$account}%")
                        ->orWhere('credit_account', 'like', "%{$account}%");
                });
            })
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            })
            ->orderByDesc('created_at')
            ->orderByDesc('date')
            ->orderByDesc('id');

        $entries = $entriesQuery->get();

        $summary = $this->summarizeEntries($entries);
        $importHistory = $this->getImportHistory();
        $planSource = $this->planExists() ? 'Base de données' : 'Plan par défaut';

        return view('accounting', array_merge(
            [
                'entries' => $entries,
                'importHistory' => $importHistory,
                'planSource' => $planSource,
                'search' => $search,
                'documentType' => $documentType,
                'account' => $account,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'prefillDocument' => $prefillDocument,
                'prefillData' => $prefillDocument ? $this->buildEntryPrefillData($prefillDocument) : null,
            ],
            $summary
        ));
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
            'document_id' => ['nullable', 'integer'],
            'date' => ['required', 'date'],
            'document_type' => ['required', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx,zip', 'max:20480'],
        ]);

        $data = $validated;
        $accounts = $this->resolveAccountsForDocumentType($validated['document_type']);
        $data['debit_account'] = $accounts['debit'];
        $data['credit_account'] = $accounts['credit'];
        $ocrData = [];
        $statusMessage = 'Écriture enregistrée';
        $linkedDocument = $this->resolveLinkedDocumentFromRequest($request);
        if ($linkedDocument) {
            $data['document_id'] = $linkedDocument->id;
        }

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('accounting-attachments', 'public');
            $entryOcrAnalysis = $this->analyzeEntryAttachment(
                $data['attachment_path'],
                $this->buildEntryVerificationPayload($request, $validated)
            );
            $ocrData = $entryOcrAnalysis['ocr_data'];
            $statusMessage .= $entryOcrAnalysis['status_suffix'];
        } elseif ($linkedDocument) {
            $linkedDocumentData = (array) $linkedDocument->extracted_data;
            $data['attachment_path'] = $linkedDocument->stored_path;
            $ocrData = [
                'ocr_status' => $linkedDocument->status === 'validated'
                    ? 'manual_verified'
                    : ($linkedDocument->status === 'ocr_failed' ? 'failed' : 'pending'),
                'ocr_detected_amount' => $this->toFloatOrNull(
                    $linkedDocumentData['amount_ttc']
                    ?? $linkedDocumentData['amount_ht']
                    ?? null
                ),
                'ocr_verified_at' => $linkedDocument->status === 'validated' ? now() : null,
                'ocr_text' => $linkedDocumentData['ocr_text']
                    ?? $linkedDocumentData['ocr_error']
                    ?? null,
            ];
        } else {
            $ocrData['ocr_status'] = 'pending';
        }

        $entryPayload = array_merge($data, $ocrData, [
            'user_id' => $this->workspaceUserId(),
            'actor_user_id' => Auth::id(),
        ]);

        $entry = ! empty($entryPayload['document_id'])
            ? AccountingEntry::updateOrCreate(
                ['document_id' => $entryPayload['document_id']],
                $entryPayload
            )
            : AccountingEntry::create($entryPayload);

        if ($linkedDocument) {
            $this->syncDocumentAfterManualEntry($linkedDocument, $entry, $request, $validated, $entryPayload['attachment_path'] ?? null);
            $statusMessage .= ' et document OCR lié au brouillon d’écriture';
        }

        return redirect()->route('accounting')->with('status', $statusMessage.'.');
    }

    /**
     * Détermine automatiquement les comptes débit/crédit selon le type de document.
     */
    private function resolveAccountsForDocumentType(string $documentType): array
    {
        $map = [
            'Achat' => ['debit' => '607 Achats de marchandises', 'credit' => '401 Fournisseurs'],
            'Vente' => ['debit' => '411 Clients', 'credit' => '701 Ventes de marchandises'],
            'Reçu' => ['debit' => '512 Banque', 'credit' => '411 Clients'],
            'Justificatif' => ['debit' => '627 Services bancaires', 'credit' => '512 Banque'],
        ];

        return $map[$documentType] ?? ['debit' => '471 Compte transitoire', 'credit' => "472 Compte d'attente"];
    }

    public function editEntry(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        return view('accounting.edit-entry', compact('entry'));
    }

    public function updateEntry(Request $request, AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'document_type' => ['required', 'string', 'max:255'],
            'document_reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,doc,docx,zip', 'max:20480'],
            'remove_attachment' => ['nullable', 'boolean'],
        ]);

        // On normalise les valeurs textuelles pour éviter les espaces parasites.
        foreach (['document_type', 'document_reference', 'description'] as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = trim((string) $validated[$field]);
            }
        }

        $accounts = $this->resolveAccountsForDocumentType($validated['document_type']);
        $validated['debit_account'] = $accounts['debit'];
        $validated['credit_account'] = $accounts['credit'];

        $removeAttachment = (bool) $request->boolean('remove_attachment');
        $attachmentReplaced = false;
        $attachmentRemoved = false;
        $ocrNeedsRecheck = false;
        $ocrRechecked = false;

        if ($removeAttachment && $entry->attachment_path && ! $request->hasFile('attachment')) {
            Storage::disk('public')->delete($entry->attachment_path);
            $validated['attachment_path'] = null;
            $attachmentRemoved = true;
        }

        if ($request->hasFile('attachment')) {
            if ($entry->attachment_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $validated['attachment_path'] = $request->file('attachment')->store('accounting-attachments', 'public');
            $attachmentReplaced = true;
        }

        // Si les donnees comptables ou le fichier changent, le controle OCR doit etre rejoue.
        $ocrSensitiveChanges = [
            'date',
            'document_type',
            'document_reference',
            'description',
            'debit_account',
            'credit_account',
            'amount',
            'attachment_path',
        ];

        foreach ($ocrSensitiveChanges as $field) {
            if (array_key_exists($field, $validated) && (string) $validated[$field] !== (string) $entry->{$field}) {
                $ocrNeedsRecheck = true;
                $validated['ocr_status'] = 'pending';
                $validated['ocr_detected_amount'] = null;
                $validated['ocr_verified_at'] = null;
                $validated['ocr_text'] = null;
                break;
            }
        }

        // Si le justificatif est remplacé, on relance immédiatement l'OCR pour prendre en compte le nouveau fichier.
        if ($attachmentReplaced && ! empty($validated['attachment_path'])) {
            $entryOcrAnalysis = $this->analyzeEntryAttachment(
                $validated['attachment_path'],
                [
                    'document_reference' => $validated['document_reference'] ?? '',
                    'date' => $validated['date'],
                    'amount' => (float) $validated['amount'],
                    'amount_ht' => (float) $validated['amount'],
                    'amount_tva' => 0,
                    'ttc_amount' => (float) $validated['amount'],
                    'tva_rate' => 0,
                    'partner_name' => '',
                ]
            );
            $validated = array_merge($validated, $entryOcrAnalysis['ocr_data']);
            $ocrRechecked = true;
        } elseif ($ocrNeedsRecheck && ! $attachmentRemoved) {
            $storedPathForRecheck = $validated['attachment_path'] ?? $entry->attachment_path;
            if (! empty($storedPathForRecheck)) {
                $entryOcrAnalysis = $this->analyzeEntryAttachment(
                    (string) $storedPathForRecheck,
                    [
                        'document_reference' => $validated['document_reference'] ?? '',
                        'date' => $validated['date'],
                        'amount' => (float) $validated['amount'],
                        'amount_ht' => (float) $validated['amount'],
                        'amount_tva' => 0,
                        'ttc_amount' => (float) $validated['amount'],
                        'tva_rate' => 0,
                        'partner_name' => '',
                    ]
                );
                $validated = array_merge($validated, $entryOcrAnalysis['ocr_data']);
                $ocrRechecked = true;
            }
        }

        $validated['actor_user_id'] = Auth::id();
        $entry->update($validated);

        $status = 'Écriture mise à jour.';
        $ocrReset = isset($validated['ocr_status']) && $validated['ocr_status'] === 'pending';

        if ($attachmentReplaced) {
            $status .= ' Nouveau justificatif pris en compte.';
        } elseif ($attachmentRemoved) {
            $status .= ' Justificatif supprimé.';
        }

        if ($ocrRechecked) {
            $status .= ' Contrôle OCR rejoué automatiquement.';
        } elseif ($ocrReset) {
            $status .= ' Le contrôle OCR a été réinitialisé suite aux modifications.';
        }

        return redirect()
            ->route('accounting')
            ->with('status', $status)
            ->with('ocr_reset', $ocrReset);
    }

    public function destroyEntry(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        TreasuryAudit::log($entry->user_id, 'accounting.entry.deleted', $entry, [
            'actor_user_id' => Auth::id(),
            'description' => $entry->description,
            'amount' => (float) $entry->amount,
            'document_reference' => $entry->document_reference,
        ]);

        if ($entry->attachment_path) {
            Storage::disk('public')->delete($entry->attachment_path);
        }

        $entry->delete();

        return redirect()->route('accounting')->with('status', 'Écriture supprimée.');
    }

    public function bulkDeleteEntries(Request $request)
    {
        $entries = $this->getOwnedEntriesFromRequest($request);

        if ($entries->isEmpty()) {
            return redirect()->route('accounting')->with('status', 'Aucune écriture sélectionnée.');
        }

        $deleted = 0;
        foreach ($entries as $entry) {
            TreasuryAudit::log($entry->user_id, 'accounting.entry.deleted', $entry, [
                'actor_user_id' => Auth::id(),
                'description' => $entry->description,
                'amount' => (float) $entry->amount,
                'document_reference' => $entry->document_reference,
                'bulk' => true,
            ]);

            if ($entry->attachment_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $entry->delete();
            $deleted++;
        }

        return redirect()
            ->route('accounting')
            ->with('status', $deleted.' écriture(s) supprimée(s) avec succès.');
    }

    public function bulkRetryEntryOcr(Request $request)
    {
        $entries = $this->getOwnedEntriesFromRequest($request);

        if ($entries->isEmpty()) {
            return redirect()->route('accounting')->with('status', 'Aucune écriture sélectionnée.');
        }

        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (! $entry->attachment_path) {
                $skipped++;

                continue;
            }

            if (in_array((string) $entry->ocr_status, ['verified', 'manual_verified'], true)) {
                $skipped++;

                continue;
            }

            $entryOcrAnalysis = $this->analyzeEntryAttachment(
                $entry->attachment_path,
                [
                    'document_reference' => $entry->document_reference ?? '',
                    'date' => $entry->date?->toDateString(),
                    'amount' => (float) $entry->amount,
                    'amount_ht' => (float) $entry->amount,
                    'amount_tva' => 0,
                    'ttc_amount' => (float) $entry->amount,
                    'tva_rate' => 0,
                    'partner_name' => '',
                ]
            );
            if (($entryOcrAnalysis['ocr_data']['ocr_status'] ?? null) === 'failed') {
                $entry->update(array_merge($entryOcrAnalysis['ocr_data'], [
                    'actor_user_id' => Auth::id(),
                ]));
                $failed++;

                continue;
            }

            $entry->update(array_merge($entryOcrAnalysis['ocr_data'], [
                'actor_user_id' => Auth::id(),
            ]));
            $success++;
        }

        $status = sprintf(
            'Relance OCR terminée: %d succès, %d échec(s), %d ignorée(s) (sans fichier).',
            $success,
            $failed,
            $skipped
        );

        return redirect()->route('accounting')->with('status', $status);
    }

    public function showEntry(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        $autoCorrectionProposal = $this->buildAutoCorrectionProposal($entry);

        return view('accounting.show-entry', compact('entry', 'autoCorrectionProposal'));
    }

    public function retryEntryOcr(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        if (! $entry->attachment_path) {
            return redirect()
                ->back()
                ->with('status', 'Relance OCR impossible: aucun justificatif attaché.')
                ->with('ocr_retry_error', true);
        }

        if (in_array((string) $entry->ocr_status, ['verified', 'manual_verified'], true)) {
            return redirect()
                ->back()
                ->with('status', 'OCR déjà vérifié pour cette écriture. Aucune relance nécessaire.');
        }

        $entryOcrAnalysis = $this->analyzeEntryAttachment(
            $entry->attachment_path,
            [
                'document_reference' => $entry->document_reference ?? '',
                'date' => $entry->date?->toDateString(),
                'amount' => (float) $entry->amount,
                'amount_ht' => (float) $entry->amount,
                'amount_tva' => 0,
                'ttc_amount' => (float) $entry->amount,
                'tva_rate' => 0,
                'partner_name' => '',
            ]
        );

        if (($entryOcrAnalysis['ocr_data']['ocr_status'] ?? null) === 'failed') {
            $entry->update([
                ...$entryOcrAnalysis['ocr_data'],
                'actor_user_id' => Auth::id(),
            ]);

            return redirect()
                ->back()
                ->with('status', 'OCR relancé, mais a échoué. Consultez le détail pour agir.')
                ->with('ocr_retry_error', true);
        }

        $entry->update([
            ...$entryOcrAnalysis['ocr_data'],
            'actor_user_id' => Auth::id(),
        ]);

        return redirect()
            ->back()
            ->with('status', 'OCR relancé avec succès.');
    }

    public function autoCorrectEntryFromOcr(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        request()->validate([
            'confirm_auto_correction' => ['required', 'accepted'],
        ]);

        $storedPath = $entry->document?->stored_path ?: $entry->attachment_path;
        if (! $storedPath) {
            return redirect()
                ->back()
                ->with('status', 'Correction automatique impossible: aucun justificatif OCR lié à cette écriture.')
                ->with('ocr_retry_error', true);
        }

        $ocrPipeline = new OcrPipelineService;
        $pipelineResult = $ocrPipeline->processStoredDocument($storedPath);
        $ocrResult = (array) ($pipelineResult['ocr_result'] ?? []);

        if (! ($ocrResult['success'] ?? false)) {
            $entry->update([
                'ocr_status' => 'failed',
                'ocr_detected_amount' => null,
                'ocr_verified_at' => null,
                'ocr_text' => $this->formatOcrFailureDetails($ocrResult),
                'actor_user_id' => Auth::id(),
            ]);

            return redirect()
                ->back()
                ->with('status', 'Correction automatique impossible: l’OCR du document a échoué.')
                ->with('ocr_retry_error', true);
        }

        $ocrText = (string) ($ocrResult['text'] ?? '');
        $ocrService = new OcrService;
        $initialVerification = $ocrService->verifyCompleteDocument($ocrText, [
            'document_reference' => $entry->document_reference ?? '',
            'date' => $entry->date?->toDateString(),
            'amount' => (float) $entry->amount,
            'amount_ht' => (float) $entry->amount,
            'amount_tva' => 0,
            'ttc_amount' => (float) $entry->amount,
            'tva_rate' => 0,
            'partner_name' => '',
        ]);

        $extracted = (array) ($initialVerification['extracted'] ?? []);
        $richExtracted = (array) ($pipelineResult['rich_data'] ?? []);
        if (empty($richExtracted)) {
            $richExtracted = $ocrService->extractRichDocumentData($ocrText);
        }

        $documentType = $this->detectDocumentTypeFromOcrText($ocrText, $entry->document_type ?: 'Justificatif');
        $normalized = $this->buildValidationExtractedData($ocrText, $extracted, $richExtracted, $documentType);
        $correctedPayload = $this->buildAutoCorrectedEntryPayload($entry, $normalized, $documentType);
        $verificationPayload = $this->buildAutoCorrectionVerificationPayload($correctedPayload, $normalized);
        $verifyResult = $ocrService->verifyCompleteDocument($ocrText, $verificationPayload);
        $ocrAnalysis = $this->buildEntryOcrDataFromVerification($verifyResult, $ocrText);

        $entry->update(array_merge($correctedPayload, $ocrAnalysis['ocr_data'], [
            'actor_user_id' => Auth::id(),
        ]));

        return redirect()
            ->route('accounting.entries.show', $entry)
            ->with('status', 'Écriture corrigée automatiquement depuis l’OCR'.$ocrAnalysis['status_suffix'].'.');
    }

    private function buildAutoCorrectionProposal(AccountingEntry $entry): ?array
    {
        if (OcrStatus::normalize((string) $entry->ocr_status) !== OcrStatus::MISMATCH) {
            return null;
        }

        $ocrText = trim($entry->getOcrRawText());
        if ($ocrText === '') {
            return null;
        }

        $ocrService = new OcrService;
        $initialVerification = $ocrService->verifyCompleteDocument($ocrText, [
            'document_reference' => $entry->document_reference ?? '',
            'date' => $entry->date?->toDateString(),
            'amount' => (float) $entry->amount,
            'amount_ht' => (float) $entry->amount,
            'amount_tva' => 0,
            'ttc_amount' => (float) $entry->amount,
            'tva_rate' => 0,
            'partner_name' => '',
        ]);

        $extracted = (array) ($initialVerification['extracted'] ?? []);
        $richExtracted = $ocrService->extractRichDocumentData($ocrText);
        $documentType = $this->detectDocumentTypeFromOcrText($ocrText, $entry->document_type ?: 'Justificatif');
        $normalized = $this->buildValidationExtractedData($ocrText, $extracted, $richExtracted, $documentType);
        $correctedPayload = $this->buildAutoCorrectedEntryPayload($entry, $normalized, $documentType);
        $verificationPayload = $this->buildAutoCorrectionVerificationPayload($correctedPayload, $normalized);

        $changes = $this->buildAutoCorrectionChanges($entry, $correctedPayload);

        if (empty($changes)) {
            return null;
        }

        return [
            'document_type' => $documentType,
            'normalized' => $normalized,
            'verification_payload' => $verificationPayload,
            'changes' => $changes,
        ];
    }

    public function storeManualOcrValidation(Request $request, AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        $validated = $request->validate([
            'confirm_document_read' => ['required', 'accepted'],
            'confirm_amount_match' => ['required', 'accepted'],
            'confirm_reference_checked' => ['required', 'accepted'],
            'manual_comment' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $manualLog = "=== VALIDATION MANUELLE OCR ===\n"
            .'Date: '.now()->format('Y-m-d H:i:s')."\n"
            .'Utilisateur: '.(Auth::user()?->email ?? 'N/A')."\n"
            .'Commentaire: '.trim($validated['manual_comment'])."\n\n";

        $entry->update([
            'ocr_status' => 'manual_verified',
            'ocr_verified_at' => now(),
            'ocr_text' => $manualLog.($entry->ocr_text ?? ''),
            'actor_user_id' => Auth::id(),
        ]);

        return redirect()
            ->route('accounting.entries.show', $entry)
            ->with('status', 'Validation manuelle enregistrée. Le fallback local est actif pour cette écriture.');
    }

    public function planComptable()
    {
        $plan = $this->getPlanAccounts();
        $source = $this->planExists() ? 'Base de données' : 'Plan par défaut';
        $validation = $this->validatePlan($plan);
        $qualityAlerts = $this->analyzePlanQuality($plan);
        $importHistory = $this->getImportHistory();

        return view('accounting.plan-comptable', compact('plan', 'source', 'validation', 'qualityAlerts', 'importHistory'));
    }

    public function uploadPlanComptable(Request $request)
    {
        $request->validate([
            'plan_comptable' => ['required', 'file', 'mimes:xls,xlsx,csv,pdf', 'max:20480'],
        ]);

        $file = $request->file('plan_comptable');
        $storedPath = $file->store('plan-comptable-imports', 'public');
        $fullPath = storage_path('app/public/'.$storedPath);
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $ocrPipeline = new OcrPipelineService;
            $pipelineResult = $ocrPipeline->processStoredDocument($storedPath);
            $ocrResult = (array) ($pipelineResult['ocr_result'] ?? []);
            if (! $ocrResult['success']) {
                Storage::disk('public')->delete($storedPath);

                return redirect()->back()->withErrors([
                    'plan_comptable' => 'PDF illisible ou OCR en échec : '.($ocrResult['message'] ?? 'erreur inconnue'),
                ]);
            }
            $result = $this->parsePlanComptableFromText($ocrResult['text'] ?? '');
        } else {
            $result = $this->parsePlanComptable($fullPath);
        }

        Storage::disk('public')->delete($storedPath);
        $plan = $result['accounts'];
        $invalidRows = $result['invalidRows'];

        if (empty($plan)) {
            $this->logPlanComptableImport(
                $file->getClientOriginalName(),
                'failed',
                'Aucun compte valide trouvé dans le fichier. Vérifiez les colonnes Compte/Code et Intitulé/Libellé.',
                0,
                count($invalidRows),
                $invalidRows
            );

            return redirect()->back()->withErrors(['plan_comptable' => 'Aucun compte valide trouvé dans le fichier. Vérifiez les colonnes Compte/Code et Intitulé/Libellé.']);
        }

        $this->savePlanAccounts($plan);

        $status = empty($invalidRows) ? 'success' : 'partial';
        $message = empty($invalidRows)
            ? 'Plan comptable importé avec succès.'
            : 'Plan comptable importé avec des lignes invalides.';

        $this->logPlanComptableImport(
            $file->getClientOriginalName(),
            $status,
            $message,
            count($plan),
            count($invalidRows),
            $invalidRows
        );

        $response = redirect()->route('accounting.plan')->with('status', $message);
        if (! empty($invalidRows)) {
            $response = $response->with('invalidRows', $invalidRows);
        }

        return $response;
    }

    public function updatePlanComptable(Request $request)
    {
        $request->validate([
            'plan' => ['required', 'array'],
        ]);

        $plan = [];
        foreach ($request->input('plan') as $key => $accountData) {
            $normalizedKey = (string) $key;
            $prefix = (string) ($accountData['prefix'] ?? $normalizedKey);

            $label = trim((string) ($accountData['label'] ?? $accountData['libelle_compte'] ?? ''));
            if ($label === '') {
                continue;
            }

            $account = [
                'prefix' => $prefix,
                'label' => $label,
                'libelle_compte' => $accountData['libelle_compte'] ?? $label,
                'category' => $this->getCategoryByPrefix($prefix),
                'subtype' => $accountData['subtype'] ?? $this->getSubtypeByPrefix($prefix),
            ];

            // Add detailed fields if present
            if (isset($accountData['numero_compte'])) {
                $account['numero_compte'] = $accountData['numero_compte'];
            }
            if (isset($accountData['type_compte'])) {
                $account['type_compte'] = $accountData['type_compte'];
            }
            if (isset($accountData['classe'])) {
                $account['classe'] = $accountData['classe'];
            } else {
                $account['classe'] = $prefix;
            }

            $plan[$account['numero_compte'] ?? $prefix] = $account;
        }

        $validation = $this->validatePlan($plan);
        if (! empty($validation['missingClasses'])) {
            return redirect()
                ->route('accounting.plan')
                ->withErrors([
                    'plan' => 'Impossible d’enregistrer : classes manquantes ('.implode(', ', $validation['missingClasses']).').',
                ]);
        }

        $this->savePlanAccounts($plan);

        return redirect()->route('accounting.plan')->with(
            'status',
            'Plan comptable mis à jour avec règles expertes (catégories pilotées par classes 1 à 7).'
        );
    }

    public function resetPlanComptable()
    {
        PlanComptableAccount::where('user_id', $this->workspaceUserId())->delete();

        return redirect()->route('accounting.plan')->with('status', 'Plan comptable réinitialisé au plan par défaut.');
    }

    public function downloadPlanComptableTemplate()
    {
        $filePath = storage_path('app/public/plan-comptable-modele.csv');

        if (! file_exists($filePath)) {
            abort(404, 'Modèle de plan comptable non trouvé.');
        }

        return response()->download($filePath, 'plan-comptable-modele.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function analyzeSyscohadaFile()
    {
        $filePath = base_path('Doc_comptabilite/modele_syscohada_PLAN COMPLET + LIASSE BCEAO_5.xlsx');
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Fichier introuvable', 'path' => $filePath], 404);
        }

        $spreadsheet = IOFactory::load($filePath);
        $result = [];

        foreach ($spreadsheet->getAllSheets() as $sheetIndex => $sheet) {
            $sheetName = $sheet->getTitle();
            $highestRow = $sheet->getHighestRow();
            $highestCol = $sheet->getHighestColumn();

            $rows = [];
            $maxRowsToShow = 50;

            for ($row = 1; $row <= min($highestRow, $maxRowsToShow); $row++) {
                $cells = [];
                for ($col = 'A'; $col <= $highestCol; $col++) {
                    $cellValue = $sheet->getCell($col . $row)->getValue();
                    $cells[] = mb_substr(trim((string)$cellValue), 0, 100);
                }
                $rows[] = $cells;
            }

            $result[] = [
                'name' => $sheetName,
                'index' => $sheetIndex,
                'dimensions' => "{$highestCol} x {$highestRow}",
                'rows' => $rows,
                'total_rows' => $highestRow,
                'total_cols' => $highestCol
            ];
        }

        return response()->json($result);
    }

    private function parsePlanComptable(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $accounts = [];
        $invalidRows = [];
        $headers = [];

        $codeHeaders = ['compte', 'code', 'account', 'account number', 'numero', 'numéro', 'n°', 'n', 'num', 'no', 'compte comptable'];
        $labelHeaders = ['intitulé', 'intitule', 'libellé', 'libelle', 'designation', 'label', 'description', 'name', 'nom', 'nom du compte', 'intitulé compte'];
        $typeHeaders = ['type', 'type compte', 'type_compte', 'nature', 'type de compte'];
        $classeHeaders = ['classe', 'class'];

        foreach ($rows as $rowIndex => $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            if (! $headers) {
                foreach ($row as $column => $value) {
                    $normalized = mb_strtolower(trim((string) $value));

                    foreach ($codeHeaders as $candidate) {
                        if (str_contains($normalized, $candidate)) {
                            $headers['code'] = $column;
                            break;
                        }
                    }

                    foreach ($labelHeaders as $candidate) {
                        if (str_contains($normalized, $candidate)) {
                            $headers['label'] = $column;
                            break;
                        }
                    }
                    
                    foreach ($typeHeaders as $candidate) {
                        if (str_contains($normalized, $candidate)) {
                            $headers['type'] = $column;
                            break;
                        }
                    }
                    
                    foreach ($classeHeaders as $candidate) {
                        if (str_contains($normalized, $candidate)) {
                            $headers['classe'] = $column;
                            break;
                        }
                    }
                }

                if (! isset($headers['code']) || ! isset($headers['label'])) {
                    continue;
                }

                continue;
            }

            $code = trim((string) ($row[$headers['code']] ?? ''));
            $label = trim((string) ($row[$headers['label']] ?? ''));
            $type = trim((string) ($row[$headers['type']] ?? ''));
            $classe = trim((string) ($row[$headers['classe']] ?? ''));
            $reason = null;

            if (! $code || ! $label) {
                $reason = 'Compte ou libellé manquant';
            } else {
                $prefix = preg_match('/^([1-7])/', $code, $matches) ? $matches[1] : null;
                if (! $prefix) {
                    $reason = 'Code invalide ou sans classe 1-7';
                }
                if (empty($classe)) {
                    $classe = $prefix;
                }
            }

            if ($reason) {
                $invalidRows[] = [
                    'row' => $rowIndex,
                    'code' => $code,
                    'label' => $label,
                    'reason' => $reason,
                ];

                continue;
            }

            $accounts[$code] = [
                'numero_compte' => $code,
                'libelle_compte' => $label,
                'label' => $label,
                'type_compte' => $type,
                'classe' => $classe,
                'prefix' => $prefix,
                'category' => $this->getCategoryByPrefix($prefix),
                'subtype' => $this->getSubtypeByPrefix($prefix),
                'is_actif' => true,
                'sort_order' => count($accounts),
            ];
        }

        if (empty($accounts) && empty($headers)) {
            $invalidRows[] = [
                'row' => 'N/A',
                'code' => '',
                'label' => '',
                'reason' => 'En-têtes non reconnues : utilisez des colonnes Compte/Code et Intitulé/Libellé.',
            ];
        }

        return [
            'accounts' => $accounts,
            'invalidRows' => $invalidRows,
        ];
    }

    /**
     * Extrait les classes 1–7 depuis un texte OCR (PDF scanné ou export texte).
     */
    private function parsePlanComptableFromText(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $accounts = [];
        $invalidRows = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || mb_strlen($line) < 4) {
                continue;
            }

            if (preg_match('/^(compte|code|libellé|libelle|intitulé|intitule|numéro|numero)\b/ui', $line)) {
                continue;
            }

            if (preg_match('/^([1-7][0-9]{0,8})\s+(.{2,})$/u', $line, $matches)) {
                $code = $matches[1];
                $label = trim($matches[2]);
                $label = preg_replace('/\s{2,}/u', ' ', $label) ?? $label;

                if (! preg_match('/^([1-7])/', $code, $prefixMatch)) {
                    continue;
                }
                $prefix = $prefixMatch[1];

                $accounts[$prefix] = [
                    'label' => $label,
                    'category' => $this->getCategoryByPrefix($prefix),
                    'subtype' => $this->getSubtypeByPrefix($prefix),
                ];

                continue;
            }

            if (preg_match('/^([1-7])\s+[-–—]?\s*(.{2,})$/u', $line, $matches)) {
                $prefix = $matches[1];
                $label = trim($matches[2]);
                $accounts[$prefix] = [
                    'label' => $label,
                    'category' => $this->getCategoryByPrefix($prefix),
                    'subtype' => $this->getSubtypeByPrefix($prefix),
                ];
            }
        }

        if (empty($accounts)) {
            $invalidRows[] = [
                'row' => 'N/A',
                'code' => '',
                'label' => '',
                'reason' => 'Aucune ligne reconnue dans le PDF. Préférez un export Excel/CSV ou un PDF avec texte sélectionnable.',
            ];
        }

        return [
            'accounts' => $accounts,
            'invalidRows' => $invalidRows,
        ];
    }

    private function planExists(): bool
    {
        return PlanComptableAccount::where('user_id', $this->workspaceUserId())->exists();
    }

    private function savePlanAccounts(array $accounts): void
    {
        $userId = $this->workspaceUserId();

        PlanComptableAccount::where('user_id', $userId)->delete();

        foreach ($accounts as $key => $account) {
            $data = [
                'user_id' => $userId,
                'prefix' => $account['prefix'] ?? (is_numeric($key) && $key >=1 && $key <=7 ? $key : null),
                'label' => $account['label'] ?? $account['libelle_compte'] ?? '',
                'category' => $account['category'] ?? 'other',
                'subtype' => $account['subtype'] ?? null,
            ];
            if (isset($account['numero_compte'])) {
                $data['numero_compte'] = $account['numero_compte'];
            }
            if (isset($account['libelle_compte'])) {
                $data['libelle_compte'] = $account['libelle_compte'];
            }
            if (isset($account['type_compte'])) {
                $data['type_compte'] = $account['type_compte'];
            }
            if (isset($account['sous_type'])) {
                $data['sous_type'] = $account['sous_type'];
            }
            if (isset($account['classe'])) {
                $data['classe'] = $account['classe'];
            }
            if (isset($account['observation'])) {
                $data['observation'] = $account['observation'];
            }
            if (isset($account['is_actif'])) {
                $data['is_actif'] = $account['is_actif'];
            }
            if (isset($account['sort_order'])) {
                $data['sort_order'] = $account['sort_order'];
            }
            PlanComptableAccount::create($data);
        }
    }

    private function getImportHistory()
    {
        return PlanComptableImport::where('user_id', $this->workspaceUserId())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    private function logPlanComptableImport(string $originalFilename, string $status, string $message, int $validRows, int $invalidRows, array $invalidDetails = []): void
    {
        PlanComptableImport::create([
            'user_id' => $this->workspaceUserId(),
            'original_filename' => $originalFilename,
            'status' => $status,
            'message' => $message,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'invalid_details' => $invalidDetails,
        ]);
    }

    private function validatePlan(array $plan): array
    {
        $expected = ['1', '2', '3', '4', '5', '6', '7'];
        // Check if it's class-based first
        $keys = array_keys($plan);
        $isClassBased = count(array_filter($keys, fn($k) => in_array($k, $expected))) === count($keys);
        
        if ($isClassBased) {
            $missing = array_values(array_diff($expected, $keys));
            return [
                'missingClasses' => $missing,
                'isValid' => empty($missing),
            ];
        }
        
        // For detailed accounts, check we have at least one account per class
        $presentClasses = [];
        foreach ($plan as $account) {
            $classe = $account['classe'] ?? $account['prefix'] ?? null;
            if ($classe && in_array($classe, $expected)) {
                $presentClasses[] = $classe;
            }
        }
        
        $presentClasses = array_unique($presentClasses);
        $missing = array_values(array_diff($expected, $presentClasses));
        
        return [
            'missingClasses' => $missing,
            'isValid' => empty($missing),
        ];
    }

    /**
     * Détecte les libellés de classes trop vagues pour la lecture comptable.
     */
    private function analyzePlanQuality(array $plan): array
    {
        $genericPatterns = [
            '/^divers?$/i',
            '/^autres?$/i',
            '/^g[eé]n[eé]ral(?:e|es)?$/i',
            '/^compte(?:s)?$/i',
            '/^n\/?a$/i',
            '/^test$/i',
            '/^classe\s*[1-7]$/i',
        ];

        $alerts = [];
        foreach ($plan as $prefix => $account) {
            $label = trim((string) ($account['label'] ?? ''));
            if ($label === '') {
                $alerts[] = [
                    'prefix' => (string) $prefix,
                    'label' => '(vide)',
                    'reason' => 'Libellé vide : classe non exploitable dans les états.',
                ];

                continue;
            }

            foreach ($genericPatterns as $pattern) {
                if (preg_match($pattern, $label)) {
                    $alerts[] = [
                        'prefix' => (string) $prefix,
                        'label' => $label,
                        'reason' => 'Libellé trop générique : préciser la nature économique de la classe.',
                    ];
                    break;
                }
            }
        }

        return $alerts;
    }

    private function getCategoryByPrefix(?string $prefix): string
    {
        return match ($prefix) {
            '1', '2', '3', '4', '5' => 'balance',
            '6' => 'resultat',
            '7' => 'resultat',
            default => 'other',
        };
    }

    private function getSubtypeByPrefix(?string $prefix): ?string
    {
        return match ($prefix) {
            '2' => 'investissement',
            '6' => 'charge',
            '7' => 'produit',
            default => null,
        };
    }

    private function resolvePrefillDocument(Request $request): ?AccountingDocument
    {
        $documentId = (int) $request->query('prefill_document', 0);
        if ($documentId <= 0) {
            return null;
        }

        $document = AccountingDocument::findOrFail($documentId);
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }

        return $document;
    }

    private function resolveLinkedDocumentFromRequest(Request $request): ?AccountingDocument
    {
        $documentId = (int) $request->input('document_id', 0);
        if ($documentId <= 0) {
            return null;
        }

        $document = AccountingDocument::findOrFail($documentId);
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }

        return $document;
    }

    private function buildEntryPrefillData(AccountingDocument $document): array
    {
        $data = (array) $document->extracted_data;
        $richPrimary = (array) (($data['ocr_detected_fields']['primary'] ?? []));
        $amountHt = (float) ($data['amount_ht'] ?? 0);
        $amountTva = (float) ($data['tva'] ?? 0);
        $amountTtc = (float) ($data['amount_ttc'] ?? ($amountHt + $amountTva));

        if ($amountHt <= 0 && is_numeric($richPrimary['amount_ht'] ?? null)) {
            $amountHt = (float) $richPrimary['amount_ht'];
        }
        if ($amountTva <= 0 && is_numeric($richPrimary['amount_tva'] ?? null)) {
            $amountTva = (float) $richPrimary['amount_tva'];
        }
        if ($amountTtc <= 0 && is_numeric($richPrimary['amount_ttc'] ?? null)) {
            $amountTtc = (float) $richPrimary['amount_ttc'];
        }

        $documentReference = trim((string) ($data['invoice_number'] ?? ($richPrimary['invoice_number'] ?? '')));
        $partner = trim((string) ($data['partner'] ?? ($richPrimary['partner_name'] ?? '')));
        $invoiceDate = $data['invoice_date'] ?? ($richPrimary['invoice_date'] ?? null);
        $description = trim(implode(' - ', array_values(array_filter([
            $partner !== '' ? $partner : 'Document OCR',
            $documentReference !== '' ? $documentReference : null,
        ]))));

        return [
            'document_id' => $document->id,
            'document_type' => $document->document_type ?: 'Justificatif',
            'partner_name' => $partner,
            'date' => $invoiceDate ?? now()->toDateString(),
            'document_reference' => $documentReference,
            'description' => $description !== '' ? $description : 'Document OCR à vérifier',
            'amount' => $amountHt > 0 ? $amountHt : max(0, $amountTtc - $amountTva),
            'amount_tva' => $amountTva,
            'ttc_amount' => $amountTtc,
            'tva_rate' => $this->computePrefillTvaRate($amountHt, $amountTva),
        ];
    }

    private function computePrefillTvaRate(float $amountHt, float $amountTva): float
    {
        if ($amountHt <= 0 || $amountTva <= 0) {
            return 18.0;
        }

        return round(($amountTva / $amountHt) * 100, 2);
    }

    private function buildEntryVerificationPayload(Request $request, array $validated): array
    {
        $amountHt = (float) ($validated['amount'] ?? 0);
        $amountTva = (float) $request->input('amount_tva', 0);

        return [
            'document_reference' => $validated['document_reference'] ?? '',
            'date' => $validated['date'],
            'amount' => $amountHt,
            'amount_ht' => $amountHt,
            'amount_tva' => $amountTva,
            'ttc_amount' => (float) $request->input('ttc_amount', $amountHt + $amountTva),
            'tva_rate' => (float) $request->input('tva_rate', 0),
            'partner_name' => (string) $request->input('partner_name', ''),
        ];
    }

    private function analyzeEntryAttachment(string $storedPath, array $formData): array
    {
        $ocrPipeline = new OcrPipelineService;
        $pipelineResult = $ocrPipeline->processStoredDocument($storedPath);
        $ocrResult = (array) ($pipelineResult['ocr_result'] ?? []);

        if (! ($ocrResult['success'] ?? false)) {
            return [
                'ocr_data' => [
                    'ocr_status' => 'failed',
                    'ocr_detected_amount' => null,
                    'ocr_verified_at' => null,
                    'ocr_text' => $this->formatOcrFailureDetails($ocrResult),
                ],
                'status_suffix' => ' (Erreur OCR: '.($ocrResult['message'] ?? 'erreur inconnue').')',
            ];
        }

        $ocrService = new OcrService;
        $verifyResult = $ocrService->verifyCompleteDocument((string) ($ocrResult['text'] ?? ''), $formData);

        return $this->buildEntryOcrDataFromVerification($verifyResult, (string) ($ocrResult['text'] ?? ''));
    }

    private function buildEntryOcrDataFromVerification(array $verifyResult, string $ocrText): array
    {
        $verificationDetails = array_values((array) ($verifyResult['details'] ?? []));
        $extracted = (array) ($verifyResult['extracted'] ?? []);
        $detectedAmount = $this->toFloatOrNull(
            $extracted['amount_ttc_fcfa']
            ?? $extracted['amount_ttc']
            ?? $extracted['amount_ht_fcfa']
            ?? $extracted['amount_ht']
            ?? null
        );

        $statusSuffix = '';
        if (($verifyResult['overall_status'] ?? null) === 'verified' && count($verificationDetails) > 0) {
            $statusSuffix = ' et vérification OCR ✅ ('.count($verificationDetails).'/'.($verifyResult['total_fields'] ?? count($verificationDetails)).' champs)';
        } elseif (OcrStatus::normalize((string) ($verifyResult['overall_status'] ?? null)) === OcrStatus::MISMATCH) {
            $statusSuffix = ' (⚠️ Certains champs OCR ne correspondent pas)';
        }

        return [
            'ocr_data' => [
                'ocr_status' => OcrStatus::normalize((string) ($verifyResult['overall_status'] ?? 'verified')),
                'ocr_detected_amount' => $detectedAmount,
                'ocr_verified_at' => now(),
                'ocr_text' => $this->buildOcrVerificationNarrative($verifyResult, $ocrText),
            ],
            'status_suffix' => $statusSuffix,
        ];
    }

    private function buildAutoCorrectedEntryPayload(AccountingEntry $entry, array $normalized, string $documentType): array
    {
        $accounts = $this->resolveAccountsForDocumentType($documentType);
        $partner = trim((string) ($normalized['partner'] ?? ''));
        $reference = trim((string) ($normalized['invoice_number'] ?? ''));
        $amountHt = $this->toFloatOrNull($normalized['amount_ht'] ?? null) ?? 0.0;
        $amountTva = $this->toFloatOrNull($normalized['tva'] ?? null) ?? 0.0;
        $amountTtc = $this->toFloatOrNull($normalized['amount_ttc'] ?? null) ?? 0.0;
        $targetAmount = $amountTtc > 0
            ? $amountTtc
            : ($amountHt > 0 ? max($amountHt, $amountHt + $amountTva) : (float) $entry->amount);

        return [
            'date' => $normalized['invoice_date'] ?? $entry->date?->toDateString() ?? now()->toDateString(),
            'document_type' => $documentType,
            'document_reference' => $reference !== '' ? $reference : $entry->document_reference,
            'description' => $this->buildAutoCorrectedEntryDescription($entry, $partner, $reference),
            'debit_account' => $accounts['debit'],
            'credit_account' => $accounts['credit'],
            'amount' => $targetAmount,
        ];
    }

    private function buildAutoCorrectionVerificationPayload(array $correctedPayload, array $normalized): array
    {
        $amountHt = $this->toFloatOrNull($normalized['amount_ht'] ?? null) ?? 0.0;
        $amountTva = $this->toFloatOrNull($normalized['tva'] ?? null) ?? 0.0;
        $amountTtc = $this->toFloatOrNull($normalized['amount_ttc'] ?? null) ?? 0.0;
        $partner = trim((string) ($normalized['partner'] ?? ''));

        if ($amountHt <= 0 && $amountTtc > 0 && $amountTva >= 0 && $amountTtc >= $amountTva) {
            $amountHt = max(0.0, $amountTtc - $amountTva);
        }
        if ($amountTtc <= 0) {
            $amountTtc = (float) ($correctedPayload['amount'] ?? 0);
        }

        return [
            'document_reference' => (string) ($correctedPayload['document_reference'] ?? ''),
            'date' => (string) ($correctedPayload['date'] ?? now()->toDateString()),
            'amount' => $amountHt > 0 ? $amountHt : $amountTtc,
            'amount_ht' => $amountHt > 0 ? $amountHt : max(0.0, $amountTtc - $amountTva),
            'amount_tva' => $amountTva,
            'ttc_amount' => $amountTtc,
            'tva_rate' => $amountHt > 0 && $amountTva > 0 ? round(($amountTva / $amountHt) * 100, 2) : 0.0,
            'partner_name' => $partner,
        ];
    }

    private function buildAutoCorrectedEntryDescription(AccountingEntry $entry, string $partner, string $reference): string
    {
        $parts = array_values(array_filter([$partner !== '' ? $partner : null, $reference !== '' ? $reference : null]));
        if (! empty($parts)) {
            return '[OCR] '.implode(' - ', $parts);
        }

        return (string) $entry->description;
    }

    private function buildAutoCorrectionChanges(AccountingEntry $entry, array $correctedPayload): array
    {
        $labels = [
            'date' => 'Date',
            'document_type' => 'Type',
            'document_reference' => 'Référence',
            'description' => 'Description',
            'debit_account' => 'Compte débit',
            'credit_account' => 'Compte crédit',
            'amount' => 'Montant',
        ];

        $changes = [];
        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $correctedPayload)) {
                continue;
            }

            $before = $field === 'date'
                ? optional($entry->date)->toDateString()
                : $entry->{$field};
            $after = $correctedPayload[$field];

            if ((string) $before === (string) $after) {
                continue;
            }

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'before' => $this->formatAutoCorrectionValue($field, $before),
                'after' => $this->formatAutoCorrectionValue($field, $after),
            ];
        }

        return $changes;
    }

    private function formatAutoCorrectionValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Non renseigné';
        }

        if ($field === 'amount' && is_numeric($value)) {
            return number_format((float) $value, 2, ',', ' ').' FCFA';
        }

        return (string) $value;
    }

    private function buildOcrVerificationNarrative(array $verifyResult, string $ocrText): string
    {
        $summaryLines = $this->buildOcrVerificationSummaryLines($verifyResult);
        $cleanText = trim($ocrText);

        if (empty($summaryLines)) {
            return $cleanText;
        }

        $parts = [
            '=== RÉSUMÉ OCR ===',
            implode("\n", $summaryLines),
        ];

        if ($cleanText !== '') {
            $parts[] = '=== TEXTE OCR ===';
            $parts[] = $cleanText;
        }

        return implode("\n\n", $parts);
    }

    private function buildOcrVerificationSummaryLines(array $verifyResult): array
    {
        $details = (array) ($verifyResult['details'] ?? []);
        $lines = [];

        $fieldLabels = [
            'amount_ht' => 'HT',
            'amount_tva' => 'TVA',
            'amount_ttc' => 'TTC',
            'tva_rate' => 'TVA %',
            'invoice_number' => 'Référence',
            'date' => 'Date',
            'partner_name' => 'Partenaire',
        ];

        foreach ($fieldLabels as $key => $label) {
            $detail = trim((string) ($details[$key] ?? ''));
            if ($detail === '') {
                continue;
            }

            if (str_starts_with($detail, '✅')) {
                $lines[] = '✅ '.$label.' OK';

                continue;
            }

            if (str_starts_with($detail, '⚠️')) {
                $lines[] = '⚠️ '.$label.' différent';
            }
        }

        return $lines;
    }

    private function syncDocumentAfterManualEntry(
        AccountingDocument $document,
        AccountingEntry $entry,
        Request $request,
        array $validated,
        ?string $attachmentPath = null
    ): void {
        $existingData = (array) $document->extracted_data;
        $amountHt = (float) ($validated['amount'] ?? 0);
        $amountTva = (float) $request->input('amount_tva', 0);
        $amountTtc = (float) $request->input('ttc_amount', $amountHt + $amountTva);
        $partner = trim((string) $request->input('partner_name', $existingData['partner'] ?? ''));

        $document->update([
            'document_type' => $validated['document_type'],
            'status' => 'validated',
            'stored_path' => $attachmentPath ?: $document->stored_path,
            'actor_user_id' => Auth::id(),
            'confidence' => 100.0,
            'extracted_data' => array_merge($existingData, [
                'partner' => $partner !== '' ? $partner : null,
                'invoice_date' => $validated['date'],
                'invoice_number' => $validated['document_reference'] ?? null,
                'amount_ht' => $amountHt,
                'amount_ttc' => $amountTtc,
                'tva' => $amountTva,
                'currency' => $existingData['currency'] ?? 'FCFA',
                'debit_account' => $entry->debit_account,
                'credit_account' => $entry->credit_account,
                'linked_entry_id' => $entry->id,
            ]),
        ]);
    }

    /**
     * Construit un message d'erreur OCR détaillé pour faciliter le fallback manuel.
     */
    private function formatOcrFailureDetails(array $ocrResult): string
    {
        $message = trim((string) ($ocrResult['message'] ?? 'Erreur OCR non détaillée'));
        $lower = mb_strtolower($message);
        $reason = 'Erreur technique OCR';
        $location = (string) ($ocrResult['error_location'] ?? 'Non précisée');
        $endpoint = (string) ($ocrResult['endpoint'] ?? 'Non précisé');
        $httpStatus = $ocrResult['http_status'] ?? null;
        $errorCode = (string) ($ocrResult['error_code'] ?? 'OCR_UNKNOWN');

        if (str_contains($lower, 'format')) {
            $reason = 'Format de fichier non supporté';
        } elseif (str_contains($lower, 'timeout') || str_contains($lower, 'timed out')) {
            $reason = 'Délai dépassé (timeout OCR)';
        } elseif (str_contains($lower, 'aucun texte')) {
            $reason = 'Texte non détecté';
        } elseif (str_contains($lower, 'api')) {
            $reason = 'Réponse API OCR invalide';
        } elseif (str_contains($lower, 'fichier non trouvé')) {
            $reason = 'Fichier justificatif introuvable';
        } elseif (str_contains($lower, 'runner paddleocr')) {
            $reason = 'Runner local PaddleOCR introuvable';
        } elseif (str_contains($lower, 'paddleocr')) {
            $reason = 'Erreur du moteur OCR local PaddleOCR';
        }

        $action = "Relancer l'OCR ou utiliser la validation manuelle guidée.";
        if ($httpStatus === 404) {
            $action = "Vérifier l'URL de l'endpoint OCR et la connectivité sortante serveur.";
        } elseif ($httpStatus === 401 || $httpStatus === 403) {
            $action = 'Vérifier la clé API OCR (droits, quota, activation).';
        } elseif ($errorCode === 'UNSUPPORTED_MIME') {
            $action = 'Utiliser uniquement un fichier JPG, JPEG, PNG ou PDF lisible.';
        } elseif ($errorCode === 'LOCAL_OCR_DISABLED') {
            $action = "Activer PADDLE_OCR_ENABLED dans l'environnement Laravel.";
        } elseif ($errorCode === 'LOCAL_OCR_RUNNER_NOT_FOUND') {
            $action = 'Vérifier le chemin PADDLE_OCR_RUNNER_PATH et la présence du script Python local.';
        } elseif ($errorCode === 'PADDLE_OCR_IMPORT_ERROR') {
            $action = "Installer PaddleOCR et PaddlePaddle dans l'environnement Python configuré.";
        } elseif ($errorCode === 'PADDLE_OCR_RUNTIME_ERROR') {
            $action = 'Tester le runner local en CPU, puis vérifier la compatibilité GPU si nécessaire.';
        } elseif ($errorCode === 'LOCAL_OCR_TIMEOUT') {
            $action = 'Augmenter PADDLE_OCR_TIMEOUT ou réduire la taille / le nombre de pages du document.';
        }

        return "=== ERREUR OCR ===\n"
            .'Type: '.$reason."\n"
            .'Code: '.$errorCode."\n"
            .'Message: '.$message."\n"
            .'Emplacement: '.$location."\n"
            .'Endpoint: '.$endpoint."\n"
            .($httpStatus ? ('HTTP Status: '.$httpStatus."\n") : '')
            .'Date: '.now()->format('Y-m-d H:i:s')."\n"
            .'Action recommandée: '.$action."\n";
    }

    public function report(Request $request)
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $entries = AccountingEntry::whereIn('user_id', $this->workspaceDataUserIds())
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $summary = $this->summarizeEntries($entries);
        $user = Auth::user();
        $companyName = $user->company_name ?: $user->company_designation ?: config('plancomptable.company.name', config('app.name'));
        $companySigle = $user->company_sigle;
        $companyAddress = $user->address;
        $companyTaxId = $user->company_tax_id ?: $user->rccm;
        $companyLogo = $user->company_logo;

        $periodStart = $entries->min('date');
        $periodEnd = $entries->max('date');
        $exerciseDate = $periodEnd ?? now();

        $reportType = 'full';
        if ($request->routeIs('accounting.report.journal')) {
            $reportType = 'journal';
        } elseif ($request->routeIs('accounting.report.grand-livre')) {
            $reportType = 'grand-livre';
        } elseif ($request->routeIs('accounting.report.balance')) {
            $reportType = 'balance';
        } elseif ($request->routeIs('accounting.report.bilan')) {
            $reportType = 'bilan';
        } elseif ($request->routeIs('accounting.report.resultat')) {
            $reportType = 'resultat';
        } elseif ($request->routeIs('accounting.report.tafire')) {
            $reportType = 'tafire';
        } elseif ($request->routeIs('accounting.report.annexe')) {
            $reportType = 'annexe';
        }

        $bilanReference = null;
        $qrUrl = null;
        if ($reportType === 'bilan') {
            $referenceInput = sprintf(
                '%s|%s|%s|%s|%s|%s|%s',
                $companyName,
                $companySigle,
                $companyTaxId,
                $exerciseDate->format('Y'),
                $periodEnd ? $periodEnd->format('Y-m-d') : '',
                $this->workspaceUserId(),
                now()->format('Y-m-d H:i:s')
            );

            $bilanReference = strtoupper(Str::substr(hash('sha256', $referenceInput), 0, 16));
            $qrData = sprintf('BILAN|%s|%s|%s|%s|REF:%s', $companyName, $companySigle, $companyTaxId, $exerciseDate->format('Y'), $bilanReference);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($qrData);
        }

        return view('accounting.report', array_merge([
            'entries' => $entries,
            'companyName' => $companyName,
            'companySigle' => $companySigle,
            'companyAddress' => $companyAddress,
            'companyTaxId' => $companyTaxId,
            'companyLogo' => $companyLogo,
            'bilanReference' => $bilanReference,
            'qrUrl' => $qrUrl,
            'exerciseEnd' => $periodEnd ? $periodEnd->format('d/m/Y') : '',
            'exerciseYear' => $exerciseDate->format('Y'),
            'previousYear' => $exerciseDate->copy()->subYear()->format('Y'),
            'durationMonths' => $periodStart && $periodEnd ? Carbon::parse($periodStart)->diffInMonths(Carbon::parse($periodEnd)) + 1 : '',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
        ], $summary));
    }

    public function downloadBilan(Request $request)
    {
        $payload = $this->buildReportPayload($request, 'bilan');

        return Pdf::setOptions(['isRemoteEnabled' => true])
            ->loadView('accounting.report-bilan-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->download('bilan-'.($payload['bilanReference'] ?? 'rapport').'.pdf');
    }

    public function viewBilanPdf(Request $request)
    {
        $payload = $this->buildReportPayload($request, 'bilan');

        return Pdf::setOptions(['isRemoteEnabled' => true])
            ->loadView('accounting.report-bilan-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->stream('bilan-'.($payload['bilanReference'] ?? 'rapport').'.pdf');
    }

    public function showBilanPdfViewer(Request $request): View
    {
        $payload = $this->buildReportPayload($request, 'bilan');
        $pdfBinary = Pdf::setOptions(['isRemoteEnabled' => true])
            ->loadView('accounting.report-bilan-pdf', $payload)
            ->setPaper('a4', 'portrait')
            ->output();

        $queryParams = array_filter([
            'date_from' => $request->query('date_from'),
            'date_to' => $request->query('date_to'),
        ]);

        return view('accounting.document-viewer', [
            'documentName' => 'Bilan - '.($payload['exerciseYear'] ?? now()->format('Y')),
            'documentTypeLabel' => 'Rapport bilan (PDF généré)',
            'previewType' => 'pdf',
            'previewUrl' => route('accounting.report.bilan.view', $queryParams),
            'backUrl' => route('accounting.report.bilan', $queryParams),
            'mimeType' => 'application/pdf',
            'fileExtension' => 'PDF',
            'fileSizeLabel' => $this->formatFileSizeLabel(strlen($pdfBinary)),
            'textPreview' => null,
            'pdfDataBase64' => base64_encode($pdfBinary),
        ]);
    }

    private function formatFileSizeLabel(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, ',', ' ').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return $bytes.' octets';
    }

    private function buildReportPayload(Request $request, string $reportType = 'full'): array
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $entries = AccountingEntry::whereIn('user_id', $this->workspaceDataUserIds())
            ->when($dateFrom, function ($query, $dateFrom) {
                $query->whereDate('date', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query, $dateTo) {
                $query->whereDate('date', '<=', $dateTo);
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $summary = $this->summarizeEntries($entries);
        $user = Auth::user();
        $companyName = $user->company_name ?: $user->company_designation ?: config('plancomptable.company.name', config('app.name'));
        $companySigle = $user->company_sigle ?? '';
        $companyAddress = $user->address ?? '';
        $companyTaxId = $user->company_tax_id ?: $user->rccm;

        // Convert logo to base64 for PDF rendering
        $companyLogo = null;
        if ($user->company_logo) {
            $logoPath = storage_path('app/public/'.$user->company_logo);
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $logoMime = finfo_file($finfo, $logoPath);
                finfo_close($finfo);
                $companyLogo = 'data:'.$logoMime.';base64,'.base64_encode($logoData);
            }
        }

        $periodStart = $entries->min('date');
        $periodEnd = $entries->max('date');
        $exerciseDate = $periodEnd ?? now();

        $bilanReference = null;
        $qrUrl = null;
        if ($reportType === 'bilan') {
            $referenceInput = sprintf(
                '%s|%s|%s|%s|%s|%s|%s',
                $companyName,
                $companySigle,
                $companyTaxId,
                $exerciseDate->format('Y'),
                $periodEnd ? $periodEnd->format('Y-m-d') : '',
                $this->workspaceUserId(),
                now()->format('Y-m-d H:i:s')
            );

            $bilanReference = strtoupper(Str::substr(hash('sha256', $referenceInput), 0, 16));
            $qrData = sprintf('BILAN|%s|%s|%s|%s|REF:%s', $companyName, $companySigle, $companyTaxId, $exerciseDate->format('Y'), $bilanReference);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($qrData);
        }

        return array_merge([
            'entries' => $entries,
            'companyName' => $companyName,
            'companySigle' => $companySigle,
            'companyAddress' => $companyAddress,
            'companyTaxId' => $companyTaxId,
            'companyLogo' => $companyLogo,
            'bilanReference' => $bilanReference,
            'qrUrl' => $qrUrl,
            'exerciseEnd' => $periodEnd ? $periodEnd->format('d/m/Y') : '',
            'exerciseYear' => $exerciseDate->format('Y'),
            'previousYear' => $exerciseDate->copy()->subYear()->format('Y'),
            'durationMonths' => $periodStart && $periodEnd ? Carbon::parse($periodStart)->diffInMonths(Carbon::parse($periodEnd)) + 1 : '',
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'reportType' => $reportType,
        ], $summary);
    }

    private function authorizeEntry(AccountingEntry $entry): void
    {
        if (! $this->workspaceOwnsDataUserId((int) $entry->user_id)) {
            abort(403);
        }
    }

    /**
     * Valide la sélection en lot et ne conserve que les écritures de l'utilisateur.
     */
    private function getOwnedEntriesFromRequest(Request $request)
    {
        $validated = $request->validate([
            'entry_ids' => ['required', 'array', 'min:1'],
            'entry_ids.*' => ['integer', 'distinct'],
        ]);

        return AccountingEntry::whereIn('user_id', $this->workspaceDataUserIds())
            ->whereIn('id', $validated['entry_ids'])
            ->get();
    }

    private function summarizeEntries($entries): array
    {
        $ledger = [];
        $totalDebit = 0;
        $totalCredit = 0;
        $income = 0;
        $expenses = 0;
        $incomeByYear = [];
        $expensesByYear = [];

        foreach ($entries as $entry) {
            $year = $entry->date->format('Y');
            $totalDebit += $entry->amount;
            $totalCredit += $entry->amount;

            foreach (['debit_account', 'credit_account'] as $side) {
                $account = $entry->{$side};
                if (! isset($ledger[$account])) {
                    $ledger[$account] = ['debit' => 0, 'credit' => 0];
                }
                if ($side === 'debit_account') {
                    $ledger[$account]['debit'] += $entry->amount;
                } else {
                    $ledger[$account]['credit'] += $entry->amount;
                }
            }

            $debitPrefix = $this->getAccountPrefix($entry->debit_account);
            $creditPrefix = $this->getAccountPrefix($entry->credit_account);

            if ($debitPrefix === '6') {
                $expensesByYear[$year] = ($expensesByYear[$year] ?? 0) + $entry->amount;
            } elseif ($debitPrefix === '7') {
                $incomeByYear[$year] = ($incomeByYear[$year] ?? 0) - $entry->amount;
            }

            if ($creditPrefix === '7') {
                $incomeByYear[$year] = ($incomeByYear[$year] ?? 0) + $entry->amount;
            } elseif ($creditPrefix === '6') {
                $expensesByYear[$year] = ($expensesByYear[$year] ?? 0) - $entry->amount;
            }
        }

        $assets = 0;
        $liabilities = 0;

        foreach ($ledger as $account => $amounts) {
            $prefix = $this->getAccountPrefix($account);
            $debitNet = $amounts['debit'] - $amounts['credit'];
            $creditNet = $amounts['credit'] - $amounts['debit'];

            // Bilan simplifié (OHADA) : aligné sur SmeFinancialRatioService — actif 2, 3, 5 (soldes débiteurs) + créances clients (4 débitrice).
            if (in_array($prefix, ['2', '3', '5'], true) && $debitNet > 0) {
                $assets += $debitNet;
            } elseif ($prefix === '4' && $debitNet > 0) {
                $assets += $debitNet;
            } elseif (in_array($prefix, ['1', '4'], true) && $creditNet > 0) {
                $liabilities += $creditNet;
            }

            // Resultat: charges (classe 6) et produits (classe 7) sur soldes nets.
            if ($prefix === '6' && $debitNet > 0) {
                $expenses += $debitNet;
            } elseif ($prefix === '7' && $creditNet > 0) {
                $income += $creditNet;
            }

        }

        return [
            'ledger' => $ledger,
            'ledgerCount' => count($ledger),
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'income' => $income,
            'expenses' => $expenses,
            'incomeByYear' => $incomeByYear,
            'expensesByYear' => $expensesByYear,
            'assets' => max($assets, 0),
            'liabilities' => max($liabilities, 0),
            // Avec des écritures en partie double symétriques, débit = crédit par ligne : l’écart global est toujours 0 (contrôle d’équilibre).
            'balance' => $totalDebit - $totalCredit,
            'entriesCount' => $entries->count(),
        ];
    }

    private function getPlanAccounts(): array
    {
        $userId = $this->workspaceUserId();
        $storedAccounts = PlanComptableAccount::where('user_id', $userId)
            ->orderBy('sort_order', 'asc')
            ->orderBy('numero_compte', 'asc')
            ->orderBy('prefix', 'asc')
            ->get();

        // Check if we have detailed accounts (with numero_compte set)
        $hasDetailedAccounts = $storedAccounts->filter(fn($a) => !empty($a->numero_compte))->isNotEmpty();

        if ($storedAccounts->isNotEmpty()) {
            if ($hasDetailedAccounts) {
                return $storedAccounts->mapWithKeys(function (PlanComptableAccount $account) {
                    $key = $account->numero_compte ?? $account->prefix;
                    return [
                        $key => [
                            'label' => $account->label,
                            'libelle_compte' => $account->libelle_compte ?? $account->label,
                            'category' => $account->category,
                            'subtype' => $account->subtype,
                            'type_compte' => $account->type_compte,
                            'sous_type' => $account->sous_type,
                            'classe' => $account->classe,
                            'observation' => $account->observation,
                            'is_actif' => $account->is_actif,
                            'prefix' => $account->prefix,
                            'numero_compte' => $account->numero_compte,
                            'sort_order' => $account->sort_order,
                        ],
                    ];
                })->toArray();
            }
            // Fallback to old behavior for backward compatibility
            return $storedAccounts->mapWithKeys(function (PlanComptableAccount $account) {
                return [
                    $account->prefix => [
                        'label' => $account->label,
                        'category' => $account->category,
                        'subtype' => $account->subtype,
                    ],
                ];
            })->toArray();
        }

        return config('plancomptable.accounts', []);
    }

    private function getAccountPrefix(string $account): ?string
    {
        if (preg_match('/^(\d)/', trim($account), $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getPlanAccount(string $account): array
    {
        $prefix = $this->getAccountPrefix($account);
        $accounts = $this->getPlanAccounts();

        return $accounts[$prefix] ?? ['label' => 'Compte inconnu', 'category' => 'other', 'subtype' => null];
    }

    private function getAccountCategory(string $account): string
    {
        return $this->getPlanAccount($account)['category'];
    }

    private function getAccountSubtype(string $account): ?string
    {
        return $this->getPlanAccount($account)['subtype'] ?? null;
    }

    public function documents()
    {
        $documents = AccountingDocument::with(['entries' => function ($query) {
            $query->orderByDesc('id');
        }])
            ->whereIn('user_id', $this->workspaceDataUserIds())
            ->orderByDesc('created_at')
            ->get();

        return view('accounting.documents', compact('documents'));
    }

    public function documentsComparison()
    {
        $documents = AccountingDocument::with(['entries' => function ($query) {
            $query->orderByDesc('id');
        }])
            ->whereIn('user_id', $this->workspaceDataUserIds())
            ->orderByDesc('created_at')
            ->get();

        return view('accounting.documents-comparison', compact('documents'));
    }

    public function uploadDocuments(Request $request)
    {
        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv', 'max:20480'],
        ], [
            'documents.required' => 'Veuillez sélectionner au moins un document.',
            'documents.array' => 'Le lot de documents envoyé est invalide.',
            'documents.min' => 'Veuillez sélectionner au moins un document.',
            'documents.*.file' => 'Chaque élément doit être un fichier valide.',
            'documents.*.mimes' => 'Format non supporté. Utilisez PDF, JPG, JPEG, PNG, XLSX, XLS ou CSV.',
            'documents.*.max' => 'Fichier trop volumineux. Taille maximale: 20 Mo par document.',
            'documents.*.uploaded' => 'Échec de l’upload. Vérifiez la taille du fichier et la configuration serveur (upload_max_filesize / post_max_size).',
        ]);

        $uploadedFiles = array_values(array_filter(
            (array) $request->file('documents', []),
            fn ($file) => $file instanceof UploadedFile
        ));

        if (empty($uploadedFiles)) {
            return redirect()
                ->back()
                ->withErrors([
                    'documents' => 'Aucun fichier valide reçu. Vérifiez la taille autorisée et réessayez.',
                ])
                ->withInput();
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if (! $uploadedFile->isValid()) {
                return redirect()
                    ->back()
                    ->withErrors([
                        'documents' => $this->describeUploadErrorCode($uploadedFile->getError()),
                    ])
                    ->withInput();
            }
        }

        $ocrService = new OcrService;
        $ocrPipeline = new OcrPipelineService;
        $createdCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;
        $autoValidatedCount = 0;
        $pendingReviewCount = 0;

        foreach ($uploadedFiles as $file) {
            $hash = sha1_file($file->getRealPath());
            $existing = AccountingDocument::whereIn('user_id', $this->workspaceDataUserIds())
                ->where('document_hash', $hash)
                ->first();

            if ($existing) {
                $duplicateCount++;

                continue;
            }

            $storedPath = $file->store('accounting-documents', 'public');
            $pipelineResult = $ocrPipeline->processStoredDocument($storedPath);
            $ocrResult = (array) ($pipelineResult['ocr_result'] ?? []);
            $documentType = $this->guessDocumentTypeFromFilename($file->getClientOriginalName());
            $status = 'ocr_failed';
            $confidence = 0;
            $extractedData = [
                'partner' => null,
                'invoice_number' => null,
                'invoice_date' => now()->toDateString(),
                'amount_ht' => 0,
                'amount_ttc' => 0,
                'tva' => 0,
                'currency' => 'FCFA',
                'debit_account' => $this->resolveAccountsForDocumentType($documentType)['debit'],
                'credit_account' => $this->resolveAccountsForDocumentType($documentType)['credit'],
                'ocr_error' => null,
            ];

            if ($ocrResult['success'] ?? false) {
                $confidence = (float) ($pipelineResult['global_confidence'] ?? $ocrResult['confidence'] ?? 0);

                $formDataForOcr = [
                    'document_reference' => '',
                    'date' => now()->toDateString(),
                    'amount' => 0,
                    'amount_ht' => 0,
                    'amount_tva' => 0,
                    'ttc_amount' => 0,
                    'tva_rate' => 0,
                    'partner_name' => '',
                ];

                $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
                $extracted = $verifyResult['extracted'] ?? [];
                $richExtracted = (array) ($pipelineResult['rich_data'] ?? []);
                if (empty($richExtracted)) {
                    $richExtracted = $ocrService->extractRichDocumentData($ocrResult['text']);
                }
                $documentType = $this->detectDocumentTypeFromOcrText($ocrResult['text'], $documentType);
                $accounts = $this->resolveAccountsForDocumentType($documentType);
                $normalizedExtracted = $this->buildValidationExtractedData($ocrResult['text'], $extracted, $richExtracted, $documentType);

                $extractedData = [
                    'partner' => $normalizedExtracted['partner'],
                    'invoice_number' => $normalizedExtracted['invoice_number'],
                    'invoice_date' => $normalizedExtracted['invoice_date'],
                    'amount_ht' => $normalizedExtracted['amount_ht'],
                    'amount_ttc' => $normalizedExtracted['amount_ttc'],
                    'tva' => $normalizedExtracted['tva'],
                    'currency' => $normalizedExtracted['currency'],
                    'debit_account' => $accounts['debit'],
                    'credit_account' => $accounts['credit'],
                    'ocr_text' => $ocrResult['text'],
                    'ocr_detected_fields' => $richExtracted,
                    'ocr_field_confidence' => (array) ($pipelineResult['field_confidence'] ?? []),
                    'ocr_low_confidence_fields' => (array) ($pipelineResult['low_confidence_fields'] ?? []),
                    'ocr_review_required' => (bool) ($pipelineResult['review_required'] ?? false),
                    'ocr_missing_required_fields' => (array) ($pipelineResult['missing_required_fields'] ?? []),
                    'ocr_error' => null,
                ];

                // Filtre de qualité (PRD 4.1, D1/D2) : blocage basé uniquement sur la
                // présence des champs obligatoires (numéro de pièce, dates, identification
                // du tiers) — plus sur le score de confiance OCR, qui reste affiché comme
                // signal de confiance sans être bloquant à lui seul.
                $canAutoValidate = ! (bool) ($pipelineResult['review_required'] ?? false)
                    && (float) ($normalizedExtracted['amount_ttc'] ?? 0) > 0;

                if ($canAutoValidate) {
                    $status = 'validated';
                    $extractedData['ocr_auto_validated'] = true;
                } else {
                    $status = 'pending_validation';
                    $extractedData['ocr_auto_validated'] = false;
                    $extractedData['ocr_auto_validation_reason'] = 'Validation manuelle requise (informations obligatoires manquantes ou montant incomplet).';
                    $pendingReviewCount++;
                }
            } else {
                $failedCount++;
                $extractedData['ocr_error'] = $this->formatOcrFailureDetails($ocrResult);
            }

            $document = AccountingDocument::create([
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'document_type' => $documentType,
                'status' => $status,
                'document_hash' => $hash,
                'extracted_data' => $extractedData,
                'confidence' => $confidence,
                'compliance_rate' => (float) ($pipelineResult['compliance_rate'] ?? 0),
            ]);

            if ($status === 'validated') {
                if ($this->createEntryFromAutoValidatedDocument($document)) {
                    $autoValidatedCount++;
                } else {
                    $documentData = (array) $document->extracted_data;
                    $documentData['ocr_auto_validated'] = false;
                    $documentData['ocr_auto_validation_reason'] = 'Validation manuelle requise (données OCR insuffisantes).';
                    $document->update([
                        'status' => 'pending_validation',
                        'extracted_data' => $documentData,
                        'actor_user_id' => Auth::id(),
                    ]);
                    $pendingReviewCount++;
                }
            }

            $createdCount++;
        }

        $messageParts = ["{$createdCount} document(s) importé(s)"];
        if ($duplicateCount > 0) {
            $messageParts[] = "{$duplicateCount} doublon(s) ignoré(s)";
        }
        if ($failedCount > 0) {
            $messageParts[] = "{$failedCount} en échec OCR (relance possible)";
        }
        if ($autoValidatedCount > 0) {
            $messageParts[] = "{$autoValidatedCount} validé(s) automatiquement";
        }
        if ($pendingReviewCount > 0) {
            $messageParts[] = "{$pendingReviewCount} en revue manuelle";
        }

        return redirect()->route('accounting.documents')->with('status', implode(' · ', $messageParts).'.');
    }

    /**
     * Retourne un message d’erreur lisible selon le code natif d’upload PHP.
     */
    private function describeUploadErrorCode(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la taille autorisée. Réduisez la taille ou augmentez 'upload_max_filesize' et 'post_max_size'.",
            UPLOAD_ERR_PARTIAL => 'Le fichier a été partiellement uploadé. Réessayez sur une connexion stable.',
            UPLOAD_ERR_NO_TMP_DIR => 'Le serveur ne dispose pas de dossier temporaire pour l’upload.',
            UPLOAD_ERR_CANT_WRITE => 'Le serveur ne peut pas écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a bloqué l’upload.',
            default => 'Échec de l’upload du fichier. Vérifiez la taille, le format et réessayez.',
        };
    }

    public function retryDocumentOcr(AccountingDocument $document)
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }

        $ocrService = new OcrService;
        $ocrPipeline = new OcrPipelineService;
        $pipelineResult = $ocrPipeline->processStoredDocument($document->stored_path);
        $ocrResult = (array) ($pipelineResult['ocr_result'] ?? []);

        if (! $ocrResult['success']) {
            $data = (array) $document->extracted_data;
            $data['ocr_error'] = $this->formatOcrFailureDetails($ocrResult);

            $document->update([
                'status' => 'ocr_failed',
                'confidence' => 0,
                'extracted_data' => $data,
                'actor_user_id' => Auth::id(),
            ]);

            return back()->with('status', "Relance OCR échouée pour {$document->original_name}.");
        }

        $formDataForOcr = [
            'document_reference' => '',
            'date' => now()->toDateString(),
            'amount' => 0,
            'amount_ht' => 0,
            'amount_tva' => 0,
            'ttc_amount' => 0,
            'tva_rate' => 0,
            'partner_name' => '',
        ];
        $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
        $extracted = $verifyResult['extracted'] ?? [];
        $richExtracted = (array) ($pipelineResult['rich_data'] ?? []);
        if (empty($richExtracted)) {
            $richExtracted = $ocrService->extractRichDocumentData($ocrResult['text']);
        }
        $documentType = $this->detectDocumentTypeFromOcrText($ocrResult['text'], $document->document_type);
        $accounts = $this->resolveAccountsForDocumentType($documentType);
        $data = (array) $document->extracted_data;
        $normalizedExtracted = $this->buildValidationExtractedData($ocrResult['text'], $extracted, $richExtracted, $documentType);

        $data['partner'] = $normalizedExtracted['partner'] ?? ($data['partner'] ?? null);
        $data['invoice_number'] = $normalizedExtracted['invoice_number'] ?? ($data['invoice_number'] ?? null);
        $data['invoice_date'] = $normalizedExtracted['invoice_date'] ?? ($data['invoice_date'] ?? now()->toDateString());
        $data['amount_ht'] = $normalizedExtracted['amount_ht'] ?? ($data['amount_ht'] ?? 0);
        $data['amount_ttc'] = $normalizedExtracted['amount_ttc'] ?? ($data['amount_ttc'] ?? 0);
        $data['tva'] = $normalizedExtracted['tva'] ?? ($data['tva'] ?? 0);
        $data['currency'] = $normalizedExtracted['currency'] ?? ($data['currency'] ?? 'FCFA');
        $data['debit_account'] = $accounts['debit'];
        $data['credit_account'] = $accounts['credit'];
        $data['ocr_text'] = $ocrResult['text'];
        $data['ocr_detected_fields'] = $richExtracted;
        $data['ocr_field_confidence'] = (array) ($pipelineResult['field_confidence'] ?? []);
        $data['ocr_low_confidence_fields'] = (array) ($pipelineResult['low_confidence_fields'] ?? []);
        $data['ocr_review_required'] = (bool) ($pipelineResult['review_required'] ?? false);
        $data['ocr_missing_required_fields'] = (array) ($pipelineResult['missing_required_fields'] ?? []);
        $data['ocr_error'] = null;

        $confidence = (float) ($pipelineResult['global_confidence'] ?? $ocrResult['confidence'] ?? 0);
        $complianceRate = (float) ($pipelineResult['compliance_rate'] ?? 0);
        $canAutoValidate = ! (bool) ($pipelineResult['review_required'] ?? false)
            && (float) ($normalizedExtracted['amount_ttc'] ?? 0) > 0;

        $data['ocr_auto_validated'] = $canAutoValidate;
        if (! $canAutoValidate) {
            $data['ocr_auto_validation_reason'] = 'Validation manuelle requise (informations obligatoires manquantes ou montant incomplet).';
        } else {
            $data['ocr_auto_validation_reason'] = null;
        }

        $document->update([
            'document_type' => $documentType,
            'status' => $canAutoValidate ? 'validated' : 'pending_validation',
            'confidence' => $confidence,
            'compliance_rate' => $complianceRate,
            'extracted_data' => $data,
            'actor_user_id' => Auth::id(),
        ]);

        if ($canAutoValidate && $this->createEntryFromAutoValidatedDocument($document)) {
            return back()->with('status', "OCR relancé avec succès et validé automatiquement pour {$document->original_name}.");
        }

        return back()->with('status', "OCR relancé avec succès pour {$document->original_name}. Validation manuelle requise.");
    }

    public function destroyDocument(AccountingDocument $document): RedirectResponse
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }

        TreasuryAudit::log($document->user_id, 'accounting.document.deleted', $document, [
            'actor_user_id' => Auth::id(),
            'original_name' => $document->original_name,
            'status' => $document->status,
            'linked_entries_count' => $document->entries()->count(),
        ]);

        $deletedEntries = 0;
        foreach ($document->entries as $entry) {
            if ($entry->attachment_path && $entry->attachment_path !== $document->stored_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $entry->delete();
            $deletedEntries++;
        }

        TreasuryTransaction::query()
            ->where('user_id', $this->workspaceUserId())
            ->where('payment_module', 'accounting_document')
            ->where('bank_reference', 'DOC-BANK-'.$document->id)
            ->delete();

        if ($document->stored_path) {
            Storage::disk('public')->delete($document->stored_path);
        }

        $documentName = $document->original_name;
        $document->delete();

        $status = "Document supprimé: {$documentName}.";
        if ($deletedEntries > 0) {
            $status .= " {$deletedEntries} écriture(s) liée(s) supprimée(s).";
        }

        return redirect()->route('accounting.documents')->with('status', $status);
    }

    private function createEntryFromAutoValidatedDocument(AccountingDocument $document): bool
    {
        $data = (array) $document->extracted_data;
        $type = (string) ($document->document_type ?: 'Justificatif');
        $amount = (float) ($data['amount_ttc'] ?? 0);

        if ($amount <= 0) {
            return false;
        }

        $accounts = $this->resolveAccountsForDocumentType($type);
        $debitAccount = (string) ($data['debit_account'] ?? $accounts['debit']);
        $creditAccount = (string) ($data['credit_account'] ?? $accounts['credit']);

        $entry = AccountingEntry::updateOrCreate(
            ['document_id' => $document->id],
            [
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'date' => (string) ($data['invoice_date'] ?? now()->toDateString()),
                'document_type' => $type,
                'document_reference' => $data['invoice_number'] ?? null,
                'description' => '[OCR] '.((string) ($data['partner'] ?? 'Document')).' - '.((string) ($data['invoice_number'] ?? 'Sans référence')),
                'debit_account' => $debitAccount,
                'credit_account' => $creditAccount,
                'amount' => $amount,
                'attachment_path' => $document->stored_path,
                'ocr_status' => 'verified',
                'ocr_detected_amount' => $amount,
                'ocr_verified_at' => now(),
                'ocr_text' => $data['ocr_text'] ?? null,
            ]
        );

        $data['linked_entry_id'] = $entry->id;
        $document->update([
            'status' => 'validated',
            'actor_user_id' => Auth::id(),
            'extracted_data' => $data,
        ]);

        return true;
    }

    private function guessDocumentTypeFromFilename(string $filename): string
    {
        $name = mb_strtolower($filename);

        if (str_contains($name, 'achat')) {
            return 'Achat';
        }
        if (str_contains($name, 'vente')) {
            return 'Vente';
        }
        if (str_contains($name, 'reçu') || str_contains($name, 'recu')) {
            return 'Reçu';
        }

        return 'Justificatif';
    }

    private function detectDocumentTypeFromOcrText(string $text, string $fallback): string
    {
        $lowerText = mb_strtolower($text);

        if (preg_match('/fournisseur|achat|purchase/i', $text)) {
            return 'Achat';
        }
        if (preg_match('/reçu|recu|receipt/i', $text)) {
            return 'Reçu';
        }
        if (str_contains($lowerText, 'facture') && preg_match('/client|vente|invoice/i', $text)) {
            return 'Vente';
        }

        return $fallback;
    }

    private function buildValidationExtractedData(
        string $ocrText,
        array $extracted,
        array $richExtracted = [],
        ?string $documentType = null
    ): array {
        $invoiceDate = $this->normalizeOcrDate((string) ($extracted['date'] ?? ($richExtracted['primary']['invoice_date'] ?? '')));
        if ($invoiceDate === null) {
            $invoiceDate = $this->normalizeOcrDate((string) ($this->extractByPatterns(
                $ocrText,
                [
                    '/\b(?:date|date facture|date d[\' ]?emission|date d[\' ]?édition)\s*[:\-]?\s*(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4})/iu',
                    '/\b(\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2})\b/u',
                    '/\b(\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4})\b/u',
                ]
            ) ?? ''));
        }

        $invoiceNumber = trim((string) ($extracted['invoice_number'] ?? ($richExtracted['primary']['invoice_number'] ?? '')));
        if ($invoiceNumber === '') {
            $invoiceNumber = (string) ($this->extractByPatterns(
                $ocrText,
                [
                    '/\b(?:facture|invoice)\s*(?:n[°ºo.]?|num(?:[ée]ro)?)?\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu',
                    '/\b(?:ref(?:erence)?|r[ée]f)\s*(?:n[°ºo.]?)?\s*[:#\-]?\s*([A-Z0-9][A-Z0-9\-\/_.]{2,})/iu',
                ]
            ) ?? '');
        }

        $preferredPartner = $this->resolvePreferredPartner($documentType, $richExtracted);
        $partner = trim((string) ($preferredPartner ?? $extracted['partner_name'] ?? ($richExtracted['primary']['partner_name'] ?? '')));
        if ($partner === '') {
            $partner = (string) ($this->extractByPatterns(
                $ocrText,
                [
                    '/\b(?:fournisseur|supplier|vendeur|vendor)\s*[:\-]\s*([^\n\r]{3,100})/iu',
                    '/\b(?:client|customer|fournisseur|supplier|destinataire|societe|soci[ée]t[ée]|entreprise|company)\s*[:\-]\s*([^\n\r]{3,100})/iu',
                ]
            ) ?? '');
        }

        $amountHt = $this->toFloatOrNull($extracted['amount_ht_fcfa'] ?? $extracted['amount_ht'] ?? ($richExtracted['primary']['amount_ht'] ?? null));
        $amountTva = $this->toFloatOrNull($extracted['amount_tva_fcfa'] ?? $extracted['amount_tva'] ?? ($richExtracted['primary']['amount_tva'] ?? null));
        $amountTtc = $this->toFloatOrNull($extracted['amount_ttc_fcfa'] ?? $extracted['amount_ttc'] ?? ($richExtracted['primary']['amount_ttc'] ?? null));

        if ($amountHt === null) {
            $amountHt = $this->extractAmountFromText($ocrText, ['montant ht', 'total ht', 'sous-total ht', 'sous total ht', 'sous-total', 'ht']);
        }
        if ($amountTva === null) {
            $amountTva = $this->extractAmountFromText($ocrText, ['tva', 'taxe', 'montant tva']);
        }
        if ($amountTtc === null) {
            $amountTtc = $this->extractAmountFromText($ocrText, ['montant ttc', 'total ttc', 'net a payer', 'net à payer', 'total']);
        }

        if ($amountHt === null && $amountTtc !== null && $amountTva !== null) {
            $amountHt = max(0.0, $amountTtc - $amountTva);
        }
        if ($amountTtc === null && $amountHt !== null && $amountTva !== null) {
            $amountTtc = $amountHt + $amountTva;
        }
        if ($amountTva === null && $amountHt !== null && $amountTtc !== null && $amountTtc >= $amountHt) {
            $amountTva = $amountTtc - $amountHt;
        }

        return [
            'partner' => $partner !== '' ? $partner : null,
            'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
            'invoice_date' => $invoiceDate ?? now()->toDateString(),
            'amount_ht' => $amountHt ?? 0.0,
            'amount_ttc' => $amountTtc ?? 0.0,
            'tva' => $amountTva ?? 0.0,
            'currency' => strtoupper((string) ($extracted['currency'] ?? ($richExtracted['primary']['currency'] ?? 'FCFA'))),
        ];
    }

    private function extractByPatterns(string $text, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                $value = trim((string) ($matches[1] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function resolvePreferredPartner(?string $documentType, array $richExtracted): ?string
    {
        $parties = (array) ($richExtracted['parties'] ?? []);

        return match ($documentType) {
            'Achat' => $parties['supplier'] ?? ($richExtracted['primary']['supplier_name'] ?? null),
            'Vente', 'Reçu' => $parties['client'] ?? ($richExtracted['primary']['client_name'] ?? null),
            default => $parties['supplier']
                ?? ($parties['client'] ?? ($richExtracted['primary']['partner_name'] ?? null)),
        };
    }

    private function extractAmountFromText(string $text, array $keywords): ?float
    {
        $escapedKeywords = array_map(
            fn (string $keyword) => preg_quote($keyword, '/'),
            $keywords
        );

        $labelRegex = implode('|', $escapedKeywords);
        $lines = preg_split('/\R/u', $text) ?: [];
        $amounts = [];

        foreach ($lines as $index => $line) {
            $candidate = trim((string) $line);
            if ($candidate === '' || ! preg_match('/(?:'.$labelRegex.')/iu', $candidate)) {
                continue;
            }

            $amounts = array_merge($amounts, $this->extractAmountsNearOcrLine($lines, (int) $index));
        }

        return ! empty($amounts) ? max($amounts) : null;
    }

    private function extractAmountsNearOcrLine(array $lines, int $index): array
    {
        $amounts = [];
        $maxLookahead = 2;
        $maxNonEmptyLines = 3;
        $visited = 0;

        for ($offset = 0; $offset <= $maxLookahead; $offset++) {
            $currentIndex = $index + $offset;
            if (! isset($lines[$currentIndex])) {
                break;
            }

            $candidate = trim((string) $lines[$currentIndex]);
            if ($candidate === '') {
                continue;
            }

            $visited++;
            if ($this->looksLikeOcrSectionHeader($candidate) && $offset > 0) {
                break;
            }

            foreach ($this->extractNumericAmountsFromOcrLine($candidate) as $parsed) {
                $amounts[] = $parsed;
            }

            if (! empty($amounts) || $visited >= $maxNonEmptyLines) {
                break;
            }
        }

        return $amounts;
    }

    private function looksLikeOcrSectionHeader(string $line): bool
    {
        if (preg_match('/^[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9\s\-:()%\/]{4,}$/u', $line)) {
            return true;
        }

        return (bool) preg_match('/\b(?:facture|description|quantit[eé]|prix|montant|total|sous-total|tva|taxe|client|fournisseur|date|r[ée]f)\b/iu', $line);
    }

    private function extractNumericAmountsFromOcrLine(string $line): array
    {
        $amounts = [];
        if (! preg_match_all('/\b(?:\d{1,3}(?:[ \x{00A0}.]\d{3})+(?:[.,]\d{2})?|\d+(?:[.,]\d{2})?)\b/u', $line, $matches)) {
            return $amounts;
        }

        foreach ((array) ($matches[0] ?? []) as $rawAmount) {
            $parsed = $this->parseNumericAmount((string) $rawAmount);
            if ($parsed !== null) {
                $amounts[] = $parsed;
            }
        }

        return $amounts;
    }

    private function parseNumericAmount(string $rawAmount): ?float
    {
        $value = preg_replace('/[^\d,.\s]/u', '', $rawAmount);
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace(' ', '', $value);
            if (strrpos($value, ',') > strrpos($value, '.')) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } else {
            $value = str_replace(' ', '', $value);
            if (substr_count($value, ',') === 1 && substr_count($value, '.') === 0) {
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return $this->parseNumericAmount((string) $value);
    }

    private function normalizeOcrDate(string $date): ?string
    {
        $candidate = trim($date);
        if ($candidate === '') {
            return null;
        }

        $candidate = str_replace('.', '/', $candidate);
        $candidate = str_replace('-', '/', $candidate);

        $formats = ['Y/m/d', 'd/m/Y', 'd/m/y', 'm/d/Y', 'm/d/y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $candidate)->format('Y-m-d');
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($candidate)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractFromTextFile(string $path): array
    {
        $fullPath = storage_path('app/public/'.ltrim($path, '/\\'));

        if (! file_exists($fullPath) || ! is_readable($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath);
        if (! is_string($content) || trim($content) === '') {
            return [];
        }

        return ['raw_text' => $content];
    }

    private function mockExtraction(string $filename, ?string $storedPath = null): array
    {
        // Méthode conservée pour compatibilité historique de tests.
        return [
            'document_type' => $this->guessDocumentTypeFromFilename($filename),
            'data' => $this->extractFromTextFile($storedPath ?? $filename),
            'confidence' => 0,
        ];

    }

    /**
     * Détecte un compte de trésorerie OHADA (classe 5 : banques, caisse, etc.).
     */
    private function accountStartsWithClassFive(?string $account): bool
    {
        $normalized = ltrim(trim((string) $account), '0');

        return $normalized !== '' && str_starts_with($normalized, '5');
    }

    /**
     * Rapprochement bancaire : compare trésorerie « effectuée » et mouvements sur comptes de trésorerie (classe 5 OHADA).
     */
    public function bankReconciliation(Request $request): View
    {
        $dateFrom = $request->query('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->query('date_to', now()->endOfMonth()->toDateString());
        $ids = $this->workspaceDataUserIds();

        $treasuryBase = TreasuryTransaction::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'effectue')
            ->whereDate('transaction_date', '>=', $dateFrom)
            ->whereDate('transaction_date', '<=', $dateTo);

        $treasuryEncaissements = (clone $treasuryBase)->where('type', 'encaissement')->sum('amount');
        $treasuryDecaissements = (clone $treasuryBase)->where('type', 'decaissement')->sum('amount');
        $treasuryNet = (float) $treasuryEncaissements - (float) $treasuryDecaissements;

        $entries = AccountingEntry::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->get();

        $class5Debit = 0.0;
        $class5Credit = 0.0;
        foreach ($entries as $entry) {
            if ($this->accountStartsWithClassFive($entry->debit_account)) {
                $class5Debit += (float) $entry->amount;
            }
            if ($this->accountStartsWithClassFive($entry->credit_account)) {
                $class5Credit += (float) $entry->amount;
            }
        }
        $class5NetMovement = $class5Debit - $class5Credit;

        $recentTreasury = (clone $treasuryBase)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $recentClass5Entries = AccountingEntry::query()
            ->whereIn('user_id', $ids)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->filter(function (AccountingEntry $entry) {
                return $this->accountStartsWithClassFive($entry->debit_account)
                    || $this->accountStartsWithClassFive($entry->credit_account);
            })
            ->take(20)
            ->values();

        return view('accounting.bank-reconciliation', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'treasuryEncaissements' => (float) $treasuryEncaissements,
            'treasuryDecaissements' => (float) $treasuryDecaissements,
            'treasuryNet' => $treasuryNet,
            'class5Debit' => $class5Debit,
            'class5Credit' => $class5Credit,
            'class5NetMovement' => $class5NetMovement,
            'deltaIndicative' => $treasuryNet - $class5NetMovement,
            'recentTreasury' => $recentTreasury,
            'recentClass5Entries' => $recentClass5Entries,
        ]);
    }

    /**
     * Clôture mensuelle : grille de contrôle et enregistrement d’une date de clôture métier.
     */
    public function monthlyClosing(Request $request): View
    {
        $defaultYm = now()->subMonth()->format('Y-m');
        $ym = (string) $request->query('month', $defaultYm);
        if (! preg_match('/^\d{4}-\d{2}$/', $ym)) {
            $ym = $defaultYm;
        }

        try {
            $start = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } catch (\Throwable) {
            $start = Carbon::parse($defaultYm.'-01')->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $ym = $start->format('Y-m');
        }

        $ids = $this->workspaceDataUserIds();
        $uid = $this->workspaceUserId();

        $entriesCount = AccountingEntry::query()
            ->whereIn('user_id', $ids)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $treasuryEffectueCount = TreasuryTransaction::query()
            ->whereIn('user_id', $ids)
            ->where('status', 'effectue')
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->count();

        $closure = AccountingMonthClosure::query()
            ->with('closedBy')
            ->where('user_id', $uid)
            ->where('year_month', $ym)
            ->first();

        $checkJournal = $entriesCount > 0;
        $checkTreasury = $treasuryEffectueCount > 0;
        $checkBalance = $checkJournal;

        return view('accounting.monthly-closing', [
            'yearMonth' => $ym,
            'periodLabel' => $start->locale('fr')->translatedFormat('F Y'),
            'entriesCount' => $entriesCount,
            'treasuryEffectueCount' => $treasuryEffectueCount,
            'checkJournal' => $checkJournal,
            'checkTreasury' => $checkTreasury,
            'checkBalance' => $checkBalance,
            'closure' => $closure,
            'reportQuery' => http_build_query([
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
            ]),
            'bankRecoFrom' => $start->toDateString(),
            'bankRecoTo' => $end->toDateString(),
        ]);
    }

    public function storeMonthClosure(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'year_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            Carbon::createFromFormat('Y-m', $data['year_month'])->startOfMonth();
        } catch (\Throwable) {
            return back()->withErrors(['year_month' => 'Mois invalide.'])->withInput();
        }

        AccountingMonthClosure::query()->updateOrCreate(
            [
                'user_id' => $this->workspaceUserId(),
                'year_month' => $data['year_month'],
            ],
            [
                'closed_at' => now(),
                'closed_by_user_id' => Auth::id(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('accounting.monthly-closing', ['month' => $data['year_month']])
            ->with('status', 'Clôture du mois '.$data['year_month'].' enregistrée (repère métier).');
    }
}
