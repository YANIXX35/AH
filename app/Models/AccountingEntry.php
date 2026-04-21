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
            'pending' => ['color' => 'secondary', 'icon' => 'clock', 'text' => 'Pas de fichier'],
        ];

        return $badges[$this->ocr_status] ?? $badges['pending'];
    }

    /**
     * Obtenir extrait du texte OCR
     */
    public function getOcrTextPreview($length = 200): string
    {
        if (!$this->ocr_text) return 'Aucun texte OCR';
        return substr($this->ocr_text, 0, $length) . (strlen($this->ocr_text) > $length ? '...' : '');
    }
}
