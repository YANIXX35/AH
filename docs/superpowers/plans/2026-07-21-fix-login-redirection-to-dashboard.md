# Fix Login Redirection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Assurer que lors de chaque connexion (login), l'utilisateur est TOUJOURS redirigé directement vers le Tableau de Bord principal (`/dashboard`), sans être redirigé de force vers la Fiche Entreprise (FIRD) `/profile/entreprise/fird`.

**Architecture:** Supprimer la redirection conditionnelle forcée vers `profile.company.fird` dans `AuthController::login()` et remplacer la redirection par un routage direct selon le rôle (`admin.dashboard`, `accountant.dashboard`, ou `dashboard`).

**Tech Stack:** Laravel PHP, AuthController, Blade.

## Proposed Changes

### Component: Auth Controller
#### [MODIFY] [AuthController.php](file:///c:/Users/yaniss/Desktop/application/app/Http/Controllers/AuthController.php)
- Supprimer la vérification `$firdMissing` qui forçait la redirection vers `/profile/entreprise/fird`.
- Rediriger systématiquement vers `route('dashboard')` pour les utilisateurs entreprise, `route('accountant.dashboard')` pour les comptables, et `route('admin.dashboard')` pour les admins.

---

## Verification Plan

### Automated Tests
- Vider les vues et caches : `php artisan view:clear`
- Vérifier la syntaxe PHP du contrôleur.

### Manual Verification
- Tester la connexion utilisateur et valider la redirection immédiate vers `/dashboard`.
