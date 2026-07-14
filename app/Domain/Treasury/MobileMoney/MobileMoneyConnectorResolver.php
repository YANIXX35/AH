<?php

namespace App\Domain\Treasury\MobileMoney;

/**
 * Choisit le connecteur à utiliser pour un opérateur donné. Aujourd'hui, seul
 * l'import de relevé CSV est disponible (aucun agrément marchand Wave / Orange
 * Money / MTN MoMo). Quand un agrément sera obtenu pour un opérateur, brancher
 * ici son connecteur API (ex: WaveApiConnector) sans toucher au reste du module.
 */
class MobileMoneyConnectorResolver
{
    public function __construct(private readonly CsvMobileMoneyConnector $csvConnector)
    {
    }

    public function resolve(string $operator): MobileMoneyConnector
    {
        return $this->csvConnector;
    }
}
