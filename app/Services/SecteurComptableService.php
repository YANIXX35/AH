<?php

namespace App\Services;

/**
 * Mapping secteur d'activité → comptes SYSCOHADA OHADA.
 *
 * Chaque secteur définit :
 *  - des comptes débit/crédit par type de document (Achat, Vente, Frais Généraux…)
 *  - des sous-types pour "Achat" détectables par mots-clés OCR
 *  - un label lisible et un compte de vente par défaut
 *
 * Structure par secteur :
 *   map[sector][document_type] = ['debit' => '...', 'credit' => '...']
 *   subtypes[sector] = [['pattern' => '...', 'debit' => '...', 'credit' => '...']]
 */
class SecteurComptableService
{
    // ─── Comptes partagés toutes activités ────────────────────────────────────

    private const FOURNISSEURS     = '401 Fournisseurs, dettes en compte';
    private const CLIENTS          = '411 Clients';
    private const BANQUE           = '521 Banques locales';
    private const CAISSE           = '571 Caisse siège social';
    private const PAIE_CREDIT      = '422 Personnel, rémunérations dues';
    private const CHARGES_SOCIALES = '431 Sécurité sociale';
    private const OD_DEBIT         = '471 Débiteurs et créditeurs divers';
    private const OD_CREDIT        = '472 Créances et dettes sur titres';

    // ─── Mapping complet secteur → type → comptes ─────────────────────────────

