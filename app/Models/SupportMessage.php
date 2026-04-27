<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'user_id',
        'body',
        'is_staff_reply',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'is_staff_reply' => 'boolean',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveryState(): string
    {
        if ($this->read_at !== null) {
            return 'read';
        }
        if ($this->delivered_at !== null) {
            return 'delivered';
        }

        return 'sent';
    }
}
