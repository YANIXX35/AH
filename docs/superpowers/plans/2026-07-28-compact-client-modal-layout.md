# Plan d'implémentation - Layout Ergonomique du Modal d'Ajout Client

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redimensionner et réorganiser le modal de création client cabinet en 2 colonnes compactes pour une lisibilité optimale sur PC et mobile.

**Architecture:** Restructuration de la grille Bootstrap (`col-md-6`) et resserrement de la largeur du conteneur modal (`max-width: 680px`).

**Tech Stack:** HTML5, Blade, Bootstrap 5 (Grids & Modals).

---

### Task 1: Refonte des grilles et dimensions du Modal

**Files:**
- Modify: `resources/views/accountant/clients-index.blade.php:188-305`

- [ ] **Step 1: Resserre la largeur maximale du modal sur PC à 680px**
- [ ] **Step 2: Aligner les champs de l'Étape 1 et de l'Étape 2 en 2 colonnes (`row g-3`)**
- [ ] **Step 3: Repositionner le modal vers le haut avec une marge confortable (`mt-4`)**

- [ ] **Step 4: Commit**

```bash
git add resources/views/accountant/clients-index.blade.php
git commit -m "style(modal): compact client wizard modal to 680px max-width with 2-column layout"
```
