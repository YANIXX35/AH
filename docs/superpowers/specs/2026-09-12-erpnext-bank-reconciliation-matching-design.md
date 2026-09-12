# Vrai pointage bancaire ERPNext (v2) — Design Spec

## Contexte et objectif

Suite au sous-projet 5 (v1 : création de lignes de relevé `Bank Transaction`), cette version ajoute le vrai mécanisme de **pointage** : associer une ligne de relevé à un paiement/écriture déjà existant dans ERPNext, comme le ferait le "Bank Reconciliation Tool" natif.

**Ce qui reste inchangé** : tout le sous-projet 5 v1 (création de lignes, onglet de consultation, auto-provisioning Bank/Bank Account).

## Découverte technique vérifiée empiriquement

Contrairement à une intuition initiale (appeler une méthode RPC dédiée type `reconcile_vouchers`), le pointage se fait en réalité par une **simple mise à jour du document `Bank Transaction` lui-même** : il possède un champ tableau natif `payment_entries` (sous-doctype `Bank Transaction Payments`), qu'on alimente directement via `PUT /api/resource/Bank Transaction/{name}` avec :
```json
{
  "payment_entries": [
    {"payment_document": "Journal Entry", "payment_entry": "ACC-JV-2026-00002", "allocated_amount": 10000}
  ]
}
```
Vérifié en direct : après cet appel, `unallocated_amount` diminue correctement (20 000 → 10 000 après une allocation de 10 000), et le sous-enregistrement montre `reconciliation_type: "Matched"`.

**Piège découvert** : une fois `unallocated_amount` retombé à 0 (montant totalement alloué), le champ `status` **ne repasse pas automatiquement** à `"Reconciled"` via cette API brute (contrairement à ce qui se passerait en utilisant l'outil graphique natif d'ERPNext, qui doit déclencher une logique serveur supplémentaire). Il faut le forcer explicitement avec un second appel `PUT` contenant `{"status": "Reconciled"}` — vérifié en direct, fonctionne correctement.

**Important — pour ajouter une ligne sans écraser les précédentes** : `payment_entries` est un tableau complet remplacé à chaque `PUT`, pas une liste à laquelle on "ajoute" côté serveur. Il faut donc **d'abord relire (`GET`) le document, récupérer son tableau `payment_entries` actuel, y ajouter la nouvelle ligne, puis renvoyer le tableau complet** — vérifié en direct (un test avec un tableau ne contenant qu'une seule nouvelle ligne aurait supprimé la précédente).

## Décisions verrouillées

- Sélection de la pièce à associer (Journal Entry / Payment Entry / Sales Invoice) via **texte libre** pour le nom exact de la pièce — pas de liste déroulante exhaustive de toutes les pièces existantes (potentiellement des milliers, pas pertinent pour une v1 de test).
- Le montant à allouer est pré-rempli avec le `unallocated_amount` restant de la ligne, mais reste modifiable (allocation partielle possible).
- Reste dans l'espace de test admin — rien côté PME360 local n'est modifié.

## Architecture

### Nouvelle méthode `ErpNextClient::reconcileBankTransactionForPme()`

```php
public function reconcileBankTransactionForPme(
    string $bankTransactionName,
    string $voucherType,
    string $voucherName,
    float $allocatedAmount
): array
```

- `GET /api/resource/Bank Transaction/{bankTransactionName}` pour récupérer le tableau `payment_entries` actuel.
- Ajoute la nouvelle ligne `{'payment_document' => $voucherType, 'payment_entry' => $voucherName, 'allocated_amount' => $allocatedAmount}` au tableau existant.
- `PUT /api/resource/Bank Transaction/{bankTransactionName}` avec le tableau complet mis à jour.
- Si la réponse indique `unallocated_amount <= 0`, effectue un second `PUT` avec `{'status' => 'Reconciled'}`, et retourne cette réponse finale (sinon retourne la réponse du premier `PUT`).
- Lève `ErpNextApiException` si ERPNext refuse (ex: montant alloué dépassant le montant restant, pièce introuvable).

### Formulaire et contrôleur

Dans l'onglet "Rapprochement bancaire" existant : chaque ligne avec `status = Pending` et `unallocated_amount > 0` affiche un lien "Pointer" vers un nouveau formulaire `reconcile-bank-transaction.blade.php` (PME déjà connue via la session/paramètre, nom de la transaction en paramètre) :
- Type de pièce (menu déroulant : Journal Entry / Payment Entry / Sales Invoice).
- Nom de la pièce (texte libre).
- Montant à allouer (pré-rempli avec `unallocated_amount`).

Deux nouvelles méthodes sur `ErpNextAccountingTestController` (`reconcileBankTransaction()`/`storeReconcileBankTransaction()`), même schéma que les formulaires précédents.

## Tests (vérification manuelle)

1. Sur une ligne "Pending" existante, pointer un montant partiel vers une pièce réelle → vérifier que `unallocated_amount` diminue du montant exact, statut toujours `Pending` si non nul.
2. Pointer le reste du montant → vérifier que `unallocated_amount` atteint 0 et que le statut passe bien à `Reconciled` dans l'onglet de consultation.
3. Pointer un deuxième montant sur une ligne déjà partiellement pointée → vérifier que la première allocation n'est pas perdue (le tableau `payment_entries` contient bien les deux lignes après relecture).

## Fichiers concernés

- `app/Services/ErpNextClient.php` (modifié — 1 nouvelle méthode publique)
- `app/Http/Controllers/ErpNextAccountingTestController.php` (modifié — 2 nouvelles méthodes)
- `routes/web.php` (modifié — 2 routes ajoutées)
- `resources/views/admin/erpnext-accounting-test/reconcile-bank-transaction.blade.php` (nouveau)
- `resources/views/admin/erpnext-accounting-test/show.blade.php` (modifié — lien "Pointer" ajouté par ligne dans l'onglet existant)

Ne pas toucher : le reste des sous-projets déjà livrés.
