# Facturation — ERPNext comme point de saisie unique (webhook entrant)

## Contexte et objectif

Deuxième module basculé sur le patron "ERPNext = point de saisie, PME360 = affichage", après le Stock. Confirmé avec l'utilisateur : **création, paiement et annulation de factures se font désormais exclusivement sur ERPNext** — PME360 reçoit et reflète automatiquement ces événements via webhook, comme pour le Stock.

**Différence majeure avec le Stock** : la Facturation a des enjeux légaux/comptables que le Stock n'a pas :
- Une **numérotation séquentielle sans trou** des factures par PME et par année (`InvoiceService::allocateInvoiceNumber()`, exigence de conformité e-invoicing UEMOA) — doit continuer à fonctionner normalement même pour une facture dont l'origine est ERPNext.
- De **vraies écritures comptables locales** (411/701 OHADA à la création, 411/trésorerie à l'encaissement) que le reporting comptable de PME360 (Bilan, Compte de résultat) utilise déjà — doivent continuer à être générées, pas contournées.

## Décision d'architecture centrale

**Réutiliser `InvoiceService::createInvoice()`/`recordPayment()`/`cancelInvoice()` tels quels**, en les alimentant avec des données extraites du document ERPNext reçu par webhook, plutôt que de réécrire la logique comptable/numérotation. Ainsi, une facture reçue depuis ERPNext obtient un numéro `FA-{année}-{numéro}` généré localement (dans l'ordre réel de réception, donc toujours sans trou) et les mêmes écritures 411/701 que si elle avait été saisie sur PME360.

**Nouveau paramètre sur les 3 méthodes** : `bool $skipErpNextSync = false`. Le chemin webhook l'utilise à `true` pour empêcher que la donnée qu'on vient de recevoir d'ERPNext reparte immédiatement se synchroniser vers ERPNext (boucle infinie évitée) — les jobs `SyncInvoiceToErpNext`/`SyncInvoicePaymentToErpNext`/`SyncInvoiceCancellationToErpNext` existants restent inchangés, simplement pas dispatchés dans ce cas précis.

## Décisions verrouillées

- **Trois Webhooks ERPNext** (créés via l'API, même patron que le Stock) :
  1. `Sales Invoice`, `on_submit` → création.
  2. `Payment Entry`, `on_submit` → paiement.
  3. `Sales Invoice`, `on_cancel` → annulation.
  Tous pointent vers une nouvelle route unique `POST /webhooks/erpnext/invoicing`, payload minimal `{doctype, name, company}` — même relecture différée du document complet via `ErpNextClient::getDocument()` (déjà construite pour le Stock, réutilisée telle quelle).
- **Résolution de la PME** : identique au Stock (`User::where('erpnext_company_name', ...)`), 200 silencieux si aucune correspondance.
- **Résolution de la facture locale** (pour paiement/annulation) : via la table `invoice_erpnext_syncs` déjà existante, en sens inverse (`InvoiceErpNextSync::where('erpnext_invoice_name', $payload['name'])->first()?->invoice`) — cette table sert déjà à l'autre sens, elle est complétée/mise à jour dans les deux sens désormais.
- **Extraction des données depuis le `Sales Invoice` ERPNext reçu** (création) :
  - `client_name` = `customer_name` du document.
  - `client_tax_id`/`client_contact`/`client_address` : laissés `null` si non présents sur le `Customer` ERPNext (limite assumée — ERPNext ne stocke pas forcément ces champs pour les clients créés côté PME360).
  - `items` : chaque ligne (`item_name`, `qty`, `rate`) → `{description, quantity, unit_price}`.
  - `tax_rate` : lu depuis la première ligne de `taxes` du document (`rate`), `0` si absent — cohérent avec le gabarit TVA à taux unique déjà utilisé partout ailleurs dans ce projet.
  - `issue_date`/`due_date` = `posting_date`/`due_date`.
  - `currency` = `currency` du document (repli `XOF`).
- **Extraction depuis le `Payment Entry` ERPNext reçu** (paiement) : `references` (table enfant) donne la facture ERPNext ciblée (`reference_name`) et le montant alloué (`allocated_amount`) — c'est ce montant qui alimente `recordPayment()`, pas `paid_amount` total (un même Payment Entry peut, en théorie, couvrir plusieurs factures — chaque ligne de `references` déclenche son propre `recordPayment()`).
- **Compte de trésorerie local** : non renseigné (`treasury_account_code` omis) lors d'un paiement reçu depuis ERPNext — `recordPayment()` gère déjà ce cas (pas d'écriture de trésorerie locale générée, uniquement l'`InvoicePayment` et le nouveau statut de la facture) — limite assumée, cohérente avec le comportement optionnel déjà existant de ce paramètre.
- **Annulation** : si la facture locale a déjà un `amount_paid > 0`, `cancelInvoice()` refuse (règle métier locale déjà existante, volontairement conservée) — l'échec est capturé et journalisé, jamais laissé remonter en erreur HTTP vers ERPNext.
- **Retrait des formulaires PME360** : `InvoiceController::create()`/`store()`/`edit()`/`update()`/`storePayment()`/`cancel()`/`destroy()` et leurs routes sont supprimés. `index()`/`show()`/`downloadPdf()`/`export()` restent (consultation seule).
- **Idempotence** : chaque appel relit l'état réel ; une facture déjà connue localement (déjà mappée dans `invoice_erpnext_syncs`) qui recevrait un second `on_submit` (ne devrait normalement pas arriver) ne recrée pas de doublon — vérification `if InvoiceErpNextSync::where('erpnext_invoice_name', ...)->exists()` avant de créer.

## Tests (vérification manuelle)

1. Créer et soumettre une `Sales Invoice` directement sur ERPNext pour NotifyMails → vérifier qu'une facture apparaît sur `/invoicing` avec un numéro `FA-2026-XXXXXX` correctement séquencé, les bonnes lignes, et les écritures 411/701 générées dans `/accounting`.
2. Créer un `Payment Entry` contre cette facture sur ERPNext → vérifier que le statut passe à `payé`/`partiellement payé` sur PME360.
3. Annuler la `Sales Invoice` sur ERPNext (si non payée) → vérifier le statut `annulée` côté PME360.
4. Vérifier que `/invoicing/create` et les actions de paiement/annulation ne sont plus accessibles côté PME360.
5. Vérifier qu'une facture créée AVANT ce sous-projet (déjà existante localement, déjà synchronisée PME360→ERPNext) ne se dédouble pas si un `on_submit` ERPNext venait à être rejoué dessus.

## Fichiers concernés

- `app/Domain/Invoicing/InvoiceService.php` (modifié — paramètre `$skipErpNextSync` sur les 3 méthodes)
- `app/Http/Controllers/ErpNextInvoicingWebhookController.php` (nouveau)
- `routes/web.php` (nouvelle route hors `auth`, suppression des routes de création/paiement/annulation)
- `app/Http/Controllers/InvoiceController.php` (modifié — suppression de `create()`/`store()`/`edit()`/`update()`/`storePayment()`/`cancel()`/`destroy()`)
- Vues `invoicing/create.blade.php`, formulaires de paiement/annulation dans `invoicing/show.blade.php` (retirés)
- Bootstrap CSRF exemption (`bootstrap/app.php`, ajout de la nouvelle route à la liste déjà créée pour le Stock)

Ne pas toucher : `Invoice`/`InvoiceItem`/`InvoicePayment` (modèles), `InvoiceNumberCounter`, `createSaleAccountingEntries()` (réutilisée telle quelle), les jobs `Sync*ToErpNext` existants (toujours utilisés pour le sens PME360→ERPNext, désormais simplement non dispatchés côté webhook).
