<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'amount_requested',
        'currency',
        'horizon',
        'purpose',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'legal_representative',
        'fiscal_closing_at',
        'revenue_n1',
        'equity_n1',
        'attachments_commitment',
        'certifies_accuracy',
        'photo_path',
        'identity_document_front_path',
        'identity_document_back_path',
        'identity_document_type',
        'identity_document_number',
        'identity_document_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_requested' => 'decimal:2',
            'fiscal_closing_at' => 'date',
            'revenue_n1' => 'decimal:2',
            'equity_n1' => 'decimal:2',
            'certifies_accuracy' => 'boolean',
            'reviewed_at' => 'datetime',
            'identity_document_expires_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPdfPath(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return str_ends_with(strtolower($path), '.pdf');
    }
}
