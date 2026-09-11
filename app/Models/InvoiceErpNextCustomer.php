<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceErpNextCustomer extends Model
{
    protected $table = 'invoice_erpnext_customers';

    protected $fillable = [
        'user_id',
        'dedup_key',
        'erpnext_customer_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
