# Paie (Payroll) — Synchronisation ERPNext en production

## Contexte et objectif

Sous-projet suivant dans la connexion PME360 ↔ ERPNext : le module **Paiement des Salaires** (`PayrollController`, `PayrollRun`/`PayrollItem`) n'a aujourd'hui qu'une "synchronisation" purement locale — `PayrollController::sync()` (`app/Http/Controllers/PayrollController.php:142-191`) crée une `AccountingEntry` (661/422) et une `TreasuryTransaction` (décaissement), mais ne touche jamais ERPNext. Ce sous-projet ajoute le miroir ERPNext, exactement comme pour la Facturation et le Stock.

**Ce qui existe côté PME360 et reste inchangé** : la saisie d'un lot de paie (`store()`, formulaire ad hoc listant des salariés avec brut/CNPS/ITS/net), l'écriture comptable locale 661/422 et la transaction de trésorerie créées par `sync()`. Rien de ce calcul métier n'est modifié.

## Découverte technique vérifiée empiriquement

- Les comptes SYSCOHADA utilisés localement existent tels quels sur ERPNext (vérifié en direct sur la Company de test "Test Inscription E2E 1789166589 #15") :
  - `661-Rémunérations directes versées au personnel national`
  - `422-Personnel, rémunérations dues`
  - `5711-Caisse en monnaie nationale` (déjà utilisé comme compte de trésorerie par `recordPaymentForPme()` pour la Facturation)
- `ErpNextClient::createJournalEntryForPme(User $pme, string $voucherType, string $postingDate, array $lines, ?string $referenceNumber, ?string $referenceDate): array` existe déjà (sous-projet Journal Entry), crée et soumet une `Journal Entry` à partir de lignes `{account_number, debit, credit}` résolues via `findAccountByNumber()`. Directement réutilisable sans modification.
- Le module Paie local n'a pas de notion d'employés persistants entre deux lots (formulaire ad hoc à chaque fois) — construire le module RH natif d'ERPNext (Employee, Salary Structure, Salary Slip) serait disproportionné par rapport au besoin réel. Le mapping choisi reste au niveau comptable (Journal Entry), pas RH.

## Décisions verrouillées

