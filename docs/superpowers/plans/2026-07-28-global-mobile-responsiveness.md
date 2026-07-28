# Plan d'implémentation - Refonte Mobile Responsive Globale

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre l'ensemble de l'application Sitiame Capital totalement responsive et ergonomique sur les appareils mobiles (Smartphones & Tablettes).

**Architecture:** Consolidation CSS mobile centralisée, adaptation du layout principal (Sidebar drawer & Top Navbar), ajustements des pages d'authentification et encapsulation des grilles/tableaux de données.

**Tech Stack:** Laravel, Blade, Vanilla CSS / Bootstrap 5 / TailwindCSS (auth), HTML5, JavaScript.

---

### Task 1: Refonte CSS globale & Styles Mobile Drawer

**Files:**
- Create: `public/css/mobile-responsive.css`
- Modify: `resources/views/layouts/app.blade.php:16-18`

- [ ] **Step 1: Créer le fichier `public/css/mobile-responsive.css`**

```css
/* Mobile Responsive Utility Styles for Sitiame Capital */

@media (max-width: 991.98px) {
    /* Sidebar Overlay & Offcanvas Drawer */
    .sidebar {
        position: fixed !important;
        top: 0 !important;
        bottom: 0 !important;
        left: -280px !important;
        width: 280px !important;
        z-index: 1050 !important;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        box-shadow: 0 0.5rem 2rem rgba(0, 0, 0, 0.25) !important;
    }

    .sidebar.show {
        left: 0 !important;
    }

    .sidebar-backdrop {
        position: fixed;
        inset: 0;
        background-color: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }

    .sidebar-backdrop.show {
        opacity: 1;
        visibility: visible;
    }

    /* Main Container Padding */
    .main {
        width: 100% !important;
        min-width: 100% !important;
        padding-left: 0 !important;
    }

    /* Table & Card Utilities */
    .table-responsive-wrapper {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
}

@media (max-width: 767.98px) {
    /* Navbar Adjustment */
    .navbar {
        padding: 0.5rem 1rem !important;
    }

    /* Mobile Headers & Hub Admin */
    .admin-hub-header {
        flex-direction: column !important;
        align-items: stretch !important;
        border-radius: 1rem !important;
        padding: 1.25rem !important;
    }

    .admin-hub-header .nav-pills {
        flex-wrap: wrap !important;
        gap: 0.5rem !important;
    }

    /* Grid Force 1 Column on Mobile */
    .grid-mobile-1 {
        grid-template-columns: repeat(1, minmax(0, 1fr)) !important;
    }
}
```

- [ ] **Step 2: Déclarer le CSS dans `resources/views/layouts/app.blade.php`**

Injecter `<link href="{{ asset('css/mobile-responsive.css') }}" rel="stylesheet">` sous `adminkit-app.css`.

- [ ] **Step 3: Vérifier l'intégration du CSS**

Vérifier que le fichier est correctement chargé dans l'en-tête HTML.

- [ ] **Step 4: Commit**

```bash
git add public/css/mobile-responsive.css resources/views/layouts/app.blade.php
git commit -m "feat(responsive): add mobile-responsive.css styles and include in app layout"
```

---

### Task 2: Adaptation de la Sidebar Mobile & Gestions des Événements JavaScript

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php:1-50`
- Modify: `resources/views/layouts/app.blade.php:750-800`

- [ ] **Step 1: Ajouter le bouton de fermeture mobile et l'élément d'arrière-plan (Backdrop)**

Ajouter dans `sidebar.blade.php` un bouton de fermeture mobile (`btn-close`) visible seulement sur mobile, et injecter la div `<div id="sidebarBackdrop" class="sidebar-backdrop"></div>` dans `app.blade.php`.

- [ ] **Step 2: Ajouter le script JS pour la bascule de la sidebar sur mobile**

```javascript
document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.getElementById("sidebar");
    const togglers = document.querySelectorAll(".sidebar-toggle, .js-sidebar-toggle");
    const backdrop = document.getElementById("sidebarBackdrop");

    function toggleSidebar() {
        if (sidebar) sidebar.classList.toggle("show");
        if (backdrop) backdrop.classList.toggle("show");
    }

    togglers.forEach(btn => btn.addEventListener("click", toggleSidebar));
    if (backdrop) backdrop.addEventListener("click", toggleSidebar);
});
```

- [ ] **Step 3: Vérifier l'ouverture et fermeture fluide de la sidebar sur mobile**

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php resources/views/layouts/app.blade.php
git commit -m "fix(responsive): improve mobile sidebar toggle and backdrop handling"
```

---

### Task 3: Correction des Pages d'Authentification (Login & Register)

**Files:**
- Modify: `resources/views/login.blade.php:129-140`
- Modify: `resources/views/register.blade.php` (si présent)

- [ ] **Step 1: Adapter le bouton flottant de support client sur mobile**

Rendre le widget Service Client compact sur mobile (icône seule sans texte sur `< 640px`) et ajouter un padding au bas du formulaire pour éviter qu'il n'obstrue le bouton de connexion.

```html
<div class="fixed bottom-4 right-4 z-50">
    <a href="mailto:contact@sitiame-capital.com" class="group flex items-center gap-2.5 rounded-full bg-gradient-to-r from-orange-500 to-amber-600 p-3 sm:px-5 sm:py-3.5 text-white shadow-xl hover:shadow-2xl transition-all duration-200 border border-white/20" title="Contacter le Service Client">
        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 backdrop-blur-sm">
            <i class="fas fa-envelope text-white text-base"></i>
        </div>
        <div class="hidden sm:flex flex-col text-left">
            <span class="text-[10px] font-bold uppercase tracking-widest text-orange-100">Service Client</span>
            <span class="text-xs font-extrabold text-white">contact@sitiame-capital.com</span>
        </div>
    </a>
</div>
```

- [ ] **Step 2: Vérifier l'accessibilité du bouton "Se connecter" sur smartphone**

- [ ] **Step 3: Commit**

```bash
git add resources/views/login.blade.php
git commit -m "fix(responsive): make support floating button compact on mobile screens"
```

---

### Task 4: Responsive des Tableaux et Cartes KPIs

**Files:**
- Modify: `resources/views/partials/kpi-card.blade.php`
- Modify: `resources/views/partials/data-table.blade.php`

- [ ] **Step 1: Entourer les DataTables dans des wrappers `table-responsive`**

Garantir que tous les tableaux HTML disposent d'un conteneur avec `overflow-x: auto` pour éviter d'étirer l'écran.

- [ ] **Step 2: Harmoniser les cartes KPIs sur 1 colonne sur smartphone**

S'assurer que la grille des cartes KPI bascule à `col-12` pour les petits écrans.

- [ ] **Step 3: Commit**

```bash
git add resources/views/partials/
git commit -m "feat(responsive): wrap data tables and stack kpi cards on mobile"
```
