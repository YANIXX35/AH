# Charte Visuelle Unique par Section Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Appliquer un thème visuel unique et haut de gamme pour chacune des 8 sections principales de l'application (Comptabilité, Trésorerie, Facturation, Stock, Mon Entreprise, Investisseurs, Abonnement, Support).

**Architecture:** Définir pour chaque section un bloc CSS de thème thématique dédié avec bannières, cartes, pilules KPI et jauges adaptées en préservant 100% de la logique métier Blade.

**Tech Stack:** Laravel Blade, CSS3 Gradients & Animations, Bootstrap 5, Feather Icons.

## Global Constraints

- Chaque section possède sa propre palette thématique unique.
- Aucune modification des contrôleurs ni de la logique de traitement des formulaires.
- Validation avec `php artisan view:clear` après chaque section.

---

### Task 1: Thème Section Comptabilité (*Emerald Precision & Slate Gold*)
**Files:**
- Modify: `resources/views/accounting/documents.blade.php`
- Modify: `resources/views/accounting/plan-comptable.blade.php`
- Modify: `resources/views/accounting/bank-reconciliation.blade.php`
- Modify: `resources/views/accounting/monthly-closing.blade.php`
- Modify: `resources/views/accounting/liasse-bceao.blade.php`

- [ ] **Step 1: Appliquer le thème Emerald & Gold sur les vues de la section Comptabilité**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "style(accounting): apply Emerald & Slate Gold theme"`

---

### Task 2: Thème Section Trésorerie (*Fintech Electric Indigo & Cyan*)
**Files:**
- Modify: `resources/views/treasury/balance.blade.php`
- Modify: `resources/views/treasury/forecast.blade.php`
- Modify: `resources/views/treasury/mobile-money/import.blade.php`

- [ ] **Step 1: Appliquer le thème Néo-banque Indigo & Cyan aux vues Trésorerie**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "style(treasury): apply Electric Indigo & Cyan fintech theme"`

---

### Task 3: Thème Section Facturation & Stock (*Sapphire, Amber & Deep Teal*)
**Files:**
- Modify: `resources/views/invoicing/show.blade.php`
- Modify: `resources/views/invoicing/create.blade.php`
- Modify: `resources/views/stock/show.blade.php`
- Modify: `resources/views/stock/create.blade.php`

- [ ] **Step 1: Appliquer le thème Sapphire/Amber pour Facturation et Deep Teal/Mint pour Stock**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "style(invoicing-stock): apply Sapphire and Teal themes"`

---

### Task 4: Thème Mon Entreprise / Profil & Équipe (*Corporate Violet & Rose*)
**Files:**
- Modify: `resources/views/profile.blade.php`
- Modify: `resources/views/profile-company-fird.blade.php`
- Modify: `resources/views/profile-team.blade.php`

- [ ] **Step 1: Appliquer le thème Violet & Rose sur Profil, FIRD et Équipe**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "style(profile): apply Corporate Violet & Rose theme"`

---

### Task 5: Thème Investisseurs, Abonnements & Support (*Gold Obsidian, Amethyst, Ocean Azure*)
**Files:**
- Modify: `resources/views/investor/index.blade.php`
- Modify: `resources/views/subscriptions/history.blade.php`
- Modify: `resources/views/payments/sandbox.blade.php`
- Modify: `resources/views/support/index.blade.php`
- Modify: `resources/views/support/tickets.blade.php`

- [ ] **Step 1: Appliquer les thèmes Or/Obsidienne (Investisseurs), Améthyste (Abonnements) et Ocean Azure (Support)**
- [ ] **Step 2: Vérifier le rendu avec `php artisan view:clear`**
- [ ] **Step 3: Commiter** `git commit -m "style(platform): apply Luxury Gold, Amethyst and Azure themes"`

---

### Task 6: Clôture & Déploiement

- [ ] **Step 1: Exécuter `php artisan view:clear`**
- [ ] **Step 2: Pousser sur le dépôt distant (`git push`)**
- [ ] **Step 3: Générer le walkthrough**
