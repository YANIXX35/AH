# Modernisation de la liste des dossiers clients (/accountant/clients) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Embellir la vue des dossiers clients, resserrer la largeur maximale du tableau pour éliminer le grand vide blanc, ajouter des badges et avatars modernes.

**Architecture:** Édition du Blade `resources/views/accountant/clients-index.blade.php`.

**Tech Stack:** HTML5, Blade, Bootstrap 5.

---

### Task 1: Restructuration du Tableau des Dossiers Clients

**Files:**
- Modify: `resources/views/accountant/clients-index.blade.php:37-185`

- [ ] **Step 1: Remplacer l'affichage étiré par un composant carte/tableau compact avec badges et avatars**

- [ ] **Step 2: Commit**

```bash
git add resources/views/accountant/clients-index.blade.php
git commit -m "style(accountant-clients): modernize client list layout and remove excessive horizontal white space"
```
