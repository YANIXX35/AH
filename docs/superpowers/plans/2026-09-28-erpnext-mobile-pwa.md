# ERPNext mobile et installable (PWA) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre le bureau ERPNext installable (manifeste, service worker, icônes, bandeau d'installation) et corriger l'affichage téléphone des écrans ajoutés par Sitiame.

**Architecture:** `sitiame_core/pwa.py` (renderer `page_renderer` qui sert `/manifest.webmanifest` et `/sitiame-sw.js`), `public/pwa/` (icônes + page hors ligne), `public/js/pwa.js` (balises head, enregistrement, bandeau), `public/css/sitiame_mobile.css` + retouches de deux écrans.

**Tech Stack:** Frappe v16 (hook `page_renderer`), werkzeug `Response`, Service Worker API, Pillow (génération d'icônes), pytest.

**Spec:** `docs/superpowers/specs/2026-09-28-erpnext-mobile-pwa-design.md`

## Global Constraints

- Repo `C:\Users\yaniss\Desktop\sitiame_core`, branche `master`, tests `python -m pytest -q tests` (125 au départ).
- Le service worker ne met JAMAIS en cache `/api/`, ni une page, ni une requête non-GET, ni une autre origine.
- `theme_color` `#05157c`. Démarrage `/desk`.
- Styles en ligne des widgets → surcharges CSS en `!important`, sous `max-width: 575.98px`.
- `localStorage` toujours en `try/catch`.
- CI : ajouter `werkzeug` à l'installation pip de `.github/workflows/tests.yml`.
- Déploiement via `sitiame_core_deploy.sh` (build inclus).
- Commits terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

---

### Task 1: Icônes, page hors ligne, `pwa.py`, hooks

**Files:**
- Create: `scripts/make_pwa_icons.py`, `sitiame_core/public/pwa/{icon-192.png, icon-512.png, icon-maskable-512.png, apple-touch-icon.png, offline.html}`, `sitiame_core/pwa.py`, `tests/test_pwa.py`
- Modify: `sitiame_core/hooks.py` (`page_renderer`), `.github/workflows/tests.yml`

**Interfaces:**
- Produces: `pwa.PWARenderer(path, http_status_code)` avec `can_render()`/`render()`; `pwa.MANIFEST` (dict); `pwa.SERVICE_WORKER` (str); `pwa.OFFLINE_URL`.

- [ ] **Step 1: Icônes** — `scripts/make_pwa_icons.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Regenerates the installable app's icons (sitiame_core/public/pwa/) from
the circular emblem of the logo -- the S in the gold arrows circle, left
part of public/images/sitiame-capital-logo.png. Run from the repo root:

    python scripts/make_pwa_icons.py
"""

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1] / "sitiame_core" / "public"
logo = Image.open(ROOT / "images" / "sitiame-capital-logo.png").convert("RGBA")
out = ROOT / "pwa"

# emblem = non-white, non-transparent pixels of the left 45% of the logo
left = logo.crop((0, 0, int(logo.width * 0.45), logo.height))
mask = Image.new("L", left.size, 0)
pixels, marks = left.load(), mask.load()
for x in range(left.width):
	for y in range(left.height):
		r, g, b, a = pixels[x, y]
		if a > 40 and min(r, g, b) < 225:
			marks[x, y] = 255
emblem = left.crop(mask.getbbox())


def icon(size, ratio):
	canvas = Image.new("RGBA", (size, size), (255, 255, 255, 255))
	scale = size * ratio / max(emblem.size)
	art = emblem.resize((round(emblem.width * scale), round(emblem.height * scale)), Image.LANCZOS)
	canvas.alpha_composite(art, ((size - art.width) // 2, (size - art.height) // 2))
	return canvas.convert("RGB")


icon(192, 0.86).save(out / "icon-192.png", optimize=True)
icon(512, 0.86).save(out / "icon-512.png", optimize=True)
# maskable: Android crops to a circle/squircle, the art must stay in the
# central safe zone
icon(512, 0.62).save(out / "icon-maskable-512.png", optimize=True)
icon(180, 0.86).save(out / "apple-touch-icon.png", optimize=True)
```

Run: `mkdir -p sitiame_core/public/pwa && python scripts/make_pwa_icons.py` → 4 PNG.

- [ ] **Step 2: Page hors ligne** — `sitiame_core/public/pwa/offline.html` :

```html
<!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#05157c">
	<title>Hors ligne - Sitiame</title>
	<style>
		body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
			font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f8fafc; color: #0f172a; }
		main { max-width: 360px; padding: 32px 24px; text-align: center; }
		img { width: 96px; height: 96px; }
		h1 { font-size: 20px; color: #05157c; margin: 16px 0 8px; }
		p { font-size: 15px; line-height: 1.5; color: #475569; margin: 0 0 24px; }
		button { font-size: 16px; padding: 12px 28px; border: none; border-radius: 8px; background: #05157c; color: #fff; }
	</style>
</head>
<body>
	<main>
		<img src="/assets/sitiame_core/pwa/icon-192.png" alt="Sitiame">
		<h1>Vous êtes hors ligne</h1>
		<p>Sitiame a besoin d'une connexion pour afficher vos données à jour. Vérifiez votre réseau puis réessayez.</p>
		<button type="button" onclick="location.reload()">Réessayer</button>
	</main>
</body>
</html>
```

- [ ] **Step 3: Failing tests** — `tests/test_pwa.py` :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Installable app: manifest + service worker served at the site root
(sitiame_core.pwa), icons, and their hooks."""

import json
from pathlib import Path

import pytest
from PIL import Image

from sitiame_core import hooks, pwa

PUBLIC = Path(__file__).resolve().parents[1] / "sitiame_core" / "public"


def _get(path):
	renderer = pwa.PWARenderer(path)
	assert renderer.can_render()
	return renderer.render()


@pytest.mark.parametrize("path", ["desk", "login", "sw.js", "manifest.json", "api/method/x", ""])
def test_other_paths_are_left_to_frappe(path):
	assert not pwa.PWARenderer(path).can_render()


def test_manifest_is_served_as_a_web_app_manifest():
	response = _get("/manifest.webmanifest")
	assert response.mimetype == "application/manifest+json"
	assert response.headers["Cache-Control"] == "no-cache"
	manifest = json.loads(response.get_data(as_text=True))
	assert manifest["start_url"] == "/desk" and manifest["scope"] == "/"
	assert manifest["display"] == "standalone"
	assert manifest["theme_color"] == "#05157c"


def test_manifest_icons_exist_at_their_declared_size():
	purposes = set()
	for icon in pwa.MANIFEST["icons"]:
		path = PUBLIC / icon["src"].removeprefix("/assets/sitiame_core/")
		width, height = Image.open(path).size
		assert f"{width}x{height}" == icon["sizes"]
		purposes.add(icon["purpose"])
	assert {"any", "maskable"} <= purposes
	assert any(icon["sizes"] == "512x512" for icon in pwa.MANIFEST["icons"])


def test_service_worker_is_served_for_the_whole_site():
	response = _get("sitiame-sw.js")
	assert response.mimetype == "application/javascript"
	assert response.headers["Service-Worker-Allowed"] == "/"
	assert response.headers["Cache-Control"] == "no-cache"
	assert response.get_data(as_text=True) == pwa.SERVICE_WORKER


def test_service_worker_never_touches_business_data():
	sw = pwa.SERVICE_WORKER
	assert 'request.method !== "GET"' in sw
	assert "url.origin !== self.location.origin" in sw
	assert 'if (!url.pathname.startsWith("/assets/")) return;' in sw
	assert "/api/" not in sw


def test_offline_page_is_precached_and_exists():
	assert pwa.OFFLINE_URL in pwa.SERVICE_WORKER
	assert (PUBLIC / pwa.OFFLINE_URL.removeprefix("/assets/sitiame_core/")).is_file()


def test_hooks_wire_the_pwa():
	assert "sitiame_core.pwa.PWARenderer" in hooks.page_renderer
	assert "/assets/sitiame_core/js/pwa.js" in hooks.app_include_js
	assert "/assets/sitiame_core/js/pwa.js" in hooks.web_include_js
	assert "/assets/sitiame_core/css/sitiame_mobile.css" in hooks.app_include_css
```

- [ ] **Step 4:** `python -m pytest -q tests/test_pwa.py` → FAIL (module absent).

- [ ] **Step 5: `sitiame_core/pwa.py`** :

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

"""Installable app (PWA) for the ERPNext desk: the web app manifest and
the service worker, served at the site root through the page_renderer hook
-- a service worker only controls the pages under its own path, and
Frappe's static www pages refuse .js/.json files.

The service worker never caches business data: pages and API calls always
go to the network (an ERP must show live figures, and a lost phone must
not hold the books). It keeps versioned front-end files and an offline
page, nothing else.
"""

import json

from werkzeug.wrappers import Response

THEME_COLOR = "#05157c"
ICONS = "/assets/sitiame_core/pwa"
OFFLINE_URL = f"{ICONS}/offline.html"

MANIFEST = {
	"id": "/desk",
	"name": "Sitiame Capital",
	"short_name": "Sitiame",
	"description": "Comptabilité, facturation, stock et financement des PME",
	"lang": "fr",
	"start_url": "/desk",
	"scope": "/",
	"display": "standalone",
	"orientation": "any",
	"theme_color": THEME_COLOR,
	"background_color": "#ffffff",
	"icons": [
		{"src": f"{ICONS}/icon-192.png", "sizes": "192x192", "type": "image/png", "purpose": "any"},
		{"src": f"{ICONS}/icon-512.png", "sizes": "512x512", "type": "image/png", "purpose": "any"},
		{"src": f"{ICONS}/icon-maskable-512.png", "sizes": "512x512", "type": "image/png", "purpose": "maskable"},
	],
}

SERVICE_WORKER = (
	"""// Sitiame Capital service worker (sitiame_core/pwa.py). Never caches
// business data: pages and API calls always go to the network.
const CACHE = "sitiame-v1";
const OFFLINE_URL = "%(offline)s";
// Files whose URL changes with their content (hashed bundles, fonts):
// safe to serve from the cache first.
const VERSIONED = /[.-][A-Z0-9]{8,}\\.(js|css)$|\\.(woff2?|ttf)$/i;

self.addEventListener("install", (event) => {
	event.waitUntil(
		caches.open(CACHE).then((cache) => cache.addAll([OFFLINE_URL, "%(icon)s"])).then(() => self.skipWaiting())
	);
});

self.addEventListener("activate", (event) => {
	event.waitUntil(
		caches
			.keys()
			.then((keys) => Promise.all(keys.filter((key) => key !== CACHE).map((key) => caches.delete(key))))
			.then(() => self.clients.claim())
	);
});

self.addEventListener("fetch", (event) => {
	const request = event.request;
	if (request.method !== "GET") return;
	const url = new URL(request.url);
	if (url.origin !== self.location.origin) return;

	if (request.mode === "navigate") {
		event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
		return;
	}
	if (!url.pathname.startsWith("/assets/")) return;

	if (VERSIONED.test(url.pathname)) {
		event.respondWith(
			caches.open(CACHE).then((cache) =>
				cache.match(request).then(
					(hit) =>
						hit ||
						fetch(request).then((response) => {
							if (response.ok) cache.put(request, response.clone());
							return response;
						})
				)
			)
		);
		return;
	}
	// unversioned (Sitiame scripts, images): network first, cache offline
	event.respondWith(
		fetch(request)
			.then((response) => {
				if (response.ok) {
					const copy = response.clone();
					caches.open(CACHE).then((cache) => cache.put(request, copy));
				}
				return response;
			})
			.catch(() => caches.match(request))
	);
});
"""
	% {"offline": OFFLINE_URL, "icon": f"{ICONS}/icon-192.png"}
)

_ROUTES = {
	"manifest.webmanifest": ("application/manifest+json", lambda: json.dumps(MANIFEST, ensure_ascii=False)),
	"sitiame-sw.js": ("application/javascript", lambda: SERVICE_WORKER),
}


class PWARenderer:
	"""page_renderer hook: Frappe tries it before its own renderers."""

	def __init__(self, path=None, http_status_code=None):
		self.path = (path or "").strip("/ ")
		self.http_status_code = http_status_code or 200

	def can_render(self):
		return self.path in _ROUTES

	def render(self):
		mimetype, body = _ROUTES[self.path]
		response = Response(body(), mimetype=mimetype)
		# a new manifest/worker must be picked up on the next deploy
		response.headers["Cache-Control"] = "no-cache"
		if self.path == "sitiame-sw.js":
			response.headers["Service-Worker-Allowed"] = "/"
		return response
```

- [ ] **Step 6: hooks** — ajouter après `web_include_css` : `page_renderer = ["sitiame_core.pwa.PWARenderer"]`; ajouter `"/assets/sitiame_core/js/pwa.js"` en tête de `web_include_js` et de `app_include_js`; `app_include_css` += `"/assets/sitiame_core/css/sitiame_mobile.css"`. (`pwa.js`/`sitiame_mobile.css` créés en Task 2 ; le test hooks passe à la fin de Task 2.) CI : `pip install pytest requests openpyxl python-docx werkzeug`.

- [ ] **Step 7:** `python -m pytest -q tests` → tout passe sauf `test_hooks_wire_the_pwa` si Task 2 pas encore faite (faire Task 2 avant de committer, ou committer ensemble). Vérifier le JS du SW : écrire `pwa.SERVICE_WORKER` dans un fichier temporaire puis `node --check`.
- [ ] **Step 8: Commit** `feat(pwa): installable ERPNext desk (manifest, service worker, icons, offline page)`.

---

### Task 2: `pwa.js`, CSS mobile, écrans Sitiame

**Files:**
- Create: `sitiame_core/public/js/pwa.js`, `sitiame_core/public/css/sitiame_mobile.css`
- Modify: `sitiame_core/sitiame_core/page/erp_financial_ranking/erp_financial_ranking.js`, `sitiame_core/www/company-signup.html`

- [ ] **Step 1: `pwa.js`** :

```js
// Installable app (sitiame_core/pwa.py): head tags, service worker, and a
// one-time install banner for logged-in users -- the browser's own prompt
// on Android/desktop, a "Partager > Sur l'ecran d'accueil" hint on iPhone
// (Safari has no install prompt). Loaded on the desk and on the website
// pages (login), so it never relies on desk-only helpers.
(function () {
	if (window.sitiame_pwa_loaded) return;
	window.sitiame_pwa_loaded = true;

	var ICONS = "/assets/sitiame_core/pwa";
	var DISMISSED_KEY = "sitiame_install_prompt_dismissed";
	var t = window.__ || function (text) {
		return text;
	};

	function headTag(tag, key, attrs) {
		var el = document.head.querySelector(tag + "[" + key + "='" + attrs[key] + "']");
		if (!el) el = document.head.appendChild(document.createElement(tag));
		Object.keys(attrs).forEach(function (name) {
			el.setAttribute(name, attrs[name]);
		});
	}

	headTag("link", "rel", { rel: "manifest", href: "/manifest.webmanifest" });
	headTag("link", "rel", { rel: "apple-touch-icon", href: ICONS + "/apple-touch-icon.png" });
	headTag("meta", "name", { name: "theme-color", content: "#05157c" });
	headTag("meta", "name", { name: "apple-mobile-web-app-title", content: "Sitiame" });
	headTag("meta", "name", { name: "apple-mobile-web-app-capable", content: "yes" });

	if ("serviceWorker" in navigator) {
		navigator.serviceWorker.register("/sitiame-sw.js").catch(function () {});
	}

	function dismissed() {
		try {
			return window.localStorage.getItem(DISMISSED_KEY) === "1";
		} catch (e) {
			return false;
		}
	}

	function standalone() {
		return (
			(window.matchMedia && window.matchMedia("(display-mode: standalone)").matches) ||
			window.navigator.standalone === true
		);
	}

	function loggedIn() {
		return !!(window.frappe && frappe.session && frappe.session.user && frappe.session.user !== "Guest");
	}

	var banner = null;

	function hide() {
		if (banner) banner.remove();
		banner = null;
		document.body.classList.remove("sitiame-install-banner-shown");
	}

	function dismiss() {
		try {
			window.localStorage.setItem(DISMISSED_KEY, "1");
		} catch (e) {
			// private browsing: the banner simply comes back next time
		}
		hide();
	}

	function show(text, actionLabel, onAction) {
		if (banner || dismissed() || standalone() || !loggedIn()) return;
		banner = document.createElement("div");
		banner.id = "sitiame-install-banner";
		banner.style.cssText =
			"position:fixed;left:0;right:0;bottom:0;z-index:10000;display:flex;align-items:center;gap:10px;" +
			"padding:12px 14px;background:#05157c;color:#fff;font-size:14px;line-height:1.35;" +
			"box-shadow:0 -4px 14px rgba(0,0,0,.18);";

		var icon = document.createElement("img");
		icon.src = ICONS + "/icon-192.png";
		icon.alt = "";
		icon.style.cssText = "width:36px;height:36px;border-radius:8px;background:#fff;flex:none;";

		var text_el = document.createElement("span");
		text_el.style.cssText = "flex:1;";
		text_el.textContent = text;

		banner.appendChild(icon);
		banner.appendChild(text_el);

		if (actionLabel) {
			var action = document.createElement("button");
			action.type = "button";
			action.textContent = actionLabel;
			action.style.cssText =
				"flex:none;border:none;border-radius:6px;padding:8px 14px;background:#d4af37;color:#05157c;font-weight:700;font-size:14px;";
			action.addEventListener("click", onAction);
			banner.appendChild(action);
		}

		var close = document.createElement("button");
		close.type = "button";
		close.setAttribute("aria-label", t("Fermer"));
		close.innerHTML = "&times;";
		close.style.cssText = "flex:none;border:none;background:none;color:#fff;font-size:24px;line-height:1;padding:0 4px;";
		close.addEventListener("click", dismiss);
		banner.appendChild(close);

		document.body.appendChild(banner);
		document.body.classList.add("sitiame-install-banner-shown");
	}

	var deferredPrompt = null;

	function offerInstall() {
		if (deferredPrompt) {
			show(t("Installez Sitiame sur cet appareil pour l'ouvrir comme une application."), t("Installer"), function () {
				var promptEvent = deferredPrompt;
				deferredPrompt = null;
				promptEvent.prompt();
				promptEvent.userChoice.then(function (choice) {
					if (choice.outcome === "accepted") dismiss();
					else hide();
				});
			});
		} else if (/iphone|ipad|ipod/i.test(window.navigator.userAgent)) {
			show(t("Pour installer Sitiame : touchez Partager puis « Sur l'écran d'accueil »."));
		}
	}

	window.addEventListener("beforeinstallprompt", function (event) {
		event.preventDefault();
		deferredPrompt = event;
		offerInstall();
	});
	window.addEventListener("appinstalled", dismiss);

	// the session may not be ready yet when the prompt event fires
	if (window.jQuery) {
		window.jQuery(document).on("app_ready", function () {
			setTimeout(offerInstall, 3000);
		});
	}
	setTimeout(offerInstall, 4000);
})();
```

- [ ] **Step 2: `sitiame_mobile.css`** :

```css
/* Phone-width adjustments for the screens Sitiame adds to the desk -- the
   native ERPNext lists and forms are already responsive. The widgets set
   inline styles from their JS, hence the !important. */

@media (max-width: 575.98px) {
	/* FR/EN switch (language_switcher.js) */
	#sitiame-lang-switch {
		margin-right: 6px !important;
		gap: 2px !important;
	}
	#sitiame-lang-switch button {
		padding: 2px 5px !important;
		font-size: 10px !important;
	}

	/* AI assistant (ai_assistant.js): full-width sheet above its button */
	#sitiame-ai-toggle {
		width: 46px !important;
		height: 46px !important;
		right: 14px !important;
		bottom: 14px !important;
		font-size: 20px !important;
	}
	#sitiame-ai-panel {
		left: 8px !important;
		right: 8px !important;
		width: auto !important;
		max-width: none !important;
		bottom: 70px !important;
		height: 72vh !important;
		max-height: none !important;
	}
	/* iOS zooms the page when an input under 16px gets focus */
	#sitiame-ai-input {
		font-size: 16px !important;
	}

	/* renewal banner (subscription_banner.js) */
	#sitiame-subscription-banner {
		font-size: 12px !important;
		padding: 6px 10px !important;
		gap: 6px !important;
	}
}

/* keep the AI assistant above the install banner (pwa.js) */
body.sitiame-install-banner-shown #sitiame-ai-toggle {
	bottom: 84px !important;
}
body.sitiame-install-banner-shown #sitiame-ai-panel {
	bottom: 140px !important;
}
```

- [ ] **Step 3: Classement financier** (`erp_financial_ranking.js`) : les 3 `col-sm-3` des filtres deviennent `col-12 col-sm-4` ; dans `statCard`, `col-sm-3` devient `col-6 col-md-3` ; envelopper le tableau : `"<div class='table-responsive'><table class='table table-bordered bg-white'>..."` et fermer par `"</tbody></table></div>"`.

- [ ] **Step 4: Inscription** (`company-signup.html`) : dans le `<style>`, après la règle `.signup-field input, .signup-field select { ... }`, ajouter :

```css
	/* iOS zooms the page when an input under 16px gets focus */
	@media (max-width: 575.98px) {
		.signup-field input, .signup-field select { font-size: 16px; }
	}
```

- [ ] **Step 5:** `node --check` sur `pwa.js` et `erp_financial_ranking.js` ; `python -m pytest -q tests` → tout passe (dont `test_hooks_wire_the_pwa`).
- [ ] **Step 6: Commit** `feat(mobile): install banner and phone layout of the Sitiame screens`.

---

### Task 3: Déploiement et vérification

- [ ] **Step 1:** push + `sitiame_core_deploy.sh` → `site OK`.
- [ ] **Step 2:** `curl -sI https://erp.sitiame-capital.com/manifest.webmanifest` → 200, `application/manifest+json` ; `/sitiame-sw.js` → 200, `application/javascript`, `Service-Worker-Allowed: /` ; `/assets/sitiame_core/pwa/icon-512.png` et `offline.html` → 200 ; `/login` contient `pwa.js`.
- [ ] **Step 3:** Test utilisateur sur téléphone : Android (Chrome) bandeau « Installer » puis icône Sitiame sur l'écran d'accueil ; iPhone (Safari) astuce Partager → écran d'accueil ; écrans Classement financier, assistant IA, FR/EN, inscription.
