# Plan de Correction UrlGenerationException sur le Tableau de Bord Expert-Comptable (/accountant)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Éliminer définitivement l'erreur `UrlGenerationException: Missing required parameter for [Route: accountant.clients.show]` sur le tableau de bord expert-comptable (`/accountant`).

**Architecture:** 
1. Dans `resources/views/accountant/dashboard.blade.php`, sécuriser l'extraction de `$uId` dans la boucle des inscriptions récentes.
2. Remplacer l'appel direct `route('accountant.clients.show', $uId)` par un lien d'action sécurisé avec repli (`$uTargetUrl`) sur `route('accountant.clients.index')` si `$uId` est nul ou indéfini.
3. Vider le cache des vues Blade et re-tester le système.

---

### Task 1: Sécuriser `resources/views/accountant/dashboard.blade.php`
**Files:**
- Modify: `resources/views/accountant/dashboard.blade.php`

- [ ] **Step 1: Mettre à jour l'extraction de `$uId` avec détection `is_scalar`**
- [ ] **Step 2: Ajouter la variable `$uTargetUrl` avec fallback sur `route('accountant.clients.index')`**
- [ ] **Step 3: Remplacer le lien en ligne 505 par `$uTargetUrl`**

---

### Task 2: Validation Globale & Déploiement
- [ ] **Step 1: Exécuter `php artisan view:clear`**
- [ ] **Step 2: Exécuter le script de vérification automatisé `php scratch/test_crud_verification.php`**
- [ ] **Step 3: Commiter** `git commit -m "fix(accountant): prevent UrlGenerationException on accountant clients show link"`
- [ ] **Step 4: Pousser sur le dépôt distant (`git push`)**
