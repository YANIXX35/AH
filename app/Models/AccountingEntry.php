<?php

namespace App\Models;

use App\Support\OcrStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'quality_status',
        'quality_reviewed_at',
        'quality_issues',
        'payment_status',
        'amount_paid',
    ];

    public $timestamps = true;

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'quality_reviewed_at' => 'datetime',
        'quality_issues' => 'array',
    ];

    public function setOcrStatusAttribute(?string $value): void
    {
        $this->attributes['ocr_status'] = OcrStatus::normalize($value);
    }

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

    public function payments(): HasMany
    {
        return $this->hasMany(AccountingEntryPayment::class);
    }

    /**
     * Statut de règlement à donner à une écriture au moment de sa création.
     * Une écriture dont le débit OU le crédit touche déjà un compte de
     * trésorerie (classe 5 : 512 Banque, 571 Caisse...) a vu son règlement
     * se produire au moment même de la saisie — typiquement un
     * "Justificatif" (627/512) ou un "Reçu" (512/411) — donc aucun paiement
     * futur n'est attendu et elle démarre directement payée. Toute autre
     * écriture (typiquement Achat 607/401 ou Vente 411/701, avec un compte
     * tiers mais pas encore de mouvement de trésorerie) démarre impayée.
     *
     * @return array{payment_status: string, amount_paid: float}
     */
    public static function defaultPaymentState(?string $debitAccount, ?string $creditAccount, float $amount): array
    {
        if (self::isClassFiveAccount($debitAccount) || self::isClassFiveAccount($creditAccount)) {
            return ['payment_status' => 'paid', 'amount_paid' => $amount];
        }

        return ['payment_status' => 'unpaid', 'amount_paid' => 0.0];
    }

    public static function isClassFiveAccount(?string $account): bool
    {
        $normalized = ltrim(trim((string) $account), '0');

        return $normalized !== '' && str_starts_with($normalized, '5');
    }

    /**
     * Sens du mouvement de trésorerie qu'un paiement sur cette écriture doit
     * générer. Se base sur la numérotation OHADA : un débit 411 (Clients)
     * signifie qu'un tiers nous doit de l'argent — l'encaisser augmente la
     * trésorerie. Un crédit 401 (Fournisseurs) signifie que nous devons de
     * l'argent à un tiers — le payer diminue la trésorerie. Toute autre
     * combinaison (pas de compte 401/411 reconnu) ne génère pas de
     * mouvement automatique : le paiement reste enregistré, seul le lien
     * Trésorerie est absent.
     */
    public function inferPaymentMovementType(): ?string
    {
        $debit = ltrim(trim((string) $this->debit_account), '0');
        $credit = ltrim(trim((string) $this->credit_account), '0');

        if (str_starts_with($debit, '411')) {
            return 'encaissement';
        }

        if (str_starts_with($credit, '401')) {
            return 'decaissement';
        }

        return null;
    }

    /**
     * Recalcule payment_status/amount_paid à partir de la somme des
     * paiements liés, et sauvegarde. À appeler après chaque création (ou
     * suppression) de paiement.
     */
    public function recalculatePaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->amount;

        $status = 'unpaid';
        if ($paid > 0 && $paid < $total) {
            $status = 'partial';
        } elseif ($paid >= $total && $total > 0) {
            $status = 'paid';
        }

        $this->forceFill(['amount_paid' => $paid, 'payment_status' => $status])->save();
    }

    public function paymentStatusBadge(): array
    {
        return match ($this->payment_status) {
            'paid' => ['class' => 'bg-success-subtle text-success-emphasis', 'label' => 'Payé'],
            'partial' => ['class' => 'bg-warning-subtle text-warning-emphasis', 'label' => 'Partiel'],
            default => ['class' => 'bg-danger-subtle text-danger-emphasis', 'label' => 'Impayé'],
        };
    }

    /**
     * Obtenir le badge d'état OCR
     */
    public function getOcrBadge(): array
    {
        $status = OcrStatus::normalize((string) $this->ocr_status);
        $badges = [
            'verified' => ['color' => 'success', 'icon' => 'check-circle', 'text' => 'Vérifié ✓'],
            'manual_verified' => ['color' => 'success', 'icon' => 'check', 'text' => 'Vérifié manuellement'],
            'mismatch' => ['color' => 'warning', 'icon' => 'alert-circle', 'text' => 'Incohérences détectées'],
            'failed' => ['color' => 'danger', 'icon' => 'x-circle', 'text' => '❌ Erreur OCR'],
            'pending' => ['color' => 'secondary', 'icon' => 'clock', 'text' => 'OCR en attente'],
        ];

        return $badges[$status] ?? $badges['pending'];
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
        if (! $this->document?->stored_path && ! $this->attachment_path) {
            return null;
        }

        return route('accounting.entries.document.stream', $this);
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
            OcrStatus::normalize((string) $this->ocr_status) === OcrStatus::MISMATCH
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

        return substr($rawText, 0, $length).(strlen($rawText) > $length ? '...' : '');
    }
}