    private function map(): array
    {
        return [

            // ──────────────────────────────────────────────────────────────────
            // COMMERCE / NÉGOCE  (import-export, distribution, boutique, marché)
            // ──────────────────────────────────────────────────────────────────
            'commerce' => [
                'Achat'               => ['debit' => '601 Achats de marchandises',                   'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '701 Ventes de marchandises'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6065 Fournitures de bureau',                   'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '6311 Frais sur achats',                        'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // BTP  (bâtiment, travaux publics, génie civil, construction)
            // ──────────────────────────────────────────────────────────────────
            'btp' => [
                'Achat'               => ['debit' => '604 Achats de matières et fournitures consommables', 'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                         'credit' => '704 Travaux'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                          'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                                  'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6041 Matières et fournitures consommables',           'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',                  'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                          'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '624 Transports de biens',                             'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                       'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // RESTAURATION / HÔTELLERIE  (restaurant, hôtel, traiteur, fast-food)
            // ──────────────────────────────────────────────────────────────────
            'restauration' => [
                'Achat'               => ['debit' => '602 Achats de matières premières',             'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Services vendus'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6041 Matières et fournitures consommables',    'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '625 Déplacements, missions, réceptions',       'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SERVICES  (conseil, audit, expertise, formation, cabinet)
            // ──────────────────────────────────────────────────────────────────
            'services' => [
                'Achat'               => ['debit' => '628 Divers',                                   'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Services vendus'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6065 Fournitures de bureau',                   'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '622 Rémunérations d\'intermédiaires et honoraires', 'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // TRANSPORT / LOGISTIQUE  (routier, fret, transit, coursier)
            // ──────────────────────────────────────────────────────────────────
            'transport' => [
                'Achat'               => ['debit' => '6022 Fournitures liées à la production',       'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Services de transport vendus'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '624 Transports de biens',                      'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '624 Transports de biens',                      'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // SANTÉ / PHARMACIE  (clinique, cabinet médical, pharmacie, labo)
            // ──────────────────────────────────────────────────────────────────
            'sante' => [
                'Achat'               => ['debit' => '602 Achats de matières premières médicales',   'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Services médicaux vendus'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6041 Matières et fournitures consommables',    'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '625 Déplacements, missions',                   'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // AGRICULTURE / AGRO-ALIMENTAIRE  (cultures, élevage, pêche, transformation)
            // ──────────────────────────────────────────────────────────────────
            'agriculture' => [
                'Achat'               => ['debit' => '602 Achats de matières premières agricoles',   'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '701 Ventes de produits agricoles'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6041 Matières et fournitures consommables',    'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '624 Transports de biens',                      'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // INDUSTRIE / MANUFACTURE  (usine, transformation, artisanat)
            // ──────────────────────────────────────────────────────────────────
            'industrie' => [
                'Achat'               => ['debit' => '6021 Matières premières',                      'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '702 Ventes de produits finis'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6041 Matières et fournitures consommables',    'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '625 Déplacements, missions',                   'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // IT / NUMÉRIQUE / TÉLÉCOMS  (informatique, software, internet, mobile)
            // ──────────────────────────────────────────────────────────────────
            'it' => [
                'Achat'               => ['debit' => '628 Frais de télécommunications et services informatiques', 'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                              'credit' => '706 Services informatiques vendus'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                               'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                                       'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '626 Frais postaux et télécommunications',                  'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',                       'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                               'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '622 Rémunérations d\'intermédiaires',                      'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                            'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // ÉDUCATION / FORMATION  (école, centre de formation, université)
            // ──────────────────────────────────────────────────────────────────
            'education' => [
                'Achat'               => ['debit' => '6065 Fournitures scolaires et pédagogiques',  'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Prestations éducatives'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '6065 Fournitures de bureau',                   'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '625 Déplacements, missions',                   'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // IMMOBILIER / PROMOTION  (agence immobilière, promoteur, gérant)
            // ──────────────────────────────────────────────────────────────────
            'immobilier' => [
                'Achat'               => ['debit' => '604 Matières et fournitures pour travaux',     'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '706 Prestations immobilières'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '622 Commissions et honoraires',                'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '622 Rémunérations d\'intermédiaires',          'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],

            // ──────────────────────────────────────────────────────────────────
            // FALLBACK  (secteur non renseigné ou non reconnu)
            // ──────────────────────────────────────────────────────────────────
            'default' => [
                'Achat'               => ['debit' => '601 Achats de marchandises',                   'credit' => self::FOURNISSEURS],
                'Vente'               => ['debit' => self::CLIENTS,                                   'credit' => '701 Ventes de marchandises'],
                'Règlement Client'    => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Règlement Fournisseur' => ['debit' => self::FOURNISSEURS,                            'credit' => self::BANQUE],
                'Frais Généraux'      => ['debit' => '628 Divers',                                   'credit' => self::BANQUE],
                'Paie'                => ['debit' => '661 Rémunérations directes versées',            'credit' => self::BANQUE],
                'Reçu'                => ['debit' => self::BANQUE,                                    'credit' => self::CLIENTS],
                'Justificatif'        => ['debit' => '631 Frais bancaires et charges assimilées',    'credit' => self::BANQUE],
                'Opérations Diverses' => ['debit' => self::OD_DEBIT,                                 'credit' => self::OD_CREDIT],
            ],
        ];
    }

    // ─── Sous-types "Achat" détectables par mots-clés OCR ─────────────────────
    // Pour un même type "Achat", le compte diffère selon la nature de la dépense.
    // L'ordre dans le tableau compte : le premier match gagne.

    private function purchaseSubtypes(): array
    {
        return [

            'btp' => [
                ['pattern' => '/sous[\-\s]trait|prestation\s+ext|façonnier|façonnage/iu',     'debit' => '621 Sous-traitance générale',              'credit' => self::FOURNISSEURS],
                ['pattern' => '/location\s+(?:engin|grue|bétonnière|camion|nacelle)/iu',       'debit' => '6231 Locations de matériel et outillage',  'credit' => self::FOURNISSEURS],
                ['pattern' => '/carburant|gasoil|diesel|essence|lubrifiant/iu',                'debit' => '6022 Fournitures liées à la production',   'credit' => self::FOURNISSEURS],
                ['pattern' => '/ciment|fer|béton|acier|armature|ferraille|gravier|sable|parpaing|brique|bois\s+de\s+charpente/iu', 'debit' => '604 Matières et fournitures consommables', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/quincaillerie|vis|boulon|clou|outil|outillage|peinture|badigeon/iu', 'debit' => '6064 Fournitures d\'entretien et petit outillage', 'credit' => self::FOURNISSEURS],
            ],

            'restauration' => [
                ['pattern' => '/boisson|bière|vin|alcool|soda|eau\s+minérale|jus/iu',         'debit' => '601 Achats de boissons',                   'credit' => self::FOURNISSEURS],
                ['pattern' => '/viande|poulet|poisson|fruits\s+de\s+mer|bœuf|mouton|agneau/iu', 'debit' => '602 Matières premières alimentaires',    'credit' => self::FOURNISSEURS],
                ['pattern' => '/légume|farine|riz|huile|sucre|sel|épice|condiment|tomate/iu', 'debit' => '602 Matières premières alimentaires',      'credit' => self::FOURNISSEURS],
                ['pattern' => '/produit\s+(?:nettoyage|entretien)|détergent|savon|désinfectant/iu', 'debit' => '6041 Produits d\'entretien',         'credit' => self::FOURNISSEURS],
                ['pattern' => '/gaz|charbon|bois|combustible/iu',                              'debit' => '6063 Combustibles et énergie',             'credit' => self::FOURNISSEURS],
            ],

            'transport' => [
                ['pattern' => '/carburant|essence|gasoil|diesel|super/iu',                    'debit' => '6022 Carburants et lubrifiants',           'credit' => self::FOURNISSEURS],
                ['pattern' => '/pneu|pneumatique|chambre\s+à\s+air/iu',                       'debit' => '6041 Matières et fournitures consommables', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/pièces?\s+(?:de\s+)?rechange|réparation|entretien\s+(?:véhicule|camion)/iu', 'debit' => '6041 Pièces de rechange',  'credit' => self::FOURNISSEURS],
                ['pattern' => '/péage|autoroute|parking|stationnement/iu',                    'debit' => '624 Frais de transport et péages',         'credit' => self::FOURNISSEURS],
                ['pattern' => '/assurance\s+(?:véhicule|flotte|transport)/iu',                'debit' => '627 Primes d\'assurance',                  'credit' => self::FOURNISSEURS],
            ],

            'sante' => [
                ['pattern' => '/médicament|pharmacie|spécialité\s+pharmaceutique|DCI|gélule|comprimé|sirop/iu', 'debit' => '601 Achats de médicaments', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/réactif|consommable\s+(?:médical|labo)|kit\s+(?:test|diagnostic)/iu', 'debit' => '602 Réactifs et consommables médicaux', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/gant|seringue|masque|blouse|aiguille|cathéter|sonde|pansement/iu', 'debit' => '6041 Fournitures médicales consommables', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/matériel\s+médical|équipement\s+(?:médical|sanitaire)|appareil\s+(?:médical|imagerie)/iu', 'debit' => '6041 Équipements médicaux consommables', 'credit' => self::FOURNISSEURS],
            ],

            'agriculture' => [
                ['pattern' => '/semence|graine|plant|bouture|engrais|pesticide|herbicide|insecticide|phytosanitaire/iu', 'debit' => '602 Intrants agricoles', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/aliment\s+(?:bétail|volaille|poisson)|provende|tourteau|son/iu', 'debit' => '602 Aliments pour animaux',             'credit' => self::FOURNISSEURS],
                ['pattern' => '/carburant|gasoil|diesel|essence|lubrifiant/iu',                'debit' => '6022 Carburants et lubrifiants agricoles', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/emballage|sac|bidon|fût|conteneur|conditionnement/iu',         'debit' => '6042 Emballages perdus',                   'credit' => self::FOURNISSEURS],
            ],

            'industrie' => [
                ['pattern' => '/matière\s+première|matière\s+brute|MP\b|coton|tissu|textile|cuir|bois\s+(?:brut|massif)/iu', 'debit' => '6021 Matières premières',  'credit' => self::FOURNISSEURS],
                ['pattern' => '/sous[\-\s]trait|façonnage|prestation\s+ext/iu',               'debit' => '621 Sous-traitance industrielle',          'credit' => self::FOURNISSEURS],
                ['pattern' => '/fourniture\s+(?:industrielle|consommable)|visserie|courroie|joint|roulement/iu', 'debit' => '6022 Fournitures consommables liées à la production', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/carburant|gasoil|diesel|essence|gaz\s+industriel|fluide/iu',  'debit' => '6063 Combustibles et énergie',             'credit' => self::FOURNISSEURS],
                ['pattern' => '/emballage|carton|sac|bidon|étiquette|film\s+plastique/iu',    'debit' => '6042 Emballages perdus',                   'credit' => self::FOURNISSEURS],
            ],

            'it' => [
                ['pattern' => '/licence|abonnement\s+(?:logiciel|cloud|saas|erp)|maintenance\s+logiciel/iu', 'debit' => '628 Abonnements et licences logiciels', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/matériel|serveur|ordinateur|laptop|écran|disque\s+dur|composant/iu', 'debit' => '604 Matériel informatique consommable',  'credit' => self::FOURNISSEURS],
                ['pattern' => '/prestation|développement|infogérance|hébergement|maintenance/iu', 'debit' => '628 Prestations informatiques externes', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/téléphonie|internet|data|bandwidth|connectivité/iu',           'debit' => '626 Frais de télécommunications',          'credit' => self::FOURNISSEURS],
            ],

            'commerce' => [
                ['pattern' => '/emballage|carton|sac|conditionnement|étiquette/iu',            'debit' => '6042 Emballages perdus',                   'credit' => self::FOURNISSEURS],
                ['pattern' => '/transport|livraison|fret|transit|douane/iu',                   'debit' => '6311 Frais d\'achat (transport)',           'credit' => self::FOURNISSEURS],
                ['pattern' => '/impôt|taxe\s+(?:douanière|d\'entrée)|droits\s+de\s+douane/iu', 'debit' => '6312 Frais d\'achat (droits de douane)',   'credit' => self::FOURNISSEURS],
            ],

            'services' => [
                ['pattern' => '/honoraire|cabinet|expert|consultant|avocat|notaire|commissaire/iu', 'debit' => '622 Rémunérations d\'intermédiaires et honoraires', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/abonnement|logiciel|saas|licence|outil\s+(?:en\s+ligne|digital)/iu', 'debit' => '628 Abonnements et services divers', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/fourniture\s+(?:bureau|scolaire)|papier|stylo|cartouche|imprimante/iu', 'debit' => '6065 Fournitures de bureau',       'credit' => self::FOURNISSEURS],
            ],

            'immobilier' => [
                ['pattern' => '/sous[\-\s]trait|entreprise\s+(?:bâtiment|travaux)|prestataire\s+(?:btp|construction)/iu', 'debit' => '621 Sous-traitance de construction', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/matériaux|ciment|fer|béton|parpaing|peinture/iu',             'debit' => '604 Matériaux de construction',             'credit' => self::FOURNISSEURS],
                ['pattern' => '/annonce|publicité|portail\s+immo|affichage/iu',               'debit' => '623 Publicité et publications',             'credit' => self::FOURNISSEURS],
            ],

            'education' => [
                ['pattern' => '/livre|manuel|document|fourniture\s+scolaire|cahier|crayon/iu', 'debit' => '6065 Fournitures pédagogiques',            'credit' => self::FOURNISSEURS],
                ['pattern' => '/licence|logiciel|abonnement\s+éducatif|plateforme\s+(?:e.learning|formation)/iu', 'debit' => '628 Licences pédagogiques', 'credit' => self::FOURNISSEURS],
                ['pattern' => '/formateur|intervenant|vacataire|honoraire/iu',                'debit' => '622 Honoraires formateurs',                 'credit' => self::FOURNISSEURS],
            ],
        ];
    }

    // ─── API publique ──────────────────────────────────────────────────────────

    /**
     * Retourne les comptes SYSCOHADA pour un secteur + type de document donné.
     * Si un texte OCR est fourni pour un "Achat", affine le compte via mots-clés.
     *
     * @param  string  $sector       Valeur du champ `sector` sur l'utilisateur (ex: "btp", "commerce").
     * @param  string  $documentType Type de document (ex: "Achat", "Vente", "Frais Généraux").
     * @param  string  $ocrText      Texte OCR brut pour affiner la détection (optionnel).
     * @return array{debit: string, credit: string}
     */
    public function resolve(string $sector, string $documentType, string $ocrText = ''): array
    {
        $sectorKey = $this->normalizeSector($sector);

        // Tentative d'affinage par mots-clés OCR sur les achats.
        if ($documentType === 'Achat' && $ocrText !== '') {
            $subtype = $this->detectPurchaseSubtype($sectorKey, $ocrText);
            if ($subtype !== null) {
                return $subtype;
            }
        }

        return $this->resolveByMap($sectorKey, $documentType);
    }

    /**
     * Retourne le mapping complet (tous types) pour un secteur donné.
     * Utilisé pour pré-remplir l'interface de saisie selon le secteur.
     *
     * @return array<string, array{debit: string, credit: string}>
     */
    public function fullMapForSector(string $sector): array
    {
        $sectorKey = $this->normalizeSector($sector);
        $map       = $this->map();

        return $map[$sectorKey] ?? $map['default'];
    }

    /**
     * Liste des secteurs disponibles avec leur label affichable.
     *
     * @return array<string, string>  ['btp' => 'BTP / Construction', ...]
     */
    public function sectorLabels(): array
    {
        return [
            'agriculture'  => 'Agriculture / Agro-alimentaire',
            'btp'          => 'BTP / Construction',
            'commerce'     => 'Commerce / Négoce',
            'education'    => 'Éducation / Formation',
            'immobilier'   => 'Immobilier / Promotion',
            'industrie'    => 'Industrie / Manufacture',
            'it'           => 'IT / Numérique / Télécoms',
            'restauration' => 'Restauration / Hôtellerie',
            'sante'        => 'Santé / Pharmacie',
            'services'     => 'Services / Conseil',
            'transport'    => 'Transport / Logistique',
        ];
    }

    // ─── Méthodes privées ──────────────────────────────────────────────────────

    private function resolveByMap(string $sectorKey, string $documentType): array
    {
        $map = $this->map();
        $sectorMap = $map[$sectorKey] ?? $map['default'];

        return $sectorMap[$documentType]
            ?? $map['default'][$documentType]
            ?? ['debit' => self::OD_DEBIT, 'credit' => self::OD_CREDIT];
    }

    private function detectPurchaseSubtype(string $sectorKey, string $ocrText): ?array
    {
        $subtypes = $this->purchaseSubtypes();
        $sectorSubtypes = $subtypes[$sectorKey] ?? [];

        foreach ($sectorSubtypes as $rule) {
            if (preg_match($rule['pattern'], $ocrText)) {
                return ['debit' => $rule['debit'], 'credit' => $rule['credit']];
            }
        }

        return null;
    }

    private function normalizeSector(string $sector): string
    {
        $sector = mb_strtolower(trim($sector));

        $aliases = [
            'btp'                    => 'btp',
            'bâtiment'               => 'btp',
            'batiment'               => 'btp',
            'construction'           => 'btp',
            'génie civil'            => 'btp',
            'genie civil'            => 'btp',
            'travaux publics'        => 'btp',
            'commerce'               => 'commerce',
            'négoce'                 => 'commerce',
            'negoce'                 => 'commerce',
            'distribution'           => 'commerce',
            'import'                 => 'commerce',
            'import-export'          => 'commerce',
            'restauration'           => 'restauration',
            'restaurant'             => 'restauration',
            'hôtellerie'             => 'restauration',
            'hotellerie'             => 'restauration',
            'hôtel'                  => 'restauration',
            'hotel'                  => 'restauration',
            'traiteur'               => 'restauration',
            'services'               => 'services',
            'conseil'                => 'services',
            'consulting'             => 'services',
            'audit'                  => 'services',
            'expertise'              => 'services',
            'transport'              => 'transport',
            'logistique'             => 'transport',
            'fret'                   => 'transport',
            'transit'                => 'transport',
            'santé'                  => 'sante',
            'sante'                  => 'sante',
            'pharmacie'              => 'sante',
            'médical'                => 'sante',
            'medical'                => 'sante',
            'clinique'               => 'sante',
            'laboratoire'            => 'sante',
            'agriculture'            => 'agriculture',
            'élevage'                => 'agriculture',
            'elevage'                => 'agriculture',
            'pêche'                  => 'agriculture',
            'peche'                  => 'agriculture',
            'agro-alimentaire'       => 'agriculture',
            'agroalimentaire'        => 'agriculture',
            'industrie'              => 'industrie',
            'manufacture'            => 'industrie',
            'artisanat'              => 'industrie',
            'transformation'         => 'industrie',
            'it'                     => 'it',
            'informatique'           => 'it',
            'numérique'              => 'it',
            'numerique'              => 'it',
            'télécoms'               => 'it',
            'telecoms'               => 'it',
            'ntic'                   => 'it',
            'education'              => 'education',
            'éducation'              => 'education',
            'formation'              => 'education',
            'école'                  => 'education',
            'ecole'                  => 'education',
            'université'             => 'education',
            'universite'             => 'education',
            'immobilier'             => 'immobilier',
            'promotion immobilière'  => 'immobilier',
            'promotion immobiliere'  => 'immobilier',
            'agence immobilière'     => 'immobilier',
            'agence immobiliere'     => 'immobilier',
        ];

        return $aliases[$sector] ?? ($this->isKnownSector($sector) ? $sector : 'default');
    }

    private function isKnownSector(string $sector): bool
    {
        return array_key_exists($sector, $this->map());
    }
}
