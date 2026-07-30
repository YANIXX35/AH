<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prospect extends Model
{
    use HasFactory;

    protected $fillable = [
        'commercial_user_id',
        'name',
        'company_name',
        'job_title',
        'email',
        'phone',
        'need_type',
        'status',
        'notes',
    ];

    public function commercial(): BelongsTo
    {
        return $table = $this->belongsTo(User::class, 'commercial_user_id');
    }

    public function getNeedLabelAttribute(): string
    {
        return match ($this->need_type) {
            'diagnostic' => 'Diagnostic Financier (FIRD)',
            'syscohada' => 'Comptabilité SYSCOHADA',
            'tresorerie' => 'Gestion Trésorerie & Mobile Money',
            'levee_fonds' => 'Préparation Levée de Fonds',
            'ma' => 'Restructuration & M&A',
            default => 'Autre Besoin Financier',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        if (empty($this->status)) {
            return 'Nouveau Lead';
        }

        return match ($this->status) {
            'nouveau' => 'Nouveau Lead',
            'contacte' => 'Contacté',
            'qualifie' => 'Qualifié',
            'client' => 'Converti en Client',
            'sans_suite' => 'Sans suite',
            default => ucfirst((string) $this->status),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        if (empty($this->status)) {
            return 'bg-info text-white';
        }

        return match ($this->status) {
            'nouveau' => 'bg-info text-white',
            'contacte' => 'bg-warning text-dark',
            'qualifie' => 'bg-primary text-white',
            'client' => 'bg-success text-white',
            'sans_suite' => 'bg-secondary text-white',
            default => 'bg-light text-dark',
        };
    }
}
