# Design Specification: Global Mobile Responsiveness Refactor

**Date** : 2026-07-28  
**Topic** : Refonte de la réactivité mobile sur l'ensemble de l'application Sitiame Capital.

---

## 1. Vue d'ensemble et Objectif

Le présent document définit la conception technique pour corriger les problèmes d'affichage et de réactivité mobile observés sur les différentes vues de l'application (Dashboard Admin, Sidebar mobile, Top Navbar, Login/Auth, et grilles de données).

### Problèmes identifiés (Captures écran) :
1. **Header / Hub Admin en haut** : Mauvaise adaptation sur écran smartphone (< 768px). Chevauchement des badges et cercle Admin avec les icônes de la navbar.
2. **Sidebar Mobile** : Tiroir latéral tronqué à l'ouverture, chevauchant le contenu principal sans masque (backdrop) ni gestion propre du défilement.
3. **Login / Widget Flottant** : Le bouton flottant "Service Client" vient superposer les éléments du formulaire (bouton "Se connecter" ou texte de bas de page) en bas d'écran.
4. **Tableaux et KPIs** : Débordement horizontal des grilles et cartes statistiques sur petits écrans.

---

## 2. Découpage et Composants Impactés

### A. Layout Principal (`resources/views/layouts/app.blade.php` & `adminkit-app.css`)
- **Offcanvas / Tiroir Sidebar** :
  - Sur mobile (`< 992px`), la sidebar doit s'ouvrir en superposition avec un `z-index` supérieur (1050+), un fond opaque sombre (backdrop), et occuper 100% de la hauteur sans débordement horizontal.
  - Ajout d'un bouton de fermeture explicite (`X`) ou clic extérieur pour fermer la navigation.
- **Top Navbar** :
  - Restructuration des conteneurs pour empiler proprement le bouton hamburger et les informations de profil sur les petits écrans.
  - Ajustement des badges d'administration et d'espace métier pour un affichage en sous-ligne (flex wrap).

### B. Header / Hub Admin (`resources/views/admin/dashboard.blade.php` & partials)
- **Adaptation de la bulle / carte de bienvenue** :
  - Transformation de la carte en disposition flex verticale sur mobile (`flex-column sm:flex-row`).
  - Suppression de la forme circulaire fixe ou des marges négatives provoquant les débordements sur écran `< 768px`.

### C. Page de Connexion & Authentification (`resources/views/login.blade.php`, `register.blade.php`)
- **Widget Service Client Flottant** :
  - Réduction de la taille du badge flottant sur mobile (`bottom-4 right-4`, version compacte icône seule ou badge réduit).
  - Ajout de marges basses (`pb-20` sur mobile) au conteneur de formulaire pour éviter tout masquage des boutons d'action.

### D. Tableaux de Bord & DataTables (`resources/views/partials/kpi-card.blade.php`, `data-table.blade.php`, etc.)
- **Grilles de KPIs** : Passage systématique de 3/4 colonnes à 1 colonne sur mobile (`col-12 col-md-6 col-xl-3`).
- **Tableaux de données** : Enveloppement universel dans un conteneur `.table-responsive` permettant un défilement horizontal fluide de la table seule sans casser la largeur du reste de la page.

---

## 3. Stratégie CSS et Règle Media Queries

Création / Consolidation d'un fichier dédié `resources/css/mobile-responsive.css` (ou inclusion directe dans le layout principal) contenant :

```css
/* Mobile Breakpoints (< 768px & < 992px) */
@media (max-width: 991.98px) {
  /* Sidebar Mobile Drawer */
  .sidebar {
    position: fixed !important;
    top: 0;
    left: -100%;
    bottom: 0;
    z-index: 1050;
    width: 280px !important;
    transition: left 0.3s ease-in-out;
  }
  .sidebar.show {
    left: 0 !important;
  }
  .sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
  }

  /* Admin Header Card */
  .admin-hub-card {
    border-radius: 1rem !important;
    width: 100% !important;
    max-width: 100% !important;
  }
}

@media (max-width: 575.98px) {
  /* Floating support button */
  .floating-support-btn {
    bottom: 1rem !important;
    right: 1rem !important;
    padding: 0.5rem 0.75rem !important;
  }
  .floating-support-btn .support-text {
    display: none !important;
  }
}
```

---

## 4. Plan de Vérification

1. **Test visuel sur différentes définitions d'écran** :
   - Écran mobile Portrait (375px - iPhone SE / 390px - iPhone 12/13/14).
   - Écran Tablette (768px).
2. **Vérification comportementale** :
   - Ouverture / Fermeture fluide du menu latéral.
   - Accessibilité du bouton "Se connecter" sur l'écran d'accueil sans obstruction.
   - Défilement horizontal des tableaux sans déformation de la mise en page global.
