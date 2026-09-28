# ERPNext mobile et installable (PWA) — Design

## Contexte

Demande utilisateur (2026-09-28) : rendre l'application responsive en version mobile et installable. Application retenue : **ERPNext** (erp.sitiame-capital.com). Audit automatisé par captures écarté par l'utilisateur au profit d'une revue du code + tests sur son téléphone.

Constaté en production : aucun manifeste ni service worker (`/manifest.json`, `/manifest.webmanifest`, `/sw.js`, `/service-worker.js` → 404). `desk.html` n'offre aucun point d'injection dans `<head>` (seulement `app_include_css`). Les pages www statiques de Frappe refusent `.js`/`.json` (`UNSUPPORTED_STATIC_PAGE_TYPES`). En revanche le hook `page_renderer` accepte toute classe exposant `can_render`/`render` (vérifié dans `path_resolver.get_custom_page_renderers`). Les scripts `/assets/sitiame_core/js/*.js` sont servis **sans empreinte de version** (pas de `?v=`), seuls les bundles Frappe sont hachés (`frappe-web.bundle.B64X257C.js`).

Revue des écrans ajoutés par Sitiame : les listes/formulaires natifs sont responsives ; à corriger : assistant IA (panneau 360 px fixe, champ 13 px → zoom automatique iOS à la saisie), sélecteur FR/EN (non adapté), bandeau d'abonnement (serré), page « Classement financier » (tableau 7 colonnes sans défilement, cartes `col-sm-3`), page d'inscription (champs 15 px → zoom iOS). Connexion : déjà responsive (image masquée sous 992 px).

## Architecture

### 1. Manifeste et service worker (`sitiame_core/pwa.py`)

Classe `PWARenderer` (hook `page_renderer`) qui sert à la racine du site :
- `/manifest.webmanifest` (`application/manifest+json`) : nom « Sitiame Capital », `short_name` « Sitiame », `id`/`start_url` `/desk`, `scope` `/`, `display` `standalone`, `lang` `fr`, `theme_color` `#05157c` (bleu du logo), `background_color` `#ffffff`, icônes 192/512 (`any`) et 512 `maskable`.
- `/sitiame-sw.js` (`application/javascript`, `Service-Worker-Allowed: /`) — à la racine pour contrôler `/desk` et `/login`.
- Les deux avec `Cache-Control: no-cache` (un nouveau service worker est pris au déploiement suivant).

Stratégie du service worker — **aucune donnée métier en cache** :
- navigation (pages) : réseau ; si le réseau échoue → page hors ligne `/assets/sitiame_core/pwa/offline.html` (pré-cachée à l'installation).
- `/assets/` versionnés (bundles hachés `[.-][A-Z0-9]{8,}.js|css`, polices) : cache d'abord.
- autres `/assets/` (scripts Sitiame non versionnés, images) : réseau d'abord, cache en secours hors ligne.
- tout le reste (API `/api/...`, autres origines, non-GET) : non intercepté.
- `activate` supprime les anciens caches (`sitiame-v1` → versions suivantes).

### 2. Icônes et page hors ligne (`sitiame_core/public/pwa/`)

`icon-192.png`, `icon-512.png` (emblème du logo — le S dans le cercle doré — à 86 % sur fond blanc), `icon-maskable-512.png` (62 %, zone de sécurité Android), `apple-touch-icon.png` (180). Générées depuis `public/images/sitiame-capital-logo.png` par `scripts/make_pwa_icons.py` (committé, pour régénérer si le logo change). `offline.html` : page autonome (styles inline) « Vous êtes hors ligne » + bouton « Réessayer ».

### 3. Branchement côté navigateur (`sitiame_core/public/js/pwa.js`)

Chargé par `app_include_js` (bureau) et `web_include_js` (connexion) :
- ajoute dans `<head>` : `link rel=manifest`, `link rel=apple-touch-icon`, `meta theme-color` (#05157c, remplace le bleu Frappe), `apple-mobile-web-app-title`, `apple-mobile-web-app-capable` ;
- enregistre `/sitiame-sw.js` ;
- bandeau d'installation, **uniquement connecté, hors mode application, et jamais après fermeture** (`localStorage` `sitiame_install_prompt_dismissed`, lectures/écritures en `try/catch`) :
  - Android/Chrome/Edge : sur `beforeinstallprompt`, « Installez Sitiame sur cet appareil… » + bouton « Installer » (appelle `prompt()`) + ×. Si l'événement arrive avant que la session soit prête, il est gardé et proposé à `app_ready`.
  - iPhone/iPad (pas de prompt dans Safari) : après 3 s, « Pour installer Sitiame : touchez Partager puis « Sur l'écran d'accueil ». » + ×.
  - `appinstalled` → bandeau fermé définitivement.
- styles du bandeau en ligne (le fichier sert aussi hors bureau).

### 4. Responsive (`sitiame_core/public/css/sitiame_mobile.css`, `app_include_css`)

Sous 576 px (les styles en ligne posés par les widgets imposent `!important`) : sélecteur FR/EN compact ; bouton IA 46 px ; panneau IA pleine largeur (8 px de marge), 72 vh, au-dessus du bouton ; champ IA 16 px ; bandeau d'abonnement 12 px. Quand le bandeau d'installation est affiché (`body.sitiame-install-banner-shown`), bouton et panneau IA remontent pour ne pas le couvrir.

`erp_financial_ranking.js` : cartes `col-6 col-md-3` (2 par ligne sur téléphone), filtres `col-12 col-sm-4`, tableau dans `div.table-responsive`.

`www/company-signup.html` : champs à 16 px sous 576 px (anti-zoom iOS).

## Hors périmètre

Saisie hors ligne, synchronisation, notifications push, PME360, refonte des écrans natifs ERPNext.

## Vérification

- pytest : `PWARenderer` (routes servies / non servies, types MIME, en-têtes), manifeste (JSON, `start_url`, `display`, icônes existantes aux bonnes tailles), service worker (n'intercepte ni `/api/` ni les non-GET, page hors ligne pré-cachée), hooks (renderer, JS/CSS inclus).
- `node --check` sur `pwa.js` et sur le service worker généré.
- Production : `curl` des deux routes (200, types, en-têtes), des icônes et de `offline.html` ; test par l'utilisateur sur téléphone (installation Android/iPhone, écrans Sitiame).
