<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_name',
        'employee_matricule',
        'employee_job',
        'base_salary',
        'bonuses',
        'cnps_employee',
        'cnps_employer',
        'its_tax',
        'net_payable',
        'payment_details',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'bonuses' => 'decimal:2',
        'cnps_employee' => 'decimal:2',
        'cnps_employer' => 'decimal:2',
        'its_tax' => 'decimal:2',
        'net_payable' => 'decimal:2',
    ];

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
