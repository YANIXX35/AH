# Design Specification: Modern Modal UI/UX Refactor (Stripe/Linear Style)

**Date** : 2026-07-28  
**Topic** : Refonte UX/UI du modal d'ajout client dans le Dashboard Comptable (`resources/views/accountant/clients-index.blade.php`).

---

## 1. Principes & Contraintes

- **Logique Métier** : 100% intacte (aucun contrôleur, route, validation ou événement JS modifié).
- **Structure** :
  - **Header fixe** : `position: sticky; top: 0; z-index: 10;`
  - **Body scrollable** : `max-height: 60vh; overflow-y: auto;` avec scrollbar personnalisée épurée.
  - **Footer fixe** : `position: sticky; bottom: 0; z-index: 10; background: #fff;` avec bouton `Annuler` à gauche et `Suivant` / `Créer` à droite.
- **Style** : Inspiré de Vercel/Linear/Clerk, largeur 850px sur PC, responsive sur mobile/tablette.

---

## 2. Découpage du Composant HTML/CSS

- **Fichier impacté** : `resources/views/accountant/clients-index.blade.php` (lignes 186 à 310).
- **Style CSS** : Ingestion dans `public/css/mobile-responsive.css` pour la scrollbar interne fine et l'effet de fixité du header/footer.
