# Spec Design : Refonte des Tableaux de Bord (Style Mondays)

## 🎯 Objectif
Harmoniser et moderniser tous les tableaux de bord de l'application (hors administration) sur le modèle visuel **Mondays** : esthétique épurée sur fond clair (`#F8FAFC`), cartes arrondies avec bordures légères, en-tête héro avec salutation et pilules KPI rapides, tableaux de suivi avec badges arrondis de statut, calendrier d'échéances hebdomadaire et blocs de notes / tâches rapides.

---

## 🎨 Spécifications de la Charte Visuelle (Mondays Theme)

### 1. Canvas & Conteneurs
- **Fond de page** : `#F8FAFC`
- **Cartes** : `background: #FFFFFF`, `border: 1px solid #E2E8F0`, `border-radius: 16px`, `box-shadow: 0 1px 3px rgba(0,0,0,0.04), 0 6px 16px rgba(0,0,0,0.02)`.
- **Bordures d'interaction** : `hover: border-color: #CBD5E1`.

### 2. En-tête Hero
- **Sous-titre Date** : Affichage de la date courante en français (ex: *Mardi 21 Juillet 2026*), couleur `#64748B`, taille `0.85rem`, `fw-500`.
- **Titre Salutation** : *Bonjour, [Nom d'utilisateur] 👋*, couleur `#0F172A`, taille `1.75rem`, `fw-700`.
- **Barre de Pilules KPI Inline** :
  - Conteneur arrondi (`background: #FFFFFF`, `border: 1px solid #E2E8F0`, `border-radius: 9999px`, `padding: 6px 18px`).
  - Métriques affichées avec icônes (ex: ⏱️ *Gain de temps*, 🎯 *Objectifs / Dossiers*, ⌛ *En cours*).
- **Actions Droite** : Boutons primaires bleus (`#2563EB`) et secondaires contour pour les actions rapides (`+ Nouveau`, `Exporter`, `Partager`).

### 3. Statuts & Badges (Pills)
- **Vert (Succès / En cours)** : `background: #DCFCE7`, `color: #15803D`, `border-radius: 9999px`, `font-size: 0.75rem`, `font-weight: 600`.
- **Violet / Rose (En attente / Validation)** : `background: #F3E8FF`, `color: #7E22CE`, `border-radius: 9999px`, `font-size: 0.75rem`, `font-weight: 600`.
- **Bleu (Terminé / Validé)** : `background: #DBEAFE`, `color: #1D4ED8`, `border-radius: 9999px`, `font-size: 0.75rem`, `font-weight: 600`.
- **Orange (Attention / Relance)** : `background: #FFEDD5`, `color: #C2410C`, `border-radius: 9999px`, `font-size: 0.75rem`, `font-weight: 600`.

---

## 📂 Périmètre & Fichiers Concernés

1. **Tableau de bord utilisateur principal** : `resources/views/dashboard.blade.php`
2. **Tableau de bord cabinet comptable** : `resources/views/accountant/dashboard.blade.php`
3. **Tableau de bord comptabilité** : `resources/views/accounting.blade.php`
4. **Tableau de bord trésorerie** : `resources/views/treasury/tracking.blade.php`
5. **Tableau de bord facturation** : `resources/views/invoicing/index.blade.php`
6. **Tableau de bord stocks** : `resources/views/stock/index.blade.php`

*(Les vues du sous-dossier `resources/views/admin/` restent inchangées conformément aux consignes).*

---

## ⚡ Stratégie d'Implémentation

1. **Composants CSS Réutilisables** :
   Définir une classe CSS globale `.mondays-dashboard` dans `layouts/app.blade.php` ou via les blocs `@push('styles')` des tableaux de bord pour garantir la cohérence sans effets de bord sur le reste du site.
2. **Conservation Strict de la Logique Métier** :
   Chaque tableau de bord conserve l'intégralité de ses données Blade (`$metrics`, `$recentTransactions`, `$invoices`, `$stockAlerts`, etc.), les variables de contrôleur et la logique des formulaires sans aucune régression.
