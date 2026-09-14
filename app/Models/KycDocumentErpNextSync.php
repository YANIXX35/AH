<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocumentErpNextSync extends Model
{
    protected $table = 'kyc_document_erpnext_syncs';

    protected $fillable = [
        'kyc_document_id',
        'status',
        'erpnext_file_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KycDocument::class, 'kyc_document_id');
    }
}
