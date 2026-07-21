# Refonte des Tableaux de Bord (Mondays Theme) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Moderniser tous les tableaux de bord non-admin de l'application selon le modèle visuel Mondays (fond clair, pilules KPI en-tête avec date, cartes arrondies, badges de statut pills, calendrier d'échéances et notes/tâches rapides).

**Architecture:** Mettre en place un système de cartes légères avec conteneurs d'en-tête héro, filtres rapides et badges statut pills réutilisables tout en conservant scrupuleusement la logique métier et toutes les variables Blade existantes.

**Tech Stack:** Laravel Blade, HTML5, Vanilla CSS, Bootstrap 5, Feather Icons.

## Global Constraints

- Fond clair : `#F8FAFC`, cartes de fond `#FFFFFF` avec bordure `#E2E8F0` et radius `16px`.
- Conserver 100% de la logique métier, des routes, des formulaires et des variables Blade existantes.
- Ne pas toucher aux vues du sous-dossier `resources/views/admin/`.

---

### Task 1: Refonte du Tableau de Bord Général (`resources/views/dashboard.blade.php`)

**Files:**
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 1: Mettre à jour la structure visuelle et le layout Mondays dans `dashboard.blade.php`**
- [ ] **Step 2: Vérifier le rendu de la vue avec `php artisan view:clear`**
- [ ] **Step 3: Commiter les changements**

```bash
git add resources/views/dashboard.blade.php
git commit -m "style(dashboard): apply Mondays design system to main user dashboard"
```

---

### Task 2: Refonte du Tableau de Bord Cabinet Comptable (`resources/views/accountant/dashboard.blade.php`)

**Files:**
- Modify: `resources/views/accountant/dashboard.blade.php`

- [ ] **Step 1: Adapter le layout Mondays avec en-tête héro, pilules KPI dossiers, tableau clients et tâches cabinet**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter les changements**

```bash
git add resources/views/accountant/dashboard.blade.php
git commit -m "style(dashboard): apply Mondays design system to accountant dashboard"
```

---

### Task 3: Refonte du Tableau de Bord Comptabilité (`resources/views/accounting.blade.php`)

**Files:**
- Modify: `resources/views/accounting.blade.php`

- [ ] **Step 1: Structurer le tableau de bord comptable avec le style Mondays (pilules équilibre debit/credit, journal recent, calendrier clôture/Liasse BCEAO)**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter les changements**

```bash
git add resources/views/accounting.blade.php
git commit -m "style(accounting): apply Mondays design system to accounting overview dashboard"
```

---

### Task 4: Refonte du Tableau de Bord Trésorerie (`resources/views/treasury/tracking.blade.php`)

**Files:**
- Modify: `resources/views/treasury/tracking.blade.php`

- [ ] **Step 1: Transformer la vue de trésorerie avec le layout Mondays (pilules solde & flux, tableau transactions avec badges pills, prévisions)**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter les changements**

```bash
git add resources/views/treasury/tracking.blade.php
git commit -m "style(treasury): apply Mondays design system to treasury tracking dashboard"
```

---

### Task 5: Refonte des Tableaux de Bord Facturation (`invoicing/index.blade.php`) et Stock (`stock/index.blade.php`)

**Files:**
- Modify: `resources/views/invoicing/index.blade.php`
- Modify: `resources/views/stock/index.blade.php`

- [ ] **Step 1: Adapter `invoicing/index.blade.php` avec le layout Mondays (pilules facturation, tableau factures & badges de paiement, relances)**
- [ ] **Step 2: Adapter `stock/index.blade.php` avec le layout Mondays (pilules stock, mouvements récents & réapprovisionnement)**
- [ ] **Step 3: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 4: Commiter les changements**

```bash
git add resources/views/invoicing/index.blade.php resources/views/stock/index.blade.php
git commit -m "style(modules): apply Mondays design system to invoicing and stock dashboards"
```

---

### Task 6: Clôture & Vérification Globale

- [ ] **Step 1: Exécuter `php artisan view:clear`**
- [ ] **Step 2: Générer l'artefact Walkthrough**
