<?php

return [
    'accounting' => [
        'label' => 'Accounting',
        'owner' => 'finance',
        'critical_flows' => ['ocr', 'entries', 'reports'],
    ],
    'treasury' => [
        'label' => 'Treasury',
        'owner' => 'finance',
        'critical_flows' => ['balance', 'forecast', 'payments'],
    ],
    'investor' => [
        'label' => 'Investor',
        'owner' => 'investor_relations',
        'critical_flows' => ['readiness', 'investment_requests'],
    ],
    'payment' => [
        'label' => 'Payment',
        'owner' => 'finance_ops',
        'critical_flows' => ['stripe_webhook', 'fedapay'],
    ],
    'admin' => [
        'label' => 'Admin',
        'owner' => 'platform',
        'critical_flows' => ['license', 'supervision'],
    ],
];
