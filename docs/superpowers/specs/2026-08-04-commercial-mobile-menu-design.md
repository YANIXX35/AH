# Refonte du menu mobile — Espace Commercial

Date : 2026-08-04
Statut : Validé, prêt pour plan d'implémentation

## Contexte

Une capture d'écran d'une autre application (menu investisseur mobile : avatar rond en en-tête,
liste d'items avec icône + libellé + chevron, séparateur de section "AUTRES SERVICES", bouton
déconnexion rouge en bas, bottom tab bar à 3 icônes) sert d'inspiration pour redessiner la
**disposition** du menu mobile de Sitiame Capital — pas sa palette de couleurs.

Décisions de cadrage (issues du brainstorming) :
- On garde la charte graphique actuelle du site (Bootstrap + variables existantes), on copie
  uniquement la disposition/structure de la photo.
- On procède espace par espace. Premier espace traité : **Commercial**.
- Le menu ne liste que des fonctionnalités déjà implémentées — aucune nouvelle page créée.
- Approche technique : on restyle le mécanisme de menu mobile existant (le `.sidebar` togglé par
  le bouton hamburger, cf. `resources/views/layouts/partials/sidebar.blade.php` et
  `public/css/mobile-responsive.css`) plutôt que de construire un composant entièrement séparé.
  On y ajoute une bottom tab bar, qui n'existe nulle part dans l'app aujourd'hui.

## État actuel (constats)

- Layout partagé : `resources/views/layouts/app.blade.php` (navbar + toggle hamburger ligne 388,
  JS toggle lignes 816-835, avatar/dropdown utilisateur ligne 527).
- Sidebar partagé : `resources/views/layouts/partials/sidebar.blade.php`, section Commercial
  gardée par `@if($sidebarIsCommercial)` (ligne 62), 8 liens actuels lignes 63-103 (Tableau de
  bord, Mon Portefeuille, Offres Marketing & Service, Guides & Lead Magnets, Sitiame Finance Club,
  Pipeline Leads CRM, Inscrire Client/PME, Importer & Analyser Fichier). Pas de séparateur de
  section, pas de bouton déconnexion dans ce bloc.
- CSS mobile existant : `public/css/mobile-responsive.css` gère déjà l'ouverture/fermeture du
  sidebar en mobile (`.sidebar`, `.sidebar.show`, `.sidebar-backdrop`, `.sidebar-close-btn`).
- Icônes : Feather Icons (`data-feather="..."`) partout, remplacées via `feather.replace()`.
- Aucune bottom tab bar n'existe dans le projet, quel que soit l'espace.
- 7 vues Commercial, toutes `@extends('layouts.app')` :
  `dashboard.blade.php`, `portefeuille.blade.php`, `showcase.blade.php`, `guides.blade.php`,
  `club.blade.php`, `prospects.blade.php`, `import.blade.php`.

## Design validé

### 1. Menu latéral (off-canvas), section Commercial

- **En-tête** : avatar rond (réutilise le même avatar que dans le navbar, ligne 527 de
  `app.blade.php`), nom de l'utilisateur, badge de rôle "Commercial".
- **Liste principale** (icône Feather + libellé + chevron `>`) :
  1. Tableau de bord → `commercial.dashboard`
  2. Mon Portefeuille → `commercial.portefeuille`
  3. Pipeline Leads CRM → `commercial.prospects`
- **Séparateur "AUTRES SERVICES"**, puis :
  4. Offres Marketing & Service → `commercial.showcase`
  5. Guides & Lead Magnets → `commercial.guides`
  6. Sitiame Finance Club → `commercial.club`
  7. Importer & Analyser Fichier → `commercial.import`
  8. Inscrire Client / PME → `commercial.dashboard?action=add-client`
- **Déconnexion** : en rouge, séparée par une ligne, tout en bas du bloc off-canvas.
- **Mécanisme** : inchangé — toggle via le bouton hamburger existant (`.js-sidebar-toggle`),
  seul le rendu visuel (markup + CSS) du bloc Commercial est redessiné. Le sidebar desktop
  (`≥ lg`) garde son apparence Bootstrap actuelle, seule la vue mobile (`< lg`) affiche la
  nouvelle disposition.

### 2. Bottom tab bar (nouveau composant, mobile uniquement)

- Barre fixe en bas d'écran (`position: fixed; bottom: 0`), visible uniquement sous le
  breakpoint mobile (masquée en desktop où le sidebar classique suffit).
- 4 icônes : **Tableau de bord** / **Portefeuille** / **Prospects** / **Menu**.
  - Les 3 premières pointent directement vers leurs routes (`commercial.dashboard`,
    `commercial.portefeuille`, `commercial.prospects`).
  - "Menu" ouvre le même off-canvas que le hamburger (réutilise `.js-sidebar-toggle`).
- L'onglet correspondant à la route active est surligné avec la couleur primaire du site
  (déterminé côté Blade via `request()->routeIs(...)`).
- Le contenu des 7 pages Commercial reçoit un `padding-bottom` supplémentaire en mobile pour
  ne pas passer sous la barre fixe.

## Composants à créer/modifier

1. `resources/views/layouts/partials/sidebar.blade.php` — restructurer le bloc
   `@if($sidebarIsCommercial)` : ajouter en-tête avatar/nom/badge (mobile only), regrouper les
   items principaux vs secondaires, ajouter séparateur "AUTRES SERVICES", ajouter item
   Déconnexion rouge en fin de bloc (mobile only — desktop garde le déconnexion existant du
   navbar/sidebar général).
2. Nouveau partial `resources/views/layouts/partials/bottom-nav-commercial.blade.php` — la
   bottom tab bar, inclus depuis `layouts/app.blade.php` uniquement quand `$sidebarIsCommercial`
   est vrai.
3. `public/css/mobile-responsive.css` — nouvelles classes pour l'en-tête avatar du off-canvas,
   le séparateur de section, l'item déconnexion rouge, et la bottom tab bar (dont le
   `padding-bottom` appliqué au conteneur de contenu principal en mobile).
4. `resources/views/layouts/app.blade.php` — point d'inclusion du nouveau partial bottom-nav,
   conditionné à `$sidebarIsCommercial` et à l'affichage mobile.

## Hors périmètre

- Les autres espaces (Investisseur, Admin, Comptable, etc.) ne sont pas touchés dans ce cycle ;
  ils pourront réutiliser le même patron une fois celui-ci validé sur Commercial.
- Aucune nouvelle page/fonctionnalité métier n'est créée.
- Aucun changement de palette de couleurs.

## Vérification

- Test manuel responsive (DevTools mobile + réel si possible) sur les 7 pages Commercial :
  ouverture/fermeture du menu, navigation via bottom nav, état actif correct par page,
  déconnexion fonctionnelle, aucun chevauchement de contenu avec la bottom bar.
- Vérifier que le rendu desktop (`≥ lg`) est inchangé (sidebar Bootstrap classique, pas de
  bottom nav).
