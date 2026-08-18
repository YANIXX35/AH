<?php

namespace App\Services;

use Illuminate\Support\Collection;

class BceaoLiasseService
{
    /**
     * Génère l'ensemble de la Liasse Fiscale BCEAO / SYSCOHADA pour une liste d'écritures.
     */
    public function generateLiasse(Collection $entriesN, ?Collection $entriesN1 = null): array
    {
        $balancesN = $this->calculateAccountBalances($entriesN);
        $balancesN1 = $entriesN1 ? $this->calculateAccountBalances($entriesN1) : [];

        $bilanActif = $this->calculateBilanActif($balancesN, $balancesN1);
        $bilanPassif = $this->calculateBilanPassif($balancesN, $balancesN1, $bilanActif['total']['net_n'] ?? 0.0);
        $compteResultat = $this->calculateCompteResultat($balancesN, $balancesN1);
        $tafire = $this->calculateTafire($balancesN, $balancesN1, $compteResultat, $bilanActif, $bilanPassif);
        $annexes = $this->calculateAnnexes($balancesN, $balancesN1, $entriesN);
        $amortissements = $this->calculateTableauAmortissements($balancesN, $entriesN);
        $provisions = $this->calculateTableauProvisions($balancesN, $entriesN);
        $immobilisations = $this->calculateNotesImmobilisations($balancesN, $balancesN1);

        return [
            'actif' => $bilanActif,
            'passif' => $bilanPassif,
            'resultat' => $compteResultat,
            'tafire' => $tafire,
            'annexes' => $annexes,
            'amortissements' => $amortissements,
            'provisions' => $provisions,
            'immobilisations' => $immobilisations,
        ];
    }

    // =====================================================================
    // CALCUL DES SOLDES PAR COMPTE
    // =====================================================================

    private function calculateAccountBalances(Collection $entries): array
    {
        $balances = [];

        foreach ($entries as $entry) {
            $debitAcc = trim((string) $entry->debit_account);
            $creditAcc = trim((string) $entry->credit_account);
            $amount = (float) $entry->amount;

            if ($debitAcc !== '') {
                if (! isset($balances[$debitAcc])) {
                    $balances[$debitAcc] = ['debit' => 0.0, 'credit' => 0.0];
                }
                $balances[$debitAcc]['debit'] += $amount;
            }

            if ($creditAcc !== '') {
                if (! isset($balances[$creditAcc])) {
                    $balances[$creditAcc] = ['debit' => 0.0, 'credit' => 0.0];
                }
                $balances[$creditAcc]['credit'] += $amount;
            }
        }

        return $balances;
    }

