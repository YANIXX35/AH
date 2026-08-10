<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionReceiptNumberCounter extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'last_number',
    ];
}
