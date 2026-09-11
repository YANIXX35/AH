<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ErpNextTestInvoice extends Model
{
    use HasFactory;

    protected $table = 'erpnext_test_invoices';

    protected $fillable = [
        'user_id',
        'created_by_user_id',
        'erpnext_invoice_name',
        'status',
        'error_message',
        'total_before_tax',
        'total_taxes',
        'grand_total',
        'outstanding_amount',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'total_before_tax' => 'decimal:2',
        'total_taxes' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ErpNextTestInvoiceItem::class);
    }
}
