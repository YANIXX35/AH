<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminPasswordResetLink extends Model
{
    protected $fillable = [
        'user_id',
        'created_by_user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'used_from_ip',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Crée un lien pour l'utilisateur donné et retourne le token EN CLAIR
     * (seul son hash est stocké — comme un mot de passe, il n'est jamais
     * récupérable après coup).
     */
    public static function generateFor(User $user, ?int $createdByUserId, int $validForMinutes = 60): string
    {
        $token = Str::random(48);

        static::where('user_id', $user->id)->whereNull('used_at')->delete();

        static::create([
            'user_id' => $user->id,
            'created_by_user_id' => $createdByUserId,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($validForMinutes),
        ]);

        return $token;
    }

    public static function findValidByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
