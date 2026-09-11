# Branchement du module Facturation sur ERPNext — Design Spec

## Contexte et objectif

Suite au sous-projet 1 (provisionnement automatique d'une Company ERPNext par PME, voir `docs/superpowers/specs/2026-09-11-erpnext-pme-provisioning-design.md`), l'objectif est de connecter le vrai module Facturation existant de PME360 (`InvoiceController` / `App\Domain\Invoicing\InvoiceService` / `Invoice`) à la Company ERPNext de chaque PME, pour que la comptabilité SYSCOHADA de la PME vive réellement dans ERPNext (Bilan, Compte de résultat), en plus de la comptabilité locale déjà existante (`AccountingEntry`/`PlanComptableAccount`).

**Ce qui existe déjà et reste inchangé** : `InvoiceService::createInvoice()` calcule déjà les totaux (`subtotal`, `tax_amount` via un `tax_rate` global, `total_amount`), persiste `Invoice` + `InvoiceItem[]`, génère déjà ses propres écritures `AccountingEntry` (411/701 vente, 411/4431 TVA). `recordPayment()` gère les paiements (`InvoicePayment`, `amount_paid`, statut). `cancelInvoice()` change le statut sans reverser les écritures (refuse si la facture est déjà payée). Rien de tout cela n'est modifié — ce sous-projet ajoute une synchronisation **en plus**, jamais à la place.

## Décisions verrouillées (issues du brainstorming)

- Synchronisation en **file d'attente** (Jobs Laravel), jamais synchrone — la facturation locale ne doit jamais dépendre de la disponibilité d'ERPNext, même principe que le sous-projet 1.
- Périmètre v1 : **création + paiement + annulation** (pas la modification d'une facture existante — `updateInvoice()` n'est déclenché que si la facture est encore non payée côté PME360 ; répercuter une modification sur une facture déjà soumise dans ERPNext ouvrirait une complexité disproportionnée pour cette version, donc **hors scope explicite**).
- La facture ERPNext est **soumise** (`docstatus: 1`) immédiatement après création — sinon elle n'alimente aucun rapport financier ERPNext (Bilan, Compte de résultat), ce qui viderait l'intégration de son intérêt.
- Le paiement est répercuté via un **Payment Entry** ERPNext (mécanisme natif prévu pour ça).
- L'annulation utilise le **mécanisme natif d'annulation ERPNext** (`docstatus: 2`), qui reverse automatiquement les écritures comptables côté ERPNext — plus robuste que le comportement actuel de PME360 (qui ne reverse rien localement).
- Aucun de ces Jobs ne doit jamais faire échouer ou bloquer l'opération PME360 correspondante (création/paiement/annulation locaux restent toujours la source de vérité immédiate pour l'utilisateur).

## Architecture

### Nouvelles tables

**`invoice_erpnext_customers`** — déduplique le "Customer" ERPNext d'un client final d'une PME (il n'existe aucun modèle `Client` aujourd'hui, `client_name`/`client_tax_id` sont du texte libre sur `Invoice`) :
```
- id
- user_id (FK -> users, la PME)
- dedup_key (string, indexé avec user_id) : client_tax_id normalisé si renseigné, sinon client_name normalisé (minuscules, espaces réduits)
- erpnext_customer_name (string)
- timestamps
```
Index unique sur `(user_id, dedup_key)`.

**`invoice_erpnext_syncs`** — un enregistrement par `Invoice`, trace son état de synchronisation :
```
- id
- invoice_id (FK -> invoices, unique)
- status (string 16, défaut 'pending' : pending/synced/failed)
- erpnext_invoice_name (string, nullable)
- last_error (text, nullable)
- last_synced_at (timestamp, nullable)
- raw_response (json, nullable)
- timestamps
```

### Extension de `ErpNextClient`

Trois nouvelles méthodes publiques, suivant le pattern déjà établi (`post()`/`get()` privés existants) :

```php
public function findOrCreateCustomerForPme(User $pme, string $clientName, ?string $clientTaxId): string
```
- Calcule `dedup_key` (normalisation simple : `Str::lower(Str::squish(...))`), cherche dans `invoice_erpnext_customers` pour `(user_id: $pme->id, dedup_key)`.
- Si trouvé, vérifie que le Customer existe toujours côté ERPNext (`GET /api/resource/Customer/{name}`, même logique que `findOrCreateCustomer()` existant) ; sinon recrée.
- Sinon, `POST /api/resource/Customer` avec `company: $pme->erpnext_company_name`, `customer_group: 'Commercial'`, `territory: 'Ivory Coast'`. Enregistre le mapping dans `invoice_erpnext_customers`.

