# Module Club Sportif — nouveau module PME360, construit sur ERPNext

## Contexte et objectif

Contrairement à tous les sous-projets précédents (Stock, Facturation, Comptabilité), ce module **n'existe pas encore du tout dans PME360**. Objectif confirmé avec l'utilisateur : un nouveau module "Club Sportif" (gestion de membres, cotisations, événements pour une PME cliente qui gère une association/club sportif), construit **directement sur ERPNext** dès le départ — pas de double saisie locale, PME360 affiche ce qui existe sur ERPNext.

**Périmètre** : Membres/adhésions, Cotisations, Événements — les 3 confirmés par l'utilisateur.

## Découverte technique vérifiée empiriquement

- **Le module "Non-Profit" natif d'ERPNext (`Member`, `Membership`, `Chapter`, `Donor`) n'est pas réellement installé** sur cette instance — même limitation que pour le module RH/Paie découverte précédemment (`Error: No module named 'frappe.core.doctype.member'`, malgré le doctype visible dans les métadonnées). Écarté.
- **`Customer`** (déjà utilisé pour la Facturation) fonctionne parfaitement comme substitut pour un "membre" — testé implicitement via `findOrCreateCustomer()` déjà existant, réutilisable tel quel.
- **`Subscription` + `Subscription Plan`** fonctionnent réellement (testés en direct : création d'un plan à 5000 XOF/mois, puis d'une souscription liée à un client, statut `Active` confirmé). ERPNext génère automatiquement de vraies `Sales Invoice` selon le calendrier de facturation défini — **ces factures sont déjà captées par le webhook Facturation existant** (`ErpNextInvoicingWebhookController`), sans rien construire de neuf pour ce flux.
- **`Event`** (doctype natif du cœur Frappe, pas d'une app optionnelle) fonctionne réellement — testé en direct, création réussie (`EV00001`). Porte un champ `reference_doctype`/`reference_name` générique, permettant de lier chaque événement à un document quelconque.
- **Problème d'isolement identifié et résolu** : contrairement au Stock/à la Facturation (scopés par Company), `Customer` n'a pas de champ "company" natif — un simple filtre par `customer_group` partagerait les membres entre PME. Parade : un `Customer Group` **par PME** (`"Membre Club Sportif - {abrégé}"`, même convention que `"Magasin principal - NOT69"`). Pour `Event`, on utilise `reference_doctype: "Company"` / `reference_name: <company de la PME>` pour le même isolement.

## Décisions verrouillées

- **Membres** : pas de stockage local, pas de webhook — PME360 lit en direct la liste des `Customer` filtrés par le `Customer Group` dédié de la PME, à chaque affichage de la page. Volume faible (liste de membres), pas besoin de synchronisation en arrière-plan.
- **Cotisations** : aucune nouvelle mécanique de synchronisation — les factures générées automatiquement par `Subscription` arrivent déjà via le webhook Facturation existant et créent des `Invoice` locales normalement. **Ajout d'un champ `erpnext_subscription` (nullable) sur la table `invoices`**, rempli par `ErpNextInvoicingWebhookController::handleCreation()` quand le document ERPNext porte un champ `subscription` non vide — sert uniquement à filtrer l'affichage ("Cotisations du club" = factures avec ce champ rempli, distinctes des factures commerciales classiques).
- **Événements** : nouveau Webhook ERPNext (`Event`, `on_update` — couvre à la fois création et modification, un événement n'a pas de notion de "soumission" comme les documents comptables), même patron d'authentification que les webhooks existants. Nouvelle table locale `SportEvent` (miroir simple : nom, date, description, référence ERPNext) — contrairement aux Membres, un calendrier mérite un affichage rapide sans dépendre d'un appel API à chaque chargement de page.
- **Nouveau menu PME360** "Club Sportif" (`/sport`), avec 3 sous-pages : Membres (lecture directe ERPNext), Cotisations (filtre sur `Invoice` local), Événements (lecture depuis `SportEvent` local, alimenté par le webhook).
- **Provisionnement** : le `Customer Group` dédié de la PME est créé à la demande (find-or-create) au premier accès à la page Membres — pas besoin de l'ajouter au provisionnement systématique de `provisionCompanyForPme()`, ce module étant optionnel/spécifique à certaines PME seulement (contrairement à Stock/Facturation qui concernent toutes les PME).

## Architecture

### Nouvelles méthodes `ErpNextClient`

```php
public function findOrCreateSportMemberGroup(User $pme): string
```
Find-or-create du `Customer Group` `"Membre Club Sportif - {abbr}"` pour la PME.

```php
public function listSportMembers(User $pme): array
```
`GET /api/resource/Customer` filtré sur `customer_group` = celui de la PME, retourne `{name, customer_name, mobile_no, email_id}`.

```php
public function createSportMember(User $pme, string $name, ?string $mobile, ?string $email): array
```
Crée un `Customer` directement rattaché au groupe dédié de la PME (utilisable depuis PME360 si on veut aussi permettre la création depuis PME360 — à trancher lors du plan, pas bloquant pour le design).

### Nouvelle table + modèle

`sport_events` (migration) + `SportEvent` (modèle) : `user_id`, `erpnext_event_name` (unique), `subject`, `starts_on`, `description`.

### Nouveau contrôleur webhook

`ErpNextSportEventWebhookController::handle()` — même patron que les 3 webhooks existants (jeton partagé, résolution PME via `reference_name` = Company), `SportEvent::updateOrCreate()`.

### Modification du webhook Facturation existant

`ErpNextInvoicingWebhookController::handleCreation()` lit `$document['subscription'] ?? null` et le stocke sur l'`Invoice` créée (nouvelle colonne `erpnext_subscription`).

### Nouveau module PME360

`SportController` (`index`, `members`, `cotisations`, `events`), routes `/sport/*`, vues dédiées — nouveau module isolé, aucun fichier existant touché en dehors de la modification ciblée du webhook Facturation.

## Tests (vérification manuelle)

1. Créer un `Customer` directement sur ERPNext dans le groupe dédié d'une PME → vérifier qu'il apparaît dans `/sport/membres` de cette PME, et **pas** dans celle d'une autre PME.
2. Créer un `Subscription Plan` + une `Subscription` liée à ce membre sur ERPNext → vérifier qu'une facture apparaît normalement sur `/invoicing`, ET dans `/sport/cotisations` (filtrée grâce à `erpnext_subscription`).
3. Créer un `Event` sur ERPNext lié (`reference_name`) à la Company d'une PME → vérifier qu'il apparaît dans `/sport/evenements` de cette PME uniquement.

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — 3 nouvelles méthodes)
- `database/migrations/..._create_sport_events_table.php` + `..._add_erpnext_subscription_to_invoices_table.php` (nouvelles)
- `app/Models/SportEvent.php` (nouveau)
- `app/Http/Controllers/ErpNextSportEventWebhookController.php` (nouveau)
- `app/Http/Controllers/ErpNextInvoicingWebhookController.php` (modifié — une ligne pour capter `subscription`)
- `app/Http/Controllers/SportController.php` (nouveau)
- `resources/views/sport/*.blade.php` (nouvelles)
- `routes/web.php` (nouvelles routes, dont le nouveau webhook hors `auth`)
- `bootstrap/app.php` (nouvelle exemption CSRF)

Ne pas toucher : tout le reste des modules Stock/Facturation/Comptabilité déjà construits.
