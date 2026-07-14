<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceNumberCounter extends Model
{
    protected $fillable = [
        'user_id',
        'year',
        'last_number',
    ];
}
