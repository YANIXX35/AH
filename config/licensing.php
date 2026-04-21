<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Licence multi-utilisateurs (entreprise)
    |--------------------------------------------------------------------------
    |
    | Nombre maximum de comptes utilisateurs distincts pour une même entreprise
    | couverts par une licence générée côté administration (offre pack équipe).
    |
    */
    'enterprise_max_users_per_license' => (int) env('ENTERPRISE_LICENSE_MAX_USERS', 3),

];
