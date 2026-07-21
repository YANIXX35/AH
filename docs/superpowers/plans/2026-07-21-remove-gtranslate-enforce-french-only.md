# Plan de Suppression des Traducteurs & Verrouillage en Français Uniquement (100% FR)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Supprimer le bouton de traduction (FR/EN) et les scripts Google Translate externes pour verrouiller définitivement l'application en Français (100% FR).

**Architecture:** 
1. Dans `resources/views/layouts/app.blade.php`, supprimer l'élément `#topbar-locale-switch` (les boutons FR/EN), le div masqué `.gtranslate_wrapper`, le script CDN `cdn.gtranslate.net`, et réinitialiser les cookies de traduction tiers (`googtrans`).
2. Dans `app/Http/Middleware/SetAppLocale.php`, forcer `fr` comme langue unique et immuable.
3. Exécuter `php artisan view:clear` et la suite de vérifications.

---

### Task 1: Nettoyer `resources/views/layouts/app.blade.php`
**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Supprimer le bloc HTML `#topbar-locale-switch` des boutons FR/EN**
- [ ] **Step 2: Supprimer l'inclusion du script CDN GTranslate `dropdown.js`**
- [ ] **Step 3: Remplacer les scripts JS GTranslate par un script de nettoyage des cookies `googtrans`**

---

### Task 2: Verrouiller la Langue dans `SetAppLocale.php`
**Files:**
- Modify: `app/Http/Middleware/SetAppLocale.php`

- [ ] **Step 1: Forcer la locale `fr` dans le middleware `SetAppLocale`**

---

### Task 3: Validation & Déploiement
- [ ] **Step 1: Exécuter `php artisan view:clear`**
- [ ] **Step 2: Exécuter le script d'audit automatisé `php scratch/test_crud_verification.php`**
- [ ] **Step 3: Commiter** `git commit -m "feat(i18n): remove translation buttons and enforce french language exclusively"`
- [ ] **Step 4: Pousser sur le dépôt distant (`git push`)**
