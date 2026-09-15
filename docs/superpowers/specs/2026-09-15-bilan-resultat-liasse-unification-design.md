# Unification Bilan / Compte de résultat vers BceaoLiasseService — Design

## Contexte

`/accounting/report` (contrôleur `AccountingController::report()`) affiche 7 onglets : Journal, Grand livre, Balance, Bilan, Compte de résultat, TAFIRE, État annexe. Les onglets **Bilan** et **Compte de résultat** calculent leurs chiffres via `summarizeEntries()`, une agrégation simplifiée à seulement 2 agrégats globaux (actifs / passifs), pas les vraies masses SYSCOHADA par compte.

Un moteur correct existe déjà et tourne déjà en local (pas d'ERPNext) : `BceaoLiasseService::generateLiasse(Collection $entriesN, ?Collection $entriesN1 = null): array`, utilisé par la route séparée `accounting.liasse-bceao` (`AccountingController::liasseBceao()` → `buildLiassePayload()`), qui calcule les vraies lignes SYSCOHADA (AA, AB, AC, DA, CI…) avec comparaison N/N-1.

Le code contient déjà, pour les 3 routes PDF du Bilan, un correctif identique appliqué au même problème :

```php
/**
 * @deprecated Ce "Bilan" calculait ses lignes (AA/AB/AC/DA/CI...) à partir de
 * seulement 2 agrégats globaux (actifs/passifs), pas des vraies masses SYSCOHADA
 * par compte — au contraire de BceaoLiasseService, déjà correct et déjà utilisé
 * par la Liasse BCEAO. On y redirige plutôt que de dupliquer/corriger ce calcul.
 */
public function downloadBilan(Request $request) { return redirect()->route('accounting.liasse-bceao.pdf.download', $request->query()); }
public function viewBilanPdf(Request $request) { return redirect()->route('accounting.liasse-bceao.pdf.view', $request->query()); }
public function showBilanPdfViewer(Request $request) { return redirect()->route('accounting.liasse-bceao', array_merge($request->query(), ['_' => 'actif'])); }
```

Ce design applique le même principe aux 2 onglets restants qui n'ont jamais été corrigés : `accounting.report.bilan` et `accounting.report.resultat`.

**Preuve additionnelle que c'est un vrai bug, pas juste une incohérence esthétique** : la branche bilan de `report()` (lignes 2069-2112) appelle déjà `$liasseService->generateLiasse($entries)` — un seul argument, sans N-1 — juste pour générer son QR code de vérification (`DocumentVerification` type `bilan`), alors que `buildLiassePayload()` l'appelle correctement avec les deux collections (lignes 2187-2207). Même l'infrastructure de vérification actuelle du Bilan est donc incomplète.

## Compatibilité des paramètres (vérifiée)

`report()` lit `date_from`/`date_to` en query string (lignes 2026-2027). `buildLiassePayload()` lit exactement les mêmes noms, même format (lignes 2184-2185), et dérive lui-même la période N-1 depuis la date max de N — aucun paramètre supplémentaire (type "exercice fiscal") n'est requis. Un simple `redirect()->route('accounting.liasse-bceao', $request->query())` est donc suffisant, sans aucune conversion.

## Décisions de conception

1. **Redirection au niveau contrôleur**, pas de fusion visuelle dans `report.blade.php`. Les sections `#bilan-section`/`#resultat-section` de `report.blade.php` sont inconditionnellement calculées et rendues dans le HTML (masquées seulement en CSS selon `$reportType`) — donc une correction "chirurgicale" dans le Blade serait plus risquée qu'une redirection au niveau route, qui est le pattern déjà validé et en production pour les 3 routes PDF.
2. **Les boutons de navigation "Bilan" / "Compte de résultat" restent visibles** dans la barre d'onglets de `report.blade.php` (ligne 474-475) — ils pointent vers les mêmes routes nommées (`accounting.report.bilan`, `accounting.report.resultat`), qui redirigent désormais silencieusement. Aucun changement visible pour l'utilisateur sauf l'URL finale et, bien sûr, des chiffres corrects.
3. **Nettoyage du code mort** : la branche `bilan` de `report()` (lignes ~2069-2112, y compris l'appel incomplet à `generateLiasse()` et la génération `DocumentVerification` type `bilan`) est supprimée — elle ne sera plus jamais atteinte. `'bilan'` et `'resultat'` sont retirés de la liste des `$reportType` valides déterminés par `routeIs()`.
4. **Nettoyage Blade** : suppression des sections `#bilan-section` (lignes 1280-1566) et `#resultat-section` (lignes 1570-1887), et de tous les blocs conditionnels associés devenus inatteignables : CSS d'affichage sélectif (266-289, 327-336), QR code bilan (510-520), toolbar impression/visualisation bilan (552-567), formulaire de filtre résultat (663-686). Les routes `accounting.report.bilan.viewer/.view/.download` (déjà redirigées vers liasse-bceao) restent inchangées — seules leurs références visuelles dans le bilan-section disparaissent avec la section.
5. **Pas de changement à `BceaoLiasseService` ni à `accounting.liasse-bceao`** — ils sont déjà corrects et ne sont pas touchés.
6. **Pas de changement à l'outil admin ERPNext** (`ErpNextAccountingTestController`) — hors périmètre, confirmé précédemment par l'utilisateur.

## Fichiers concernés

- `app/Http/Controllers/AccountingController.php` — modifié : `report()` (suppression branche bilan + retrait `bilan`/`resultat` de la liste des types), ajout de 2 nouvelles méthodes courtes `reportBilanRedirect()`/`reportResultatRedirect()` (ou réutilisation directe d'un redirect inline si `report()` garde ces 2 routes tant que le nettoyage Blade n'a pas eu lieu — décision technique laissée au plan).
- `routes/web.php` — modifié : les 2 routes `accounting.report.bilan`/`accounting.report.resultat` pointent vers les nouvelles méthodes de redirection au lieu de `report()`.
- `resources/views/accounting/report.blade.php` — modifié : suppression des sections et blocs conditionnels listés au point 4.

## Vérification

Comme pour les sous-projets précédents de cette session, PHPUnit ne peut pas tourner en local (PHP 8.2 vs 8.4 requis) — vérification manuelle en local (tinker / navigateur local) puis en production après déploiement.

1. Visiter `/accounting/report/bilan` (avec et sans `date_from`/`date_to`) → doit rediriger vers `/accounting/liasse-bceao` avec les mêmes paramètres de date, affichant les vraies lignes SYSCOHADA.
2. Visiter `/accounting/report/resultat` → même vérification pour le Compte de résultat.
3. Vérifier que les onglets Journal/Grand livre/Balance/TAFIRE/État annexe de `/accounting/report` fonctionnent toujours normalement (non-régression).
4. Vérifier que les 3 routes PDF bilan (`.viewer`, `.view`, `.download`) fonctionnent toujours (elles redirigeaient déjà avant ce changement, aucune modification prévue).
5. Vérifier qu'aucune erreur PHP n'apparaît sur `/accounting/report` (routes journal/grand-livre/balance/tafire/annexe) après suppression des blocs Blade bilan/resultat — s'assurer qu'aucune variable/section restante ne référence les blocs supprimés.
