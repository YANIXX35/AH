# Correction de 2 bugs comptables dans le pipeline Facturation → Écritures — Design

## Contexte

En comparant le moteur réel d'ERPNext (GL Entry, lu dans le code source cloné `frappe-source/erpnext`) avec `InvoiceService` de PME360, deux vrais bugs comptables ont été identifiés — pas des différences d'architecture, mais des cas où PME360 perd ou omet des écritures que n'importe quel moteur comptable correct doit produire.

### Bug 1 — Un encaissement synchronisé depuis ERPNext ne génère jamais d'écriture de trésorerie

`InvoiceService::recordPayment()` n'est appelé qu'à un seul endroit dans toute l'application : `ErpNextInvoicingWebhookController::handlePayment()` (ligne 152-165), avec `'treasury_account_code' => null` **codé en dur**. Le corps de `recordPayment()` :

```php
$accountingEntry = null;
if (! empty($data['treasury_account_code'])) {
    $accountingEntry = AccountingEntry::create([...]);
}
```

Comme `treasury_account_code` est toujours `null` à cet unique point d'appel, **cette condition n'est jamais vraie** — aucune écriture (débit compte de trésorerie / crédit 411) n'est jamais créée pour un encaissement, alors que le paiement est bien enregistré (`InvoicePayment`, `amount_paid`, `status`). Il n'existe aucun autre formulaire/route créant un paiement manuellement (confirmé : `grep -rn "recordPayment(" app/` ne renvoie qu'un seul résultat) — c'est donc systématique, pas un cas limite.

ERPNext, lui, résout dynamiquement le compte de trésorerie via `Payment Entry.paid_to` (le compte réel choisi lors du paiement dans ERPNext) et génère toujours la ligne bancaire correspondante (`add_bank_gl_entries`).

### Bug 2 — Annuler une facture dans ERPNext laisse les écritures de vente/TVA intactes côté PME360

`InvoiceService::cancelInvoice()` (lignes 242-263) change uniquement le statut de la facture (`status`, `cancelled_at`, `cancelled_reason`) et journalise un `TreasuryAudit`. **Aucune écriture de contrepassation n'est créée** — les deux `AccountingEntry` de vente (411/701) et de TVA (411/4431) créées à la facturation restent en place comme si la facture était toujours valide, faussant durablement le chiffre d'affaires et la TVA collectée affichés dans les rapports.

ERPNext, lui, génère systématiquement des écritures inverses à l'annulation (`make_reverse_gl_entries` dans `general_ledger.py`).

**Fait important qui simplifie la correction** : `cancelInvoice()` a déjà un garde-fou —

```php
if ($invoice->status === 'paid' || (float) $invoice->amount_paid > 0) {
    throw new \InvalidArgumentException('Facture déjà réglée ... impossible d\'annuler, établir un avoir.');
}
```

Une facture ne peut donc être annulée que si elle n'a **jamais reçu aucun encaissement** — il n'y a donc jamais d'écriture de trésorerie à contrepasser en même temps que la vente, seulement les 2 écritures `facture_vente` (vente + TVA le cas échéant).

## Décisions de conception

### Fix 1 — Résolution dynamique du compte de trésorerie

`ErpNextInvoicingWebhookController::handlePayment()` doit résoudre `$document['payment_type']`/`$document['paid_to']` (champs du `Payment Entry` ERPNext, déjà disponibles dans `$document` car celui-ci est relu intégralement via `ErpNextClient::getDocument()`, pas limité au payload du webhook) en un code de compte local, exactement comme `ErpNextAccountingEntryWebhookController::resolveLocalAccount()` le fait déjà pour les écritures génériques (même repli 4→7 chiffres, déjà vérifié en production).

Cette logique de résolution est donc nécessaire **deux fois** dans la codebase (Comptabilité et maintenant Facturation) — décision : l'extraire dans `ErpNextClient` en méthode publique `resolveLocalAccountCode(User $pme, string $erpNextAccountName): ?string`, avec un corps strictement identique à l'actuel `resolveLocalAccount()` (aucun changement de comportement). `ErpNextAccountingEntryWebhookController` est mis à jour pour appeler `$erpNext->resolveLocalAccountCode(...)` au lieu de sa méthode privée, qui est supprimée — élimine la duplication sans changer le comportement déjà vérifié en production.

Portée volontairement limitée : seuls les `Payment Entry` de type `payment_type === 'Receive'` (encaissement client) sont traités — c'est le seul cas qui peut apparaître ici, puisque la boucle de `handlePayment()` filtre déjà sur `reference_doctype === 'Sales Invoice'` (PME360 ne gère pas les factures fournisseur). Si `paid_to` ne trouve aucune correspondance locale, on logue un avertissement (même pattern que `ErpNextAccountingEntryWebhookController`) et on laisse `treasury_account_code` à `null` — le paiement reste enregistré (`InvoicePayment`) même sans écriture comptable, pour ne pas bloquer la synchronisation ; seul le paiement `null` silencieux disparaît, un vrai échec de correspondance reste visible dans les logs.

### Fix 2 — Écritures de contrepassation à l'annulation

Nouvelle méthode privée `InvoiceService::reverseSaleAccountingEntries(Invoice $invoice, int $actorUserId): void`, appelée depuis `cancelInvoice()` juste après la mise à jour du statut. Elle :
- Cherche tous les `AccountingEntry` où `document_type = 'facture_vente'` et `document_reference = $invoice->invoice_number` (les 1 ou 2 lignes créées par `createSaleAccountingEntries()`).
- Pour chacune, crée une nouvelle `AccountingEntry` avec `debit_account`/`credit_account` inversés, même montant, `document_type = 'annulation_facture'`, même `document_reference`, datée du jour de l'annulation (pas de la facture d'origine — convention comptable standard : la contrepassation est datée de l'événement d'annulation).
- Ne supprime ni ne modifie les écritures d'origine (traçabilité complète : la facture, sa vente, sa TVA, et son annulation restent toutes visibles dans le Grand Livre).

