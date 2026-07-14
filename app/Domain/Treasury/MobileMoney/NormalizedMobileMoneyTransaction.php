<?php

namespace App\Domain\Treasury\MobileMoney;

/**
 * Ligne de transaction Mobile Money normalisée, indépendante du format source
 * (export CSV aujourd'hui, réponse d'API opérateur demain).
 */
final class NormalizedMobileMoneyTransaction
{
    public function __construct(
        public readonly \DateTimeImmutable $occurredAt,
        public readonly float $amount,
        public readonly string $direction, // 'in' | 'out'
        public readonly ?string $externalReference,
        public readonly ?string $counterpartyName,
        public readonly ?string $counterpartyNumber,
        public readonly string $rawLine,
    ) {
    }
}
