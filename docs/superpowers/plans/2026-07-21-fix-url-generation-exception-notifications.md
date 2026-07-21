# Plan de Correction Définitive de UrlGenerationException & Navigation Comptabilité

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Éliminer définitivement l'erreur `UrlGenerationException: Missing required parameter for [Route: notifications.go]` sur toutes les pages et sous-sections de la Comptabilité.

**Architecture:** 
1. Dans `AppServiceProvider.php`, supprimer le `Cache::remember` sur le `ViewComposer` de `layouts.app` qui sérialisait les modèles Eloquent et faisait perdre leurs clés primaires (`id`).
2. Dans `resources/views/layouts/app.blade.php`, ajouter une génération d'URL sécurisée avec repli (fallback) sur `route('notifications.index')` si `$nId` est nul ou indéfini.
3. Vérifier et valider le bon fonctionnement de l'ensemble des 13 sous-sections comptables (`/accounting/report/balance`, `journal`, `ledger`, `documents`, `plan-comptable`, etc.).

---

### Task 1: Sécuriser la génération des URLs dans `resources/views/layouts/app.blade.php`
**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Remplacer la génération directe `route('notifications.go', $nId)` par une URL sécurisée avec fallback**
- [ ] **Step 2: Securiser également `route('support.tickets.show', $tId)` avec fallback sur `route('support.tickets')`**

---

### Task 2: Corriger `AppServiceProvider.php`
**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Charger directement les modèles Eloquent frais sans sérialisation de cache dans le composer de `layouts.app`**
- [ ] **Step 2: Vider le cache de l'application (`php artisan cache:clear`)**

---

### Task 3: Validation Globale & Déploiement
- [ ] **Step 1: Exécuter le script de vérification automatisé `php scratch/test_crud_verification.php`**
- [ ] **Step 2: Exécuter `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "fix(layout): prevent UrlGenerationException on notifications and tickets links"`
- [ ] **Step 4: Pousser sur le dépôt distant (`git push`)**