```php
public function createAndSubmitSalesInvoiceForPme(User $pme, string $erpNextCustomerName, Invoice $invoice): array
```
- Construit les lignes à partir de `$invoice->items` (chaque `InvoiceItem` → `findOrCreateItem($item->description)` déjà existant, `qty: $item->quantity`, `rate: $item->unit_price`, `warehouse: $pme->erpnext_warehouse`, `income_account: $pme->erpnext_income_account`).
- `POST /api/resource/Sales Invoice` avec `company: $pme->erpnext_company_name`, `customer: $erpNextCustomerName`, `posting_date: $invoice->issue_date`, `due_date: $invoice->due_date` ; si `$invoice->tax_rate > 0`, applique `taxes_and_charges: $pme->erpnext_tax_template` + les lignes de taxe explicites (même contournement déjà découvert et implémenté pour le dashboard de test — l'API brute ne calcule pas les taxes à partir du seul nom de gabarit).
- Une fois créée, **soumet** le document : `PUT /api/resource/Sales Invoice/{name}` avec `docstatus: 1` (déclenche la validation ERPNext et la génération des écritures comptables réelles).
- Retourne la réponse complète (utilisée pour `raw_response`).

```php
public function recordPaymentForPme(User $pme, string $erpNextInvoiceName, InvoicePayment $payment): array
```
- `POST /api/resource/Payment Entry` avec `payment_type: 'Receive'`, `company: $pme->erpnext_company_name`, `party_type: 'Customer'`, `party: <résolu depuis la facture>`, `paid_amount: $payment->amount`, `posting_date: $payment->paid_at`, `references: [{ reference_doctype: 'Sales Invoice', reference_name: $erpNextInvoiceName, allocated_amount: $payment->amount }]`.
- Soumet également ce Payment Entry (`docstatus: 1`), pour qu'il réduise réellement `outstanding_amount` côté ERPNext.

```php
public function cancelSalesInvoiceForPme(string $erpNextInvoiceName): void
```
- `PUT /api/resource/Sales Invoice/{name}` avec `docstatus: 2` (annulation native ERPNext).

### Trois Jobs

Chacun suit le pattern déjà établi par `ProvisionErpNextCompanyForPme` (`ShouldQueue`, garde-fou d'entrée, laisse les exceptions remonter pour que Laravel marque le Job en échec) :

**`SyncInvoiceToErpNext`** (`app/Jobs/SyncInvoiceToErpNext.php`)
- Reçoit `Invoice $invoice`.
- Garde-fou : si `InvoiceErpNextSync` existe déjà avec `status = 'synced'` pour cette facture, ne rien refaire.
- Garde-fou : si `$invoice->user->erpnext_company_name` est vide (PME pas encore provisionnée — cas rare mais possible si le Job du sous-projet 1 a échoué), marque la sync `failed` avec un message explicite et sort, sans exception bruyante.
- Crée/met à jour la ligne `InvoiceErpNextSync` en `pending`, appelle `findOrCreateCustomerForPme()` puis `createAndSubmitSalesInvoiceForPme()`, met à jour la ligne en `synced` (avec `erpnext_invoice_name`, `raw_response`) ou `failed` (avec `last_error`) selon le résultat — **capturé dans le Job lui-même** (contrairement au sous-projet 1) car un échec de facture individuelle ne doit pas empêcher l'utilisateur de réessayer plus tard sans intervention admin ; le Job se termine toujours normalement, l'échec est visible via le statut `failed` en base, pas via `failed_jobs`.

**`SyncInvoicePaymentToErpNext`** (`app/Jobs/SyncInvoicePaymentToErpNext.php`)
- Reçoit `InvoicePayment $payment`.
- Garde-fou : si la facture associée n'a pas de sync `synced` avec `erpnext_invoice_name` renseigné, log un avertissement et sort (rien à faire, la facture n'a jamais été synchronisée).
- Appelle `recordPaymentForPme()`. Erreurs loggées (`Log::warning`), pas de nouvelle table de suivi dédiée pour cette v1 (le paiement reste de toute façon visible/correct côté PME360 quoi qu'il arrive côté ERPNext).

**`SyncInvoiceCancellationToErpNext`** (`app/Jobs/SyncInvoiceCancellationToErpNext.php`)
- Reçoit `Invoice $invoice`.
- Même garde-fou que le paiement (nécessite un `erpnext_invoice_name` synchronisé).
- Appelle `cancelSalesInvoiceForPme()`. Erreurs loggées.

### Points de déclenchement dans `InvoiceService`

Dans `app/Domain/Invoicing/InvoiceService.php` :
- `createInvoice()` (fin de méthode, après la création de `Invoice`+`InvoiceItem[]`+`AccountingEntry`, ~ligne 90) : `SyncInvoiceToErpNext::dispatch($invoice);`
- `recordPayment()` (fin de méthode, après la création de `InvoicePayment`, ~ligne 153) : `SyncInvoicePaymentToErpNext::dispatch($payment);`
- `cancelInvoice()` (fin de méthode, après le changement de statut, ~ligne 243) : `SyncInvoiceCancellationToErpNext::dispatch($invoice);`

Aucune autre modification à `InvoiceService`, `InvoiceController`, ou aux vues existantes — le calcul des totaux, la génération du PDF, les écritures comptables locales restent strictement identiques à aujourd'hui.

### Visibilité pour l'utilisateur

Hors scope pour cette v1 : pas de nouvel écran affichant le statut de synchronisation ERPNext dans l'interface PME. Cette information reste consultable uniquement via la base de données (`invoice_erpnext_syncs`) ou un futur outil admin — cohérent avec le principe qu'ERPNext est un mécanisme annexe invisible pour l'utilisateur final à ce stade.

## Tests (vérification manuelle)

1. Créer une facture PME360 pour une PME déjà provisionnée (sous-projet 1) → vérifier que la facture PME360 se crée normalement (PDF, écriture comptable locale inchangés) et que, après traitement de la file, `invoice_erpnext_syncs` passe à `synced` avec un vrai numéro ERPNext, visible et **soumis** (`docstatus: 1`) dans ERPNext, apparaissant dans son Bilan/Compte de résultat.
2. Créer une deuxième facture pour la même PME, même `client_name`/`client_tax_id` → vérifier qu'aucun nouveau Customer ERPNext n'est créé (réutilisation via `invoice_erpnext_customers`).
3. Enregistrer un paiement sur une facture synchronisée → vérifier qu'un Payment Entry apparaît dans ERPNext et que `outstanding_amount` de la facture ERPNext diminue en conséquence.
4. Annuler une facture non payée et synchronisée → vérifier que la facture passe en `docstatus: 2` dans ERPNext (annulée), et que les écritures ERPNext sont bien reversées automatiquement.
5. Facture pour une PME dont le provisionnement (sous-projet 1) a échoué (pas de `erpnext_company_name`) → vérifier que la facture PME360 se crée normalement, et que `invoice_erpnext_syncs` passe à `failed` avec un message clair, sans exception ni impact utilisateur.
6. Couper `ERPNEXT_API_KEY` temporairement → créer une facture → vérifier `failed` proprement enregistré, aucun impact sur la création locale.

## Fichiers concernés

- `database/migrations/xxxx_create_invoice_erpnext_customers_table.php` (nouveau)
- `database/migrations/xxxx_create_invoice_erpnext_syncs_table.php` (nouveau)
- `app/Models/InvoiceErpNextCustomer.php` (nouveau)
- `app/Models/InvoiceErpNextSync.php` (nouveau)
- `app/Services/ErpNextClient.php` (modifié — 4 nouvelles méthodes publiques)
- `app/Jobs/SyncInvoiceToErpNext.php` (nouveau)
- `app/Jobs/SyncInvoicePaymentToErpNext.php` (nouveau)
- `app/Jobs/SyncInvoiceCancellationToErpNext.php` (nouveau)
- `app/Domain/Invoicing/InvoiceService.php` (modifié — 3 lignes de dispatch ajoutées, aucune autre logique touchée)

Ne pas toucher : `InvoiceController`, `Invoice`, `InvoiceItem`, `InvoicePayment`, les vues de facturation, `AccountingEntry`/`PlanComptableAccount` (comptabilité locale inchangée), le dashboard `admin/erpnext-test/*`.
