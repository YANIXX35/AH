<?php

namespace App\Services;

use App\Contracts\FinancialRatioServiceContract;
use App\Models\AccountingEntry;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Ratios financiers PME à partir des écritures (plan OHADA simplifié) et de la trésorerie.
 * Les commentaires d’interprétation sont volontairement prudents (outil d’aide à la décision).
 */
class SmeFinancialRatioService implements FinancialRatioServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function analyze(int $userId, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $query = AccountingEntry::query()->where('user_id', $userId);
        if ($dateFrom !== null) {
            $query->whereDate('date', '>=', $dateFrom->toDateString());
        }
        if ($dateTo !== null) {
            $query->whereDate('date', '<=', $dateTo->toDateString());
        }

        $entries = $query->orderBy('date')->get();
        $summary = $this->summarizeEntriesForUser($entries);

        $treasuryBalance = $this->treasuryNetBalance($userId, $dateFrom, $dateTo);

        $assets = (float) $summary['assets'];
        $liabilities = (float) $summary['liabilities'];
        $revenue = (float) $summary['income'];
        $charges = (float) $summary['expenses'];
        $netResult = $revenue - $charges;
        // Capitaux propres estimés = actif − passif agrégés (peut être négatif : ne pas masquer pour la lecture expert).
        $equity = $assets - $liabilities;

        $roa = $assets > 0 ? ($netResult / $assets) * 100 : null;
        $roe = $equity > 0.01 ? ($netResult / $equity) * 100 : null;
        $margeNette = $revenue > 0 ? ($netResult / $revenue) * 100 : null;

        $levier = $equity > 0.01 ? $liabilities / $equity : null;
        $endettementActif = $assets > 0 ? ($liabilities / $assets) * 100 : null;

        $liquiditeTresorerie = $liabilities > 0 ? $treasuryBalance / $liabilities : null;
        $ratioLiquiditeGenerale = $liabilities > 0 ? $assets / $liabilities : null;

        $rotationActif = $assets > 0 ? $revenue / $assets : null;

        $creances = (float) $summary['receivables_net'];
        $delaiCreancesJours = $revenue > 0 ? ($creances / $revenue) * 365 : null;

        $verdicts = $this->buildVerdicts(
            $entries->count(),
            $netResult,
            $roa,
            $margeNette,
            $equity,
            $endettementActif,
            $ratioLiquiditeGenerale,
            $levier
        );

        $qualiteDonnees = $this->buildQualiteDonneesAlerts(
            $entries->count(),
            $revenue,
            $charges,
            $assets,
            $liabilities,
            $netResult
        );

        $scores = $this->buildScores(
            $entries->count(),
            $netResult,
            $revenue,
            $roa,
            $roe,
            $margeNette,
            $equity,
            $endettementActif,
            $ratioLiquiditeGenerale,
            $levier,
            $qualiteDonnees
        );

        $result = [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'entries_count' => $entries->count(),
            'verdicts' => $verdicts,
            'scores' => $scores,
            'qualite_donnees' => $qualiteDonnees,
            'modele_financier' => $this->describeModeleFinancierReference(),
            'base' => [
                'chiffre_affaires_ht' => $revenue,
                'charges' => $charges,
                'resultat_net' => $netResult,
                'total_actif' => $assets,
                'total_passif' => $liabilities,
                'capitaux_propres_estimes' => $equity,
                'creances_nettes' => $creances,
                'tresorerie_comptable_classe5' => (float) $summary['cash_ledger'],
                'solde_tresorerie_effectue' => $treasuryBalance,
            ],
            'ratios' => [
                'roa_pct' => $roa !== null ? round($roa, 2) : null,
                'roe_pct' => $roe !== null ? round($roe, 2) : null,
                'marge_nette_pct' => $margeNette !== null ? round($margeNette, 2) : null,
                'endettement_sur_actif_pct' => $endettementActif !== null ? round($endettementActif, 2) : null,
                'dettes_sur_capitaux_propres' => $levier !== null ? round($levier, 3) : null,
                'liquidite_generale' => $ratioLiquiditeGenerale !== null ? round($ratioLiquiditeGenerale, 3) : null,
                'couverture_tresorerie_passif' => $liquiditeTresorerie !== null ? round($liquiditeTresorerie, 3) : null,
                'rotation_actif' => $rotationActif !== null ? round($rotationActif, 3) : null,
                'delai_creances_jours' => $delaiCreancesJours !== null ? round($delaiCreancesJours, 0) : null,
            ],
            'interpretation' => $this->buildInterpretation(
                $roa,
                $roe,
                $margeNette,
                $endettementActif,
                $levier,
                $ratioLiquiditeGenerale,
                $liquiditeTresorerie,
                $rotationActif,
                $delaiCreancesJours,
                $entries->count(),
                $assets,
                $liabilities
            ),
        ];

        $result['classement'] = $this->evaluateClassementFinancier($result);

        return $result;
    }

    /**
     * Classement automatique solvable / finançable (règles indicatives, même logique que scores & verdicts).
     *
     * @param  array<string, mixed>  $analysis
     * @return array<string, mixed>
     */
    public function evaluateClassementFinancier(array $analysis): array
    {
        $entries = (int) ($analysis['entries_count'] ?? 0);
        $verdicts = $analysis['verdicts'] ?? [];
        $scores = $analysis['scores'] ?? [];

        $solvNv = $verdicts['solvabilite']['niveau'] ?? 'secondary';
        $rentNv = $verdicts['rentabilite']['niveau'] ?? 'secondary';

        $sSolv = isset($scores['solvabilite']['valeur']) ? (float) $scores['solvabilite']['valeur'] : null;
        $sRent = isset($scores['rentabilite']['valeur']) ? (float) $scores['rentabilite']['valeur'] : null;
        $fiab = isset($scores['fiabilite_donnees_pct']) ? (float) $scores['fiabilite_donnees_pct'] : null;
        $synF = isset($scores['global']['valeur_fiabilisee']) ? (float) $scores['global']['valeur_fiabilisee'] : null;

        $motifs = [];

        if ($entries === 0 || $solvNv === 'secondary' || $rentNv === 'secondary') {
            return [
                'code' => 'insuffisant',
                'solvable' => false,
                'financable' => false,
                'libelle' => 'Non classé — données insuffisantes',
                'motifs' => ['Pas d’écriture exploitable sur la période ou verdicts non calculables.'],
                'score_tri' => 0.0,
            ];
        }

        // Solvable : structure de bilan / levier compatibles (verdict ou score de solvabilité).
        $solvable = false;
        if ($solvNv === 'danger') {
            $solvable = false;
            $motifs[] = 'Verdict solvabilité défavorable (structure ou endettement).';
        } elseif ($solvNv === 'success' && $entries >= 5) {
            $solvable = true;
            $motifs[] = 'Solvabilité : synthèse favorable.';
        } elseif ($entries >= 5 && $sSolv !== null && $sSolv >= 54.0 && $solvNv !== 'danger') {
            $solvable = true;
            $motifs[] = 'Solvabilité : score ≥ 54/100 avec historique minimal.';
        } elseif ($entries < 5) {
            $motifs[] = 'Moins de 5 écritures : solvabilité non retenue pour classement.';
        } else {
            $motifs[] = 'Solvabilité : score ou verdict insuffisant.';
        }

        // Finançable : solvable + rentabilité + fiabilité des données (prêt à l’analyse crédit interne).
        $financable = false;
        if ($solvable) {
            if ($rentNv === 'danger') {
                $motifs[] = 'Rentabilité : verdict défavorable — non retenu pour financement automatique.';
            } elseif ($entries < 15) {
                $motifs[] = 'Moins de 15 écritures : profil non retenu pour « finançable » (seuil prudence).';
            } elseif ($fiab !== null && $fiab < 52.0) {
                $motifs[] = 'Indice de fiabilité des données < 52 % — profil non retenu pour financement.';
            } elseif ($synF !== null && $synF < 48.0) {
                $motifs[] = 'Synthèse fiabilisée < 48 — profil non retenu pour financement.';
            } elseif ($rentNv === 'success' || ($sRent !== null && $sRent >= 56.0)) {
                $financable = true;
                $motifs[] = 'Rentabilité favorable et critères de fiabilité atteints.';
            } else {
                $motifs[] = 'Rentabilité correcte mais hors seuil « finançable » (score ou verdict).';
            }
        }

        if ($financable) {
            $code = 'financable';
            $libelle = 'Finançable (automatique)';
        } elseif ($solvable) {
            $code = 'solvable_seulement';
            $libelle = 'Solvable seulement';
        } else {
            $code = 'non_retenu';
            $libelle = 'Non retenu';
        }

        $scoreTri = $synF ?? ($sSolv !== null && $sRent !== null ? ($sSolv + $sRent) / 2.0 : 0.0);

        return [
            'code' => $code,
            'solvable' => $solvable,
            'financable' => $financable,
            'libelle' => $libelle,
            'motifs' => array_values(array_unique($motifs)),
            'score_tri' => round((float) $scoreTri, 1),
        ];
    }

    /**
     * Liste des entreprises clientes classées pour la période (hors admins plateforme).
     *
     * @return list<array{ user: User, classement: array<string, mixed>, synthese_fiabilisee: ?float, entries_count: int }>
     */
    public function classementPlateforme(?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $users = User::query()
            ->where(function ($q) {
                $q->where('is_platform_admin', false)->orWhereNull('is_platform_admin');
            })
            ->where(function ($q) {
                $q->where('is_accountant', false)->orWhereNull('is_accountant');
            })
            ->where(function ($q) {
                $q->where('account_suspended', false)->orWhereNull('account_suspended');
            })
            ->orderBy('company_name')
            ->orderBy('name')
            ->get();

        $lignes = [];
        foreach ($users as $user) {
            $analysis = $this->analyze($user->id, $dateFrom, $dateTo);
            $classement = $analysis['classement'] ?? [];
            $lignes[] = [
                'user' => $user,
                'classement' => $classement,
                'synthese_fiabilisee' => Arr::get($analysis, 'scores.global.valeur_fiabilisee'),
                'entries_count' => (int) ($analysis['entries_count'] ?? 0),
            ];
        }

        $prio = ['financable' => 4, 'solvable_seulement' => 3, 'non_retenu' => 2, 'insuffisant' => 1];
        usort($lignes, function (array $a, array $b) use ($prio) {
            $ca = $a['classement']['code'] ?? 'insuffisant';
            $cb = $b['classement']['code'] ?? 'insuffisant';
            $pa = $prio[$ca] ?? 0;
            $pb = $prio[$cb] ?? 0;
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            $sa = $a['synthese_fiabilisee'];
            $sb = $b['synthese_fiabilisee'];
            if ($sa !== null && $sb !== null) {
                return $sb <=> $sa;
            }

            return ($b['entries_count'] ?? 0) <=> ($a['entries_count'] ?? 0);
        });

        return $lignes;
    }

    /**
     * @return array<string, float|int|array>
     */
    private function summarizeEntriesForUser(Collection $entries): array
    {
        $ledger = [];
        foreach ($entries as $entry) {
            foreach (['debit_account', 'credit_account'] as $side) {
                $account = $this->extractAccountCode((string) $entry->{$side});
                if ($account === null) {
                    continue;
                }
                if (! isset($ledger[$account])) {
                    $ledger[$account] = ['debit' => 0.0, 'credit' => 0.0];
                }
                if ($side === 'debit_account') {
                    $ledger[$account]['debit'] += (float) $entry->amount;
                } else {
                    $ledger[$account]['credit'] += (float) $entry->amount;
                }
            }
        }

        $rowsByCode = [];
        foreach ($ledger as $code => $amounts) {
            $debit = (float) ($amounts['debit'] ?? 0.0);
            $credit = (float) ($amounts['credit'] ?? 0.0);
            $rowsByCode[$code] = [
                'debit' => $debit,
                'credit' => $credit,
                'debit_net' => max($debit - $credit, 0.0),
                'credit_net' => max($credit - $debit, 0.0),
            ];
        }

        $sumByPrefixes = function (array $prefixes, string $column = 'credit_net') use ($rowsByCode): float {
            $total = 0.0;
            foreach ($rowsByCode as $code => $row) {
                foreach ($prefixes as $prefix) {
                    $prefix = (string) $prefix;
                    if ($prefix !== '' && str_starts_with((string) $code, $prefix)) {
                        $total += (float) ($row[$column] ?? 0.0);
                        break;
                    }
                }
            }

            return $total;
        };

        $income = $sumByPrefixes(['7'], 'credit_net');
        $expenses = $sumByPrefixes(['6'], 'debit_net');
        $netResult = $income - $expenses;

        // Capitaux propres (référentiel SYSCOHADA aligné sur les masses du bilan).
        $capital = $sumByPrefixes(['101'], 'credit_net');
        $primesReserves = $sumByPrefixes(['104', '105', '106', '107'], 'credit_net');
        $subventionsInvestissement = $sumByPrefixes(['131'], 'credit_net');
        $provisionsAssimilees = $sumByPrefixes(['141', '142', '143', '144', '145', '146', '147', '148'], 'credit_net');
        $equity = $capital + $primesReserves + $subventionsInvestissement + $provisionsAssimilees + $netResult;

        // Dettes et passifs exigibles (hors capitaux propres).
        $dettesFinancieres = $sumByPrefixes(['161', '162', '163'], 'credit_net');
        $dettesCreditBail = $sumByPrefixes(['164'], 'credit_net');
        $dettesFinDiverses = $sumByPrefixes(['109', '165', '166'], 'credit_net');
        $provisionsFinancieres = $sumByPrefixes(['19'], 'credit_net');
        $fournisseursExploit = $sumByPrefixes(['401', '403', '408'], 'credit_net');
        $dettesFiscales = $sumByPrefixes(['441', '442', '443', '444', '445', '447'], 'credit_net');
        $dettesSociales = $sumByPrefixes(['428', '431'], 'credit_net');
        $autresDettes = $sumByPrefixes(['421', '451', '455', '467'], 'credit_net');
        $risquesProvisionnes = $sumByPrefixes(['19', '49'], 'credit_net');
        $tresoreriePassif = $sumByPrefixes(['512', '514', '515', '521', '531', '541', '542', '566', '581'], 'credit_net');

        $liabilities = $dettesFinancieres
            + $dettesCreditBail
            + $dettesFinDiverses
            + $provisionsFinancieres
            + $fournisseursExploit
            + $dettesFiscales
            + $dettesSociales
            + $autresDettes
            + $risquesProvisionnes
            + $tresoreriePassif;

        // Actif aligné bilan : immobilisations + circulant + trésorerie.
        $chargesImmob = max($sumByPrefixes(['201'], 'debit_net') - $sumByPrefixes(['291'], 'credit_net'), 0.0);
        $primesRemboursement = max($sumByPrefixes(['206'], 'debit_net'), 0.0);
        $actifIncorporel = max(
            $sumByPrefixes(['203', '205', '207', '208'], 'debit_net') - $sumByPrefixes(['2811', '291'], 'credit_net'),
            0.0
        );
        $actifCorporel = max(
            $sumByPrefixes(['211', '212', '213', '221', '215', '241', '244', '231'], 'debit_net') - $sumByPrefixes(['2812', '2813', '2814', '2815', '2818', '292'], 'credit_net'),
            0.0
        );
        $actifFinancier = max($sumByPrefixes(['261', '271', '275'], 'debit_net'), 0.0);

        $immobilisationsBrutes = $chargesImmob + $primesRemboursement + $actifIncorporel + $actifCorporel + $actifFinancier;

        $stocksActif = max(
            $sumByPrefixes(['311', '321', '322', '331', '335', '341', '351', '355'], 'debit_net') - $sumByPrefixes(['391', '392'], 'credit_net'),
            0.0
        );
        $receivablesNet = max(
            $sumByPrefixes(['401', '403', '411', '413', '418', '421', '425', '428', '431', '438', '441', '442', '443', '444', '445', '447', '451', '455', '467'], 'debit_net'),
            0.0
        );
        $tresorerieActif = max($sumByPrefixes(['512', '514', '515', '521', '531', '541', '542', '566', '581'], 'debit_net'), 0.0);

        $assets = $immobilisationsBrutes + $stocksActif + $receivablesNet + $tresorerieActif;
        $cashLedger = $tresorerieActif - $tresoreriePassif;

        return [
            'assets' => max($assets, 0),
            'liabilities' => max($liabilities, 0),
            'income' => max($income, 0),
            'expenses' => max($expenses, 0),
            'receivables_net' => max($receivablesNet, 0),
            'cash_ledger' => $cashLedger,
        ];
    }

    private function extractAccountCode(string $account): ?string
    {
        $account = trim($account);
        if ($account === '') {
            return null;
        }

        if (preg_match('/^(\d{2,6})/', $account, $m)) {
            return $m[1];
        }

        return null;
    }

    private function treasuryNetBalance(int $userId, ?Carbon $dateFrom, ?Carbon $dateTo): float
    {
        $q = TreasuryTransaction::query()
            ->where('user_id', $userId)
            ->where('status', 'effectue');

        if ($dateFrom !== null) {
            $q->whereDate('transaction_date', '>=', $dateFrom->toDateString());
        }
        if ($dateTo !== null) {
            $q->whereDate('transaction_date', '<=', $dateTo->toDateString());
        }

        $enc = (float) (clone $q)->where('type', 'encaissement')->sum('amount');
        $dec = (float) (clone $q)->where('type', 'decaissement')->sum('amount');

        return $enc - $dec;
    }

    private function accountPrefix(string $account): ?string
    {
        if (preg_match('/^(\d)/', trim($account), $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * @return list<array{ titre: string, niveau: string, texte: string }>
     */
    private function buildInterpretation(
        ?float $roa,
        ?float $roe,
        ?float $margeNette,
        ?float $endettementActif,
        ?float $levier,
        ?float $ratioLg,
        ?float $liqTreso,
        ?float $rotationActif,
        ?float $delaiCreances,
        int $entriesCount,
        float $assets,
        float $liabilities
    ): array {
        $out = [];

        if ($entriesCount === 0) {
            $out[] = [
                'titre' => 'Données insuffisantes',
                'niveau' => 'warning',
                'texte' => 'Aucune écriture sur la période : les ratios ne sont pas calculables. Importez ou saisissez des pièces pour alimenter l’analyse.',
            ];

            return $out;
        }

        if ($assets < 1 && $liabilities < 1) {
            $out[] = [
                'titre' => 'Bilan fonctionnel incomplet',
                'niveau' => 'info',
                'texte' => 'Les soldes d’actif et de passif issus du grand livre sont faibles ou nuls : le plan comptable doit refléter les comptes de bilan (classes 1 à 5) pour fiabiliser ROA, ROE et la liquidité.',
            ];
        }

        if ($roa !== null) {
            $niveau = $roa >= 5 ? 'success' : ($roa >= 0 ? 'warning' : 'danger');
            $out[] = [
                'titre' => 'ROA (rentabilité économique)',
                'niveau' => $niveau,
                'texte' => $roa >= 5
                    ? 'Le résultat rapporté à l’actif est satisfaisant pour une PME ; poursuivez le pilotage des marges et de l’effet levier opérationnel.'
                    : ($roa >= 0
                        ? 'Rentabilité des actifs modérée : vérifier la charge d’actifs improductifs et la marge.'
                        : 'Résultat négatif par rapport à l’actif : priorité à la remise à niveau du compte de résultat et à la trésorerie.'),
            ];
        }

        if ($roe !== null) {
            $niveau = $roe >= 10 ? 'success' : ($roe >= 0 ? 'warning' : 'danger');
            $out[] = [
                'titre' => 'ROE (rentabilité des capitaux propres)',
                'niveau' => $niveau,
                'texte' => 'Mesure le retour pour l’actionnariat (capitaux propres estimés par actif − passif). Un ROE durablement négatif appelle un réexamen de la structure financière.',
            ];
        }

        if ($margeNette !== null) {
            $out[] = [
                'titre' => 'Marge nette',
                'niveau' => $margeNette >= 5 ? 'success' : 'info',
                'texte' => 'Rapport résultat net / chiffre d’affaires (classe 7). Comparez à votre secteur ; une marge faible peut être compensée par un fort volume.',
            ];
        }

        if ($endettementActif !== null) {
            $niveau = $endettementActif <= 60 ? 'success' : ($endettementActif <= 85 ? 'warning' : 'danger');
            $out[] = [
                'titre' => 'Solvabilité (endettement / actif)',
                'niveau' => $niveau,
                'texte' => 'Plus le ratio est élevé, plus le passif finance l’actif. Au-delà de 70–80 %, surveillez la capacité de remboursement et les engagements bancaires.',
            ];
        }

        if ($levier !== null) {
            $out[] = [
                'titre' => 'Levier financier',
                'niveau' => $levier <= 2 ? 'success' : ($levier <= 3 ? 'warning' : 'danger'),
                'texte' => 'Dettes / capitaux propres estimés. Un levier élevé amplifie le risque en cas de baisse d’activité.',
            ];
        }

        if ($ratioLg !== null) {
            $niveau = $ratioLg >= 1.5 ? 'success' : ($ratioLg >= 1 ? 'warning' : 'danger');
            $out[] = [
                'titre' => 'Liquidité générale (approximation)',
                'niveau' => $niveau,
                'texte' => 'Actif / passif sur soldes simplifiés OHADA. Un ratio &lt; 1 peut signaler tension de liquidité ; à croiser avec la trésorerie réelle.',
            ];
        }

        if ($liqTreso !== null) {
            $out[] = [
                'titre' => 'Trésorerie / dettes (indicateur)',
                'niveau' => $liqTreso >= 0.2 ? 'success' : 'info',
                'texte' => 'Solde trésorerie effectuée sur la période rapporté au passif comptable. Complétez par un prévisionnel de trésorerie.',
            ];
        }

        if ($rotationActif !== null) {
            $out[] = [
                'titre' => 'Rotation de l’actif',
                'niveau' => 'info',
                'texte' => 'CA / actif : efficacité d’utilisation du bilan pour générer du volume. Varie fortement selon le secteur (services vs industrie).',
            ];
        }

        if ($delaiCreances !== null && $delaiCreances > 0) {
            $out[] = [
                'titre' => 'Délai créances (ordre de grandeur)',
                'niveau' => $delaiCreances <= 60 ? 'success' : ($delaiCreances <= 90 ? 'warning' : 'danger'),
                'texte' => 'Approximation : créances classe 4 / CA × 365. Un délai élevé peut indiquer un besoin en fonds de roulement client à surveiller.',
            ];
        }

        return $out;
    }

    /**
     * Synthèse « rentable / solvable » pour la PME (seuils indicatifs, à affiner selon secteur).
     *
     * @return array{ rentabilite: array<string, string>, solvabilite: array<string, string> }
     */
    private function buildVerdicts(
        int $entriesCount,
        float $netResult,
        ?float $roa,
        ?float $margeNette,
        float $equity,
        ?float $endettementActif,
        ?float $ratioLiquiditeGenerale,
        ?float $levier
    ): array {
        if ($entriesCount === 0) {
            return [
                'rentabilite' => [
                    'label' => 'Non évaluable',
                    'niveau' => 'secondary',
                    'resume' => 'Pas d’écriture sur la période.',
                    'criteres' => '—',
                ],
                'solvabilite' => [
                    'label' => 'Non évaluable',
                    'niveau' => 'secondary',
                    'resume' => 'Pas d’écriture sur la période.',
                    'criteres' => '—',
                ],
            ];
        }

        // --- Rentabilité : le cœur est le résultat net (produits − charges sur la période).
        if ($netResult < 0) {
            $rent = [
                'label' => 'Non rentable (sur la période)',
                'niveau' => 'danger',
                'resume' => 'Le résultat net est négatif : les charges dépassent les produits comptabilisés.',
                'criteres' => 'Résultat net &lt; 0 FCFA.',
            ];
        } elseif ($netResult == 0.0) {
            $rent = [
                'label' => 'À la limite',
                'niveau' => 'warning',
                'resume' => 'Résultat net nul : aucune création de richesse comptable sur la période.',
                'criteres' => 'Résultat net = 0.',
            ];
        } else {
            $roaOk = $roa === null || $roa >= 2.0;
            $margeOk = $margeNette === null || $margeNette >= 3.0;
            if ($roaOk && $margeOk) {
                $rent = [
                    'label' => 'Rentable (synthèse favorable)',
                    'niveau' => 'success',
                    'resume' => 'Résultat net positif, avec ROA et marge nette dans des fourchettes habituellement saines pour une PME (seuils indicatifs).',
                    'criteres' => 'Résultat net &gt; 0 ; ROA ≥ 2 % (si calculable) ; marge nette ≥ 3 % (si CA &gt; 0).',
                ];
            } elseif ($margeNette !== null && $margeNette < 3.0 && $margeNette >= 0) {
                $rent = [
                    'label' => 'Rentable mais fragile',
                    'niveau' => 'warning',
                    'resume' => 'Le résultat est positif mais la marge nette est faible : peu de marge de manœuvre face à un choc des charges ou du CA.',
                    'criteres' => 'Résultat net &gt; 0 mais marge nette &lt; 3 %.',
                ];
            } else {
                $rent = [
                    'label' => 'Rentable (à surveiller)',
                    'niveau' => 'warning',
                    'resume' => 'Résultat net positif ; affiner l’analyse avec le secteur et la qualité du bilan.',
                    'criteres' => 'Résultat net &gt; 0 ; un ou plusieurs ratios (ROA, marge) hors seuils « confort ».',
                ];
            }
        }

        // --- Solvabilité : capacité à honorer les dettes à long terme + structure du bilan.
        if ($equity <= 0.01 && $endettementActif !== null && $endettementActif > 50) {
            $solv = [
                'label' => 'Situation très tendue',
                'niveau' => 'danger',
                'resume' => 'Capitaux propres estimés quasi nuls ou négatifs avec dettes : risque de déséquilibre patrimonial (à confirmer avec un bilan complet).',
                'criteres' => 'Capitaux propres estimés ≤ 0 et endettement élevé.',
            ];
        } elseif ($equity <= 0.01) {
            $solv = [
                'label' => 'Structure à clarifier',
                'niveau' => 'warning',
                'resume' => 'Fonds propres estimés très faibles : la comptabilité doit bien refléter les comptes de capitaux (classe 1) et le passif.',
                'criteres' => 'Capitaux propres estimés ≈ 0.',
            ];
        } else {
            $endettOk = $endettementActif === null || $endettementActif <= 70.0;
            $liqOk = $ratioLiquiditeGenerale === null || $ratioLiquiditeGenerale >= 1.0;
            $levOk = $levier === null || $levier <= 2.5;

            if ($endettOk && $liqOk && $levOk) {
                $solv = [
                    'label' => 'Solvabilité favorable (synthèse)',
                    'niveau' => 'success',
                    'resume' => 'Fonds propres positifs, endettement et levier modérés, liquidité générale compatible avec le service du passif (indicateurs simplifiés).',
                    'criteres' => 'CP estimés &gt; 0 ; dettes/actif ≤ 70 % ; liquidité générale ≥ 1 ; dettes/CP ≤ 2,5.',
                ];
            } elseif (! $endettOk || ($levier !== null && $levier > 3.0)) {
                $solv = [
                    'label' => 'Solvabilité à surveiller',
                    'niveau' => 'warning',
                    'resume' => 'Le poids de la dette ou du levier mérite un suivi : capacité de remboursement à valider avec le prévisionnel et les échéances réelles.',
                    'criteres' => 'Dettes/actif &gt; 70 % et/ou levier &gt; 3.',
                ];
            } else {
                $solv = [
                    'label' => 'Solvabilité correcte sous réserves',
                    'niveau' => 'warning',
                    'resume' => 'Structure acceptable mais un indicateur (liquidité ou levier) sort de la zone « confort ».',
                    'criteres' => 'Voir détail des ratios de solvabilité ci-dessous.',
                ];
            }
        }

        return [
            'rentabilite' => $rent,
            'solvabilite' => $solv,
        ];
    }

    /**
     * Alertes lorsque la saisie ou le périmètre rendent la lecture des ratios peu fiable.
     *
     * @return list<array{ niveau: string, titre: string, texte: string }>
     */
    private function buildQualiteDonneesAlerts(
        int $entriesCount,
        float $revenue,
        float $charges,
        float $assets,
        float $liabilities,
        float $netResult
    ): array {
        $out = [];

        if ($entriesCount === 0) {
            $out[] = [
                'niveau' => 'warning',
                'titre' => 'Aucune écriture sur la période',
                'texte' => 'Les agrégats et ratios sont vides ou non significatifs : importez ou saisissez des pièces comptables pour alimenter l’analyse.',
            ];

            return $out;
        }

        if ($entriesCount < 25) {
            $out[] = [
                'niveau' => 'warning',
                'titre' => 'Volume d’écritures limité',
                'texte' => sprintf(
                    'Seulement %d écriture(s) : les indicateurs restent indicatifs. Pour un pilotage crédible, viser un journal complet sur au moins un exercice ou un trimestre représentatif.',
                    $entriesCount
                ),
            ];
        }

        if ($revenue > 0.0) {
            $tolRn = max(1.0, abs($revenue) * 1e-9);
            $partCharges = $charges / $revenue;
            $rnEgalCa = abs($netResult - $revenue) < $tolRn;
            if ($partCharges < 0.01 || $rnEgalCa) {
                $out[] = [
                    'niveau' => 'warning',
                    'titre' => 'Compte de résultat probablement incomplet',
                    'texte' => 'Les charges (classe 6) sont très faibles ou absentes par rapport au chiffre d’affaires, ou le résultat net coïncide avec le CA : vérifiez achats, salaires, charges sociales, dotations et impôts sur la période.',
                ];
            }
        }

        if ($assets > 1000.0 && $liabilities < 1.0) {
            $out[] = [
                'niveau' => 'info',
                'titre' => 'Passif (dettes) quasi nul — liquidité générale non calculable',
                'texte' => 'Aucune dette fournisseur, emprunt ou autre passif significatif détecté (classes 1 et 4 au crédit selon l’agrégation). Les capitaux propres estimés égagent alors l’actif ; le ratio actif ÷ passif n’a pas de sens mathématique. Croisez avec le cycle d’exploitation et le module Trésorerie.',
            ];
        }

        if ($revenue > 1000.0 && $liabilities < 1.0 && $assets > 1000.0) {
            $ecartRelatif = abs($assets - $revenue) / $revenue;
            $tolRn2 = max(1.0, abs($revenue) * 1e-9);
            if ($ecartRelatif < 0.02 && abs($netResult - $revenue) < $tolRn2) {
                $out[] = [
                    'niveau' => 'info',
                    'titre' => 'Scénario comptable « minimal »',
                    'texte' => 'Les montants clés (CA, résultat, actif, capitaux propres estimés) sont très proches : situation typique d’une saisie partielle (ex. factures clients sans charges ni dettes). La synthèse ne reflète pas une structure financière exhaustive.',
                ];
            }
        }

        return $out;
    }

    /**
     * Scores 0–100 (indicatifs) alignés sur les ratios et une pénalité liée à la qualité des données.
     *
     * @param  list<array{ niveau: string, titre: string, texte: string }>  $qualiteDonnees
     * @return array<string, mixed>
     */
    private function buildScores(
        int $entriesCount,
        float $netResult,
        float $revenue,
        ?float $roa,
        ?float $roe,
        ?float $margeNette,
        float $equity,
        ?float $endettementActif,
        ?float $ratioLiquiditeGenerale,
        ?float $levier,
        array $qualiteDonnees
    ): array {
        if ($entriesCount === 0) {
            return [
                'rentabilite' => null,
                'solvabilite' => null,
                'global' => null,
                'fiabilite_donnees_pct' => null,
                'legende' => 'Scores non calculables sans écriture sur la période.',
            ];
        }

        $rBrut = $this->scoreBrutRentabilite($netResult, $revenue, $roa, $roe, $margeNette);
        $sBrut = $this->scoreBrutSolvabilite($equity, $endettementActif, $levier, $ratioLiquiditeGenerale);
        $fiab = $this->calculerIndiceFiabiliteDonnees($entriesCount, $qualiteDonnees);

        $moyenne = round(($rBrut + $sBrut) / 2.0, 1);
        $fiabPct = $fiab['pourcent'];
        $valeurFiabilisee = round($moyenne * ($fiabPct / 100.0), 1);

        return [
            'rentabilite' => [
                'valeur' => $rBrut,
                'niveau' => $this->niveauScore($rBrut),
                'libelle' => $this->libelleScore($rBrut),
                'detail' => 'Pondération indicative : marge nette, ROA, ROE (si calculables).',
            ],
            'solvabilite' => [
                'valeur' => $sBrut,
                'niveau' => $this->niveauScore($sBrut),
                'libelle' => $this->libelleScore($sBrut),
                'detail' => 'Pondération indicative : endettement/actif, levier, liquidité générale.',
            ],
            'global' => [
                'valeur' => $moyenne,
                'valeur_fiabilisee' => $valeurFiabilisee,
                'niveau' => $this->niveauScore($valeurFiabilisee),
                'libelle' => $this->libelleScore($valeurFiabilisee),
                'detail' => 'Moyenne rentabilité / solvabilité ; la valeur fiabilisée applique l’indice de qualité des données.',
            ],
            'fiabilite_donnees_pct' => $fiabPct,
            'fiabilite_detail' => $fiab['motif'],
        ];
    }

    private function scoreBrutRentabilite(
        float $netResult,
        float $revenue,
        ?float $roa,
        ?float $roe,
        ?float $margeNette
    ): float {
        if ($netResult < 0) {
            $den = max(abs($revenue), 1.0);

            return round(max(5.0, min(38.0, 38.0 + ($netResult / $den) * 28.0)), 1);
        }

        if (abs($netResult) < 1e-6) {
            return 44.0;
        }

        $sm = $this->sousScoreMarge($margeNette, $revenue);
        $sr = $this->sousScoreRoa($roa);
        $se = $this->sousScoreRoe($roe);

        if ($revenue <= 0) {
            return round($sr * 0.55 + $se * 0.45, 1);
        }

        if ($roa === null && $roe === null) {
            return round($sm, 1);
        }

        if ($roa === null) {
            return round($sm * 0.58 + $se * 0.42, 1);
        }

        if ($roe === null) {
            return round($sm * 0.52 + $sr * 0.48, 1);
        }

        return round($sm * 0.38 + $sr * 0.32 + $se * 0.30, 1);
    }

    private function sousScoreMarge(?float $margeNette, float $revenue): float
    {
        if ($revenue <= 0 || $margeNette === null) {
            return 52.0;
        }

        if ($margeNette < 0) {
            return 18.0;
        }

        if ($margeNette < 3.0) {
            return 40.0 + $margeNette * 4.5;
        }

        if ($margeNette < 10.0) {
            return 53.5 + ($margeNette - 3.0) * 5.2;
        }

        if ($margeNette < 25.0) {
            return 90.0 + ($margeNette - 10.0) * 0.55;
        }

        return min(100.0, 98.5 + min(1.5, ($margeNette - 25.0) * 0.02));
    }

    private function sousScoreRoa(?float $roa): float
    {
        if ($roa === null) {
            return 56.0;
        }

        if ($roa < -8.0) {
            return 10.0;
        }

        if ($roa < 0) {
            return 12.0 + ($roa + 8.0) * 4.125;
        }

        if ($roa < 2.0) {
            return 45.0 + $roa * 13.5;
        }

        if ($roa < 8.0) {
            return 72.0 + ($roa - 2.0) * 3.33;
        }

        return min(100.0, 92.0 + min(8.0, ($roa - 8.0) * 0.4));
    }

    private function sousScoreRoe(?float $roe): float
    {
        if ($roe === null) {
            return 56.0;
        }

        if ($roe < -20.0) {
            return 10.0;
        }

        if ($roe < 0) {
            return 15.0 + ($roe + 20.0) * 1.25;
        }

        if ($roe < 10.0) {
            return 48.0 + $roe * 3.5;
        }

        if ($roe < 25.0) {
            return 83.0 + ($roe - 10.0) * 0.85;
        }

        return min(100.0, 95.0 + min(5.0, ($roe - 25.0) * 0.1));
    }

    private function scoreBrutSolvabilite(
        float $equity,
        ?float $endettementActif,
        ?float $levier,
        ?float $ratioLiquiditeGenerale
    ): float {
        if ($equity <= 0.01) {
            if ($endettementActif !== null && $endettementActif > 55.0) {
                return 20.0;
            }

            return 36.0;
        }

        $s1 = $this->sousScoreEndettementActif($endettementActif);
        $s2 = $this->sousScoreLevier($levier);
        $s3 = $this->sousScoreLiquidite($ratioLiquiditeGenerale);

        return round($s1 * 0.36 + $s2 * 0.34 + $s3 * 0.30, 1);
    }

    private function sousScoreEndettementActif(?float $pct): float
    {
        if ($pct === null) {
            return 58.0;
        }

        if ($pct <= 0) {
            return 100.0;
        }

        if ($pct <= 40.0) {
            return 100.0 - $pct * 0.45;
        }

        if ($pct <= 70.0) {
            return 82.0 - ($pct - 40.0) * 1.2;
        }

        if ($pct <= 90.0) {
            return 46.0 - ($pct - 70.0) * 0.85;
        }

        return max(12.0, 29.0 - ($pct - 90.0) * 1.1);
    }

    private function sousScoreLevier(?float $levier): float
    {
        if ($levier === null) {
            return 68.0;
        }

        if ($levier <= 0) {
            return 100.0;
        }

        if ($levier <= 1.5) {
            return 100.0 - $levier * 9.0;
        }

        if ($levier <= 2.5) {
            return 86.5 - ($levier - 1.5) * 15.0;
        }

        if ($levier <= 4.0) {
            return 71.5 - ($levier - 2.5) * 12.0;
        }

        return max(15.0, 53.5 - ($levier - 4.0) * 8.0);
    }

    private function sousScoreLiquidite(?float $ratioLg): float
    {
        if ($ratioLg === null) {
            return 62.0;
        }

        if ($ratioLg < 0.6) {
            return 28.0;
        }

        if ($ratioLg < 1.0) {
            return 28.0 + ($ratioLg - 0.6) * 82.5;
        }

        if ($ratioLg < 1.5) {
            return 61.0 + ($ratioLg - 1.0) * 54.0;
        }

        if ($ratioLg < 2.5) {
            return 88.0 + ($ratioLg - 1.5) * 8.0;
        }

        return min(100.0, 96.0 + min(4.0, ($ratioLg - 2.5) * 2.0));
    }

    /**
     * @param  list<array{ niveau: string, titre: string, texte: string }>  $qualiteDonnees
     * @return array{ pourcent: float, motif: string }
     */
    private function calculerIndiceFiabiliteDonnees(int $entriesCount, array $qualiteDonnees): array
    {
        $pen = 0.0;
        if ($entriesCount < 10) {
            $pen += 14.0;
        } elseif ($entriesCount < 25) {
            $pen += 7.0;
        }

        foreach ($qualiteDonnees as $a) {
            $n = $a['niveau'] ?? 'warning';
            if ($n === 'warning') {
                $pen += 7.0;
            } elseif ($n === 'info') {
                $pen += 3.0;
            }
        }

        $pen = min(38.0, $pen);
        $pourcent = round(max(40.0, 100.0 - $pen), 1);
        $motif = 'Indice dérivé du volume d’écritures et des alertes qualité (pénalité max. 38 pts, plancher 40 %).';

        return ['pourcent' => $pourcent, 'motif' => $motif];
    }

    private function libelleScore(float $valeur): string
    {
        if ($valeur >= 75.0) {
            return 'Élevé';
        }

        if ($valeur >= 58.0) {
            return 'Correct';
        }

        if ($valeur >= 42.0) {
            return 'Fragile';
        }

        return 'Faible';
    }

    private function niveauScore(float $valeur): string
    {
        if ($valeur >= 72.0) {
            return 'success';
        }

        if ($valeur >= 50.0) {
            return 'warning';
        }

        return 'danger';
    }

    /**
     * Cadre explicite pour rendre l’analyse interprétable (référence métier, pas norme comptable).
     *
     * @return array<string, mixed>
     */
    private function describeModeleFinancierReference(): array
    {
        return [
            'titre' => 'Modèle financier de référence (PME — OHADA simplifié)',
            'intro' => 'Cette analyse applique un modèle de masses patrimoniales et de résultat cohérent avec un plan comptable OHADA, mais volontairement simplifié pour un outil en ligne : les soldes sont reconstruits à partir des écritures saisies, sans reclassement expert ni provisions hors journal.',
            'postulats' => [
                'Équation patrimoniale utilisée : actif estimé − passif estimé = capitaux propres estimés (les comptes de capitaux sous classe 1 au crédit sont mélangés aux dettes dans le « passif » agrégé — lecture à valider avec un bilan structuré).',
                'Compte de résultat : produits (classe 7, soldes créditeurs nets) − charges (classe 6, soldes débiteurs nets) sur la période filtrée.',
                'Actif : soldes débiteurs nets des classes 2, 3, 4 (clients), 5 (trésorerie comptable) ; créances clients incluses dans l’actif.',
                'Passif : soldes créditeurs nets des classes 1 et 4 (dettes, fournisseurs, etc.) selon l’agrégation implémentée.',
                'Trésorerie « effectuée » : encaissements − décaissements du module Trésorerie (hors solde comptable seul).',
            ],
            'formules' => [
                ['indicateur' => 'Résultat net (période)', 'expression' => 'Σ produits cl. 7 − Σ charges cl. 6'],
                ['indicateur' => 'Marge nette', 'expression' => '(Résultat net ÷ CA) × 100 si CA > 0'],
                ['indicateur' => 'ROA', 'expression' => '(Résultat net ÷ Actif) × 100 si actif > 0'],
                ['indicateur' => 'ROE', 'expression' => '(Résultat net ÷ Capitaux propres estimés) × 100 si CP > 0'],
                ['indicateur' => 'Endettement / actif', 'expression' => '(Passif ÷ Actif) × 100 si actif > 0'],
                ['indicateur' => 'Levier', 'expression' => 'Passif ÷ Capitaux propres estimés si CP > 0'],
                ['indicateur' => 'Liquidité générale (approx.)', 'expression' => 'Actif ÷ Passif si passif > 0'],
                ['indicateur' => 'Rotation de l’actif', 'expression' => 'CA ÷ Actif si actif > 0'],
            ],
            'seuils_synthese' => 'Les verdicts « rentable / solvable » utilisent des seuils indicatifs type PME (ex. marge nette ≥ 3 %, ROA ≥ 2 %, dettes/actif ≤ 70 %, levier ≤ 2,5, liquidité ≥ 1 lorsque calculable). Ils sont ajustables selon le secteur.',
            'scores' => 'Les scores sur 100 sont indicatifs : rentabilité (marge nette, ROA, ROE), solvabilité (dettes/actif, levier, liquidité générale). La « synthèse » est la moyenne des deux ; la « synthèse fiabilisée » applique l’indice de qualité des données (volume d’écritures et alertes). Ce n’est pas une notation de risque bancaire.',
            'limites' => [
                'Ne remplace ni bilan social, ni liasse fiscale, ni rapport de CAC.',
                'Stocks, immobilisations et provisions : sensibles à la qualité des comptes 2 et 3 et aux inventaires.',
                'Période courte ou écritures rares : les ratios sont des photographies, pas une tendance.',
            ],
        ];
    }
}
