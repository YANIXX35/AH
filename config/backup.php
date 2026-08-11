<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Nombre de sauvegardes conservées
    |--------------------------------------------------------------------------
    |
    | Au-delà de ce nombre, les sauvegardes les plus anciennes sont supprimées
    | automatiquement après chaque nouvelle sauvegarde réussie. Avec un
    | déclenchement toutes les 4h (6/jour), 60 fichiers ≈ 10 jours d'historique.
    |
    */
    'keep_count' => env('BACKUP_KEEP_COUNT', 60),
];