- **Point d'accroche** : `PayrollController::sync()`, juste après le `DB::transaction(...)` qui crée l'écriture locale et la transaction de trésorerie — dispatch d'un nouveau job `SyncPayrollToErpNext::dispatch($payroll)`, même position que pour la Facturation et le Stock.
- **Deux écritures ERPNext par lot de paie synchronisé**, toutes deux via `createJournalEntryForPme()` :
  1. **Charge** : Débit `6611` (`Appointements salaires et commissions`, compte feuille sous `661`) / Crédit `422`, montant = `$payroll->total_gross` (si `> 0`, sinon `total_net` — même repli que l'écriture locale, `AccountingEntry::create()` dans `sync()`).
  2. **Paiement** : Débit `422` / Crédit `5711` (compte de trésorerie fixe), montant = `$payroll->total_net`.

  **Correction découverte en implémentation** : sur le plan SYSCOHADA importé par ERPNext, `661-Rémunérations directes versées au personnel national` est un **compte de regroupement** (`is_group: 1`), non postable — contrairement à PME360 local où l'écriture comptable utilise directement `661`. ERPNext refuse toute transaction sur un compte groupe (`ValidationError: ... is a Group Account and group accounts cannot be used in transactions`). Le compte feuille utilisé à la place est `6611-Appointements salaires et commissions` (premier des 8 sous-comptes de `661` sur ce plan : 6611 à 6618), vérifié en direct sur la Company de test.
- **Simplification assumée sur le compte de trésorerie** : le crédit de paiement utilise toujours `5711-Caisse en monnaie nationale`, quel que soit `payment_method` réellement choisi (bank_transfer/wave/orange_money/mtn/check/cash) — comportement identique à `recordPaymentForPme()` (Facturation), qui utilise déjà systématiquement ce même compte peu importe le mode réel. Pas de nouvelle logique de mapping mode-de-paiement → compte à inventer pour ce sous-projet.
- **Traçabilité** : nouvelle table `payroll_erpnext_syncs`, une ligne par `PayrollRun`, copie conforme de `invoice_erpnext_syncs`/`stock_movement_erpnext_syncs` : `payroll_run_id` (FK unique), `status` (pending/synced/failed), `erpnext_accrual_entry_name`, `erpnext_payment_entry_name` (les deux Journal Entry créées), `last_error`, `last_synced_at`, `raw_response` (JSON, les deux réponses).
- **Résilience** : le job attrape toute `\Throwable`, marque `failed` avec le message, ne bloque jamais `sync()` — garde identique aux jobs précédents, indispensable en production (`QUEUE_CONNECTION=sync`). Garde aussi : si `erpnext_company_name` manquant sur la PME, `failed` immédiat sans appel API.
- **Idempotence** : comme `PayrollController::sync()` refuse déjà de re-synchroniser un lot `status === 'synced'` (`app/Http/Controllers/PayrollController.php:146-148`), le job ne sera jamais redispatché deux fois pour le même lot dans le flux normal ; on garde quand même le even garde `if ($sync->status === 'synced') return;` par cohérence avec les jobs existants.
- **Hors scope explicite** :
  - Pas d'utilisation du module RH natif d'ERPNext (Employee/Salary Structure/Salary Slip).
  - Pas de synchronisation à la création du brouillon (`store()`) — uniquement à la validation (`sync()`), cohérent avec le fait que `sync()` est déjà le seul moment où PME360 lui-même produit des effets comptables/trésorerie.
  - Pas d'interface admin de suivi des synchronisations (comme pour la Facturation et le Stock).
  - Pas de sens inverse (ERPNext → PME360).

## Architecture

### Nouvelle table + modèle

`payroll_erpnext_syncs` (migration) + `PayrollErpNextSync` (modèle Eloquent), structure calquée sur `InvoiceErpNextSync`/`StockMovementErpNextSync` — avec `protected $table = 'payroll_erpnext_syncs';` explicite (même piège de nommage Eloquent que pour `StockMovementErpNextSync`, où `Str::snake` aurait sinon découpé "ErpNext" différemment).

### Nouveau Job

`App\Jobs\SyncPayrollToErpNext` (implémente `ShouldQueue`), reçoit un `PayrollRun`, résout sa PME (`$payroll->user`), appelle `createJournalEntryForPme()` deux fois (charge puis paiement), catch `\Throwable`.

### Modification de `PayrollController::sync()`

Après la fermeture du `DB::transaction`, dispatcher `SyncPayrollToErpNext::dispatch($payroll)`, avant le `return redirect()->back()...`.

## Tests (vérification manuelle, pas de suite PHPUnit exécutable localement — PHP 8.2 vs 8.4 requis)

1. Créer un lot de paie sur une PME provisionnée (au moins 1 salarié, brut/net renseignés), le synchroniser via le bouton existant → vérifier en ERPNext que 2 `Journal Entry` sont soumises : une 661/422 pour le brut, une 422/5711 pour le net. Vérifier `payroll_erpnext_syncs` marque `synced` avec les deux noms de pièce.
2. Tenter de resynchroniser le même lot → `sync()` refuse déjà localement ("Ce lot de paie est déjà synchronisé"), donc pas de nouvel appel API (déjà garanti par le comportement existant, pas de nouveau test nécessaire côté ERPNext).
3. Synchroniser un lot sur une PME **non provisionnée** → le lot local passe bien en `synced` (comportement local inchangé), et `payroll_erpnext_syncs` marque `failed` avec un message clair.
4. Simuler une panne ERPNext (mauvaise clé API) → confirmer que `sync()` continue de fonctionner normalement côté PME360 (déjà vérifié pour les sous-projets précédents avec la même garde).

## Fichiers concernés

- `database/migrations/YYYY_MM_DD_HHMMSS_create_payroll_erpnext_syncs_table.php` (nouveau)
- `app/Models/PayrollErpNextSync.php` (nouveau)
- `app/Jobs/SyncPayrollToErpNext.php` (nouveau)
- `app/Http/Controllers/PayrollController.php` (modifié — dispatch dans `sync()`)

Ne pas toucher : `PayrollRun`, `PayrollItem`, la logique de calcul brut/CNPS/ITS/net, `AccountingEntry`/`TreasuryTransaction` locales, `ErpNextClient` (la méthode nécessaire existe déjà).
