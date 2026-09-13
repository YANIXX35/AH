<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollErpNextSync extends Model
{
    protected $table = 'payroll_erpnext_syncs';

    protected $fillable = [
        'payroll_run_id',
        'status',
        'erpnext_accrual_entry_name',
        'erpnext_payment_entry_name',
        'last_error',
        'last_synced_at',
        'raw_response',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
