# Admin Platform Users Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Three named ERPNext admins get a read-only page listing every provisioned PME with its payment status, free-trial/subscription dates, and last login — built entirely from data PME360 already tracks.

**Architecture:** A new PME360 webhook endpoint (`GET /webhooks/erpnext/platform-users`, same shared-token pattern as `financing-dossier`) exposes the data; a new ERPNext page in `sitiame_core` (same pattern as `erp_financial_ranking.js`) renders it, gated by a hardcoded email allowlist in the whitelisted Python function (not just a role check) plus the existing per-user tile-hiding mechanism for the other admin accounts.

**Tech Stack:** Laravel 13 / PHP 8.4 (PME360), Frappe/ERPNext v16 (`sitiame_core`).

## Global Constraints

- Scope of "a PME" = `User::whereNotNull('erpnext_company_name')` (same filter already validated in `ProvisionErpNextAccessForExistingPmes`).
- Access to the ERPNext page and its data function is restricted to exactly `fnguessan@sitiame-capital.com`, `joseph@sitiame-capital.com`, `kyliyanisse@gmail.com` — enforced server-side, not just via role or tile visibility.
- Read-only: no actions (renew, disable, etc.) from this page in this iteration.
- Last login uses `UserLoginLog::where('event', 'login')->selectRaw('user_id, MAX(created_at) as last_login_at')->groupBy('user_id')` — the exact query already used by `CommercialTeamOverviewService`.

---

### Task 1: PME360 — webhook endpoint listing platform users

**Files:**
- Create: `app/Http/Controllers/ErpNextPlatformUsersWebhookController.php`
- Modify: `routes/web.php` (add route, after the `webhooks.erpnext.subscription-paid` route at line 99-101)
- Test: `tests/Feature/ErpNextPlatformUsersWebhookTest.php`

