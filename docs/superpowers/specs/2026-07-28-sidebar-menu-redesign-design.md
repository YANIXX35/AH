# Design Specification: Modernized Sidebar & Menu Organization

**Date** : 2026-07-28  
**Topic** : Refonte ergonomique et visuelle de la Sidebar (menu latéral principal).

---

## 1. Vue d'ensemble et Objectifs

L'objectif est d'optimiser l'organisation du menu latéral pour offrir une navigation plus fluide, plus moderne et sans surcharge visuelle.

### Objectifs Clés :
- **Clarté & Structuration** : Regrouper logiquement les menus par domaines d'activité (Administration, Cabinet, Gestion Métier PME).
- **Design Épuré & Premium** : Palette sombre élégante (Arrière-plan slate-900 `#0F172A`), effets d'activation en or doux/ambre (`#F2D89B`), et icônes alignées avec précision.
- **Mobile First & Offcanvas** : Intégration parfaite avec le tiroir mobile et l'arrière-plan flouté.

---

## 2. Structure et Organisation des Menus

### A. En-tête de la Sidebar
- Logo d'entreprise/Sitiame réactif avec badge de rôle stylisé (`Admin`, `Comptable`, `Commercial`, `Premium`).
- Espacement et séparateurs subtils.

### B. Groupes de Navigation
1. **Pôle Administration** (Visible si Administrateur Plateforme) :
   - Tableau de bord Admin
   - Utilisateurs & Entreprises
   - Licences & Paiements Pro
   - Dashboard CEO/CFO & Log
2. **Pôle Cabinet Comptable** (Visible si Comptable / Admin) :
   - Tableau de bord Cabinet
   - Dossiers Clients
   - Outils & Synthèse
3. **Pôle Gestion Métier (PME & Finance)** :
   - Tableau de bord principal
   - Comptabilité & Liasse BCEAO
   - Trésorerie & Solde
   - Paie & Bulletins
   - Facturation & Devis
   - Gestion des Stocks

### C. Pied de Sidebar (Footer Menu)
- Profil & Paramètres utilisateur.
- Bouton de déconnexion rapide.

---

## 3. Plan de Vérification
- Vérification visuelle du contraste et de la lisibilité des textes.
- Test de l'accordéon (collapse) des sous-menus.
- Vérification du comportement sur smartphone et tablette.