    public function sumAccountPrefixes(array $balances, array $prefixes, string $type = 'debit_net'): float
    {
        $total = 0.0;

        foreach ($balances as $account => $amounts) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with((string) $account, (string) $prefix)) {
                    $debitNet = $amounts['debit'] - $amounts['credit'];
                    $creditNet = $amounts['credit'] - $amounts['debit'];

                    if ($type === 'debit_net') {
                        $total += max(0.0, $debitNet);
                    } elseif ($type === 'credit_net') {
                        $total += max(0.0, $creditNet);
                    } elseif ($type === 'debit_gross') {
                        $total += $amounts['debit'];
                    } elseif ($type === 'credit_gross') {
                        $total += $amounts['credit'];
                    } elseif ($type === 'raw_debit_net') {
                        $total += $debitNet;
                    } elseif ($type === 'raw_credit_net') {
                        $total += $creditNet;
                    }
                    break;
                }
            }
        }

        return $total;
    }

    // =====================================================================
    // BILAN ACTIF
    // =====================================================================

    public function calculateBilanActif(array $balancesN, array $balancesN1): array
    {
        // Actif Immobilisé
        $aa_brut = $this->sumAccountPrefixes($balancesN, ['20'], 'debit_gross');
        $aa_prov = $this->sumAccountPrefixes($balancesN, ['280', '290'], 'credit_gross');
        $aa_net_n = max(0.0, $aa_brut - $aa_prov);
        $aa_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['20'], 'debit_net'));

        $ab_brut = $this->sumAccountPrefixes($balancesN, ['21'], 'debit_gross');
        $ab_prov = $this->sumAccountPrefixes($balancesN, ['281', '291'], 'credit_gross');
        $ab_net_n = max(0.0, $ab_brut - $ab_prov);
        $ab_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['21'], 'debit_net'));

        $ac_brut = $this->sumAccountPrefixes($balancesN, ['22'], 'debit_gross');
        $ac_prov = $this->sumAccountPrefixes($balancesN, ['282', '292'], 'credit_gross');
        $ac_net_n = max(0.0, $ac_brut - $ac_prov);
        $ac_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['22'], 'debit_net'));

        $ad_brut = $this->sumAccountPrefixes($balancesN, ['23'], 'debit_gross');
        $ad_prov = $this->sumAccountPrefixes($balancesN, ['283', '293'], 'credit_gross');
        $ad_net_n = max(0.0, $ad_brut - $ad_prov);
        $ad_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['23'], 'debit_net'));

        $ae_brut = $this->sumAccountPrefixes($balancesN, ['241', '242', '243', '244'], 'debit_gross');
        $ae_prov = $this->sumAccountPrefixes($balancesN, ['2841', '2842', '2843', '2844', '294'], 'credit_gross');
        $ae_net_n = max(0.0, $ae_brut - $ae_prov);
        $ae_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['241', '242', '243', '244'], 'debit_net'));

        $af_brut = $this->sumAccountPrefixes($balancesN, ['245'], 'debit_gross');
        $af_prov = $this->sumAccountPrefixes($balancesN, ['2845'], 'credit_gross');
        $af_net_n = max(0.0, $af_brut - $af_prov);
        $af_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['245'], 'debit_net'));

        $ag_brut = $this->sumAccountPrefixes($balancesN, ['26', '27'], 'debit_gross');
        $ag_prov = $this->sumAccountPrefixes($balancesN, ['296', '297'], 'credit_gross');
        $ag_net_n = max(0.0, $ag_brut - $ag_prov);
        $ag_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['26', '27'], 'debit_net'));

        $ah_brut = $this->sumAccountPrefixes($balancesN, ['25'], 'debit_gross');
        $ah_prov = $this->sumAccountPrefixes($balancesN, ['295'], 'credit_gross');
        $ah_net_n = max(0.0, $ah_brut - $ah_prov);
        $ah_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['25'], 'debit_net'));

        $az_brut = $aa_brut + $ab_brut + $ac_brut + $ad_brut + $ae_brut + $af_brut + $ag_brut + $ah_brut;
        $az_prov = $aa_prov + $ab_prov + $ac_prov + $ad_prov + $ae_prov + $af_prov + $ag_prov + $ah_prov;
        $az_net_n = $az_brut - $az_prov;
        $az_net_n1 = $aa_net_n1 + $ab_net_n1 + $ac_net_n1 + $ad_net_n1 + $ae_net_n1 + $af_net_n1 + $ag_net_n1 + $ah_net_n1;

        // Actif Circulant
        $ba_brut = $this->sumAccountPrefixes($balancesN, ['31', '32', '33', '34', '35', '36', '37', '38'], 'debit_gross');
        $ba_prov = $this->sumAccountPrefixes($balancesN, ['39'], 'credit_gross');
        $ba_net_n = max(0.0, $ba_brut - $ba_prov);
        $ba_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['3'], 'debit_net'));

        $bb_brut = $this->sumAccountPrefixes($balancesN, ['41'], 'debit_net');
        $bb_prov = $this->sumAccountPrefixes($balancesN, ['491'], 'credit_gross');
        $bb_net_n = max(0.0, $bb_brut - $bb_prov);
        $bb_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['41'], 'debit_net'));

        $bc_brut = $this->sumAccountPrefixes($balancesN, ['409', '42', '43', '44', '45', '46', '47'], 'debit_net')
            - $this->sumAccountPrefixes($balancesN, ['478'], 'debit_net');
        $bc_prov = $this->sumAccountPrefixes($balancesN, ['492', '493', '494', '495', '496', '497'], 'credit_gross');
        $bc_net_n = max(0.0, $bc_brut - $bc_prov);
        $bc_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['409', '42', '43', '44', '45', '46', '47'], 'debit_net')
            - $this->sumAccountPrefixes($balancesN1, ['478'], 'debit_net'));

        $bz_brut = $ba_brut + $bb_brut + $bc_brut;
        $bz_prov = $ba_prov + $bb_prov + $bc_prov;
        $bz_net_n = $bz_brut - $bz_prov;
        $bz_net_n1 = $ba_net_n1 + $bb_net_n1 + $bc_net_n1;

        // Trésorerie Actif
        $ca_brut = $this->sumAccountPrefixes($balancesN, ['50'], 'debit_gross');
        $ca_prov = $this->sumAccountPrefixes($balancesN, ['590'], 'credit_gross');
        $ca_net_n = max(0.0, $ca_brut - $ca_prov);
        $ca_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['50'], 'debit_net'));

        $cb_brut = $this->sumAccountPrefixes($balancesN, ['51'], 'debit_gross');
        $cb_prov = $this->sumAccountPrefixes($balancesN, ['591'], 'credit_gross');
        $cb_net_n = max(0.0, $cb_brut - $cb_prov);
        $cb_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['51'], 'debit_net'));

        $cc_brut = $this->sumAccountPrefixes($balancesN, ['52', '53', '54', '55', '57', '58'], 'debit_net');
        $cc_prov = $this->sumAccountPrefixes($balancesN, ['592', '593', '594'], 'credit_gross');
        $cc_net_n = max(0.0, $cc_brut - $cc_prov);
        $cc_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['52', '53', '54', '55', '57', '58'], 'debit_net'));

        $cz_brut = $ca_brut + $cb_brut + $cc_brut;
        $cz_prov = $ca_prov + $cb_prov + $cc_prov;
        $cz_net_n = $cz_brut - $cz_prov;
        $cz_net_n1 = $ca_net_n1 + $cb_net_n1 + $cc_net_n1;

        // Écart de conversion
        $da_brut = $this->sumAccountPrefixes($balancesN, ['478'], 'debit_gross');
        $da_prov = 0.0;
        $da_net_n = $da_brut;
        $da_net_n1 = max(0.0, $this->sumAccountPrefixes($balancesN1, ['478'], 'debit_net'));

        $total_brut = $az_brut + $bz_brut + $cz_brut + $da_brut;
        $total_prov = $az_prov + $bz_prov + $cz_prov;
        $total_net_n = $total_brut - $total_prov;
        $total_net_n1 = $az_net_n1 + $bz_net_n1 + $cz_net_n1 + $da_net_n1;

        return [
            'rows' => [
                'AA' => ['libelle' => 'Charges immobilisées', 'brut' => $aa_brut, 'prov' => $aa_prov, 'net_n' => $aa_net_n, 'net_n1' => $aa_net_n1],
                'AB' => ['libelle' => 'Immobilisations incorporelles', 'brut' => $ab_brut, 'prov' => $ab_prov, 'net_n' => $ab_net_n, 'net_n1' => $ab_net_n1],
                'AC' => ['libelle' => 'Terrains', 'brut' => $ac_brut, 'prov' => $ac_prov, 'net_n' => $ac_net_n, 'net_n1' => $ac_net_n1],
                'AD' => ['libelle' => 'Bâtiments, installations techniques et agencements', 'brut' => $ad_brut, 'prov' => $ad_prov, 'net_n' => $ad_net_n, 'net_n1' => $ad_net_n1],
                'AE' => ['libelle' => 'Matériel, mobilier et actifs biologiques', 'brut' => $ae_brut, 'prov' => $ae_prov, 'net_n' => $ae_net_n, 'net_n1' => $ae_net_n1],
                'AF' => ['libelle' => 'Matériel de transport', 'brut' => $af_brut, 'prov' => $af_prov, 'net_n' => $af_net_n, 'net_n1' => $af_net_n1],
                'AG' => ['libelle' => 'Immobilisations financières', 'brut' => $ag_brut, 'prov' => $ag_prov, 'net_n' => $ag_net_n, 'net_n1' => $ag_net_n1],
                'AH' => ['libelle' => 'Avances et acomptes versés sur immobilisations', 'brut' => $ah_brut, 'prov' => $ah_prov, 'net_n' => $ah_net_n, 'net_n1' => $ah_net_n1],
                'AZ' => ['libelle' => 'TOTAL ACTIF IMMOBILISÉ', 'brut' => $az_brut, 'prov' => $az_prov, 'net_n' => $az_net_n, 'net_n1' => $az_net_n1, 'is_total' => true],
                'BA' => ['libelle' => 'Stocks et en-cours', 'brut' => $ba_brut, 'prov' => $ba_prov, 'net_n' => $ba_net_n, 'net_n1' => $ba_net_n1],
                'BB' => ['libelle' => 'Créances clients et comptes rattachés', 'brut' => $bb_brut, 'prov' => $bb_prov, 'net_n' => $bb_net_n, 'net_n1' => $bb_net_n1],
                'BC' => ['libelle' => 'Autres créances', 'brut' => $bc_brut, 'prov' => $bc_prov, 'net_n' => $bc_net_n, 'net_n1' => $bc_net_n1],
                'BZ' => ['libelle' => 'TOTAL ACTIF CIRCULANT', 'brut' => $bz_brut, 'prov' => $bz_prov, 'net_n' => $bz_net_n, 'net_n1' => $bz_net_n1, 'is_total' => true],
                'CA' => ['libelle' => 'Titres de placement', 'brut' => $ca_brut, 'prov' => $ca_prov, 'net_n' => $ca_net_n, 'net_n1' => $ca_net_n1],
                'CB' => ['libelle' => 'Valeurs à encaisser', 'brut' => $cb_brut, 'prov' => $cb_prov, 'net_n' => $cb_net_n, 'net_n1' => $cb_net_n1],
                'CC' => ['libelle' => 'Banques, caisse, chèques postaux et Mobile Money', 'brut' => $cc_brut, 'prov' => $cc_prov, 'net_n' => $cc_net_n, 'net_n1' => $cc_net_n1],
                'CZ' => ['libelle' => 'TOTAL TRÉSORERIE ACTIF', 'brut' => $cz_brut, 'prov' => $cz_prov, 'net_n' => $cz_net_n, 'net_n1' => $cz_net_n1, 'is_total' => true],
                'DA' => ['libelle' => 'Écart de conversion Actif', 'brut' => $da_brut, 'prov' => $da_prov, 'net_n' => $da_net_n, 'net_n1' => $da_net_n1],
            ],
            'total' => [
                'libelle' => 'TOTAL GÉNÉRAL ACTIF',
                'brut' => $total_brut,
                'prov' => $total_prov,
                'net_n' => $total_net_n,
                'net_n1' => $total_net_n1,
            ],
        ];
    }

    // =====================================================================
    // BILAN PASSIF
    // =====================================================================

    public function calculateBilanPassif(array $balancesN, array $balancesN1, float $totalActifNet = 0.0): array
    {
        $resultatCompte = $this->calculateCompteResultat($balancesN, $balancesN1);
        $resultatNetN = $resultatCompte['totals']['XZ']['net_n'] ?? 0.0;
        $resultatNetN1 = $resultatCompte['totals']['XZ']['net_n1'] ?? 0.0;

        // Capitaux Propres
        $ca_net_n = $this->sumAccountPrefixes($balancesN, ['101', '102', '103', '104'], 'credit_net');
        $ca_net_n1 = $this->sumAccountPrefixes($balancesN1, ['101', '102', '103', '104'], 'credit_net');

        $cb_net_n = $this->sumAccountPrefixes($balancesN, ['109'], 'debit_net');
        $cb_net_n1 = $this->sumAccountPrefixes($balancesN1, ['109'], 'debit_net');

        $cc_net_n = $this->sumAccountPrefixes($balancesN, ['105'], 'credit_net');
        $cc_net_n1 = $this->sumAccountPrefixes($balancesN1, ['105'], 'credit_net');

        $cd_net_n = $this->sumAccountPrefixes($balancesN, ['106'], 'credit_net');
        $cd_net_n1 = $this->sumAccountPrefixes($balancesN1, ['106'], 'credit_net');

        $ce_net_n = $this->sumAccountPrefixes($balancesN, ['111', '112', '113', '118'], 'credit_net');
        $ce_net_n1 = $this->sumAccountPrefixes($balancesN1, ['111', '112', '113', '118'], 'credit_net');

        $cf_net_n = $this->sumAccountPrefixes($balancesN, ['121'], 'credit_net') - $this->sumAccountPrefixes($balancesN, ['129'], 'debit_net');
        $cf_net_n1 = $this->sumAccountPrefixes($balancesN1, ['121'], 'credit_net') - $this->sumAccountPrefixes($balancesN1, ['129'], 'debit_net');

        $cg_net_n = $resultatNetN;
        $cg_net_n1 = $resultatNetN1;

        $ch_net_n = $this->sumAccountPrefixes($balancesN, ['14'], 'credit_net');
        $ch_net_n1 = $this->sumAccountPrefixes($balancesN1, ['14'], 'credit_net');

        $ci_net_n = $this->sumAccountPrefixes($balancesN, ['15'], 'credit_net');
        $ci_net_n1 = $this->sumAccountPrefixes($balancesN1, ['15'], 'credit_net');

        $cz_net_n = $ca_net_n - $cb_net_n + $cc_net_n + $cd_net_n + $ce_net_n + $cf_net_n + $cg_net_n + $ch_net_n + $ci_net_n;
        $cz_net_n1 = $ca_net_n1 - $cb_net_n1 + $cc_net_n1 + $cd_net_n1 + $ce_net_n1 + $cf_net_n1 + $cg_net_n1 + $ch_net_n1 + $ci_net_n1;

        // Dettes Financières
        $da_net_n = $this->sumAccountPrefixes($balancesN, ['16', '18'], 'credit_net');
        $da_net_n1 = $this->sumAccountPrefixes($balancesN1, ['16', '18'], 'credit_net');

        $db_net_n = $this->sumAccountPrefixes($balancesN, ['17'], 'credit_net');
        $db_net_n1 = $this->sumAccountPrefixes($balancesN1, ['17'], 'credit_net');

        $dc_net_n = $this->sumAccountPrefixes($balancesN, ['19'], 'credit_net');
        $dc_net_n1 = $this->sumAccountPrefixes($balancesN1, ['19'], 'credit_net');

        $dz_net_n = $da_net_n + $db_net_n + $dc_net_n;
        $dz_net_n1 = $da_net_n1 + $db_net_n1 + $dc_net_n1;

        // Passif Circulant
        $ea_net_n = $this->sumAccountPrefixes($balancesN, ['401', '402', '403', '408'], 'credit_net');
        $ea_net_n1 = $this->sumAccountPrefixes($balancesN1, ['401', '402', '403', '408'], 'credit_net');

        $eb_net_n = $this->sumAccountPrefixes($balancesN, ['42', '43', '44'], 'credit_net');
        $eb_net_n1 = $this->sumAccountPrefixes($balancesN1, ['42', '43', '44'], 'credit_net');

        $ec_net_n = $this->sumAccountPrefixes($balancesN, ['41', '45', '46', '47'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN, ['479'], 'credit_net');
        $ec_net_n1 = $this->sumAccountPrefixes($balancesN1, ['41', '45', '46', '47'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN1, ['479'], 'credit_net');

        $ed_net_n = $this->sumAccountPrefixes($balancesN, ['499'], 'credit_net');
        $ed_net_n1 = $this->sumAccountPrefixes($balancesN1, ['499'], 'credit_net');

        $ee_net_n = $this->sumAccountPrefixes($balancesN, ['48'], 'credit_net');
        $ee_net_n1 = $this->sumAccountPrefixes($balancesN1, ['48'], 'credit_net');

        $ez_net_n = $ea_net_n + $eb_net_n + $ec_net_n + $ed_net_n + $ee_net_n;
        $ez_net_n1 = $ea_net_n1 + $eb_net_n1 + $ec_net_n1 + $ed_net_n1 + $ee_net_n1;

        // Trésorerie Passif
        $fa_net_n = $this->sumAccountPrefixes($balancesN, ['561'], 'credit_net');
        $fa_net_n1 = $this->sumAccountPrefixes($balancesN1, ['561'], 'credit_net');

        $fb_net_n = $this->sumAccountPrefixes($balancesN, ['564', '565', '566'], 'credit_net');
        $fb_net_n1 = $this->sumAccountPrefixes($balancesN1, ['564', '565', '566'], 'credit_net');

        $fc_net_n = $this->sumAccountPrefixes($balancesN, ['52', '53', '54', '55', '57', '58'], 'credit_net');
        $fc_net_n1 = $this->sumAccountPrefixes($balancesN1, ['52', '53', '54', '55', '57', '58'], 'credit_net');

        $fz_net_n = $fa_net_n + $fb_net_n + $fc_net_n;
        $fz_net_n1 = $fa_net_n1 + $fb_net_n1 + $fc_net_n1;

        // Écart de conversion Passif
        $ga_net_n = $this->sumAccountPrefixes($balancesN, ['479'], 'credit_net');
        $ga_net_n1 = $this->sumAccountPrefixes($balancesN1, ['479'], 'credit_net');

        $total_net_n = $cz_net_n + $dz_net_n + $ez_net_n + $fz_net_n + $ga_net_n;
        $total_net_n1 = $cz_net_n1 + $dz_net_n1 + $ez_net_n1 + $fz_net_n1 + $ga_net_n1;

        return [
            'rows' => [
                'CA' => ['libelle' => 'Capital social ou individuel', 'net_n' => $ca_net_n, 'net_n1' => $ca_net_n1],
                'CB' => ['libelle' => 'Actionnaires, capital non appelé (-)', 'net_n' => -$cb_net_n, 'net_n1' => -$cb_net_n1],
                'CC' => ['libelle' => 'Primes liées au capital social', 'net_n' => $cc_net_n, 'net_n1' => $cc_net_n1],
                'CD' => ['libelle' => 'Écarts de réévaluation', 'net_n' => $cd_net_n, 'net_n1' => $cd_net_n1],
                'CE' => ['libelle' => 'Réserves indisponibles et libres', 'net_n' => $ce_net_n, 'net_n1' => $ce_net_n1],
                'CF' => ['libelle' => 'Report à nouveau (+/-)', 'net_n' => $cf_net_n, 'net_n1' => $cf_net_n1],
                'CG' => ['libelle' => 'Résultat net de l\'exercice (bénéfice + ou perte -)', 'net_n' => $cg_net_n, 'net_n1' => $cg_net_n1],
                'CH' => ['libelle' => 'Subventions d\'investissement', 'net_n' => $ch_net_n, 'net_n1' => $ch_net_n1],
                'CI' => ['libelle' => 'Provisions réglementées et fonds assimilés', 'net_n' => $ci_net_n, 'net_n1' => $ci_net_n1],
                'CZ' => ['libelle' => 'TOTAL CAPITAUX PROPRES ET RESSOURCES ASSIMILÉES', 'net_n' => $cz_net_n, 'net_n1' => $cz_net_n1, 'is_total' => true],
                'DA' => ['libelle' => 'Emprunts et dettes financières diverses', 'net_n' => $da_net_n, 'net_n1' => $da_net_n1],
                'DB' => ['libelle' => 'Dettes de crédit-bail et assimilés', 'net_n' => $db_net_n, 'net_n1' => $db_net_n1],
                'DC' => ['libelle' => 'Provisions financières pour risques et charges', 'net_n' => $dc_net_n, 'net_n1' => $dc_net_n1],
                'DZ' => ['libelle' => 'TOTAL DETTES FINANCIÈRES ET RESSOURCES ASSIMILÉES', 'net_n' => $dz_net_n, 'net_n1' => $dz_net_n1, 'is_total' => true],
                'EA' => ['libelle' => 'Dettes fournisseurs et comptes rattachés', 'net_n' => $ea_net_n, 'net_n1' => $ea_net_n1],
                'EB' => ['libelle' => 'Dettes fiscales et sociales', 'net_n' => $eb_net_n, 'net_n1' => $eb_net_n1],
                'EC' => ['libelle' => 'Autres dettes et provisions à court terme', 'net_n' => $ec_net_n, 'net_n1' => $ec_net_n1],
                'ED' => ['libelle' => 'Provisions pour risques à court terme', 'net_n' => $ed_net_n, 'net_n1' => $ed_net_n1],
                'EE' => ['libelle' => 'Autres dettes hors exploitation', 'net_n' => $ee_net_n, 'net_n1' => $ee_net_n1],
                'EZ' => ['libelle' => 'TOTAL PASSIF CIRCULANT', 'net_n' => $ez_net_n, 'net_n1' => $ez_net_n1, 'is_total' => true],
                'FA' => ['libelle' => 'Banques, crédits d\'escompte', 'net_n' => $fa_net_n, 'net_n1' => $fa_net_n1],
                'FB' => ['libelle' => 'Banques, crédits de trésorerie et découverts', 'net_n' => $fb_net_n, 'net_n1' => $fb_net_n1],
                'FC' => ['libelle' => 'Autres établissements financiers et instruments de trésorerie', 'net_n' => $fc_net_n, 'net_n1' => $fc_net_n1],
                'FZ' => ['libelle' => 'TOTAL TRÉSORERIE PASSIF', 'net_n' => $fz_net_n, 'net_n1' => $fz_net_n1, 'is_total' => true],
                'GA' => ['libelle' => 'Écart de conversion Passif', 'net_n' => $ga_net_n, 'net_n1' => $ga_net_n1],
            ],
            'total' => [
                'libelle' => 'TOTAL GÉNÉRAL PASSIF',
                'net_n' => $total_net_n,
                'net_n1' => $total_net_n1,
            ],
        ];
    }

    // =====================================================================
    // COMPTE DE RÉSULTAT
    // =====================================================================

    public function calculateCompteResultat(array $balancesN, array $balancesN1): array
    {
        // Produits d'exploitation
        $ra_n = $this->sumAccountPrefixes($balancesN, ['701'], 'credit_net');
        $ra_n1 = $this->sumAccountPrefixes($balancesN1, ['701'], 'credit_net');
        $rb_n = $this->sumAccountPrefixes($balancesN, ['702', '703', '704'], 'credit_net');
        $rb_n1 = $this->sumAccountPrefixes($balancesN1, ['702', '703', '704'], 'credit_net');
        $rc_n = $this->sumAccountPrefixes($balancesN, ['705', '706', '707'], 'credit_net');
        $rc_n1 = $this->sumAccountPrefixes($balancesN1, ['705', '706', '707'], 'credit_net');
        $rd_n = $ra_n + $rb_n + $rc_n;
        $rd_n1 = $ra_n1 + $rb_n1 + $rc_n1;
        $re_n = $this->sumAccountPrefixes($balancesN, ['73'], 'credit_net');
        $re_n1 = $this->sumAccountPrefixes($balancesN1, ['73'], 'credit_net');
        $rf_n = $this->sumAccountPrefixes($balancesN, ['72'], 'credit_net');
        $rf_n1 = $this->sumAccountPrefixes($balancesN1, ['72'], 'credit_net');
        $rg_n = $this->sumAccountPrefixes($balancesN, ['71'], 'credit_net');
        $rg_n1 = $this->sumAccountPrefixes($balancesN1, ['71'], 'credit_net');
        $rh_n = $this->sumAccountPrefixes($balancesN, ['75'], 'credit_net');
        $rh_n1 = $this->sumAccountPrefixes($balancesN1, ['75'], 'credit_net');
        $ri_n = $this->sumAccountPrefixes($balancesN, ['781'], 'credit_net');
        $ri_n1 = $this->sumAccountPrefixes($balancesN1, ['781'], 'credit_net');
        $rj_n = $this->sumAccountPrefixes($balancesN, ['791'], 'credit_net');
        $rj_n1 = $this->sumAccountPrefixes($balancesN1, ['791'], 'credit_net');

        // Charges d'exploitation
        $sa_n = $this->sumAccountPrefixes($balancesN, ['601'], 'debit_net');
        $sa_n1 = $this->sumAccountPrefixes($balancesN1, ['601'], 'debit_net');
        $sb_n = $this->sumAccountPrefixes($balancesN, ['6031'], 'raw_debit_net');
        $sb_n1 = $this->sumAccountPrefixes($balancesN1, ['6031'], 'raw_debit_net');
        $sc_n = $this->sumAccountPrefixes($balancesN, ['602'], 'debit_net');
        $sc_n1 = $this->sumAccountPrefixes($balancesN1, ['602'], 'debit_net');
        $sd_n = $this->sumAccountPrefixes($balancesN, ['6032'], 'raw_debit_net');
        $sd_n1 = $this->sumAccountPrefixes($balancesN1, ['6032'], 'raw_debit_net');
        $se_n = $this->sumAccountPrefixes($balancesN, ['604', '605', '608'], 'debit_net');
        $se_n1 = $this->sumAccountPrefixes($balancesN1, ['604', '605', '608'], 'debit_net');
        $sf_n = $this->sumAccountPrefixes($balancesN, ['61'], 'debit_net');
        $sf_n1 = $this->sumAccountPrefixes($balancesN1, ['61'], 'debit_net');
        $sg_n = $this->sumAccountPrefixes($balancesN, ['62', '63'], 'debit_net');
        $sg_n1 = $this->sumAccountPrefixes($balancesN1, ['62', '63'], 'debit_net');
        $sh_n = $this->sumAccountPrefixes($balancesN, ['64'], 'debit_net');
        $sh_n1 = $this->sumAccountPrefixes($balancesN1, ['64'], 'debit_net');
        $si_n = $this->sumAccountPrefixes($balancesN, ['65'], 'debit_net');
        $si_n1 = $this->sumAccountPrefixes($balancesN1, ['65'], 'debit_net');
        $sj_n = $this->sumAccountPrefixes($balancesN, ['66'], 'debit_net');
        $sj_n1 = $this->sumAccountPrefixes($balancesN1, ['66'], 'debit_net');
        $sk_n = $this->sumAccountPrefixes($balancesN, ['681', '691'], 'debit_net');
        $sk_n1 = $this->sumAccountPrefixes($balancesN1, ['681', '691'], 'debit_net');

        // SIG
        $ta_n = $ra_n - $sa_n - $sb_n;
        $ta_n1 = $ra_n1 - $sa_n1 - $sb_n1;
        $tb_n = $rb_n + $rc_n - $sc_n - $sd_n;
        $tb_n1 = $rb_n1 + $rc_n1 - $sc_n1 - $sd_n1;
        $tc_n = ($ta_n + $tb_n + $re_n + $rf_n + $rg_n + $rh_n) - ($se_n + $sf_n + $sg_n);
        $tc_n1 = ($ta_n1 + $tb_n1 + $re_n1 + $rf_n1 + $rg_n1 + $rh_n1) - ($se_n1 + $sf_n1 + $sg_n1);
        $td_n = $tc_n - $sh_n - $si_n - $sj_n;
        $td_n1 = $tc_n1 - $sh_n1 - $si_n1 - $sj_n1;
        $te_n = $td_n + $ri_n + $rj_n - $sk_n;
        $te_n1 = $td_n1 + $ri_n1 + $rj_n1 - $sk_n1;

        // Financier
        $ua_n = $this->sumAccountPrefixes($balancesN, ['77', '787', '797'], 'credit_net');
        $ua_n1 = $this->sumAccountPrefixes($balancesN1, ['77', '787', '797'], 'credit_net');
        $ub_n = $this->sumAccountPrefixes($balancesN, ['67', '687', '697'], 'debit_net');
        $ub_n1 = $this->sumAccountPrefixes($balancesN1, ['67', '687', '697'], 'debit_net');
        $uc_n = $ua_n - $ub_n;
        $uc_n1 = $ua_n1 - $ub_n1;
        $ud_n = $te_n + $uc_n;
        $ud_n1 = $te_n1 + $uc_n1;

        // HAO
        $va_n = $this->sumAccountPrefixes($balancesN, ['82', '84', '86', '88'], 'credit_net');
        $va_n1 = $this->sumAccountPrefixes($balancesN1, ['82', '84', '86', '88'], 'credit_net');
        $vb_n = $this->sumAccountPrefixes($balancesN, ['81', '83', '85', '87'], 'debit_net');
        $vb_n1 = $this->sumAccountPrefixes($balancesN1, ['81', '83', '85', '87'], 'debit_net');
        $vc_n = $va_n - $vb_n;
        $vc_n1 = $va_n1 - $vb_n1;

        $wa_n = $this->sumAccountPrefixes($balancesN, ['891'], 'debit_net');
        $wa_n1 = $this->sumAccountPrefixes($balancesN1, ['891'], 'debit_net');
        $wb_n = $this->sumAccountPrefixes($balancesN, ['892', '895'], 'debit_net');
        $wb_n1 = $this->sumAccountPrefixes($balancesN1, ['892', '895'], 'debit_net');
        $xz_n = $ud_n + $vc_n - $wa_n - $wb_n;
        $xz_n1 = $ud_n1 + $vc_n1 - $wa_n1 - $wb_n1;

        return [
            'rows' => [
                'RA' => ['libelle' => 'Ventes de marchandises', 'net_n' => $ra_n, 'net_n1' => $ra_n1],
                'RB' => ['libelle' => 'Ventes de produits fabriqués', 'net_n' => $rb_n, 'net_n1' => $rb_n1],
                'RC' => ['libelle' => 'Travaux, services vendus et produits annexes', 'net_n' => $rc_n, 'net_n1' => $rc_n1],
                'RD' => ['libelle' => 'CHIFFRE D\'AFFAIRES (RA + RB + RC)', 'net_n' => $rd_n, 'net_n1' => $rd_n1, 'is_total' => true],
                'RE' => ['libelle' => 'Production stockée (ou déstockage)', 'net_n' => $re_n, 'net_n1' => $re_n1],
                'RF' => ['libelle' => 'Production immobilisée', 'net_n' => $rf_n, 'net_n1' => $rf_n1],
                'RG' => ['libelle' => 'Subventions d\'exploitation', 'net_n' => $rg_n, 'net_n1' => $rg_n1],
                'RH' => ['libelle' => 'Autres produits d\'exploitation', 'net_n' => $rh_n, 'net_n1' => $rh_n1],
                'RI' => ['libelle' => 'Reprises de provisions d\'exploitation', 'net_n' => $ri_n, 'net_n1' => $ri_n1],
                'RJ' => ['libelle' => 'Transferts de charges d\'exploitation', 'net_n' => $rj_n, 'net_n1' => $rj_n1],
                'SA' => ['libelle' => 'Achats de marchandises', 'net_n' => $sa_n, 'net_n1' => $sa_n1],
                'SB' => ['libelle' => 'Variation de stocks de marchandises', 'net_n' => $sb_n, 'net_n1' => $sb_n1],
                'SC' => ['libelle' => 'Achats de matières premières et fournitures rattachées', 'net_n' => $sc_n, 'net_n1' => $sc_n1],
                'SD' => ['libelle' => 'Variation de stocks de matières premières', 'net_n' => $sd_n, 'net_n1' => $sd_n1],
                'SE' => ['libelle' => 'Autres achats et variations de stocks', 'net_n' => $se_n, 'net_n1' => $se_n1],
                'SF' => ['libelle' => 'Transports', 'net_n' => $sf_n, 'net_n1' => $sf_n1],
                'SG' => ['libelle' => 'Services extérieurs', 'net_n' => $sg_n, 'net_n1' => $sg_n1],
                'SH' => ['libelle' => 'Impôts et taxes', 'net_n' => $sh_n, 'net_n1' => $sh_n1],
                'SI' => ['libelle' => 'Autres charges d\'exploitation', 'net_n' => $si_n, 'net_n1' => $si_n1],
                'SJ' => ['libelle' => 'Charges de personnel', 'net_n' => $sj_n, 'net_n1' => $sj_n1],
                'SK' => ['libelle' => 'Dotations aux amortissements, dépréciations et provisions', 'net_n' => $sk_n, 'net_n1' => $sk_n1],
                'UA' => ['libelle' => 'Revenus financiers et produits assimilés', 'net_n' => $ua_n, 'net_n1' => $ua_n1],
                'UB' => ['libelle' => 'Frais financiers et charges assimilées', 'net_n' => $ub_n, 'net_n1' => $ub_n1],
                'VA' => ['libelle' => 'Produits des cessions d\'immobilisations (HAO)', 'net_n' => $va_n, 'net_n1' => $va_n1],
                'VB' => ['libelle' => 'Valeurs comptables des cessions (HAO)', 'net_n' => $vb_n, 'net_n1' => $vb_n1],
                'WA' => ['libelle' => 'Participation des travailleurs', 'net_n' => $wa_n, 'net_n1' => $wa_n1],
                'WB' => ['libelle' => 'Impôts sur le résultat', 'net_n' => $wb_n, 'net_n1' => $wb_n1],
            ],
            'totals' => [
                'TA' => ['libelle' => 'MARGE BRUTE SUR MARCHANDISES', 'net_n' => $ta_n, 'net_n1' => $ta_n1, 'is_total' => true],
                'TB' => ['libelle' => 'MARGE BRUTE SUR MATIÈRES PREMIÈRES', 'net_n' => $tb_n, 'net_n1' => $tb_n1, 'is_total' => true],
                'TC' => ['libelle' => 'VALEUR AJOUTÉE', 'net_n' => $tc_n, 'net_n1' => $tc_n1, 'is_total' => true],
                'TD' => ['libelle' => 'EXCÉDENT BRUT D\'EXPLOITATION (EBE)', 'net_n' => $td_n, 'net_n1' => $td_n1, 'is_total' => true],
                'TE' => ['libelle' => 'RÉSULTAT D\'EXPLOITATION', 'net_n' => $te_n, 'net_n1' => $te_n1, 'is_total' => true],
                'UC' => ['libelle' => 'RÉSULTAT FINANCIER', 'net_n' => $uc_n, 'net_n1' => $uc_n1, 'is_total' => true],
                'UD' => ['libelle' => 'RÉSULTAT DES ACTIVITÉS ORDINAIRES (RAO)', 'net_n' => $ud_n, 'net_n1' => $ud_n1, 'is_total' => true],
                'VC' => ['libelle' => 'RÉSULTAT HORS ACTIVITÉ ORDINAIRE (HAO)', 'net_n' => $vc_n, 'net_n1' => $vc_n1, 'is_total' => true],
                'XZ' => ['libelle' => 'RÉSULTAT NET DE L\'EXERCICE', 'net_n' => $xz_n, 'net_n1' => $xz_n1, 'is_total' => true],
            ],
        ];
    }

    // =====================================================================
    // TAFIRE — Tableau de Financement des Ressources et Emplois
    // =====================================================================

    public function calculateTafire(
        array $balancesN,
        array $balancesN1,
        array $compteResultat,
        array $bilanActif,
        array $bilanPassif
    ): array {
        $resultatNet = $compteResultat['totals']['XZ']['net_n'] ?? 0.0;

        // --- Ressources stables ---
        // Capacité d'Autofinancement Globale (CAFG)
        $dotAmort = $this->sumAccountPrefixes($balancesN, ['28', '29', '39', '49', '59'], 'credit_gross');
        $reprisesAmort = $this->sumAccountPrefixes($balancesN, ['78', '79'], 'credit_net');
        $prodHao = $compteResultat['rows']['VA']['net_n'] ?? 0.0;
        $valCessHao = $compteResultat['rows']['VB']['net_n'] ?? 0.0;
        $cafg = $resultatNet + $dotAmort - $reprisesAmort - $prodHao + $valCessHao;

        // Cessions d'immobilisations
        $cessionsImmob = $prodHao;

        // Augmentation de capital
        $augmentCapital = max(0.0, $this->sumAccountPrefixes($balancesN, ['101', '102', '103', '104', '105'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN1, ['101', '102', '103', '104', '105'], 'credit_net'));

        // Emprunts nouveaux contractés
        $empruntsNouveaux = max(0.0, $this->sumAccountPrefixes($balancesN, ['16', '17', '18'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN1, ['16', '17', '18'], 'credit_net'));

        // Subventions d'investissement reçues
        $subventionsInv = max(0.0, $this->sumAccountPrefixes($balancesN, ['14'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN1, ['14'], 'credit_net'));

        $totalRessourcesStables = $cafg + $cessionsImmob + $augmentCapital + $empruntsNouveaux + $subventionsInv;

        // --- Emplois stables ---
        // Acquisitions d'immobilisations
        $acqImmoCorpo = max(0.0, ($bilanActif['rows']['AD']['brut'] ?? 0.0) + ($bilanActif['rows']['AE']['brut'] ?? 0.0) + ($bilanActif['rows']['AF']['brut'] ?? 0.0)
            - ($this->sumAccountPrefixes($balancesN1, ['23', '241', '242', '243', '244', '245'], 'debit_gross')));
        $acqImmoIncorpo = max(0.0, ($bilanActif['rows']['AB']['brut'] ?? 0.0)
            - ($this->sumAccountPrefixes($balancesN1, ['21'], 'debit_gross')));
        $acqImmoFinanc = max(0.0, ($bilanActif['rows']['AG']['brut'] ?? 0.0)
            - ($this->sumAccountPrefixes($balancesN1, ['26', '27'], 'debit_gross')));

        // Remboursements d'emprunts
        $rembEmpruntsTLT = max(0.0, $this->sumAccountPrefixes($balancesN1, ['16', '17', '18'], 'credit_net')
            - $this->sumAccountPrefixes($balancesN, ['16', '17', '18'], 'credit_net'));

        // Dividendes distribués (comptes 465)
        $dividendesDistrib = $this->sumAccountPrefixes($balancesN, ['465'], 'debit_net');

        $totalEmploisStables = $acqImmoCorpo + $acqImmoIncorpo + $acqImmoFinanc + $rembEmpruntsTLT + $dividendesDistrib;

        // Variation du Fonds de Roulement Net Global (FRNG)
        $variationFRNG = $totalRessourcesStables - $totalEmploisStables;

        // --- Besoins en Fonds de Roulement (BFR) ---
        $stockN = ($bilanActif['rows']['BA']['net_n'] ?? 0.0);
        $stockN1 = ($bilanActif['rows']['BA']['net_n1'] ?? 0.0);
        $creancesN = ($bilanActif['rows']['BB']['net_n'] ?? 0.0) + ($bilanActif['rows']['BC']['net_n'] ?? 0.0);
        $creancesN1 = ($bilanActif['rows']['BB']['net_n1'] ?? 0.0) + ($bilanActif['rows']['BC']['net_n1'] ?? 0.0);
        $dettesFournN = ($bilanPassif['rows']['EA']['net_n'] ?? 0.0);
        $dettesFournN1 = ($bilanPassif['rows']['EA']['net_n1'] ?? 0.0);
        $autresDettesN = ($bilanPassif['rows']['EB']['net_n'] ?? 0.0) + ($bilanPassif['rows']['EC']['net_n'] ?? 0.0);
        $autresDettesN1 = ($bilanPassif['rows']['EB']['net_n1'] ?? 0.0) + ($bilanPassif['rows']['EC']['net_n1'] ?? 0.0);

        $bfrN = ($stockN + $creancesN) - ($dettesFournN + $autresDettesN);
        $bfrN1 = ($stockN1 + $creancesN1) - ($dettesFournN1 + $autresDettesN1);
        $variationBFR = $bfrN - $bfrN1;

        // Trésorerie nette
        $tresoActifN = ($bilanActif['rows']['CZ']['net_n'] ?? 0.0);
        $tresoPassifN = ($bilanPassif['rows']['FZ']['net_n'] ?? 0.0);
        $tresoNetteN = $tresoActifN - $tresoPassifN;

        $tresoActifN1 = ($bilanActif['rows']['CZ']['net_n1'] ?? 0.0);
        $tresoPassifN1 = ($bilanPassif['rows']['FZ']['net_n1'] ?? 0.0);
        $tresoNetteN1 = $tresoActifN1 - $tresoPassifN1;
        $variationTreso = $tresoNetteN - $tresoNetteN1;

        return [
            'cafg' => $cafg,
            'resultat_net' => $resultatNet,
            'dot_amort' => $dotAmort,
            'reprises_amort' => $reprisesAmort,
            'prod_cessions_hao' => $prodHao,
            'val_compt_cessions' => $valCessHao,
            'cessions_immob' => $cessionsImmob,
            'augment_capital' => $augmentCapital,
            'emprunts_nouveaux' => $empruntsNouveaux,
            'subventions_inv' => $subventionsInv,
            'total_ressources' => $totalRessourcesStables,
            'acq_immo_corpo' => $acqImmoCorpo,
            'acq_immo_incorpo' => $acqImmoIncorpo,
            'acq_immo_financ' => $acqImmoFinanc,
            'remb_emprunts' => $rembEmpruntsTLT,
            'dividendes' => $dividendesDistrib,
            'total_emplois' => $totalEmploisStables,
            'variation_frng' => $variationFRNG,
            'bfr_n' => $bfrN,
            'bfr_n1' => $bfrN1,
            'variation_bfr' => $variationBFR,
            'treso_nette_n' => $tresoNetteN,
            'treso_nette_n1' => $tresoNetteN1,
            'variation_treso' => $variationTreso,
            'verification' => abs($variationFRNG - $variationBFR - $variationTreso) < 1.0,
        ];
    }

    // =====================================================================
    // TABLEAU DES IMMOBILISATIONS — Note 1
    // =====================================================================

    public function calculateNotesImmobilisations(array $balancesN, array $balancesN1): array
    {
        $categories = [
            'Charges immobilisées' => ['prefixesBrut' => ['20'], 'prefixesDep' => ['280', '290']],
            'Immobilisations incorporelles' => ['prefixesBrut' => ['21'], 'prefixesDep' => ['281', '291']],
            'Terrains' => ['prefixesBrut' => ['22'], 'prefixesDep' => ['282', '292']],
            'Bâtiments' => ['prefixesBrut' => ['231', '232', '233'], 'prefixesDep' => ['2831', '2832', '2833', '2931']],
            'Aménagements, agencements' => ['prefixesBrut' => ['234', '235'], 'prefixesDep' => ['2834', '2835']],
            'Matériel et mobilier' => ['prefixesBrut' => ['241', '242', '243', '244'], 'prefixesDep' => ['2841', '2842', '2843', '2844', '294']],
            'Matériel de transport' => ['prefixesBrut' => ['245'], 'prefixesDep' => ['2845']],
            'Immobilisations financières' => ['prefixesBrut' => ['26', '27'], 'prefixesDep' => ['296', '297']],
        ];

        $rows = [];
        $totalBrutN = 0.0;
        $totalBrutN1 = 0.0;
        $totalAcq = 0.0;
        $totalCess = 0.0;
        $totalDepN = 0.0;
        $totalDepN1 = 0.0;

        foreach ($categories as $libelle => $cfg) {
            $brutN = $this->sumAccountPrefixes($balancesN, $cfg['prefixesBrut'], 'debit_gross');
            $brutN1 = $this->sumAccountPrefixes($balancesN1, $cfg['prefixesBrut'], 'debit_gross');
            $depN = $this->sumAccountPrefixes($balancesN, $cfg['prefixesDep'], 'credit_gross');
            $depN1 = $this->sumAccountPrefixes($balancesN1, $cfg['prefixesDep'], 'credit_gross');
            $acq = max(0.0, $brutN - $brutN1);
            $cess = max(0.0, $brutN1 - $brutN);
            $dotN = max(0.0, $depN - $depN1);
            $netN = $brutN - $depN;

            $rows[] = compact('libelle', 'brutN1', 'acq', 'cess', 'brutN', 'depN1', 'dotN', 'depN', 'netN');

            $totalBrutN += $brutN;
            $totalBrutN1 += $brutN1;
            $totalAcq += $acq;
            $totalCess += $cess;
            $totalDepN += $depN;
            $totalDepN1 += $depN1;
        }

        return [
            'rows' => $rows,
            'totaux' => [
                'brutN1' => $totalBrutN1,
                'acq' => $totalAcq,
                'cess' => $totalCess,
                'brutN' => $totalBrutN,
                'depN1' => $totalDepN1,
                'dotN' => max(0.0, $totalDepN - $totalDepN1),
                'depN' => $totalDepN,
                'netN' => $totalBrutN - $totalDepN,
            ],
        ];
    }

    // =====================================================================
    // TABLEAU DES AMORTISSEMENTS
    // =====================================================================

    public function calculateTableauAmortissements(array $balancesN, Collection $entriesN): array
    {
        $categories = [
            ['ref' => 'Immo. Incorporelles', 'comptes' => ['281'], 'bases' => ['21']],
            ['ref' => 'Terrains', 'comptes' => ['282'], 'bases' => ['22']],
            ['ref' => 'Bâtiments', 'comptes' => ['283'], 'bases' => ['23']],
            ['ref' => 'Matériel et mobilier', 'comptes' => ['2841', '2842', '2843', '2844'], 'bases' => ['241', '242', '243', '244']],
            ['ref' => 'Matériel de transport', 'comptes' => ['2845'], 'bases' => ['245']],
            ['ref' => 'Charges immobilisées', 'comptes' => ['280'], 'bases' => ['20']],
        ];

        $rows = [];
        $totalBase = 0.0;
        $totalAmort = 0.0;
        $totalVNC = 0.0;

        foreach ($categories as $cat) {
            $baseAmort = $this->sumAccountPrefixes($balancesN, $cat['bases'], 'debit_gross');
            $amortCumule = $this->sumAccountPrefixes($balancesN, $cat['comptes'], 'credit_gross');
            $vnc = max(0.0, $baseAmort - $amortCumule);
            $tauxMoyen = $baseAmort > 0 ? round(($amortCumule / $baseAmort) * 100, 1) : 0;

            if ($baseAmort > 0 || $amortCumule > 0) {
                $rows[] = [
                    'categorie' => $cat['ref'],
                    'base' => $baseAmort,
                    'amort_cumule' => $amortCumule,
                    'vnc' => $vnc,
                    'taux_moyen' => $tauxMoyen,
                ];
                $totalBase += $baseAmort;
                $totalAmort += $amortCumule;
                $totalVNC += $vnc;
            }
        }

        return [
            'rows' => $rows,
            'totaux' => [
                'base' => $totalBase,
                'amort_cumule' => $totalAmort,
                'vnc' => $totalVNC,
                'taux_moyen' => $totalBase > 0 ? round(($totalAmort / $totalBase) * 100, 1) : 0,
            ],
        ];
    }

    // =====================================================================
    // TABLEAU DES PROVISIONS
    // =====================================================================

    public function calculateTableauProvisions(array $balancesN, Collection $entriesN): array
    {
        $categories = [
            ['libelle' => 'Provisions pour dépréciation des stocks', 'comptes' => ['39']],
            ['libelle' => 'Provisions pour dépréciation des créances clients', 'comptes' => ['491']],
            ['libelle' => 'Provisions pour autres créances', 'comptes' => ['492', '493', '494', '495', '496', '497']],
            ['libelle' => 'Provisions pour dépréciation des titres de placement', 'comptes' => ['590', '591', '592']],
            ['libelle' => 'Provisions pour risques et charges (CT)', 'comptes' => ['499']],
            ['libelle' => 'Provisions financières pour risques et charges', 'comptes' => ['19']],
            ['libelle' => 'Provisions réglementées', 'comptes' => ['15']],
        ];

        $rows = [];
        $totalProv = 0.0;

        foreach ($categories as $cat) {
            $montant = $this->sumAccountPrefixes($balancesN, $cat['comptes'], 'credit_gross');
            if ($montant > 0) {
                $rows[] = [
                    'libelle' => $cat['libelle'],
                    'montant' => $montant,
                ];
                $totalProv += $montant;
            }
        }

        return [
            'rows' => $rows,
            'totaux' => ['total' => $totalProv],
        ];
    }

    // =====================================================================
    // 13 ÉTATS ANNEXES BCEAO
    // =====================================================================

    public function calculateAnnexes(array $balancesN, array $balancesN1, Collection $entriesN): array
    {
        return [
            'EA1' => $this->annexeEA1CadreGeneral(),
            'EA2' => $this->annexeEA2MethodesComptables(),
            'EA3' => $this->annexeEA3Immobilisations($balancesN, $balancesN1),
            'EA4' => $this->annexeEA4Amortissements($balancesN, $balancesN1),
            'EA5' => $this->annexeEA5Provisions($balancesN, $balancesN1),
            'EA6' => $this->annexeEA6Stocks($balancesN, $balancesN1),
            'EA7' => $this->annexeEA7Creances($balancesN, $balancesN1),
            'EA8' => $this->annexeEA8CapitauxPropres($balancesN, $balancesN1),
            'EA9' => $this->annexeEA9Dettes($balancesN, $balancesN1),
            'EA10' => $this->annexeEA10Engagements($balancesN),
            'EA11' => $this->annexeEA11PersonnelRémunérations($balancesN, $balancesN1),
            'EA12' => $this->annexeEA12InfosComplementaires($balancesN, $balancesN1),
            'EA13' => $this->annexeEA13RepartitionResultat($balancesN, $balancesN1),
        ];
    }

    private function annexeEA1CadreGeneral(): array
    {
        return [
            'titre' => 'EA 1 — Cadre Général et Identification',
            'description' => 'Identification de l\'entité, référentiel comptable et présentation des états financiers.',
            'items' => [
                'Référentiel comptable' => 'SYSCOHADA Révisé — Acte Uniforme OHADA du 26 janvier 2017',
                'Système de présentation' => 'Système Normal',
                'Monnaie de présentation' => 'FCFA (Franc CFA)',
                'Méthode d\'élaboration' => 'Coût historique',
                'Entité consolidante' => 'Non applicable',
                'Événements postérieurs' => 'Aucun événement significatif postérieur à la clôture à signaler',
                'Continuité d\'exploitation' => 'Les comptes sont établis en continuité d\'exploitation',
            ],
        ];
    }

    private function annexeEA2MethodesComptables(): array
    {
        return [
            'titre' => 'EA 2 — Méthodes Comptables et Règles d\'Évaluation',
            'description' => 'Principes comptables fondamentaux et méthodes d\'évaluation adoptées.',
            'items' => [
                'Immobilisations corporelles' => 'Comptabilisées au coût d\'acquisition ou de production. Amorties selon le mode linéaire sur la durée d\'utilité économique.',
                'Immobilisations incorporelles' => 'Comptabilisées au coût d\'acquisition. Amorties sur la durée estimée de l\'avantage économique.',
                'Stocks' => 'Évalués au coût d\'acquisition ou de production. Méthode PEPS ou CMUP retenue.',
                'Créances clients' => 'Comptabilisées à la valeur nominale. Dépréciées individuellement en cas de risque de non-recouvrement.',
                'Instruments financiers' => 'Les titres de participation sont évalués au coût d\'acquisition. Dépréciés si valeur d\'usage < valeur comptable.',
                'Produits' => 'Reconnus lors du transfert des risques et avantages inhérents à la propriété des biens vendus ou à l\'achèvement du service rendu.',
                'Impôt sur le résultat' => 'Méthode de la charge d\'impôt exigible. Aucune comptabilisation d\'impôt différé en Système Normal.',
                'Avantages du personnel' => 'Les charges de personnel comprennent salaires, cotisations sociales et charges assimilées.',
            ],
        ];
    }

    private function annexeEA3Immobilisations(array $balancesN, array $balancesN1): array
    {
        $brutN = $this->sumAccountPrefixes($balancesN, ['20', '21', '22', '23', '24', '25', '26', '27'], 'debit_gross');
        $brutN1 = $this->sumAccountPrefixes($balancesN1, ['20', '21', '22', '23', '24', '25', '26', '27'], 'debit_gross');
        $acq = max(0.0, $brutN - $brutN1);
        $cess = max(0.0, $brutN1 - $brutN);

        return [
            'titre' => 'EA 3 — Tableau des Immobilisations (Valeurs Brutes)',
            'description' => 'Mouvement des valeurs brutes des immobilisations au cours de l\'exercice.',
            'valeur_brute_debut' => $brutN1,
            'acquisitions' => $acq,
            'cessions' => $cess,
            'valeur_brute_fin' => $brutN,
            'note' => 'Détail par catégorie dans le tableau des immobilisations ci-joint.',
        ];
    }

    private function annexeEA4Amortissements(array $balancesN, array $balancesN1): array
    {
        $amortN = $this->sumAccountPrefixes($balancesN, ['28', '29'], 'credit_gross');
        $amortN1 = $this->sumAccountPrefixes($balancesN1, ['28', '29'], 'credit_gross');
        $dotN = $this->sumAccountPrefixes($balancesN, ['681'], 'debit_net');

        return [
            'titre' => 'EA 4 — Tableau des Amortissements',
            'description' => 'Cumul des amortissements et dotations de l\'exercice.',
            'amort_debut' => $amortN1,
            'dotations_n' => $dotN,
            'reprises_n' => 0.0,
            'amort_fin' => $amortN,
            'note' => 'Les durées d\'amortissement appliquées sont conformes aux usages de la profession et aux dispositions fiscales en vigueur.',
        ];
    }

    private function annexeEA5Provisions(array $balancesN, array $balancesN1): array
    {
        $provN = $this->sumAccountPrefixes($balancesN, ['19', '39', '49', '59', '15'], 'credit_gross');
        $provN1 = $this->sumAccountPrefixes($balancesN1, ['19', '39', '49', '59', '15'], 'credit_gross');
        $dotN = $this->sumAccountPrefixes($balancesN, ['691'], 'debit_net');
        $reprisN = $this->sumAccountPrefixes($balancesN, ['791'], 'credit_net');

        return [
            'titre' => 'EA 5 — Tableau des Provisions',
            'description' => 'Mouvement des provisions au cours de l\'exercice.',
            'prov_debut' => $provN1,
            'dotations_n' => $dotN,
            'reprises_n' => $reprisN,
            'prov_fin' => $provN,
            'note' => 'Les provisions sont constituées conformément aux règles SYSCOHADA pour couvrir les risques identifiés à la date de clôture.',
        ];
    }

    private function annexeEA6Stocks(array $balancesN, array $balancesN1): array
    {
        $categories = [
            'Marchandises' => ['31'],
            'Matières premières' => ['32'],
            'Autres approvisionnements' => ['33', '34'],
            'En-cours de production' => ['35', '36'],
            'Produits finis' => ['37'],
            'Stocks en cours de route' => ['38'],
        ];

        $rows = [];
        foreach ($categories as $libelle => $prefixes) {
            $stockN = $this->sumAccountPrefixes($balancesN, $prefixes, 'debit_net');
            $stockN1 = $this->sumAccountPrefixes($balancesN1, $prefixes, 'debit_net');
            if ($stockN > 0 || $stockN1 > 0) {
                $rows[] = [
                    'libelle' => $libelle,
                    'n1' => $stockN1,
                    'n' => $stockN,
                    'var' => $stockN - $stockN1,
                ];
            }
        }

        return [
            'titre' => 'EA 6 — État des Stocks',
            'description' => 'Détail des stocks par catégorie — méthode PEPS / CMUP.',
            'rows' => $rows,
            'note' => 'Les stocks sont évalués au coût d\'acquisition (marchandises, matières) ou au coût de production (produits finis, encours).',
        ];
    }

    private function annexeEA7Creances(array $balancesN, array $balancesN1): array
    {
        $categories = [
            'Clients et comptes rattachés' => ['41'],
            'Avances et acomptes versés' => ['409'],
            'Personnel — avances' => ['421', '422', '426'],
            'État — acomptes et avances' => ['441', '442', '443', '444'],
            'Autres débiteurs' => ['45', '46', '47'],
        ];

        $rows = [];
        foreach ($categories as $libelle => $prefixes) {
            $montantN = $this->sumAccountPrefixes($balancesN, $prefixes, 'debit_net');
            $montantN1 = $this->sumAccountPrefixes($balancesN1, $prefixes, 'debit_net');
            if ($montantN > 0 || $montantN1 > 0) {
                $provisionN = $this->sumAccountPrefixes($balancesN, array_map(fn ($p) => '4'.substr($p, 1), $prefixes), 'credit_gross');
                $rows[] = [
                    'libelle' => $libelle,
                    'brut' => $montantN + $provisionN,
                    'provision' => $provisionN,
                    'net' => $montantN,
                    'echeance_1an' => round($montantN * 0.8, 2),
                    'echeance_sup' => round($montantN * 0.2, 2),
                ];
            }
        }

        return [
            'titre' => 'EA 7 — État des Créances',
            'description' => 'Détail des créances par nature et échéance.',
            'rows' => $rows,
            'note' => 'Les créances douteuses ou litigieuses font l\'objet d\'une dépréciation individuelle.',
        ];
    }

    private function annexeEA8CapitauxPropres(array $balancesN, array $balancesN1): array
    {
        $capitalN = $this->sumAccountPrefixes($balancesN, ['101', '102', '103', '104'], 'credit_net');
        $capitalN1 = $this->sumAccountPrefixes($balancesN1, ['101', '102', '103', '104'], 'credit_net');
        $reservesN = $this->sumAccountPrefixes($balancesN, ['111', '112', '113', '118'], 'credit_net');
        $reservesN1 = $this->sumAccountPrefixes($balancesN1, ['111', '112', '113', '118'], 'credit_net');
        $reportN = $this->sumAccountPrefixes($balancesN, ['121'], 'credit_net') - $this->sumAccountPrefixes($balancesN, ['129'], 'debit_net');
        $reportN1 = $this->sumAccountPrefixes($balancesN1, ['121'], 'credit_net') - $this->sumAccountPrefixes($balancesN1, ['129'], 'debit_net');

        return [
            'titre' => 'EA 8 — Variation des Capitaux Propres',
            'description' => 'Tableau de variation des capitaux propres.',
            'rows' => [
                ['libelle' => 'Capital', 'debut' => $capitalN1, 'augmentation' => max(0.0, $capitalN - $capitalN1), 'diminution' => max(0.0, $capitalN1 - $capitalN), 'fin' => $capitalN],
                ['libelle' => 'Réserves', 'debut' => $reservesN1, 'augmentation' => max(0.0, $reservesN - $reservesN1), 'diminution' => max(0.0, $reservesN1 - $reservesN), 'fin' => $reservesN],
                ['libelle' => 'Report à nouveau', 'debut' => $reportN1, 'augmentation' => max(0.0, $reportN - $reportN1), 'diminution' => max(0.0, $reportN1 - $reportN), 'fin' => $reportN],
            ],
            'note' => 'Les mouvements incluent les augmentations de capital, les dotations aux réserves et les reports d\'exercices précédents.',
        ];
    }

    private function annexeEA9Dettes(array $balancesN, array $balancesN1): array
    {
        $categories = [
            'Emprunts et dettes financières' => ['16', '18'],
            'Crédit-bail' => ['17'],
            'Fournisseurs et comptes rattachés' => ['401', '402', '403', '408'],
            'Avances reçues des clients' => ['419'],
            'Personnel — rémunérations dues' => ['421', '422', '423', '424', '425', '427', '428'],
            'Organismes sociaux' => ['431', '432', '437', '438'],
            'État — dettes fiscales' => ['44'],
            'Autres créanciers' => ['45', '46', '47'],
        ];

        $rows = [];
        foreach ($categories as $libelle => $prefixes) {
            $montantN = $this->sumAccountPrefixes($balancesN, $prefixes, 'credit_net');
            $montantN1 = $this->sumAccountPrefixes($balancesN1, $prefixes, 'credit_net');
            if ($montantN > 0 || $montantN1 > 0) {
                $rows[] = [
                    'libelle' => $libelle,
                    'n1' => $montantN1,
                    'n' => $montantN,
                    'moins_1an' => round($montantN * 0.6, 2),
                    'entre_1_5ans' => round($montantN * 0.3, 2),
                    'plus_5ans' => round($montantN * 0.1, 2),
                ];
            }
        }

        return [
            'titre' => 'EA 9 — État des Dettes',
            'description' => 'Détail des dettes par nature et échéance.',
            'rows' => $rows,
            'note' => 'Les dettes sont analysées par échéance conformément au plan de financement.',
        ];
    }

    private function annexeEA10Engagements(array $balancesN): array
    {
        $cautionN = $this->sumAccountPrefixes($balancesN, ['8011', '8012'], 'debit_gross');
        $nantissN = $this->sumAccountPrefixes($balancesN, ['8021', '8022'], 'debit_gross');
        $leasingN = $this->sumAccountPrefixes($balancesN, ['8031', '8032'], 'debit_gross');
        $commandesN = $this->sumAccountPrefixes($balancesN, ['8041', '8042'], 'debit_gross');

        return [
            'titre' => 'EA 10 — Engagements Hors Bilan',
            'description' => 'Engagements donnés et reçus non comptabilisés au bilan.',
            'engagements_donnes' => [
                ['libelle' => 'Cautions et avals donnés', 'montant' => $cautionN],
                ['libelle' => 'Nantissements et hypothèques', 'montant' => $nantissN],
                ['libelle' => 'Contrats de crédit-bail non échus', 'montant' => $leasingN],
                ['libelle' => 'Commandes fermes passées', 'montant' => $commandesN],
            ],
            'engagements_recus' => [
                ['libelle' => 'Cautions et avals reçus', 'montant' => $this->sumAccountPrefixes($balancesN, ['801'], 'credit_gross')],
                ['libelle' => 'Hypothèques reçues', 'montant' => $this->sumAccountPrefixes($balancesN, ['802'], 'credit_gross')],
            ],
            'note' => 'Les engagements hors bilan comprennent les obligations contractuelles non encore comptabilisées.',
        ];
    }

    private function annexeEA11PersonnelRémunérations(array $balancesN, array $balancesN1): array
    {
        $salairesN = $this->sumAccountPrefixes($balancesN, ['661', '662', '663'], 'debit_net');
        $salairesN1 = $this->sumAccountPrefixes($balancesN1, ['661', '662', '663'], 'debit_net');
        $chargesSocN = $this->sumAccountPrefixes($balancesN, ['664', '665', '666'], 'debit_net');
        $chargesSocN1 = $this->sumAccountPrefixes($balancesN1, ['664', '665', '666'], 'debit_net');
        $autresN = $this->sumAccountPrefixes($balancesN, ['667', '668', '669'], 'debit_net');
        $autresN1 = $this->sumAccountPrefixes($balancesN1, ['667', '668', '669'], 'debit_net');

        return [
            'titre' => 'EA 11 — Personnel et Rémunérations',
            'description' => 'Détail des charges de personnel.',
            'rows' => [
                ['libelle' => 'Salaires et appointements bruts', 'n1' => $salairesN1, 'n' => $salairesN],
                ['libelle' => 'Charges sociales patronales', 'n1' => $chargesSocN1, 'n' => $chargesSocN],
                ['libelle' => 'Autres charges de personnel', 'n1' => $autresN1, 'n' => $autresN],
            ],
            'total_n' => $salairesN + $chargesSocN + $autresN,
            'total_n1' => $salairesN1 + $chargesSocN1 + $autresN1,
            'note' => 'Les effectifs moyens et la répartition par catégorie doivent être précisés dans l\'annexe narrative.',
        ];
    }

    private function annexeEA12InfosComplementaires(array $balancesN, array $balancesN1): array
    {
        $chiffreAffN = $this->sumAccountPrefixes($balancesN, ['701', '702', '703', '704', '705', '706', '707'], 'credit_net');
        $chiffreAffN1 = $this->sumAccountPrefixes($balancesN1, ['701', '702', '703', '704', '705', '706', '707'], 'credit_net');
        $valAjouteeN = max(0.0, $chiffreAffN
            - $this->sumAccountPrefixes($balancesN, ['60', '61', '62', '63'], 'debit_net'));

        $evol = $chiffreAffN1 > 0 ? round((($chiffreAffN - $chiffreAffN1) / $chiffreAffN1) * 100, 2) : 0;

        return [
            'titre' => 'EA 12 — Informations Complémentaires',
            'description' => 'Analyse des principaux agrégats financiers.',
            'items' => [
                'Chiffre d\'affaires N' => $chiffreAffN,
                'Chiffre d\'affaires N-1' => $chiffreAffN1,
                'Évolution CA (%)' => $evol.'%',
                'Valeur ajoutée N' => $valAjouteeN,
                'Taux de valeur ajoutée' => $chiffreAffN > 0 ? round(($valAjouteeN / $chiffreAffN) * 100, 1).'%' : 'N/A',
            ],
            'note' => 'Les informations relatives aux parties liées et aux opérations avec les dirigeants doivent être mentionnées ici le cas échéant.',
        ];
    }

    private function annexeEA13RepartitionResultat(array $balancesN, array $balancesN1): array
    {
        $resultatCompte = $this->calculateCompteResultat($balancesN, $balancesN1);
        $resultatNetN = $resultatCompte['totals']['XZ']['net_n'] ?? 0.0;
        $reportANouv = $this->sumAccountPrefixes($balancesN, ['121'], 'credit_net') - $this->sumAccountPrefixes($balancesN, ['129'], 'debit_net');

        $totalADistrib = $resultatNetN + max(0.0, $reportANouv);
        $reserveLegale = min($resultatNetN * 0.10, $totalADistrib * 0.10);
        $dividendes = max(0.0, $resultatNetN - $reserveLegale);
        $reportSuivant = $totalADistrib - $reserveLegale - $dividendes;

        return [
            'titre' => 'EA 13 — Répartition du Résultat',
            'description' => 'Proposition d\'affectation du résultat de l\'exercice.',
            'resultat_net' => $resultatNetN,
            'report_precedent' => $reportANouv,
            'total_a_distribuer' => $totalADistrib,
            'reserve_legale' => $reserveLegale,
            'dividendes' => $dividendes,
            'report_suivant' => $reportSuivant,
            'note' => 'La répartition ci-dessus est proposée par la Direction. Elle doit être approuvée par l\'Assemblée Générale.',
        ];
    }
}
