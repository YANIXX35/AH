# Caisse Banque — suivi payé/impayé des écritures comptables

Statut : validé (design approuvé par l'utilisateur le 2026-08-24)

## Contexte et problème

Le module **Gestion des écritures** (`AccountingEntry`) ne porte aujourd'hui aucune
notion de règlement : une écriture n'a que Débit / Crédit / Montant. Impossible de
savoir, en le regardant, si une facture d'achat a été payée au fournisseur ou si un
client a réglé sa facture de vente.

Le module **Trésorerie** (`TreasuryTransaction`) existe séparément et n'est connecté
aux écritures que de façon incomplète : seuls les documents importés par OCR dont le
débit ou le crédit touche un compte de trésorerie génèrent, une seule fois et
automatiquement, un mouvement lié (`AccountingDocumentController::syncTreasuryMovementFromDocument`,
référence synthétique `DOC-BANK-{document_id}`). Les écritures saisies manuellement
(`AccountingController::storeEntry`) ne créent aucun mouvement de trésorerie, et rien
ne permet d'enregistrer un règlement ultérieur, ni de suivre des règlements partiels.

## Objectif

Ajouter un statut de règlement (Impayé / Partiellement payé / Payé) à chaque écriture,
avec la possibilité d'enregistrer un ou plusieurs paiements contre elle, chacun créant
automatiquement le mouvement de Trésorerie correspondant — le tout accessible depuis un
nouvel onglet **Caisse Banque**, rattaché à l'écran "Moteur Comptable & Saisie".

## Périmètre

- **Toutes** les écritures portent un statut de règlement, sans exception (validé avec
  l'utilisateur — y compris les "Justificatif", voir règle de statut par défaut
  ci-dessous).
- Les paiements peuvent être **partiels** — plusieurs règlements successifs sur une
  même écriture, comme le fait déjà `InvoiceController::storePayment` pour la
  Facturation Client (même esprit de design, appliqué ici au journal général).
- Chaque paiement enregistré crée un mouvement `TreasuryTransaction` lié (encaissement
  ou décaissement selon le sens de l'écriture).
- Hors périmètre : rapprochement bancaire automatique (relevé importé vs paiements
  enregistrés) — reste géré par l'écran "Rapprochement bancaire" existant, inchangé.

## Modèle de données

### Colonnes ajoutées à `accounting_entries`

| Colonne | Type | Rôle |
|---|---|---|
| `payment_status` | enum `unpaid`\|`partial`\|`paid`, défaut `unpaid` | Statut affiché, recalculé à chaque paiement |
| `amount_paid` | decimal(15,2), défaut `0` | Cache de la somme des paiements liés, évite un `SUM()` à chaque affichage de liste |

### Nouvelle table `accounting_entry_payments`

| Colonne | Type |
|---|---|
| `id` | bigint |
| `accounting_entry_id` | FK → `accounting_entries.id`, cascade delete |
| `user_id` | FK → `users.id` (dossier propriétaire, cohérent avec `workspaceDataUserIds()`) |
| `actor_user_id` | FK → `users.id` (qui a saisi le paiement) |
| `amount` | decimal(15,2) |
| `payment_date` | date |
| `method` | string (mobile_money, banque, especes, autre) |
| `reference` | string nullable |
| `treasury_transaction_id` | FK nullable → `treasury_transactions.id` |
| timestamps | |

### Règle de statut par défaut à la création d'une écriture

Une écriture dont le débit **ou** le crédit touche déjà un compte de trésorerie
(classe 5 — 512 Banque, 571 Caisse...) est automatiquement créée avec
`payment_status = paid` et `amount_paid = amount`, car l'argent a déjà bougé au moment
de la saisie — aucun paiement futur n'est attendu. C'est le cas typique d'un
"Justificatif" (`627 Services bancaires` / `512 Banque`), et de tout "Reçu" dont le
débit est `512 Banque`.

Toute écriture avec un compte tiers (401 Fournisseurs, 411 Clients) au débit ou au
crédit, sans compte de trésorerie en face, démarre `payment_status = unpaid` — c'est le
cas typique d'un "Achat" (`607` / `401`) ou d'une "Vente" (`411` / `701`) enregistrée
avant règlement.

La détection réutilise la même règle que `AccountingDocumentController::isClassFiveAccount()`
(compte dont le premier chiffre significatif est 5), appliquée aux deux côtés de
l'écriture.

## Comportement du paiement

1. L'utilisateur clique **"Enregistrer un paiement"** sur une écriture Impayée ou
   Partielle depuis l'écran Caisse Banque.
2. Formulaire : Montant, Date, Méthode, Référence, Compte de trésorerie à utiliser
   (champ texte libre, pré-rempli à "512 Banque" — même pattern que le champ
   `bank_account` déjà utilisé dans `treasury/create.blade.php` et dans
   `syncTreasuryMovementFromDocument`, qui n'est pas une liste fermée mais une
   chaîne libre).
3. À la validation :
   - une ligne `accounting_entry_payments` est créée ;
   - un `TreasuryTransaction` est créé (type `encaissement` si le débit de l'écriture
     est un compte tiers/produit — vente réglée — sinon `decaissement` — achat payé —
     déterminé par le même sens que `syncTreasuryMovementFromDocument`), avec
     `payment_module = 'accounting_entry_payment'` et une référence synthétique
     `ENTRY-PAY-{payment_id}` pour rester idempotent en cas de double-soumission ;
   - `accounting_entries.amount_paid` est recalculé (`SUM` des paiements liés) et
     `payment_status` recalculé : `paid` si `amount_paid >= amount`, `partial` si
     `0 < amount_paid < amount`, `unpaid` sinon.
4. Le montant d'un paiement ne peut pas dépasser le solde restant dû (validation
   serveur), pour éviter un `amount_paid > amount`.

## Écran "Caisse Banque"

- **Emplacement** : nouvelle carte dans la grille de "Moteur Comptable & Saisie" (à
  côté de Journal / Grand livre / Balance...), route `accounting.caisse-banque`, plus
  une entrée correspondante dans le sous-menu Comptabilité du sidebar.
- **En-tête** : 3 compteurs — Total impayé, Total partiellement payé, Total payé
  (FCFA), calculés sur le filtre actif.
- **Filtres** : statut (Tous/Impayé/Partiel/Payé), type de document, compte, date —
  réutilisent la même mécanique de filtre que le Journal existant (`request()->query()`
  + formulaire GET, avec soumission automatique sur les `<select>` — cf. le correctif
  déjà appliqué au filtre Type du Journal).
- **Tableau** : Date · Document · Description · Tiers · Montant total · Montant réglé ·
  Solde dû · Statut (badge coloré : gris Impayé / orange Partiel / vert Payé) · Action.
- **Action par ligne** : "Enregistrer un paiement" (si Impayé/Partiel) ; "Voir les
  paiements" (historique des règlements déjà enregistrés sur cette écriture, utile en
  cas de partiels multiples).

## Vérification

La suite PHPUnit ne peut pas tourner en local sur cette machine (PHP 8.2 installé vs
8.4 requis) — vérification manuelle en local (`php -l`, compilation Blade) puis en
production, comme pour le reste des correctifs de cette session.

Scénarios à couvrir :
1. Écriture "Achat" créée manuellement → statut Impayé par défaut.
2. Écriture "Justificatif" créée manuellement → statut Payé par défaut, aucun paiement
   à enregistrer.
3. Enregistrer un paiement partiel sur une écriture Achat → statut passe à Partiel,
   solde dû recalculé, mouvement de Trésorerie créé en décaissement.
4. Enregistrer un second paiement qui solde le reste → statut passe à Payé.
5. Tenter d'enregistrer un paiement supérieur au solde dû → rejeté avec message
   d'erreur explicite.
6. Filtrer l'écran Caisse Banque par statut → seules les écritures correspondantes
   s'affichent.
