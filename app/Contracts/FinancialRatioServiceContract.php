<?php

namespace App\Contracts;

use Carbon\Carbon;

interface FinancialRatioServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(int $userId, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function classementPlateforme(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array;
}
