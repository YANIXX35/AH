# Spécification de Conception : Refonte Mobile Responsive Globale

**Date :** 29 Juillet 2026  
**Statut :** Validé et Approuvé  
**Auteur :** Antigravity

---

## 1. Objectif Global
L'objectif est d'effectuer une refonte responsive complète de l'application Laravel (Sitiame Capital) afin qu'elle soit parfaitement fluide, lisible et utilisable sur tous les formats d'écrans (téléphones, tablettes, petits ordinateurs portables et écrans haute résolution), sans altérer la logique métier, les routes, les contrôleurs ou les services existants.

---

## 2. Architecture Responsive Hybride
*   **Centralisation des Styles CSS** : Toutes les règles CSS spécifiques et les media queries seront déclarées dans [mobile-responsive.css](file:///c:/Users/yaniss/Desktop/application/public/css/mobile-responsive.css) pour éviter la dispersion du code.
*   **Ajustements de Vues Blade** : Les fichiers Blade ne seront modifiés que pour y insérer les classes de grille native Bootstrap 5 et ajuster l'ordre ou la visibilité des éléments (à l'aide de classes comme `d-none`, `d-md-flex`, etc.).

---

## 3. Spécifications Détaillées des Composants

### 3.1. Sidebar (Barre Latérale)
*   **Desktop ($lg$ et $+$, $\ge 992px$)** : Affichage fixe traditionnel sur le côté gauche.
*   **Tablette ($md$, $768px$ à $991.98px$)** : Sidebar repliable et compacte (uniquement les icônes).
*   **Mobile ($< 768px$)** : Cachée par défaut (`left: -280px`). S'ouvre sous forme de tiroir (drawer) coulissant (`left: 0` avec classe `.show`) via un bouton hamburger placé dans le header.
*   **Fermeture & Blocage** : Un arrière-plan semi-transparent flouté (`.sidebar-backdrop`) s'affiche lors de l'ouverture. Le menu se ferme par clic sur l'arrière-plan, sur le bouton fermer (`btn-close`), ou par appui sur la touche `Echap`. Le défilement de la page arrière-plan (`body`) est bloqué pendant l'ouverture.

### 3.2. Header & Navbar
*   **Fixation** : Reste collé en haut de l'écran (`sticky-top`).
*   **Densité mobile** : Hauteur et padding réduits de 30% sur mobile. Le logo et les titres longs s'adaptent de manière fluide pour éviter tout débordement.
*   **Éléments conservés** : Les boutons de notifications, de profil, et de déclenchement du chatbot IA restent alignés horizontalement sans décalage.

### 3.3. Dashboards & Cartes (Cards)
*   **Grilles KPI** :
    *   **Desktop** : 4 ou 5 colonnes alignées.
    *   **Tablette** : 2 colonnes (`col-md-6`).
    *   **Mobile** : 1 colonne (`col-12`).
*   **Marges & Paddings** : Réduction drastique des marges intérieures et extérieures sur mobile pour augmenter la densité des informations affichées.
*   **Largeur** : Toutes les cartes prennent 100% de la largeur disponible pour s'ajuster parfaitement sans défilement horizontal global de la page.

### 3.4. Formulaires & Boutons
*   **Champs** : Positionnement systématique des labels au-dessus des champs sur mobile. Tous les champs s'étirent sur 100% de largeur (`width: 100%`).
*   **Boutons** : Hauteur minimale de **44px** sur mobile pour une zone tactile conforme aux règles d'accessibilité. Alignement vertical empilé en cas de manque de place.

### 3.5. Modales (Modals)
*   **Dimension** : S'étirent à 90% sur tablette et 98% sur mobile.
*   **Scroll Interne** : Le corps de la modale (`.modal-body`) défile verticalement de manière isolée pour conserver l'en-tête (titre) et le pied de page (boutons d'action) toujours visibles à l'écran.

### 3.6. Tableaux Hybrides
*   **Tableaux de gestion simples (Utilisateurs, Clients, Licences)** : Les lignes se transforment sur mobile en cartes empilées verticales individuelles. Les valeurs sont précédées de leur titre de colonne via l'attribut `data-label`.
*   **Tableaux comptables complexes (Journal, Grand Livre, Balance)** : Conservent leur structure tabulaire horizontale à l'intérieur d'un conteneur avec défilement horizontal fluide (`overflow-x: auto`).

### 3.7. Chatbot IA & Graphiques
*   **Chatbot** : S'adapte à 100% de largeur et 80-90% de hauteur sur mobile. La zone d'envoi reste visible au-dessus du clavier virtuel.
*   **Graphiques** : Redimensionnement automatique à la largeur de l'écran avec une hauteur réduite sur mobile pour conserver une lecture équilibrée.

---

## 4. Breakpoints Applicables (Bootstrap 5)
*   `xs` : $< 576px$
*   `sm` : $\ge 576px$
*   `md` : $\ge 768px$
*   `lg` : $\ge 992px$
*   `xl` : $\ge 1200px$
*   `xxl` : $\ge 1400px$
