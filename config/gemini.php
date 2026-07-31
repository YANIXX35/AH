<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Modèles Gemini par défaut et Secours
    |--------------------------------------------------------------------------
    */
    'default_model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    'fallback_models' => [
        'gemini-2.5-flash-lite',
        'gemini-2.5-flash',
        'gemini-flash-latest',
    ],

    /*
    |--------------------------------------------------------------------------
    | Clés API avec Rotation automatique (quota exhausted fallback)
    |--------------------------------------------------------------------------
    | La clé primaire est GEMINI_API_KEY. Si son quota (HTTP 429) est épuisé,
    | le service bascule automatiquement sur GEMINI_API_KEY_2, puis _3, puis _4.
    | Le basculement est mémorisé en cache pour éviter de re-tenter une clé
    | épuisée pendant GEMINI_KEY_LOCKOUT_MINUTES minutes (défaut : 60 min).
    */
    'key' => env('GEMINI_API_KEY'),

    'extra_keys' => array_values(array_filter([
        env('GEMINI_API_KEY_2'),
        env('GEMINI_API_KEY_3'),
        env('GEMINI_API_KEY_4'),
    ])),

    'key_lockout_minutes' => (int) env('GEMINI_KEY_LOCKOUT_MINUTES', 60),

    'timeout' => (int) env('GEMINI_TIMEOUT', 30),

    'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Stratégie de Retry exponentiel
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 3,
        'base_delay_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mise en cache des requêtes identiques
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'enabled' => true,
        'max_failures' => 5,
        'time_window_seconds' => 120,
        'lockout_duration_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Journalisation dédiée
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => 'gemini',
    ],
];
