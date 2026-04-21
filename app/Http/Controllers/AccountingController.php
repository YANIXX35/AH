<?php

namespace App\Http\Controllers;

use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use App\Models\AccountingMonthClosure;
use App\Models\PlanComptableAccount;
use App\Models\PlanComptableImport;
use App\Models\TreasuryTransaction;
use App\Services\OcrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Http\Controllers\Concerns\UsesClientWorkspace;

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

        $entriesQuery = AccountingEntry::whereIn('user_id', $this->workspaceDataUserIds())
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
            ->orderByDesc('date');

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
            ],
            $summary
        ));
    }

    public function storeEntry(Request $request)
    {
        $validated = $request->validate([
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

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('accounting-attachments', 'public');
            
            // Exécuter l'OCR sur le fichier uploadé
            $ocrService = new OcrService();
            $ocrResult = $ocrService->extractText($data['attachment_path']);
            
            if ($ocrResult['success']) {
                $ocrData['ocr_text'] = $ocrResult['text'];
                
                // Préparer les données de formulaire pour vérification complète
                $formDataForOcr = [
                    'document_reference' => $validated['document_reference'] ?? '',
                    'date' => $validated['date'],
                    'amount' => $validated['amount'],
                    'amount_ht' => $validated['amount'],
                    'amount_tva' => $request->input('amount_tva', 0),
                    'ttc_amount' => $request->input('ttc_amount', $validated['amount']),
                    'tva_rate' => $request->input('tva_rate', 0),
                    'partner_name' => $request->input('partner_name', ''),
                ];
                
                // Vérifier les informations complètes
                $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
                
                $ocrData['ocr_status'] = $verifyResult['overall_status'];
                $ocrData['ocr_verified_at'] = now();
                
                // Construire le message de statut de vérification
                $verificationDetails = [];
                foreach ($verifyResult['details'] as $field => $status) {
                    $verificationDetails[] = $status;
                }
                
                if ($verifyResult['overall_status'] === 'verified' && count($verificationDetails) > 0) {
                    $statusMessage .= ' et vérification OCR ✅ (' . count($verificationDetails) . '/' . $verifyResult['total_fields'] . ' champs)';
                } elseif ($verifyResult['overall_status'] === 'mismatched') {
                    $statusMessage .= ' (⚠️ Certains champs OCR ne correspondent pas)';
                }
                
            } else {
                $ocrData['ocr_status'] = 'failed';
                $ocrData['ocr_text'] = $this->formatOcrFailureDetails($ocrResult);
                $statusMessage .= ' (Erreur OCR: ' . $ocrResult['message'] . ')';
            }
        } else {
            $ocrData['ocr_status'] = 'pending';
        }

        AccountingEntry::create(array_merge($data, $ocrData, [
            'user_id' => $this->workspaceUserId(),
            'actor_user_id' => Auth::id(),
        ]));

        return redirect()->route('accounting')->with('status', $statusMessage . '.');
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
                $validated['ocr_status'] = 'pending';
                $validated['ocr_detected_amount'] = null;
                $validated['ocr_verified_at'] = null;
                $validated['ocr_text'] = null;
                break;
            }
        }

        // Si le justificatif est remplacé, on relance immédiatement l'OCR pour prendre en compte le nouveau fichier.
        if ($attachmentReplaced && !empty($validated['attachment_path'])) {
            $ocrService = new OcrService();
            $ocrResult = $ocrService->extractText($validated['attachment_path']);

            if ($ocrResult['success']) {
                $formDataForOcr = [
                    'document_reference' => $validated['document_reference'] ?? '',
                    'date' => $validated['date'],
                    'amount' => (float) $validated['amount'],
                    'amount_ht' => (float) $validated['amount'],
                    'amount_tva' => 0,
                    'ttc_amount' => (float) $validated['amount'],
                    'tva_rate' => 0,
                    'partner_name' => '',
                ];

                $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
                $validated['ocr_status'] = $verifyResult['overall_status'] ?? 'verified';
                $validated['ocr_verified_at'] = now();
                $validated['ocr_text'] = $ocrResult['text'];
            } else {
                $validated['ocr_status'] = 'failed';
                $validated['ocr_verified_at'] = null;
                $validated['ocr_text'] = $this->formatOcrFailureDetails($ocrResult);
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

        if ($ocrReset) {
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
            if ($entry->attachment_path) {
                Storage::disk('public')->delete($entry->attachment_path);
            }
            $entry->delete();
            $deleted++;
        }

        return redirect()
            ->route('accounting')
            ->with('status', $deleted . ' écriture(s) supprimée(s) avec succès.');
    }

    public function bulkRetryEntryOcr(Request $request)
    {
        $entries = $this->getOwnedEntriesFromRequest($request);

        if ($entries->isEmpty()) {
            return redirect()->route('accounting')->with('status', 'Aucune écriture sélectionnée.');
        }

        $ocrService = new OcrService();
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($entries as $entry) {
            if (!$entry->attachment_path) {
                $skipped++;
                continue;
            }

            $ocrResult = $ocrService->extractText($entry->attachment_path);
            if (!$ocrResult['success']) {
                $entry->update([
                    'ocr_status' => 'failed',
                    'ocr_detected_amount' => null,
                    'ocr_verified_at' => null,
                    'ocr_text' => $this->formatOcrFailureDetails($ocrResult),
                    'actor_user_id' => Auth::id(),
                ]);
                $failed++;
                continue;
            }

            $formDataForOcr = [
                'document_reference' => $entry->document_reference ?? '',
                'date' => $entry->date?->toDateString(),
                'amount' => (float) $entry->amount,
                'amount_ht' => (float) $entry->amount,
                'amount_tva' => 0,
                'ttc_amount' => (float) $entry->amount,
                'tva_rate' => 0,
                'partner_name' => '',
            ];

            $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
            $entry->update([
                'ocr_status' => $verifyResult['overall_status'] ?? 'verified',
                'ocr_verified_at' => now(),
                'ocr_text' => $ocrResult['text'],
                'actor_user_id' => Auth::id(),
            ]);
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

        return view('accounting.show-entry', compact('entry'));
    }

    public function retryEntryOcr(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        if (!$entry->attachment_path) {
            return redirect()
                ->back()
                ->with('status', 'Relance OCR impossible: aucun justificatif attaché.')
                ->with('ocr_retry_error', true);
        }

        $ocrService = new OcrService();
        $ocrResult = $ocrService->extractText($entry->attachment_path);

        if (!$ocrResult['success']) {
            $entry->update([
                'ocr_status' => 'failed',
                'ocr_detected_amount' => null,
                'ocr_verified_at' => null,
                'ocr_text' => $this->formatOcrFailureDetails($ocrResult),
                'actor_user_id' => Auth::id(),
            ]);

            return redirect()
                ->back()
                ->with('status', 'OCR relancé, mais a échoué. Consultez le détail pour agir.')
                ->with('ocr_retry_error', true);
        }

        $formDataForOcr = [
            'document_reference' => $entry->document_reference ?? '',
            'date' => $entry->date?->toDateString(),
            'amount' => (float) $entry->amount,
            'amount_ht' => (float) $entry->amount,
            'amount_tva' => 0,
            'ttc_amount' => (float) $entry->amount,
            'tva_rate' => 0,
            'partner_name' => '',
        ];

        $verifyResult = $ocrService->verifyCompleteDocument($ocrResult['text'], $formDataForOcr);
        $entry->update([
            'ocr_status' => $verifyResult['overall_status'] ?? 'verified',
            'ocr_verified_at' => now(),
            'ocr_text' => $ocrResult['text'],
            'actor_user_id' => Auth::id(),
        ]);

        return redirect()
            ->back()
            ->with('status', 'OCR relancé avec succès.');
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
            . 'Date: ' . now()->format('Y-m-d H:i:s') . "\n"
            . 'Utilisateur: ' . (Auth::user()?->email ?? 'N/A') . "\n"
            . 'Commentaire: ' . trim($validated['manual_comment']) . "\n\n";

        $entry->update([
            'ocr_status' => 'manual_verified',
            'ocr_verified_at' => now(),
            'ocr_text' => $manualLog . ($entry->ocr_text ?? ''),
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
        $fullPath = storage_path('app/public/' . $storedPath);
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'pdf') {
            $ocrService = new OcrService();
            $ocrResult = $ocrService->extractText($storedPath);
            if (! $ocrResult['success']) {
                Storage::disk('public')->delete($storedPath);

                return redirect()->back()->withErrors([
                    'plan_comptable' => 'PDF illisible ou OCR en échec : ' . ($ocrResult['message'] ?? 'erreur inconnue'),
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
        if (!empty($invalidRows)) {
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
        foreach ($request->input('plan') as $prefix => $accountData) {
            $normalizedPrefix = (string) $prefix;
            if (!preg_match('/^[1-7]$/', $normalizedPrefix)) {
                continue;
            }

            $label = trim((string) ($accountData['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $plan[$prefix] = [
                'label' => $label,
                // En mode expert comptable, la classe pilote toujours la catégorie.
                'category' => $this->getCategoryByPrefix($normalizedPrefix),
                'subtype' => $this->getSubtypeByPrefix($normalizedPrefix),
            ];
        }

        $validation = $this->validatePlan($plan);
        if (!empty($validation['missingClasses'])) {
            return redirect()
                ->route('accounting.plan')
                ->withErrors([
                    'plan' => 'Impossible d’enregistrer : classes manquantes (' . implode(', ', $validation['missingClasses']) . ').',
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

        if (!file_exists($filePath)) {
            abort(404, 'Modèle de plan comptable non trouvé.');
        }

        return response()->download($filePath, 'plan-comptable-modele.csv', [
            'Content-Type' => 'text/csv',
        ]);
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

        foreach ($rows as $rowIndex => $row) {
            if (empty(array_filter($row))) {
                continue;
            }

            if (!$headers) {
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
                }

                if (!isset($headers['code']) || !isset($headers['label'])) {
                    continue;
                }

                continue;
            }

            $code = trim((string) ($row[$headers['code']] ?? ''));
            $label = trim((string) ($row[$headers['label']] ?? ''));
            $reason = null;

            if (!$code || !$label) {
                $reason = 'Compte ou libellé manquant';
            } else {
                $prefix = preg_match('/^([1-7])/', $code, $matches) ? $matches[1] : null;
                if (!$prefix) {
                    $reason = 'Code invalide ou sans classe 1-7';
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

            $accounts[$prefix] = [
                'label' => $label,
                'category' => $this->getCategoryByPrefix($prefix),
                'subtype' => $this->getSubtypeByPrefix($prefix),
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

        foreach ($accounts as $prefix => $account) {
            PlanComptableAccount::create([
                'user_id' => $userId,
                'prefix' => $prefix,
                'label' => $account['label'],
                'category' => $account['category'] ?? 'other',
                'subtype' => $account['subtype'] ?? null,
            ]);
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
        $missing = array_values(array_diff($expected, array_keys($plan)));

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
            '6' => 'charge',
            '7' => 'produit',
            default => null,
        };
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
            $reason = 'Délai dépassé (timeout API)';
        } elseif (str_contains($lower, 'aucun texte')) {
            $reason = 'Texte non détecté';
        } elseif (str_contains($lower, 'api')) {
            $reason = 'Réponse API OCR invalide';
        } elseif (str_contains($lower, 'fichier non trouvé')) {
            $reason = 'Fichier justificatif introuvable';
        }

        $action = "Relancer l'OCR ou utiliser la validation manuelle guidée.";
        if ($httpStatus === 404) {
            $action = "Vérifier l'URL de l'endpoint OCR et la connectivité sortante serveur.";
        } elseif ($httpStatus === 401 || $httpStatus === 403) {
            $action = "Vérifier la clé API OCR (droits, quota, activation).";
        } elseif ($errorCode === 'UNSUPPORTED_MIME') {
            $action = "Utiliser uniquement un fichier JPG, JPEG, PNG ou PDF lisible.";
        }

        return "=== ERREUR OCR ===\n"
            . 'Type: ' . $reason . "\n"
            . 'Code: ' . $errorCode . "\n"
            . 'Message: ' . $message . "\n"
            . 'Emplacement: ' . $location . "\n"
            . 'Endpoint: ' . $endpoint . "\n"
            . ($httpStatus ? ('HTTP Status: ' . $httpStatus . "\n") : '')
            . 'Date: ' . now()->format('Y-m-d H:i:s') . "\n"
            . 'Action recommandée: ' . $action . "\n";
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
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($qrData);
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
            ->download('bilan-' . ($payload['bilanReference'] ?? 'rapport') . '.pdf');
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
            $logoPath = storage_path('app/public/' . $user->company_logo);
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $logoMime = finfo_file($finfo, $logoPath);
                finfo_close($finfo);
                $companyLogo = 'data:' . $logoMime . ';base64,' . base64_encode($logoData);
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
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . urlencode($qrData);
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
                if (!isset($ledger[$account])) {
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
            ->orderBy('prefix')
            ->get();

        if ($storedAccounts->isNotEmpty()) {
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
        $documents = AccountingDocument::whereIn('user_id', $this->workspaceDataUserIds())
            ->orderByDesc('created_at')
            ->get();

        return view('accounting.documents', compact('documents'));
    }

    public function uploadDocuments(Request $request)
    {
        $request->validate([
            'documents' => ['required', 'array', 'min:1'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,xlsx,xls,csv', 'max:20480'],
        ]);

        $ocrService = new OcrService();
        $createdCount = 0;
        $duplicateCount = 0;
        $failedCount = 0;

        foreach ($request->file('documents') as $file) {
            $hash = sha1_file($file->getRealPath());
            $existing = AccountingDocument::whereIn('user_id', $this->workspaceDataUserIds())
                ->where('document_hash', $hash)
                ->first();

            if ($existing) {
                $duplicateCount++;
                continue;
            }

            $storedPath = $file->store('accounting-documents', 'public');
            $ocrResult = $ocrService->extractText($storedPath);
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

            if ($ocrResult['success']) {
                $status = 'pending_validation';
                $confidence = (float) ($ocrResult['confidence'] ?? 0);

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
                $documentType = $this->detectDocumentTypeFromOcrText($ocrResult['text'], $documentType);
                $accounts = $this->resolveAccountsForDocumentType($documentType);

                $extractedData = [
                    'partner' => $extracted['partner_name'] ?? null,
                    'invoice_number' => $extracted['invoice_number'] ?? null,
                    'invoice_date' => $extracted['date'] ?? now()->toDateString(),
                    'amount_ht' => $extracted['amount_ht_fcfa'] ?? ($extracted['amount_ht'] ?? 0),
                    'amount_ttc' => $extracted['amount_ttc_fcfa'] ?? ($extracted['amount_ttc'] ?? 0),
                    'tva' => $extracted['amount_tva_fcfa'] ?? ($extracted['amount_tva'] ?? 0),
                    'currency' => $extracted['currency'] ?? 'FCFA',
                    'debit_account' => $accounts['debit'],
                    'credit_account' => $accounts['credit'],
                    'ocr_text' => $ocrResult['text'],
                    'ocr_error' => null,
                ];
            } else {
                $failedCount++;
                $extractedData['ocr_error'] = $this->formatOcrFailureDetails($ocrResult);
            }

            AccountingDocument::create([
                'user_id' => $this->workspaceUserId(),
                'actor_user_id' => Auth::id(),
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'document_type' => $documentType,
                'status' => $status,
                'document_hash' => $hash,
                'extracted_data' => $extractedData,
                'confidence' => $confidence,
            ]);

            $createdCount++;
        }

        $messageParts = ["{$createdCount} document(s) importé(s)"];
        if ($duplicateCount > 0) {
            $messageParts[] = "{$duplicateCount} doublon(s) ignoré(s)";
        }
        if ($failedCount > 0) {
            $messageParts[] = "{$failedCount} en échec OCR (relance possible)";
        }

        return redirect()->route('accounting.documents')->with('status', implode(' · ', $messageParts) . '.');
    }

    public function retryDocumentOcr(AccountingDocument $document)
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }

        $ocrService = new OcrService();
        $ocrResult = $ocrService->extractText($document->stored_path);

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
        $documentType = $this->detectDocumentTypeFromOcrText($ocrResult['text'], $document->document_type);
        $accounts = $this->resolveAccountsForDocumentType($documentType);
        $data = (array) $document->extracted_data;

        $data['partner'] = $extracted['partner_name'] ?? ($data['partner'] ?? null);
        $data['invoice_number'] = $extracted['invoice_number'] ?? ($data['invoice_number'] ?? null);
        $data['invoice_date'] = $extracted['date'] ?? ($data['invoice_date'] ?? now()->toDateString());
        $data['amount_ht'] = $extracted['amount_ht_fcfa'] ?? ($extracted['amount_ht'] ?? ($data['amount_ht'] ?? 0));
        $data['amount_ttc'] = $extracted['amount_ttc_fcfa'] ?? ($extracted['amount_ttc'] ?? ($data['amount_ttc'] ?? 0));
        $data['tva'] = $extracted['amount_tva_fcfa'] ?? ($extracted['amount_tva'] ?? ($data['tva'] ?? 0));
        $data['currency'] = $extracted['currency'] ?? ($data['currency'] ?? 'FCFA');
        $data['debit_account'] = $accounts['debit'];
        $data['credit_account'] = $accounts['credit'];
        $data['ocr_text'] = $ocrResult['text'];
        $data['ocr_error'] = null;

        $document->update([
            'document_type' => $documentType,
            'status' => 'pending_validation',
            'confidence' => (float) ($ocrResult['confidence'] ?? 0),
            'extracted_data' => $data,
            'actor_user_id' => Auth::id(),
        ]);

        return back()->with('status', "OCR relancé avec succès pour {$document->original_name}.");
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

        if (str_contains($lowerText, 'facture') && preg_match('/client|vente|invoice/i', $text)) {
            return 'Vente';
        }
        if (preg_match('/fournisseur|achat|purchase/i', $text)) {
            return 'Achat';
        }
        if (preg_match('/reçu|recu|receipt/i', $text)) {
            return 'Reçu';
        }

        return $fallback;
    }

    private function extractFromTextFile(string $path): array
    {
        $fullPath = storage_path('app/public/' . ltrim($path, '/\\'));

        if (!file_exists($fullPath) || !is_readable($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath);
        if (!is_string($content) || trim($content) === '') {
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
