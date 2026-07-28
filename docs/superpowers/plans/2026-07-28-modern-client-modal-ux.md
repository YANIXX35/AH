# Plan d'implémentation - Refonte UI/UX Modal Client (Stripe/Linear/Clerk Style)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refondre la présentation du modal "Ajouter Client / Entreprise" avec header fixe, scrollbar interne fine sur le corps, et footer d'actions fixe.

**Architecture:** Encapsulation dans `resources/views/accountant/clients-index.blade.php` avec support CSS responsive dans `public/css/mobile-responsive.css`.

**Tech Stack:** HTML5, Blade, Bootstrap 5, Custom CSS.

---

### Task 1: CSS pour Scrollbar interne & Conteneur Modal 850px

**Files:**
- Modify: `public/css/mobile-responsive.css`

- [ ] **Step 1: Ajuster les règles CSS du scrollbar et du modal dialog**

- [ ] **Step 2: Commit**

```bash
git add public/css/mobile-responsive.css
git commit -m "style(modal): refine modal scrollbar and container dimensions"
```

---

### Task 2: Refonte HTML du Modal dans `clients-index.blade.php`

**Files:**
- Modify: `resources/views/accountant/clients-index.blade.php:186-310`

- [ ] **Step 1: Structurer le Header fixe, le Body scrollable et le Footer fixe**
- [ ] **Step 2: Vérifier le bon fonctionnement des boutons Annuler, Précédent, Suivant et Submit**

- [ ] **Step 3: Commit**

```bash
git add resources/views/accountant/clients-index.blade.php
git commit -m "feat(modal): apply modern 3-part layout (fixed header, scrollable body, fixed footer)"
```
