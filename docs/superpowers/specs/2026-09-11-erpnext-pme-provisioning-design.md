# Provisionnement automatique ERPNext par PME — Design Spec

## Contexte et objectif

Suite au test concluant du dashboard "ERPNext Test" (voir `docs/superpowers/specs/2026-09-11-erpnext-test-dashboard-design.md`), l'objectif est de connecter le vrai module Facturation de PME360 (`InvoiceController`/`Invoice`, déjà existant et fonctionnel) à ERPNext, pour que chaque PME bénéficie d'une vraie comptabilité SYSCOHADA via l'API ERPNext.

**Découverte structurante** : chaque PME cliente de PME360 est une entreprise indépendante — ses ventes ne doivent pas être mélangées dans les comptes d'une seule société ERPNext partagée. ERPNext supporte le multi-sociétés nativement ("Company" doctype) ; chaque PME doit donc devenir sa propre Company ERPNext, avec son propre plan comptable, son propre entrepôt, son propre gabarit de TVA.

Ce projet est décomposé en deux sous-projets indépendants :
1. **Provisionnement automatique** (ce spec) : à l'inscription d'une PME, créer automatiquement sa Company ERPNext et ses réglages de base.
2. **Branchement du module Facturation** (spec séparé, ultérieur) : connecter `InvoiceController` pour pousser chaque facture créée vers la Company ERPNext de la PME concernée.

## Découverte technique vérifiée empiriquement

Créer une Company ERPNext via l'API REST avec seulement `country: "Ivory Coast"` **ne charge pas** le plan comptable SYSCOHADA — ERPNext retombe sur le plan générique anglais ("Standard"). Il faut explicitement passer `chart_of_accounts: "Syscohada - Plan Comptable"` (nom exact du template déjà utilisé par la société "Sitiame Capital" existante) pour obtenir le bon plan comptable. Vérifié en direct : une Company de test créée avec ce paramètre a bien généré **1378 comptes** SYSCOHADA-CI (avec les limites déjà connues : classes 4/8 scindées, classe 9 absente — identique au plan chargé pour Sitiame Capital elle-même).

## Décisions verrouillées (issues du brainstorming)

- Une "Company" ERPNext distincte par PME (pas de société partagée) — seule option comptablement correcte.
- Provisionnement **à l'inscription** de la PME (pas paresseux à la première facture) — la Company doit être prête avant que la PME ait besoin de facturer.
- Entrepôt et gabarit de TVA créés **automatiquement** pour chaque nouvelle Company (aucune intervention manuelle par PME).
- Provisionnement en **file d'attente asynchrone** (Job Laravel), jamais synchrone dans la requête d'inscription.
- **L'inscription PME360 n'est jamais bloquée** par un échec ERPNext — ERPNext reste un service annexe. Pas de retry automatique en v1 ; un échec reste consultable et relançable manuellement par un admin (cohérent avec l'absence de retry déjà actée pour le dashboard de test).
- On utilise le plan comptable SYSCOHADA déjà intégré nativement à ERPNext (`"Syscohada - Plan Comptable"`) — pas le fichier Excel enrichi analysé précédemment (`nouveau_plan_comptable/...xlsx`), qui reste une piste pour une amélioration future hors scope ici.
- Cet essai ERPNext (14 jours) reste utilisé pour construire et valider ce mécanisme ; le passage à un plan payant se fera une fois l'intégration complète vérifiée comme fonctionnelle (décision explicite de l'utilisateur, hors scope technique de ce spec).

## Architecture

### Déclenchement

Dans `app/Http/Controllers/RegisterController.php`, juste après la création du compte (`User::create([...])`, ligne 111), on ajoute :

```php
ProvisionErpNextCompanyForPme::dispatch($created);
```

Le Job est mis en file d'attente (`ShouldQueue`), donc n'ajoute aucune latence perceptible à la réponse d'inscription, et son échec n'affecte jamais le `RedirectResponse` déjà renvoyé à l'utilisateur.

### Job : `ProvisionErpNextCompanyForPme`

Nouveau fichier `app/Jobs/ProvisionErpNextCompanyForPme.php`, `implements ShouldQueue`, reçoit un `User $pme` en constructeur.

Séquence dans `handle(ErpNextClient $erpNext)` :

1. **Garde-fou** : si `$pme->erpnext_company_name` est déjà renseigné, ne rien refaire (idempotence — évite les doublons si le Job est relancé manuellement après un échec partiel).
2. Si `! $erpNext->enabled()`, log un avertissement et sortir sans lever d'exception (ERPNext non configuré = pas d'erreur bruyante).
3. Appelle `$erpNext->provisionCompanyForPme($pme)` (nouvelle méthode sur `ErpNextClient`, détaillée ci-dessous), qui retourne un tableau `['company' => string, 'warehouse' => string, 'tax_template' => string, 'income_account' => string]`.
4. Enregistre ces 4 valeurs sur `$pme` et sauvegarde.
5. Si une étape échoue (`ErpNextApiException` ou `\Throwable`), laisse l'exception remonter — Laravel marque le Job comme `failed` (visible dans la table `failed_jobs` déjà standard), consultable par un admin via `php artisan queue:failed`. Pas de gestion custom au-delà de ce comportement par défaut de Laravel.

### Nouvelle méthode : `ErpNextClient::provisionCompanyForPme()`

