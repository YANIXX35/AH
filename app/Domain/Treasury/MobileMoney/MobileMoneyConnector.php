<?php

namespace App\Domain\Treasury\MobileMoney;

/**
 * Point d'extension : une implémentation lit un relevé (ou demain une API opérateur)
 * et retourne des transactions normalisées, sans que le reste du module (rapprochement,
 * contrôleur, vues) ait à connaître le format source.
 */
interface MobileMoneyConnector
{
    /**
     * @return list<NormalizedMobileMoneyTransaction>
     */
    public function parse(string $absoluteFilePath, string $operator): array;
}
