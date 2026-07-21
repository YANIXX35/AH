# Spec Design : Charte Visuelle Unique par Section Métier

## 🎯 Objectif
Dotage de chaque section principale de l'application d'une **identité visuelle unique, thématique et haut de gamme**, avec des palettes de couleurs harmonieuses, des bannières personnalisées, des cartes épurées et des composants spécifiques, sans altérer aucune logique métier existante.

---

## 🎨 Spécification des Thèmes Visuels par Section

### 1. 📖 Section Comptabilité (*Emerald Precision & Slate Gold*)
- **Palette** : Émeraude (`#059669`), Or Comptable (`#D97706`), Slate (`#0F172A`), Blanc Glacé (`#FFFFFF`).
- **Composants** : Bannières analytiques comptables, badges de validation dorés/émeraude, cartes de contrôle de balance.
- **Fichiers** : `resources/views/accounting/documents.blade.php`, `resources/views/accounting/plan-comptable.blade.php`, `resources/views/accounting/bank-reconciliation.blade.php`, `resources/views/accounting/monthly-closing.blade.php`, `resources/views/accounting/liasse-bceao.blade.php`.

### 2. 💸 Section Trésorerie (*Fintech Electric Indigo & Cyan*)
- **Palette** : Indigo Électrique (`#4F46E5`), Cyan Néon (`#06B6D4`), Bleu Glacé (`#F0F9FF`).
- **Composants** : Cartes style Néo-banque, jauges de tendance de flux bancaire & Mobile Money.
- **Fichiers** : `resources/views/treasury/balance.blade.php`, `resources/views/treasury/forecast.blade.php`, `resources/views/treasury/mobile-money/import.blade.php`.

### 3. 📄 Section Facturation (*Royal Sapphire & Amber*)
- **Palette** : Bleu Saphir (`#2563EB`), Ambre Chaleureux (`#F59E0B`), Perle Douce (`#F8FAFC`).
- **Composants** : Mise en page style papier d'affaires d'exception, étiquettes d'échéance et de solde.
- **Fichiers** : `resources/views/invoicing/create.blade.php`, `resources/views/invoicing/show.blade.php`.

### 4. 📦 Section Stock (*Deep Teal & Mint*)
- **Palette** : Teal Profond (`#0D9488`), Menthe Fraîche (`#14B8A6`), Pêche Douce (`#FFF7ED`).
- **Composants** : Cartes d'inventaire au CUMP, jauges de stock sous seuil.
- **Fichiers** : `resources/views/stock/create.blade.php`, `resources/views/stock/show.blade.php`.

### 5. 🏢 Section Mon Entreprise / Profil & Équipe (*Corporate Violet & Rose*)
- **Palette** : Violet Corporatif (`#7C3AED`), Rose Doux (`#F43F5E`), Gris Perle (`#F9FAFB`).
- **Composants** : Cartes de profil avec avatars dégradés, barres de complétude FIRD / KYC.
- **Fichiers** : `resources/views/profile.blade.php`, `resources/views/profile-company-fird.blade.php`, `resources/views/profile-team.blade.php`.

### 6. 📈 Section Investisseurs / Readiness (*Luxury Gold & Obsidian*)
- **Palette** : Or Luxueux (`#D97706`), Obsidienne Sombre (`#1E293B`).
- **Composants** : Scorecard d'éligibilité aux levées de fonds.
- **Fichiers** : `resources/views/investor/index.blade.php`.

### 7. 💳 Section Abonnement & Paiements (*Starry Amethyst & Sapphire*)
- **Palette** : Améthyste Électrique (`#9333EA`), Bleu Royal (`#2563EB`).
- **Composants** : Cartes d'abonnement et badges d'offres Gratuit / Premium.
- **Fichiers** : `resources/views/subscriptions/history.blade.php`, `resources/views/payments/sandbox.blade.php`.

### 8. 🎧 Section Aide & Support (*Ocean Azure & Turquoise*)
- **Palette** : Bleu Azur (`#0284C7`), Turquoise (`#06B6D4`).
- **Composants** : Messagerie & cartes de suivi de tickets avec bulles fluides.
- **Fichiers** : `resources/views/support/index.blade.php`, `resources/views/support/tickets.blade.php`.

---

## ⚡ Garanties d'implémentation
- Zero breaking change sur le PHP / Blade / Contrôleurs.
- Conservation de l'ensemble des fonctionnalités, formulaires et routes.
- Test de compilation Blade (`php artisan view:clear`) à chaque étape.
