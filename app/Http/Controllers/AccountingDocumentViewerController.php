<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesClientWorkspace;
use App\Models\AccountingDocument;
use App\Models\AccountingEntry;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AccountingDocumentViewerController extends Controller
{
    use UsesClientWorkspace;

    public function showEntryDocument(AccountingEntry $entry): View
    {
        $this->authorizeEntry($entry);

        return view('accounting.document-viewer', $this->buildEntryViewerData($entry));
    }

    public function streamEntryDocument(AccountingEntry $entry)
    {
        $this->authorizeEntry($entry);

        [$storedPath, $documentName] = $this->resolveEntryStoredDocument($entry);

        return $this->streamStoredDocument($storedPath, $documentName);
    }

    public function showDocument(AccountingDocument $document): View
    {
        $this->authorizeDocument($document);

        return view('accounting.document-viewer', $this->buildDocumentViewerData($document));
    }

    public function streamDocument(AccountingDocument $document)
    {
        $this->authorizeDocument($document);

        return $this->streamStoredDocument($document->stored_path, $document->original_name);
    }

    private function buildEntryViewerData(AccountingEntry $entry): array
    {
        [$storedPath, $documentName] = $this->resolveEntryStoredDocument($entry);

        return $this->buildViewerPayload(
            storedPath: $storedPath,
            documentName: $documentName,
            documentTypeLabel: 'Justificatif de l’écriture',
            previewUrl: route('accounting.entries.document.stream', $entry),
            backUrl: route('accounting.entries.show', $entry),
        );
    }

    private function buildDocumentViewerData(AccountingDocument $document): array
    {
        return $this->buildViewerPayload(
            storedPath: $document->stored_path,
            documentName: $document->original_name,
            documentTypeLabel: 'Document importé',
            previewUrl: route('accounting.documents.stream', $document),
            backUrl: route('accounting.documents.validate', $document),
        );
    }

    private function buildViewerPayload(
        string $storedPath,
        string $documentName,
        string $documentTypeLabel,
        string $previewUrl,
        string $backUrl,
    ): array {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($storedPath), 404);

        $extension = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $mimeType = (string) ($disk->mimeType($storedPath) ?? '');
        $previewType = $this->resolvePreviewType($mimeType, $extension);

        $textPreview = null;
        $pdfDataBase64 = null;
        if ($previewType === 'text') {
            $textPreview = $this->readTextPreview($storedPath);
        } elseif ($previewType === 'pdf') {
            $pdfDataBase64 = base64_encode((string) $disk->get($storedPath));
        }

        return [
            'documentName' => $documentName,
            'documentTypeLabel' => $documentTypeLabel,
            'previewType' => $previewType,
            'previewUrl' => $previewUrl,
            'backUrl' => $backUrl,
            'mimeType' => $mimeType !== '' ? $mimeType : 'Type inconnu',
            'fileExtension' => $extension !== '' ? strtoupper($extension) : 'N/A',
            'fileSizeLabel' => $this->formatFileSize((int) $disk->size($storedPath)),
            'textPreview' => $textPreview,
            'pdfDataBase64' => $pdfDataBase64,
        ];
    }

    private function resolveEntryStoredDocument(AccountingEntry $entry): array
    {
        if ($entry->document?->stored_path) {
            return [$entry->document->stored_path, $entry->document->original_name ?? basename($entry->document->stored_path)];
        }

        abort_if(empty($entry->attachment_path), 404);

        return [$entry->attachment_path, basename((string) $entry->attachment_path)];
    }

    private function streamStoredDocument(string $storedPath, string $documentName)
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($storedPath), 404);

        $absolutePath = $disk->path($storedPath);
        $safeName = str_replace('"', '', $documentName);

        return response()->file($absolutePath, [
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
        ]);
    }

    private function resolvePreviewType(string $mimeType, string $extension): string
    {
        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return 'pdf';
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (
            str_starts_with($mimeType, 'text/')
            || in_array($extension, ['txt', 'csv', 'json', 'xml', 'log'], true)
        ) {
            return 'text';
        }

        return 'unsupported';
    }

    private function readTextPreview(string $storedPath): string
    {
        $disk = Storage::disk('public');
        $content = (string) $disk->get($storedPath);

        if (mb_strlen($content) > 200000) {
            return mb_substr($content, 0, 200000)."\n\n[Prévisualisation tronquée]";
        }

        return $content;
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2, ',', ' ').' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ').' KB';
        }

        return $bytes.' octets';
    }

    private function authorizeEntry(AccountingEntry $entry): void
    {
        if (! $this->workspaceOwnsDataUserId((int) $entry->user_id)) {
            abort(403);
        }
    }

    private function authorizeDocument(AccountingDocument $document): void
    {
        if (! $this->workspaceOwnsDataUserId((int) $document->user_id)) {
            abort(403);
        }
    }
}
