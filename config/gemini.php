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
    | Clé API & Timeouts
    |--------------------------------------------------------------------------
    */
    'key' => env('GEMINI_API_KEY'),

    'timeout' => (int) env('GEMINI_TIMEOUT', 30),

    'connect_timeout' => (int) env('GEMINI_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Stratégie de Retry exponentiel
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'max_attempts' => 4,
        'base_delay_seconds' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mise en cache des requêtes identiques
    |--------------------------------------------------------------------------
    | Pour éviter de sur-solliciter l'API pour les mêmes questions récurrentes.
    */
    'cache' => [
        'enabled' => true,
        'ttl_minutes' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker
    |--------------------------------------------------------------------------
    | Si un modèle échoue trop souvent dans un court laps de temps, il est mis 
    | hors service temporairement.
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