```php
public function provisionCompanyForPme(User $pme): array
```

Sous-étapes internes (chacune réutilise le pattern déjà établi de `post()`/`get()`) :

1. **Créer la Company** :
   - `company_name` : `$pme->company_name ?: $pme->name`, suffixé d'un identifiant court unique si nécessaire pour éviter les collisions de nom (ERPNext exige un nom de société unique) — utiliser `Str::slug` + `$pme->id` pour garantir l'unicité sans dépendre du texte libre.
   - `abbr` : dérivé du nom (max 5 caractères ERPNext), garanti unique en suffixant `$pme->id` si besoin.
   - `default_currency: 'XOF'`, `country: 'Ivory Coast'`, `chart_of_accounts: 'Syscohada - Plan Comptable'`.
   - `POST /api/resource/Company`.

2. **Créer l'entrepôt par défaut** :
   - `POST /api/resource/Warehouse` avec `warehouse_name: 'Magasin principal'`, `company: <nom de la Company créée>`.

3. **Créer le gabarit de TVA** :
   - Résoudre d'abord le compte TVA collectée de cette Company : `GET /api/resource/Account` filtré sur `company = <Company>` et `account_number = '4431'` (même logique que la vérification manuelle qu'on a faite dans l'UI, mais via API).
   - `POST /api/resource/Sales Taxes and Charges Template` avec `title: 'TVA 18%'`, `company: <Company>`, une ligne `taxes` (`charge_type: 'On Net Total'`, `account_head: <compte 4431 résolu>`, `rate: 18`).

4. **Résoudre le compte de produit par défaut** :
   - `GET /api/resource/Account` filtré sur `company = <Company>` et `account_number = '7061'`.

5. Retourne les 4 identifiants (noms complets tels que retournés par ERPNext, ex: `"Magasin principal - {abbr}"`).

Chaque sous-étape lève `ErpNextApiException` en cas d'échec (comportement déjà standard de `post()`/`get()`), ce qui fait échouer tout le Job proprement — pas de Company/Warehouse orphelins créés silencieusement à moitié.

### Données

**Nouvelles colonnes sur `users`** (une seule migration) :
```
erpnext_company_name (string, nullable)
erpnext_warehouse (string, nullable)
erpnext_tax_template (string, nullable)
erpnext_income_account (string, nullable)
```

Ajoutées au `#[Fillable([...])]` de `App\Models\User`, juste après `erpnext_customer_id`.

**Remarque de cohérence** : la colonne existante `erpnext_customer_id` (ajoutée pour le dashboard de test, où la PME jouait le rôle de "Customer" de Sitiame Capital) devient **obsolète dans ce nouveau modèle** — dans le sous-projet 2, ce sera le client final de la PME (texte libre sur `Invoice`) qui deviendra un "Customer" dans la Company de *cette* PME, pas la PME elle-même. Cette colonne n'est pas supprimée ici (elle reste utilisée par le dashboard de test, qui continue de fonctionner tel quel comme outil de validation isolé) mais ne sera plus utilisée par le nouveau mécanisme de facturation réel.

### Ce qui ne change pas

- Le dashboard "ERPNext Test" (`/admin/erpnext-test`) reste tel quel, inchangé, continue de fonctionner sur la société partagée "Sitiame Capital" — c'est un outil de validation séparé, pas remplacé.
- Aucune modification à `InvoiceController`, `Invoice`, ou au flux de facturation existant — ça fait l'objet du sous-projet 2.
- Aucune modification au flux d'inscription visible pour l'utilisateur (même formulaire, même redirection, même délai de réponse).

## Tests (vérification manuelle, cohérent avec les contraintes déjà connues de cette session)

1. Inscrire une nouvelle PME de test → vérifier dans `php artisan queue:work` (ou les logs) que le Job s'exécute et se termine sans erreur.
2. Vérifier en base que les 4 colonnes `erpnext_*` sont bien renseignées sur le nouvel utilisateur.
3. Vérifier dans ERPNext qu'une nouvelle Company existe, avec ~1378 comptes SYSCOHADA, un entrepôt, et un gabarit de TVA 18% relié au bon compte 4431 de *cette* Company.
4. Couper temporairement `ERPNEXT_API_KEY` → inscrire une nouvelle PME → vérifier que l'inscription réussit normalement (redirection habituelle) et que le Job échoue silencieusement en arrière-plan (visible dans `php artisan queue:failed`), sans aucun impact visible pour l'utilisateur.
5. Relancer un Job échoué manuellement (`php artisan queue:retry <id>`) après avoir restauré la clé API → vérifier qu'il se termine avec succès et complète le provisionnement.

## Fichiers concernés

- `app/Http/Controllers/RegisterController.php` (modifié — 1 ligne ajoutée après la création de l'utilisateur)
- `app/Jobs/ProvisionErpNextCompanyForPme.php` (nouveau)
- `app/Services/ErpNextClient.php` (modifié — ajout de `provisionCompanyForPme()`)
- `app/Models/User.php` (modifié — 4 nouvelles colonnes au fillable)
- `database/migrations/xxxx_add_erpnext_provisioning_fields_to_users_table.php` (nouveau)

Ne pas toucher : `InvoiceController`, `Invoice`, tout le dashboard `admin/erpnext-test/*` (reste fonctionnel tel quel).
