<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommercialProspection extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_NEEDS_REVISION = 'needs_revision';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Brouillon',
        self::STATUS_SUBMITTED => 'Envoyée',
        self::STATUS_UNDER_REVIEW => 'En cours de vérification',
        self::STATUS_APPROVED => 'Validée',
        self::STATUS_NEEDS_REVISION => 'À corriger',
        self::STATUS_REJECTED => 'Rejetée',
    ];

    protected $fillable = [
        'commercial_id',
        'title',
        'content',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'status',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'admin_comment',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'file_size' => 'integer',
        ];
    }

    public function commercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path);
    }

    public function hasContent(): bool
    {
        return trim((string) $this->content) !== '';
    }

    /**
     * Un rapport ne peut pas être totalement vide : il lui faut du texte OU un fichier.
     */
    public function isEmpty(): bool
    {
        return ! $this->hasContent() && ! $this->hasFile();
    }

    public function typeLabel(): string
    {
        if ($this->hasContent() && $this->hasFile()) {
            return 'Texte + fichier';
        }
        if ($this->hasFile()) {
            return 'Fichier';
        }

        return 'Texte';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Un rapport déjà transmis (ou déjà traité) ne se modifie plus librement,
     * sauf s'il a été retourné pour correction.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_NEEDS_REVISION], true);
    }

    public function getFormattedFileSizeAttribute(): ?string
    {
        if ($this->file_size === null) {
            return null;
        }
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' octets';
    }
}
