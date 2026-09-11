<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceErpNextSync extends Model
{
    protected $table = 'invoice_erpnext_syncs';

    protected $fillable = [
        'invoice_id',
        'status',
        'erpnext_invoice_name',
        'erpnext_customer_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
