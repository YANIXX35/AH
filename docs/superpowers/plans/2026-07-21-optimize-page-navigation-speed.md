# Plan d'Optimisation des Performances de Navigation (Vitesse de Chargement)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Éliminer les latences et temps d'attente lors des clics sur les sections/pages du menu pour obtenir une navigation quasi-instantanée.

**Architecture:** 
1. Mettre en cache court (30s) les requêtes répétitives du `View::composer('layouts.app')` dans `AppServiceProvider.php` (notifications et tickets support).
2. Optimiser les ressources externes dans `resources/views/layouts/app.blade.php` (`dns-prefetch` & `preconnect` pour Google Fonts, chargement conditionnel de GTranslate uniquement hors-FR).
3. Ajouter un indicateur visuel de progression de clic ultra-rapide au niveau du navigateur.
4. Exécuter l'optimisation des routes et de la configuration Laravel (`php artisan route:cache`, `config:cache`).

---

### Task 1: Optimiser `AppServiceProvider.php` (Mise en cache du ViewComposer layout)
**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Entourer les requêtes de notifications et support d'un `Cache::remember` ultra-rapide de 30 secondes par utilisateur**
- [ ] **Step 2: Vider le cache des vues pour appliquer**

---

### Task 2: Optimiser `resources/views/layouts/app.blade.php` (Ressources CDN & Scripts)
**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Ajouter `preconnect` pour Google Fonts et conditionner l'inclusion de GTranslate CDN**
- [ ] **Step 2: Tester la compilation Blade**

---

### Task 3: Optimiser Laravel & Déployer
- [ ] **Step 1: Exécuter `php artisan view:clear`**
- [ ] **Step 2: Commiter les changements** `git commit -m "perf(navigation): optimize view composer queries and cdn script loading speed"`
- [ ] **Step 3: Pousser sur le dépôt distant (`git push`)**
