<?php

return [
    'company' => [
        'name' => env('APP_NAME', 'Sitiame Capital'),
    ],
    'accounts' => [
        '1' => [
            'label' => 'Capitaux propres et ressources stables',
            'category' => 'balance',
        ],
        '2' => [
            'label' => 'Immobilisations / Investissements',
            'category' => 'balance',
            'subtype' => 'investissement',
        ],
        '3' => [
            'label' => 'Stocks',
            'category' => 'balance',
        ],
        '4' => [
            'label' => 'Tiers (clients, fournisseurs, Etat)',
            'category' => 'balance',
        ],
        '5' => [
            'label' => 'Trésorerie',
            'category' => 'balance',
        ],
        '6' => [
            'label' => 'Charges',
            'category' => 'resultat',
            'subtype' => 'charge',
        ],
        '7' => [
            'label' => 'Produits',
            'category' => 'resultat',
            'subtype' => 'produit',
        ],
        '8' => [
            'label' => 'Comptes spéciaux',
            'category' => 'other',
        ],
        '9' => [
            'label' => 'Comptes analytiques',
            'category' => 'other',
        ],
    ],
];