**Idempotence ajoutée** : `cancelInvoice()` retourne immédiatement (no-op) si `$invoice->status === 'cancelled'` déjà — garde-fou nécessaire car le webhook ERPNext peut théoriquement livrer un événement `on_cancel` en double (retry HTTP), ce qui créerait sinon une deuxième paire d'écritures de contrepassation.

## Fichiers concernés

- `app/Services/ErpNextClient.php` — ajout de `resolveLocalAccountCode()` (méthode extraite, comportement identique à l'existant `resolveLocalAccount()`).
- `app/Http/Controllers/ErpNextAccountingEntryWebhookController.php` — `resolveLocalAccount()` privée supprimée, remplacée par un appel à `$erpNext->resolveLocalAccountCode(...)`.
- `app/Http/Controllers/ErpNextInvoicingWebhookController.php` — `handlePayment()` reçoit `ErpNextClient $erpNext` et `User $pme` en paramètres (déjà disponibles dans `handle()`), résout `treasury_account_code` avant d'appeler `recordPayment()`.
- `app/Domain/Invoicing/InvoiceService.php` — `cancelInvoice()` : ajout du guard d'idempotence + appel à la nouvelle méthode privée `reverseSaleAccountingEntries()`.

## Hors périmètre (décision explicite)

- Les comptes en dur (`411`/`701`/`4431`) dans `createSaleAccountingEntries()` ne sont **pas** touchés par ce sous-projet — c'est le deuxième chantier identifié (comptes configurables par PME), plus large, traité séparément si besoin.
- Aucune gestion multi-devise / écart de change — hors sujet pour une zone SYSCOHADA/FCFA mono-devise.
- Les paiements fournisseurs (`payment_type === 'Pay'`) ne sont pas traités — PME360 n'a pas de facture fournisseur dans son périmètre actuel.

## Vérification

Comme pour tous les sous-projets précédents de cette session, PHPUnit ne peut pas tourner en local (PHP 8.2 vs 8.4) — vérification manuelle via `tinker` en local, puis en production après déploiement.

1. **Fix 1** : simuler (via Reflection ou appel direct de `handlePayment()`) un `Payment Entry` ERPNext avec `payment_type: 'Receive'`, `paid_to: '<compte trésorerie réel de la PME test>'`, et une référence vers une facture synchronisée existante → vérifier qu'une `AccountingEntry` est créée avec le bon `debit_account` (résolu localement) et `credit_account = '411'`.
2. **Fix 1 — cas d'échec de résolution** : même test avec un `paid_to` qui ne correspond à aucun compte local → vérifier qu'aucune `AccountingEntry` n'est créée (comportement inchangé) mais qu'un `Log::warning` est émis.
3. **Fix 2** : créer une facture de test avec TVA > 0 (génère 2 `AccountingEntry` `facture_vente`), l'annuler via `cancelInvoice()` → vérifier que 2 nouvelles `AccountingEntry` `annulation_facture` apparaissent avec débit/crédit inversés et même montant, et que les 2 entrées d'origine restent inchangées.
4. **Fix 2 — idempotence** : appeler `cancelInvoice()} une seconde fois sur la même facture déjà annulée → vérifier qu'aucune écriture supplémentaire n'est créée.
5. **Non-régression** : vérifier que `ErpNextAccountingEntryWebhookController` continue de résoudre correctement les comptes ERPNext→locaux après l'extraction de `resolveLocalAccountCode()` (rejouer un test déjà validé en production lors du sous-projet Comptabilité).
6. Vérification live en production sur une vraie PME (ex. "NotifyMails #69") : créer/encaisser/annuler une facture réelle dans ERPNext, confirmer dans PME360 (`/accounting/report/journal` ou `/accounting/report/grand-livre`) que les écritures de trésorerie et de contrepassation apparaissent correctement.
