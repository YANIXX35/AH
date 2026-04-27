<?php

namespace App\Support;

final class OcrStatus
{
    public const VERIFIED = 'verified';

    public const MANUAL_VERIFIED = 'manual_verified';

    public const MISMATCH = 'mismatch';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    /**
     * Ancien statut conservé pour compatibilité des données existantes.
     */
    public const LEGACY_MISMATCHED = 'mismatched';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VERIFIED,
            self::MANUAL_VERIFIED,
            self::MISMATCH,
            self::FAILED,
            self::PENDING,
        ];
    }

    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);
        if ($status === self::LEGACY_MISMATCHED) {
            return self::MISMATCH;
        }

        return in_array($status, self::all(), true)
            ? $status
            : self::PENDING;
    }
}
