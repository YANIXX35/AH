<?php

// PROVISOIRE — méthode de contrôle à valider avec la Comptabilité (PRD 4.2).
//
// Ce fichier isole la méthode de contrôle qualité par défaut utilisée par
// App\Domain\Accounting\QualityControlService en attendant la définition
// officielle (cadence, échantillonnage, responsables) par la Comptabilité.
// Aucune autre partie du code ne doit coder ce seuil en dur : tout passe par
// ici, pour qu'un changement de méthode se fasse à un seul endroit.

return [

    // Version de la méthode utilisée pour produire une suggestion de statut.
    // Change cette valeur (ex. "quarterly-sampling-v1") le jour où la Comptabilité
    // remplace la méthode provisoire — les revues déjà enregistrées gardent leur
    // ancien method_version et ne sont jamais recalculées rétroactivement.
    'method_version' => 'provisional-reliability-v1',

    // Seuil (%) de l'indice de fiabilité des données (App\Services\SmeFinancialRatioService)
    // au-delà duquel une période est suggérée "validated" plutôt que "flagged".
    // Purement indicatif tant qu'un humain (comptable/admin) ne confirme pas.
    'auto_suggest_threshold' => 70.0,

    // Cadence proposée par le PRD (trimestrielle). Utilisée pour calculer la
    // période "courante" à vérifier avant d'exposer le scoring.
    'period' => 'quarter',
];
