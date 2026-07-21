<?php

namespace Tests\Unit;

use App\Models\AccountingEntry;
use App\Services\BceaoLiasseService;
use PHPUnit\Framework\TestCase;

class BceaoLiasseServiceTest extends TestCase
{
    public function test_it_calculates_bilan_actif_and_passif_and_compte_resultat()
    {
        $service = new BceaoLiasseService();

        // Écritures de test
        $entry1 = new AccountingEntry([
            'debit_account' => '7011000',
            'credit_account' => '4111000',
            'amount' => 5000000,
        ]);

        $entry2 = new AccountingEntry([
            'debit_account' => '6011000',
            'credit_account' => '4011000',
            'amount' => 2000000,
        ]);

        $entriesN = collect([$entry1, $entry2]);
        $liasse = $service->generateLiasse($entriesN);

        $this->assertArrayHasKey('actif', $liasse);
        $this->assertArrayHasKey('passif', $liasse);
        $this->assertArrayHasKey('resultat', $liasse);

        // Ventes (RA) = 5 000 000 FCFA
        $this->assertEquals(5000000, $liasse['resultat']['rows']['RA']['net_n']);
        // Achats (SA) = 2 000 000 FCFA
        $this->assertEquals(2000000, $liasse['resultat']['rows']['SA']['net_n']);
        // Résultat Net (XZ) = 3 000 000 FCFA
        $this->assertEquals(3000000, $liasse['resultat']['totals']['XZ']['net_n']);
    }
}
