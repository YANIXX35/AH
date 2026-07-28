# Plan: Tableau Numéroté Compact des Clients

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trier les clients enregistrés par date décroissante (le plus récent en 1er) et afficher un tableau numéroté (1, 2, 3...) compact.

**Architecture:** 
- Modification de `AccountantClientController.php` pour trier `latest()`.
- Modification de `clients-index.blade.php` pour ajouter la colonne `#` numérotée et rendre le tableau compact.

---

### Task 1: Tri décroissant et numérotation du tableau

- [ ] **Step 1: Mettre à jour `AccountantClientController.php` avec `orderByDesc('created_at')`**
- [ ] **Step 2: Mettre à jour `clients-index.blade.php` pour inclure la colonne `#` numérotée (1, 2, 3...) et affiner l'espacement**
- [ ] **Step 3: Commit et Push**
