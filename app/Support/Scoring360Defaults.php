<?php

namespace App\Support;

class Scoring360Defaults
{
    /**
     * Valeurs par défaut alignées sur le classeur :
     * SITIAME_360_Integrated_inputs_lies.xlsx (onglets Parametres / Score_*).
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'meta' => [
                'source' => 'excel:SITIAME_360_Integrated_inputs_lies.xlsx',
                'version' => 1,
            ],
            'coefficients' => [
                'strong' => 1.0,
                'medium' => 0.6,
                'weak' => 0.2,
            ],
            'bank' => [
                'thresholds' => [
                    'dscr' => ['strong' => 1.5, 'medium' => 1.1, 'direction' => 'gte'],
                    'interest_coverage' => ['strong' => 3.0, 'medium' => 1.5, 'direction' => 'gte'],
                    'current_ratio' => ['strong' => 1.5, 'medium' => 1.0, 'direction' => 'gte'],
                    'debt_asset' => ['strong' => 0.5, 'medium' => 0.7, 'direction' => 'lte'],
                    'bfr_days' => ['strong' => 90.0, 'medium' => 120.0, 'direction' => 'lte'],
                ],
                'weights' => [
                    'dscr' => 30.0,
                    'interest_coverage' => 20.0,
                    'current_ratio' => 15.0,
                    'debt_asset' => 20.0,
                    'bfr_days' => 15.0,
                ],
                'decision' => [
                    'strong_min' => 80.0,
                    'medium_min' => 60.0,
                    'labels' => [
                        'strong' => 'FINANÇABLE',
                        'medium' => 'À SURVEILLER',
                        'weak' => 'RISQUE ÉLEVÉ',
                    ],
                    'lectures' => [
                        'strong' => 'Financement bancaire possible sous réserve de due diligence.',
                        'medium' => 'Financement possible avec covenants et montant prudent.',
                        'weak' => 'Préférer restructuration ou financement encadré.',
                    ],
                ],
            ],
            'investor' => [
                'thresholds' => [
                    'revenue_growth' => ['strong' => 0.15, 'medium' => 0.05, 'direction' => 'gte'],
                    'ebitda_margin' => ['strong' => 0.20, 'medium' => 0.10, 'direction' => 'gte'],
                    'roe' => ['strong' => 0.15, 'medium' => 0.08, 'direction' => 'gte'],
                    'fcf_margin' => ['strong' => 0.08, 'medium' => 0.02, 'direction' => 'gte'],
                    'asset_turnover' => ['strong' => 1.2, 'medium' => 0.8, 'direction' => 'gte'],
                ],
                'weights' => [
                    'revenue_growth' => 25.0,
                    'ebitda_margin' => 20.0,
                    'roe' => 20.0,
                    'fcf_margin' => 20.0,
                    'asset_turnover' => 15.0,
                ],
                'decision' => [
                    'strong_min' => 80.0,
                    'medium_min' => 60.0,
                    'labels' => [
                        'strong' => 'ATTRACTIF',
                        'medium' => 'POTENTIEL',
                        'weak' => 'À AMÉLIORER',
                    ],
                    'lectures' => [
                        'strong' => 'Dossier investissable avec potentiel de croissance.',
                        'medium' => 'Intérêt possible avec plan d’amélioration.',
                        'weak' => 'Rentabilité/cash insuffisants pour un investisseur exigeant.',
                    ],
                ],
            ],
            'internal' => [
                'thresholds' => [
                    'net_margin' => ['strong' => 0.10, 'medium' => 0.05, 'direction' => 'gte'],
                    'quick_ratio' => ['strong' => 1.2, 'medium' => 0.9, 'direction' => 'gte'],
                    'receivable_days' => ['strong' => 45.0, 'medium' => 60.0, 'direction' => 'lte'],
                    'inventory_days' => ['strong' => 70.0, 'medium' => 90.0, 'direction' => 'lte'],
                    'ebitda_growth' => ['strong' => 0.15, 'medium' => 0.05, 'direction' => 'gte'],
                ],
                'weights' => [
                    'net_margin' => 25.0,
                    'quick_ratio' => 20.0,
                    'receivable_days' => 20.0,
                    'inventory_days' => 15.0,
                    'ebitda_growth' => 20.0,
                ],
                'decision' => [
                    'strong_min' => 80.0,
                    'medium_min' => 60.0,
                    'labels' => [
                        'strong' => 'MAÎTRISÉ',
                        'medium' => 'SOUS CONTRÔLE',
                        'weak' => 'À CORRIGER',
                    ],
                    'lectures' => [
                        'strong' => 'Pilotage interne robuste.',
                        'medium' => 'Pilotage correct avec points de vigilance.',
                        'weak' => 'Action managériale prioritaire recommandée.',
                    ],
                ],
            ],
            'composite' => [
                'weights' => [
                    'bank' => 40.0,
                    'investor' => 35.0,
                    'internal' => 25.0,
                ],
                'decision' => [
                    'strong_min' => 80.0,
                    'medium_min' => 60.0,
                    'labels' => [
                        'strong' => 'PRÊT À DÉPLOYER',
                        'medium' => 'SOLIDE MAIS À CADRER',
                        'weak' => 'RISQUE À TRAITER',
                    ],
                    'lectures' => [
                        'strong' => 'Le dossier peut servir simultanément à un usage interne, plateforme PME et décision de financement.',
                        'medium' => 'La PME est exploitable dans SITIAME mais nécessite garde-fous sur crédit ou exécution.',
                        'weak' => 'Priorité à l’assainissement financier avant mise à l’échelle.',
                    ],
                ],
            ],
        ];
    }
}

