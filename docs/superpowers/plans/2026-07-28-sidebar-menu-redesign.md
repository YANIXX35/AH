# Plan d'implémentation - Refonte de la Sidebar & Organisation du Menu

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Réorganiser et embellir le menu latéral (Sidebar) avec des groupes thématisés, un design sombre haut de gamme et un pied de page d'options utilisateur.

**Architecture:** Restructuration du composant Blade `sidebar.blade.php`, ajout de styles dédiés aux accordéons et séparateurs de sections dans `mobile-responsive.css`.

**Tech Stack:** Laravel, Blade, Bootstrap 5 (Collapse), CSS Custom.

---

### Task 1: Mise en page CSS et typographie de la Sidebar

**Files:**
- Modify: `public/css/mobile-responsive.css`

- [ ] **Step 1: Ajouter les classes CSS pour les en-têtes de section et les animations d'accordéon**

```css
/* Sidebar Modern Styling */
.sidebar-header-title {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748B;
    padding: 1rem 1.25rem 0.35rem;
}

.sidebar-nav-sub {
    padding-left: 1rem;
    border-left: 1px solid rgba(255, 255, 255, 0.06);
    margin-left: 1.25rem;
    margin-top: 0.25rem;
    margin-bottom: 0.5rem;
}

.sidebar-nav-sub .sidebar-link {
    font-size: 0.85rem;
    padding: 0.4rem 0.75rem;
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/mobile-responsive.css
git commit -m "style(sidebar): add custom section headers and submenu tree styles"
```

---

### Task 2: Restructuration du composant `sidebar.blade.php`

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php`

- [ ] **Step 1: Réorganiser les sous-groupes par pôles clairs (Admin, Cabinet, PME & Finance)**
- [ ] **Step 2: Ajouter les badges et icônes Feather associées à chaque sous-menu**
- [ ] **Step 3: Ajouter le bloc Profil / Déconnexion en bas de Sidebar**

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php
git commit -m "feat(sidebar): reorganize menu into thematic sections with footer profile actions"
```
