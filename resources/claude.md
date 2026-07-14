Voici le PRD en clair, directement ici :

PME360 — Document d'exigences produit (PRD)
Version 1.0 — consolidée post-revue plateforme
Date 14 juillet 2026
Sources Cadrage_PME360.docx · Résumé technique du développement · Compte rendu de réunion (revue plateforme & lancement)
Statut Document de travail — pour arbitrage Direction
1. Vision produit
PME360 (SITIAME Capital) est une plateforme SaaS B2B de gestion financière destinée aux PME d'Afrique de l'Ouest (zone UEMOA/OHADA, marché prioritaire : Côte d'Ivoire). L'objectif est de donner aux PME un outil de pilotage — trésorerie, comptabilité, facturation, financement — adapté à leur réalité locale, en particulier au fait que l'essentiel de la trésorerie réelle d'une PME africaine transite par Mobile Money (Wave, Orange Money, MTN MoMo) plutôt que par les comptes bancaires classiques.
Principe directeur (hérité de la note de cadrage produit) : PME360 reste un logiciel de pilotage financier, pas un acteur régulé du paiement. Toute intégration Mobile Money privilégie la lecture seule ; toute évolution vers l'initiation de paiement doit être délibérée, étudiée et validée par la Direction — pas un effet de bord technique.
Deuxième pilier de vision, issu de la revue de plateforme du 14 juillet 2026 : la fiabilité des états financiers et du score de crédit produits par PME360 est la condition non négociable de la crédibilité du volet financement. Un score construit sur des données non fiabilisées expose l'entreprise et ses utilisateurs à un risque de réputation et de décision de financement erronée.
2. Stack technique (état actuel)
DomaineDétailBackendLaravel 13 / PHP 8.4 (production et local — composer.json affiche encore ^8.3, à corriger)FrontendBlade (templates serveur) + Bootstrap, JavaScript vanilla pour l'interactivitéBase de donnéesPostgreSQL en production (Neon, migré depuis Render/Aiven expiré), MySQL en localPDFDomPDF (factures, rapports)PaiementCinetPay et FedaPay (abonnements premium + initiation de paiement Mobile Money)OCRPaddleOCR (Python) — lecture automatique de documents comptablesIAAssistant business via HuggingFace (Llama 3.1)DéploiementDocker sur Render, autoDeploy sur push Git (github.com/YANIXX35/AH.git)ArchitectureMigration Domain-Driven en cours (pattern strangler fig) : contrôleurs HTTP legacy dans app/Http/Controllers, logique métier migrant vers app/Domain/* par domaine (Treasury, Invoicing, Inventory...)
3. État actuel de la plateforme
3.1 Modules existants avant le cycle de développement en cours

Accounting — saisie comptable, OCR de documents, plan comptable SYSCOHADA, clôtures mensuelles, rapports (journal, grand-livre, bilan, résultat, TAFIRE)
Treasury — suivi des flux de trésorerie, prévisions, verrouillage de périodes (saisie manuelle uniquement à ce stade)
Investor — scoring d'investissement, demandes de financement
Payment — abonnements premium (CinetPay/FedaPay/Stripe), et un module d'initiation de paiement Mobile Money déjà en production
Admin — RBAC, KYC/compliance, licences entreprise, billing, ops center IA

3.2 Modules livrés lors du cycle de développement en cours
ModulePrioritéCe qui est livréConnecteur Mobile Money (lecture) + réconciliationMUSTImport CSV de relevé Wave/Orange Money/MTN, rapprochement automatique avec la trésorerie, création en un clic des mouvements manquants. Consentement RGPD-like (case à cocher horodatée, IP tracée) et droit à l'effacement.Facturation clientSHOULDDistincte de la facturation SITIAME-vers-abonnés. Numérotation séquentielle par PME/année (e-invoicing), écritures 411/701/4431 automatiques, encaissement liable à un mouvement Mobile Money réconcilié, annulation (pas de suppression) si déjà réglée.Stock (négoce)COULDValorisation CUMP (norme OHADA), mouvements entrée/sortie/ajustement, garde-fou anti-stock-négatif, édition de produit avec verrouillage de l'unité de mesure après premier mouvement. Positionné en option, gaté par permission.Étude de faisabilité FedaPayÀ évaluerAnalyse réglementaire BCEAO (Instruction n°001-01-2024). Diagnostic préliminaire : PME360 opère vraisemblablement comme agent de services de paiement non immatriculé. Livrée en document séparé — rien désactivé à ce stade.
Méthode de vérification : chaque module a été testé en conditions réelles (serveur, base de données et session utilisateur réels), ce qui a permis de détecter et corriger plusieurs bugs avant la production.
4. Nouvelles exigences — issues de la revue de plateforme du 14 juillet 2026
4.1 Fiabilisation de la chaîne de validation des factures — MUST
Problème : ambiguïté sur qui valide une facture scannée par un client, ce dernier n'étant généralement pas outillé pour juger de sa conformité comptable. Décision (D1) : rendre la validation automatique une fois un filtre de qualité franchi.

Filtre de qualité à la saisie (D2, A1) : bloquer toute pièce ne comportant pas les informations obligatoires (numéro de pièce, dates, éléments d'identification du tiers).
Validation automatique post-filtre (D1, A1) : une fois le filtre franchi, génération automatique des écritures, sans validation manuelle bloquante par défaut.
Niveau de conformité affiché : conserver et exposer le taux de conformité (ex. 80 % / 100 %) comme signal de confiance, y compris après validation automatique.
IA de vérification en appui (D3, A2) : à spécifier et prototyper, en complément du filtre déterministe, pour réduire la charge des comptables.

4.2 Contrôle qualité périodique des données comptables — MUST
Problème : une entreprise peut accumuler plusieurs années de données non fiabilisées avant de solliciter un financement, invalidant le score produit.

Exigence (D4, A3) : contrôle qualité périodique (cadence proposée : trimestrielle), entreprise par entreprise, avant que les données soient utilisables pour le scoring.
À définir avec la Comptabilité : méthode de contrôle, responsables, échantillonnage.
Dépendance : condition de crédibilité du volet Investor déjà existant.

4.3 Modèle multi-cabinets partenaires — SHOULD (à cadrer)
Permettre à des cabinets comptables/chargés d'affaires partenaires de gérer leurs propres clients sur PME360 sous licence, avec centralisation des données vers SITIAME.

Hiérarchie d'accès à plusieurs niveaux (2.6) : administration / cabinet / client, avec un nombre d'instances et d'administrateurs limité par abonnement (exemple cité : 4 instances / 4 administrateurs par compte).
Point d'attention : les cabinets partenaires n'utiliseraient d'abord que le volet Comptabilité, sans Finance/Scoring — le découpage des droits doit permettre cette activation partielle.
Action (D5, A4) : cadrer niveaux d'accès, gouvernance des instances/administrateurs, centralisation des données, modèle tarifaire, avant tout développement.

4.4 Trésorerie réelle (vs trésorerie théorique) — MUST
Problème : la trésorerie affichée est théorique, pas réellement disponible en banque. Exemple : un chèque scanné est immédiatement comptabilisé comme encaissé, alors que la banque ne crédite qu'après un délai.

Exigence (D7, A6) : intégrer les conditions bancaires du client (délais d'encaissement, dates de valeur).
Distinguer explicitement plan de financement, flux de trésorerie prévisionnel, et trésorerie réelle au jour le jour.
Un modèle Excel d'ajustement en première approximation, en attendant l'intégration native.
Lien avec l'existant : les mouvements réconciliés Mobile Money sont déjà des encaissements réels et immédiats — la logique de délai concerne surtout les instruments bancaires classiques.

4.5 Feuille de route Paiements — SHOULD (approche progressive)
Intégrer les paiements depuis les factures (déclenchement/rapprochement fournisseur, constatation d'encaissement côté vente), en commençant simple avant une intégration bancaire complète.

Dépendance directe avec l'étude de faisabilité FedaPay déjà livrée — statut réglementaire non tranché.
Action (A5) : recenser les éléments d'un dossier d'agrément paiement et étudier les plateformes existantes, à mener conjointement avec l'arbitrage FedaPay.
Recommandation : ne pas lancer de nouveau développement sur cet axe avant l'arbitrage Direction sur FedaPay.

4.6 Validation externe par expert-comptable — MUST (condition de lancement)
Décision (D8, A7) : finaliser d'abord la plateforme, puis associer un expert-comptable externe pour valider les livrables comptables avant le lancement.

Porte de sortie (gate) avant lancement, pas une amélioration continue.
Modalités contractuelles restant à définir.

5. Priorisation MoSCoW consolidée

MUST : Filtre qualité + validation automatique (4.1) · Contrôle qualité périodique (4.2) · Trésorerie réelle (4.4) · Validation expert-comptable avant lancement (4.6)
SHOULD : IA de vérification (4.1) · Modèle multi-cabinets (4.3) · Feuille de route Paiements, conditionnée à l'arbitrage FedaPay (4.5)
COULD : Granularité fine des accès cabinet/client au-delà du strict nécessaire au lancement
WON'T (pour l'instant) : Intégration bancaire complète · Statut réglementaire propre d'établissement de paiement

6. Risques et dépendances

Risque réglementaire non tranché — FedaPay actif sans statut clarifié ; toute extension de la feuille de route Paiements avant l'arbitrage amplifierait ce risque.
Capacité technique limitée — deux développeurs seulement, ce qui renforce la priorité des automatisations sur les processus manuels.
Dépendance scoring ↔ qualité des données — ne pas communiquer sur le scoring avant le contrôle qualité périodique (4.2).
Dépendance modèle partenaires ↔ droits d'accès — suppose une architecture de permissions à étendre, pas reconstruire.
Connecteur Mobile Money non testé sur exports réels multi-opérateurs — risque hérité, à solder avant communication commerciale.

7. Plan d'action et jalons
N°ActionResponsableÉchéanceA1Filtre de qualité + validation automatiqueCTO / DéveloppeursAvant lancementA2Spécifier/prototyper l'IA de vérificationCTO / R&DÀ confirmerA3Processus de contrôle qualité trimestrielCFO / ComptabilitéÀ confirmerA4Cadrer le modèle cabinets partenairesDG / CTOÀ confirmerA5Recenser dossier d'agrément paiementDG / JuristeÀ confirmerA6Modèle Excel trésorerie réelleCFOCe week-endA7Finaliser + contractualiser expert-comptableDGÀ la finalisation
8. Points ouverts à trancher

Répartition définitive du rôle de validation (client / comptables internes / IA)
Modalités précises du modèle cabinets partenaires
Périmètre et calendrier des paiements, dépendant de l'arbitrage FedaPay
Modalités contractuelles avec l'expert-comptable externe
Correction du composer.json (PHP ^8.3 déclaré vs 8.4 utilisé)

Claude est une IA et peut faire des erreurs. Veuillez vérifier les réponses.Prd pme360 · DOCX