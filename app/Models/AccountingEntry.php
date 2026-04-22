<?php

namespace App\Models;

use App\Models\AccountingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingEntry extends Model
{
    protected $fillable = [
        'user_id',
        'actor_user_id',
        'document_id',
        'date',
        'document_type',
        'document_reference',
        'description',
        'debit_account',
        'credit_account',
        'amount',
        'attachment_path',
        'ocr_status',
        'ocr_detected_amount',
        'ocr_verified_at',
        'ocr_text',
    ];

    public $timestamps = true;

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Utilisateur réellement connecté ayant saisi l’écriture (équipe licence).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(AccountingDocument::class, 'document_id');
    }

    /**
     * Obtenir le badge d'état OCR
     */
    public function getOcrBadge(): array
    {
        $badges = [
            'verified' => ['color' => 'success', 'icon' => 'check-circle', 'text' => 'Vérifié ✓'],
            'manual_verified' => ['color' => 'success', 'icon' => 'check', 'text' => 'Vérifié manuellement'],
            'mismatch' => ['color' => 'warning', 'icon' => 'alert-circle', 'text' => 'Incohérences détectées'],
            'mismatched' => ['color' => 'warning', 'icon' => 'alert-circle', 'text' => '⚠️ Montant ne correspond pas'],
            'failed' => ['color' => 'danger', 'icon' => 'x-circle', 'text' => '❌ Erreur OCR'],
            'pending' => ['color' => 'secondary', 'icon' => 'clock', 'text' => 'OCR en attente'],
        ];

        return $badges[$this->ocr_status] ?? $badges['pending'];
    }

    public function getSourceDocumentName(): ?string
    {
        if ($this->document?->original_name) {
            return $this->document->original_name;
        }

        if ($this->attachment_path) {
            return basename((string) $this->attachment_path);
        }

        return null;
    }

    public function getSourceDocumentUrl(): ?string
    {
        if ($this->document?->stored_path) {
            return asset('storage/' . $this->document->stored_path);
        }

        if ($this->attachment_path) {
            return asset('storage/' . $this->attachment_path);
        }

        return null;
    }

    public function getOcrSummaryLines(): array
    {
        $text = trim((string) $this->ocr_text);
        if ($text === '' || ! str_contains($text, '=== RÉSUMÉ OCR ===')) {
            return [];
        }

        $summarySection = preg_split('/=== TEXTE OCR ===/u', $text)[0] ?? '';
        $lines = preg_split('/\R/u', $summarySection) ?: [];

        return array_values(array_filter(array_map(
            static function (string $line): ?string {
                $candidate = trim($line);
                if ($candidate === '' || $candidate === '=== RÉSUMÉ OCR ===') {
                    return null;
                }

                return $candidate;
            },
            $lines
        )));
    }

    public function getOcrTableDetail(): ?string
    {
        $lines = $this->getOcrSummaryLines();
        if (! empty($lines)) {
            return implode(', ', $lines);
        }

        if (
            in_array($this->ocr_status, ['mismatched', 'mismatch'], true)
            && $this->ocr_detected_amount !== null
            && is_numeric($this->amount)
        ) {
            return sprintf(
                'Montant saisi %s FCFA, OCR %s FCFA',
                number_format((float) $this->amount, 2, ',', ' '),
                number_format((float) $this->ocr_detected_amount, 2, ',', ' ')
            );
        }

        return null;
    }

    public function getOcrRawText(): string
    {
        $text = (string) $this->ocr_text;
        if (! str_contains($text, '=== TEXTE OCR ===')) {
            return $text;
        }

        $parts = preg_split('/=== TEXTE OCR ===/u', $text, 2);

        return trim((string) ($parts[1] ?? ''));
    }

    /**
     * Obtenir extrait du texte OCR
     */
    public function getOcrTextPreview($length = 200): string
    {
        $rawText = trim($this->getOcrRawText());
        if ($rawText === '') {
            return 'Aucun texte OCR';
        }

        return substr($rawText, 0, $length) . (strlen($rawText) > $length ? '...' : '');
    }
}