**Interfaces:**
- Consumes: `User` model (`erpnext_company_name`, `email`, `name`, `company_name`, `is_premium`, `premium_status`, `premium_trial_ends_at`, `premium_ends_at`, `created_at`), `UserLoginLog` model (`user_id`, `event`, `created_at`).
- Produces: route `webhooks.erpnext.platform-users` (GET), consumed by Task 2's ERPNext function.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ErpNextPlatformUsersWebhookTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ErpNextPlatformUsersWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.erpnext.webhook_token', self::TOKEN);
    }

    private function getWebhook(?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];

        return $this->withHeaders($headers)->getJson(route('webhooks.erpnext.platform-users'));
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $this->getWebhook(null)->assertForbidden();
        $this->getWebhook('wrong-token')->assertForbidden();
    }

    public function test_excludes_users_without_an_erpnext_company(): void
    {
        User::factory()->create(['erpnext_company_name' => null]);

        $response = $this->getWebhook();

        $response->assertOk()->assertJsonCount(0, 'users');
    }

    public function test_returns_pme_with_payment_status_and_dates(): void
    {
        $pme = User::factory()->create([
            'email' => 'pme@test.local',
            'company_name' => 'Test SARL',
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_trial_ends_at' => null,
            'premium_ends_at' => now()->addDays(20),
        ]);

        UserLoginLog::create([
            'user_id' => $pme->id,
            'event' => 'login',
            'created_at' => now()->subDay(),
        ]);
        UserLoginLog::create([
            'user_id' => $pme->id,
            'event' => 'login',
            'created_at' => now()->subHour(),
        ]);

        $response = $this->getWebhook();

        $response->assertOk()->assertJsonCount(1, 'users');
        $row = $response->json('users.0');

        $this->assertSame('pme@test.local', $row['email']);
        $this->assertSame('Test SARL #1', $row['erpnext_company_name']);
        $this->assertTrue($row['is_premium']);
        $this->assertSame('active', $row['premium_status']);
        $this->assertNotNull($row['registered_at']);
        // The most recent of the two login rows, not the oldest.
        $this->assertSame(
            $pme->fresh()->created_at->toIso8601String(),
            $row['registered_at']
        );
        $this->assertTrue(
            \Illuminate\Support\Carbon::parse($row['last_login_at'])->isSameMinute(now()->subHour())
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine \
  sh -c "vendor/bin/phpunit tests/Feature/ErpNextPlatformUsersWebhookTest.php"
```

Expected: FAIL — route/controller not defined yet.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/ErpNextPlatformUsersWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Liste en lecture seule toutes les PME provisionnées (celles avec une
 * société ERPNext), pour la page admin "Utilisateurs de la plateforme"
 * côté ERPNext -- même sens et même jeton partagé que
 * ErpNextFinancingDossierWebhookController : ERPNext appelle PME360.
 */
class ErpNextPlatformUsersWebhookController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $pmes = User::whereNotNull('erpnext_company_name')->orderBy('created_at')->get();

        $lastLogins = UserLoginLog::query()
            ->whereIn('user_id', $pmes->pluck('id'))
            ->where('event', 'login')
            ->selectRaw('user_id, MAX(created_at) as last_login_at')
            ->groupBy('user_id')
            ->pluck('last_login_at', 'user_id');

        $users = $pmes->map(fn (User $pme) => [
            'email' => $pme->email,
            'name' => $pme->name,
            'company_name' => $pme->company_name,
            'erpnext_company_name' => $pme->erpnext_company_name,
            'is_premium' => (bool) $pme->is_premium,
            'premium_status' => $pme->premium_status,
            'premium_trial_ends_at' => optional($pme->premium_trial_ends_at)->toIso8601String(),
            'premium_ends_at' => optional($pme->premium_ends_at)->toIso8601String(),
            'registered_at' => $pme->created_at->toIso8601String(),
            'last_login_at' => optional($lastLogins->get($pme->id))
                ? \Illuminate\Support\Carbon::parse($lastLogins->get($pme->id))->toIso8601String()
                : null,
        ])->values();

        return response()->json(['users' => $users], 200);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, right after the `webhooks.erpnext.subscription-paid` route (line 99-101):

```php
Route::get('/webhooks/erpnext/platform-users', [\App\Http\Controllers\ErpNextPlatformUsersWebhookController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('webhooks.erpnext.platform-users');
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine \
  sh -c "vendor/bin/phpunit tests/Feature/ErpNextPlatformUsersWebhookTest.php"
```

Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ErpNextPlatformUsersWebhookController.php routes/web.php tests/Feature/ErpNextPlatformUsersWebhookTest.php
git commit -m "feat(admin): add platform-users webhook listing every provisioned PME"
```

---

### Task 2: ERPNext — whitelisted function fetching the list, gated to 3 admins

**Files:**
- Modify: `sitiame_core/sitiame_core/api.py` (append a new function at the end of the file, after `get_financing_dossier_from_pme360`)

**Interfaces:**
- Consumes: `frappe.conf.get("pme360_base_url")`/`frappe.conf.get("pme360_webhook_token")` (already configured), Task 1's `GET /webhooks/erpnext/platform-users`.
- Produces: whitelisted method `sitiame_core.api.get_platform_users`, consumed by Task 3's page JS.

- [ ] **Step 1: Add the function**

Append to the end of `sitiame_core/sitiame_core/api.py`:

```python
PLATFORM_USERS_ALLOWED_EMAILS = {
	"fnguessan@sitiame-capital.com",
	"joseph@sitiame-capital.com",
	"kyliyanisse@gmail.com",
}


@frappe.whitelist()
def get_platform_users():
	"""Liste toutes les PME provisionnees avec leur statut d'abonnement et
	leur activite de connexion, pour la page admin "Utilisateurs de la
	plateforme". Reserve a une liste precise d'admins -- pas seulement
	System Manager, meme un autre admin ERPNext n'y a pas acces.
	"""
	if frappe.session.user not in PLATFORM_USERS_ALLOWED_EMAILS:
		frappe.throw(_("Reserve a certains administrateurs."), frappe.PermissionError)

	base_url = (frappe.conf.get("pme360_base_url") or "https://sitiame-capital.com").rstrip("/")
	token = frappe.conf.get("pme360_webhook_token")
	if not token:
		frappe.throw(_("pme360_webhook_token n'est pas configure dans site_config.json."))

	try:
		response = requests.get(
			f"{base_url}/webhooks/erpnext/platform-users",
			headers={"X-PME360-Webhook-Token": token},
			timeout=20,
		)
	except requests.RequestException as e:
		frappe.throw(_("PME360 injoignable : {0}").format(str(e)))

	if response.status_code >= 400:
		try:
			detail = response.json()
			message = detail.get("message") or detail
		except ValueError:
			message = response.text
		frappe.throw(_("PME360 a refuse la demande : {0}").format(message))

	return response.json()
```

- [ ] **Step 2: Verify the file parses cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/api.py').read())"
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/api.py
git commit -m "feat(admin): add get_platform_users, gated to 3 named admin accounts"
```

---

### Task 3: ERPNext — the "Utilisateurs de la plateforme" page

**Files:**
- Create: `sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/__init__.py`
- Create: `sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.json`
- Create: `sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.js`

**Interfaces:**
- Consumes: `sitiame_core.api.get_platform_users` (Task 2).

- [ ] **Step 1: Create the page directory and empty `__init__.py`**

```bash
mkdir -p sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs
touch sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/__init__.py
```

- [ ] **Step 2: Write the page definition**

Create `sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.json`:

```json
{
 "content": null,
 "creation": "2026-09-23 00:00:00.000000",
 "docstatus": 0,
 "doctype": "Page",
 "icon": "users",
 "idx": 0,
 "modified": "2026-09-23 00:00:00.000000",
 "modified_by": "Administrator",
 "module": "Sitiame Core",
 "name": "erp-plateforme-utilisateurs",
 "owner": "Administrator",
 "page_name": "erp-plateforme-utilisateurs",
 "restrict_to_domain": null,
 "roles": [
  {
   "role": "System Manager"
  }
 ],
 "standard": "Yes",
 "system_page": 0,
 "title": "Utilisateurs de la plateforme"
}
```

(`roles: System Manager` is the coarse Frappe-level gate so non-admins can't even load the page shell; the real, precise 3-admin check happens inside `get_platform_users` itself, Task 2 — this page grants no data on its own, it only renders whatever the whitelisted call returns.)

- [ ] **Step 3: Write the page script**

Create `sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.js`:

```js
frappe.pages["erp-plateforme-utilisateurs"].on_page_load = function (wrapper) {
	var page = frappe.ui.make_app_page({
		parent: wrapper,
		title: __("Utilisateurs de la plateforme"),
		single_column: true,
	});

	var $body = $("<div style='padding:0 15px;margin-top:15px;'></div>").appendTo(page.body);

	function statusLabel(user) {
		if (user.is_premium) return { text: __("Payant"), cls: "success" };
		if (user.premium_trial_ends_at && moment(user.premium_trial_ends_at).isAfter(moment())) {
			var days = moment(user.premium_trial_ends_at).diff(moment(), "days");
			return { text: __("Essai gratuit ({0} j restants)", [days]), cls: "warning" };
		}
		return { text: __("Expire"), cls: "secondary" };
	}

	function fmt(date) {
		return date ? frappe.datetime.str_to_user(frappe.datetime.convert_to_system_tz(date)) : "-";
	}

	function render() {
		$body.html("<p class='text-muted'>" + __("Chargement...") + "</p>");

		frappe.call({ method: "sitiame_core.api.get_platform_users" })
			.then(function (r) {
				var users = (r.message || {}).users || [];

				if (!users.length) {
					$body.html("<p class='text-muted'>" + __("Aucune PME inscrite.") + "</p>");
					return;
				}

				var html =
					"<table class='table table-bordered bg-white'><thead><tr>" +
					"<th>" + __("Societe") + "</th>" +
					"<th>" + __("Email") + "</th>" +
					"<th>" + __("Statut") + "</th>" +
					"<th>" + __("Fin d'essai gratuit") + "</th>" +
					"<th>" + __("Fin d'abonnement") + "</th>" +
					"<th>" + __("Inscrit le") + "</th>" +
					"<th>" + __("Derniere connexion") + "</th>" +
					"</tr></thead><tbody>";

				users.forEach(function (u) {
					var status = statusLabel(u);
					html +=
						"<tr>" +
						"<td class='fw-bold'>" + frappe.utils.escape_html(u.erpnext_company_name || u.company_name || "-") + "</td>" +
						"<td>" + frappe.utils.escape_html(u.email) + "</td>" +
						"<td><span class='indicator-pill " + status.cls + "'>" + status.text + "</span></td>" +
						"<td>" + fmt(u.premium_trial_ends_at) + "</td>" +
						"<td>" + fmt(u.premium_ends_at) + "</td>" +
						"<td>" + fmt(u.registered_at) + "</td>" +
						"<td>" + (u.last_login_at ? fmt(u.last_login_at) : __("Jamais")) + "</td>" +
						"</tr>";
				});

				html += "</tbody></table>";
				$body.html(html);
			})
			.catch(function () {
				$body.html("<p class='text-danger'>" + __("Impossible de charger les utilisateurs (voir le journal des erreurs).") + "</p>");
			});
	}

	render();
};
```

- [ ] **Step 4: Verify the JS file parses cleanly and the JSON is valid**

```bash
node --check sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.js
python -c "import json; json.load(open('sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/erp_plateforme_utilisateurs.json'))"
```

Expected: no output from either command.

- [ ] **Step 5: Commit**

```bash
git add sitiame_core/sitiame_core/page/erp_plateforme_utilisateurs/
git commit -m "feat(admin): add the Utilisateurs de la plateforme page"
```

---

### Task 4: ERPNext — deploy, migrate, and set up the tile

**Files:** none (deployment + one-off DB script only).

- [ ] **Step 1: Push and deploy**

```bash
git push origin master
```

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
set -e
docker exec sitiame-prod-backend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
docker exec sitiame-prod-frontend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com migrate
docker exec sitiame-prod-backend-1 bench build --app sitiame_core
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com clear-cache
'
```

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'cd /root && docker compose -f sitiame-prod-compose.yml restart backend frontend'
```

- [ ] **Step 2: Create the Desktop Icon and Workspace Sidebar (one-off script)**

Following the established pattern (`standard=0, owner="Administrator"` — a `standard=1` icon without an exported fixture gets deleted by `bench migrate`'s orphan cleanup), write locally, deploy via `scp`/`docker cp`/`bench execute`, then delete the temp file:

```python
import frappe


def run():
	if not frappe.db.exists("Workspace Sidebar", "Utilisateurs de la plateforme"):
		sidebar = frappe.get_doc(
			{
				"doctype": "Workspace Sidebar",
				"title": "Utilisateurs de la plateforme",
				"items": [
					{
						"label": "Utilisateurs de la plateforme",
						"link_type": "Page",
						"link_to": "erp-plateforme-utilisateurs",
					}
				],
			}
		)
		sidebar.insert(ignore_permissions=True)
		frappe.db.commit()

	if not frappe.db.exists("Desktop Icon", "Utilisateurs plateforme"):
		icon = frappe.get_doc(
			{
				"doctype": "Desktop Icon",
				"label": "Utilisateurs plateforme",
				"standard": 0,
				"owner": "Administrator",
				"icon_type": "Link",
				"link_type": "Workspace Sidebar",
				"link_to": "Utilisateurs de la plateforme",
				"bg_color": "gray",
			}
		)
		icon.insert(ignore_permissions=True)
		frappe.db.set_value("Desktop Icon", icon.name, "bg_color", "green")
		frappe.db.commit()

	return {
		"sidebar_created": frappe.db.exists("Workspace Sidebar", "Utilisateurs de la plateforme"),
		"icon_created": frappe.db.exists("Desktop Icon", "Utilisateurs plateforme"),
	}
```

- [ ] **Step 3: Hide the tile from the other existing admin accounts**

```python
import json

import frappe

NOT_ALLOWED = ["ekonan@sitiame-capital.com", "ahoulouariel@sitiame-capital.com", "chrys-ivan@sitiame-capital.com"]


def run():
	result = {}
	for email in NOT_ALLOWED:
		if not frappe.db.exists("User", email):
			result[email] = "not found"
			continue
		raw = frappe.db.get_value("User", email, "sitiame_hidden_desktop_icons")
		hidden = set(json.loads(raw)) if raw else set()
		hidden.add("Utilisateurs plateforme")
		frappe.db.set_value("User", email, "sitiame_hidden_desktop_icons", json.dumps(list(hidden)))
		result[email] = "hidden"

	frappe.db.commit()
	frappe.cache().delete_keys("*desktop_icons*")
	frappe.cache().delete_keys("*bootinfo*")
	frappe.clear_cache()
	return result
```

- [ ] **Step 4: Manual verification**

Ask the user to hard-refresh `/desk` as `kyliyanisse@gmail.com` and confirm: the green "Utilisateurs plateforme" tile appears, clicking it shows the PME table with correct statuses/dates. Then confirm the tile does NOT appear for ekonan/Ahoulou Ariel/Chrys-Ivan, and that navigating directly to the page URL as one of them is refused (Task 2's server-side check).
