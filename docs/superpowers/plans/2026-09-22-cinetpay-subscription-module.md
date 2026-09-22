# Module Abonnement CinetPay Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an ERPNext admin generate a CinetPay payment link for a PME's premium subscription (fixed 15 000 XOF / 1 month), confirm payment through CinetPay's own status endpoint (never trust the webhook payload), and activate premium on the PME360 side automatically.

**Architecture:** New `Subscription Payment` doctype in the `sitiame_core` ERPNext app drives the whole flow (generate link → receive webhook → re-verify via GET → notify PME360). A new `ErpNextSubscriptionPaidWebhookController` on the PME360 side receives the final confirmation and flips `is_premium`/`premium_ends_at`, reusing the existing shared-token webhook pattern already used by `register-pme` and `financing-dossier`.

**Tech Stack:** ERPNext v16 / Frappe v16 (Python, app `sitiame_core`), Laravel 13 (PHP 8.4), CinetPay Business API (`https://api.cinetpay.net`).

## Global Constraints

- Fixed tariff: `amount` defaults to `15000` (XOF), `duration_months` defaults to `1` — both are still plain editable fields, not a settings page (per approved spec).
- CinetPay `baseUrl` = `https://api.cinetpay.net`.
- Never trust the CinetPay webhook payload's status — always re-verify via `GET /v1/payment/{merchant_transaction_id}` before changing any state (CinetPay's own documented security rule).
- Webhook receiver must respond HTTP 200 within 10 seconds and be idempotent on `transaction_id`/document name (CinetPay may call it more than once).
- Shared secret header `X-PME360-Webhook-Token` (Laravel config `services.erpnext.webhook_token` / Frappe `site_config.json` key `pme360_webhook_token`) is reused as-is — do not introduce a new secret.
- ERPNext → PME360 base URL comes from `frappe.conf.get("pme360_base_url")` (already configured, defaults to `https://sitiame-capital.com` if unset).
- New ERPNext Desktop Icon must be created with `standard=0, owner="Administrator"` — a `standard=1` icon without an exported fixture gets deleted by `bench migrate`'s orphan-cleanup step (this already happened once to the Scoring tile).
- PHP tests run via `docker run --rm -v <repo>:/app -w /app php:8.4-cli-alpine ...` since the local system PHP is 8.2 (incompatible with this project's `^8.4` requirement).
- ERPNext deployment always touches **both** `sitiame-prod-backend-1` and `sitiame-prod-frontend-1` containers (git pull in both) — the frontend container has no persistent volume for `apps/sitiame_core`, a divergence there has broken asset loading before.

---

### Task 1: PME360 — webhook receiver that activates premium on confirmed payment

**Files:**
- Create: `app/Http/Controllers/ErpNextSubscriptionPaidWebhookController.php`
- Modify: `routes/web.php` (add route, near the other `webhooks.erpnext.*` routes around line 93-98)
- Modify: `bootstrap/app.php` (add to the `validateCsrfTokens(except: [...])` list around line 54-60)
- Test: `tests/Feature/ErpNextSubscriptionPaidWebhookTest.php`

**Interfaces:**
- Consumes: `User` model fields `erpnext_company_name`, `is_premium`, `premium_status`, `premium_ends_at` (already exist, see `app/Models/User.php:40-43,56`). Config key `services.erpnext.webhook_token` (`config/services.php:145`).
- Produces: route `webhooks.erpnext.subscription-paid` (POST), used by ERPNext's `_notify_pme360()` in Task 4.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ErpNextSubscriptionPaidWebhookTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ErpNextSubscriptionPaidWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-webhook-token';

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.erpnext.webhook_token', self::TOKEN);
    }

    private function postWebhook(array $payload, ?string $token = self::TOKEN)
    {
        $headers = $token !== null ? ['X-PME360-Webhook-Token' => $token] : [];

        return $this->withHeaders($headers)->postJson(route('webhooks.erpnext.subscription-paid'), $payload);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $payload = ['company' => 'Test', 'duration_months' => 1, 'paid_at' => now()->toIso8601String()];

        $this->postWebhook($payload, null)->assertForbidden();
        $this->postWebhook($payload, 'wrong-token')->assertForbidden();
    }

    public function test_requires_company_duration_and_paid_at(): void
    {
        $this->postWebhook([])->assertStatus(422);
    }

    public function test_ignores_unknown_company(): void
    {
        $payload = ['company' => 'Inconnue', 'duration_months' => 1, 'paid_at' => now()->toIso8601String()];

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'ignored', 'reason' => 'PME introuvable']);
    }

    public function test_activates_premium_when_previously_free(): void
    {
        $pme = User::factory()->create([
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => false,
            'premium_status' => 'free',
            'premium_ends_at' => null,
        ]);

        $paidAt = now();
        $this->postWebhook([
            'company' => 'Test SARL #1',
            'duration_months' => 1,
            'paid_at' => $paidAt->toIso8601String(),
        ])->assertOk()->assertJson(['status' => 'ok']);

        $pme->refresh();
        $this->assertTrue($pme->is_premium);
        $this->assertSame('active', $pme->premium_status);
        $this->assertTrue($pme->premium_ends_at->isSameDay($paidAt->copy()->addMonths(1)));
    }

    public function test_extends_from_current_expiry_when_renewing_early(): void
    {
        $currentExpiry = now()->addDays(10);
        $pme = User::factory()->create([
            'erpnext_company_name' => 'Test SARL #1',
            'is_premium' => true,
            'premium_status' => 'active',
            'premium_ends_at' => $currentExpiry,
        ]);

        $this->postWebhook([
            'company' => 'Test SARL #1',
            'duration_months' => 1,
            'paid_at' => now()->toIso8601String(),
        ])->assertOk();

        $pme->refresh();
        $this->assertTrue($pme->premium_ends_at->isSameDay($currentExpiry->copy()->addMonths(1)));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run (from the repo root, using the PHP 8.4 container since local PHP is 8.2):

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine \
  sh -c "vendor/bin/phpunit tests/Feature/ErpNextSubscriptionPaidWebhookTest.php"
```

Expected: FAIL — `Class "App\Http\Controllers\ErpNextSubscriptionPaidWebhookController" not found` (or route not defined).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/ErpNextSubscriptionPaidWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Reçoit la confirmation d'un paiement d'abonnement traité côté ERPNext
 * (module "Abonnement" / doctype Subscription Payment, paiement CinetPay
 * déjà re-vérifié par ERPNext via l'API de statut avant cet appel). Même
 * sens et même jeton partagé que register-pme/financing-dossier : ERPNext
 * appelle PME360, jamais l'inverse.
 */
class ErpNextSubscriptionPaidWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expectedToken = trim((string) config('services.erpnext.webhook_token', ''));
        $providedToken = (string) $request->header('X-PME360-Webhook-Token', '');

        if ($expectedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            abort(403);
        }

        $validated = $request->validate([
            'company' => ['required', 'string', 'max:255'],
            'duration_months' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
        ]);

        $pme = User::where('erpnext_company_name', $validated['company'])->first();
        if (! $pme) {
            return response()->json(['status' => 'ignored', 'reason' => 'PME introuvable'], 200);
        }

        $paidAt = Carbon::parse($validated['paid_at']);
        $currentExpiry = $pme->premium_ends_at;
        $baseDate = ($currentExpiry !== null && $currentExpiry->isFuture()) ? $currentExpiry : $paidAt;

        $pme->is_premium = true;
        $pme->premium_status = 'active';
        $pme->premium_ends_at = $baseDate->copy()->addMonths($validated['duration_months']);
        $pme->save();

        return response()->json(['status' => 'ok'], 200);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, right after the `webhooks.erpnext.financing-dossier` route (line 98):

```php
Route::post('/webhooks/erpnext/subscription-paid', [\App\Http\Controllers\ErpNextSubscriptionPaidWebhookController::class, 'handle'])
    ->middleware('throttle:60,1')
    ->name('webhooks.erpnext.subscription-paid');
```

- [ ] **Step 5: Exempt the route from CSRF**

In `bootstrap/app.php`, add to the `validateCsrfTokens(except: [...])` array (line 54-60):

```php
        $middleware->validateCsrfTokens(except: [
            'webhooks/erpnext/stock-movement',
            'webhooks/erpnext/invoicing',
            'webhooks/erpnext/accounting-entry',
            'webhooks/erpnext/sport-event',
            'webhooks/erpnext/register-pme',
            'webhooks/erpnext/subscription-paid',
        ]);
```

- [ ] **Step 6: Run test to verify it passes**

```bash
docker run --rm -v "C:\Users\yaniss\Desktop\application:/app" -w /app php:8.4-cli-alpine \
  sh -c "vendor/bin/phpunit tests/Feature/ErpNextSubscriptionPaidWebhookTest.php"
```

Expected: PASS (5 tests, 0 failures).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ErpNextSubscriptionPaidWebhookController.php routes/web.php bootstrap/app.php tests/Feature/ErpNextSubscriptionPaidWebhookTest.php
git commit -m "feat(erpnext): add webhook to activate premium on confirmed CinetPay subscription payment"
```

---

### Task 2: ERPNext — `Subscription Payment` doctype

**Files:**
- Create: `sitiame_core/sitiame_core/doctype/subscription_payment/__init__.py`
- Create: `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json`
- Create: `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.py`

Work in a local clone of `https://github.com/YANIXX35/sitiame_core` (any working directory — a scratchpad clone is fine). All paths below are relative to the repo root.

**Interfaces:**
- Produces: doctype `Subscription Payment` with fields `company` (Link → Company), `amount` (Currency, default 15000), `duration_months` (Int, default 1), `status` (Select: `En attente`/`Payé`/`Échoué`, default `En attente`), `transaction_id` (Data, read-only), `notify_token` (Data, read-only, hidden), `payment_url` (Data, read-only), `paid_at` (Datetime, read-only), `pme360_notified_at` (Datetime, read-only). Naming series `SUB-.YYYY.-`. Used by Task 3/4's Python code and Task 4's JS.

- [ ] **Step 1: Create the doctype directory**

```bash
mkdir -p sitiame_core/sitiame_core/doctype/subscription_payment
```

- [ ] **Step 2: Create `__init__.py`**

```bash
touch sitiame_core/sitiame_core/doctype/subscription_payment/__init__.py
```

- [ ] **Step 3: Write the doctype JSON**

Create `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.json`:

```json
{
 "actions": [],
 "creation": "2026-09-22 00:00:00.000000",
 "doctype": "DocType",
 "engine": "InnoDB",
 "field_order": [
  "naming_series",
  "company",
  "amount",
  "duration_months",
  "col_break_1",
  "status",
  "payment_section",
  "transaction_id",
  "notify_token",
  "payment_url",
  "col_break_2",
  "paid_at",
  "pme360_notified_at"
 ],
 "fields": [
  {
   "fieldname": "naming_series",
   "fieldtype": "Select",
   "label": "Serie",
   "default": "SUB-.YYYY.-",
   "options": "SUB-.YYYY.-",
   "hidden": 1
  },
  {
   "fieldname": "company",
   "fieldtype": "Link",
   "label": "Societe",
   "options": "Company",
   "reqd": 1,
   "in_list_view": 1,
   "read_only_depends_on": "eval:doc.transaction_id"
  },
  {
   "fieldname": "amount",
   "fieldtype": "Currency",
   "label": "Montant",
   "default": "15000",
   "reqd": 1,
   "in_list_view": 1,
   "read_only_depends_on": "eval:doc.transaction_id"
  },
  {
   "fieldname": "duration_months",
   "fieldtype": "Int",
   "label": "Duree (mois)",
   "default": "1",
   "reqd": 1,
   "read_only_depends_on": "eval:doc.transaction_id"
  },
  {
   "fieldname": "col_break_1",
   "fieldtype": "Column Break",
   "label": ""
  },
  {
   "fieldname": "status",
   "fieldtype": "Select",
   "label": "Statut",
   "default": "En attente",
   "options": "En attente\nPay\u00e9\n\u00c9chou\u00e9",
   "in_list_view": 1,
   "read_only": 1
  },
  {
   "fieldname": "payment_section",
   "fieldtype": "Section Break",
   "label": "Paiement CinetPay"
  },
  {
   "fieldname": "transaction_id",
   "fieldtype": "Data",
   "label": "ID de transaction CinetPay",
   "read_only": 1
  },
  {
   "fieldname": "notify_token",
   "fieldtype": "Data",
   "label": "Jeton de notification",
   "read_only": 1,
   "hidden": 1
  },
  {
   "fieldname": "payment_url",
   "fieldtype": "Data",
   "label": "Lien de paiement",
   "read_only": 1
  },
  {
   "fieldname": "col_break_2",
   "fieldtype": "Column Break",
   "label": ""
  },
  {
   "fieldname": "paid_at",
   "fieldtype": "Datetime",
   "label": "Paye le",
   "read_only": 1
  },
  {
   "fieldname": "pme360_notified_at",
   "fieldtype": "Datetime",
   "label": "PME360 notifie le",
   "read_only": 1
  }
 ],
 "index_web_pages_for_search": 0,
 "links": [],
 "modified": "2026-09-22 00:00:00.000000",
 "modified_by": "Administrator",
 "module": "Sitiame Core",
 "name": "Subscription Payment",
 "naming_rule": "By \"Naming Series\" field",
 "autoname": "naming_series:",
 "owner": "Administrator",
 "permissions": [
  {
   "create": 1,
   "delete": 1,
   "email": 0,
   "print": 1,
   "read": 1,
   "report": 1,
   "role": "System Manager",
   "share": 0,
   "write": 1
  }
 ],
 "sort_field": "modified",
 "sort_order": "DESC",
 "states": [],
 "track_changes": 1
}
```

Note: `status` is `read_only": 1` at the field level because it must only ever change through the webhook/server logic (Task 4), never by hand — prevents an admin from marking a payment "Payé" without it actually having happened.

- [ ] **Step 4: Write the controller with the field-lock guard**

Create `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.py`:

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

import frappe
from frappe import _
from frappe.model.document import Document


class SubscriptionPayment(Document):
	def validate(self):
		if self.is_new() or not self.transaction_id:
			return

		previous = frappe.db.get_value(
			"Subscription Payment", self.name, ["company", "amount", "duration_months"], as_dict=True
		)
		if not previous:
			return

		for field in ("company", "amount", "duration_months"):
			if self.get(field) != previous.get(field):
				frappe.throw(
					_("Impossible de modifier {0} : un lien de paiement a deja ete genere pour ce document.").format(
						field
					)
				)
```

- [ ] **Step 5: Commit**

```bash
git add sitiame_core/sitiame_core/doctype/subscription_payment/
git commit -m "feat(subscription): add Subscription Payment doctype for CinetPay premium payments"
```

---

### Task 3: ERPNext — CinetPay HTTP client module

**Files:**
- Create: `sitiame_core/sitiame_core/cinetpay_client.py`

**Interfaces:**
- Consumes: `frappe.conf.get("cinetpay_api_key")`, `frappe.conf.get("cinetpay_api_password")` (site_config.json keys, to be set on the VPS in Task 6).
- Produces: `get_access_token() -> str`, `init_payment(merchant_transaction_id, amount, designation, notify_url, success_url, failed_url) -> dict`, `get_payment_status(merchant_transaction_id) -> dict`. Consumed by Task 4's `subscription_api.py`.

- [ ] **Step 1: Write the client module**

Create `sitiame_core/sitiame_core/cinetpay_client.py`:

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

import requests

import frappe
from frappe import _

CINETPAY_BASE_URL = "https://api.cinetpay.net"
_TOKEN_CACHE_KEY = "cinetpay_oauth_token"


def _get_credentials():
	api_key = frappe.conf.get("cinetpay_api_key")
	api_password = frappe.conf.get("cinetpay_api_password")
	if not api_key or not api_password:
		frappe.throw(_("cinetpay_api_key / cinetpay_api_password ne sont pas configures dans site_config.json."))
	return api_key, api_password


def get_access_token():
	cached = frappe.cache().get_value(_TOKEN_CACHE_KEY)
	if cached:
		return cached

	api_key, api_password = _get_credentials()
	try:
		response = requests.post(
			f"{CINETPAY_BASE_URL}/v1/oauth/login",
			json={"api_key": api_key, "api_password": api_password},
			timeout=20,
		)
	except requests.RequestException as e:
		frappe.throw(_("CinetPay injoignable (authentification) : {0}").format(str(e)))

	if response.status_code >= 400:
		frappe.throw(_("Authentification CinetPay refusee : {0}").format(response.text))

	data = response.json()
	token = data.get("access_token")
	if not token:
		frappe.throw(_("Reponse d'authentification CinetPay invalide : {0}").format(data))

	expires_in = int(data.get("expires_in") or 3000)
	# safety margin so a cached token is never handed out right as it expires
	frappe.cache().set_value(_TOKEN_CACHE_KEY, token, expires_in_sec=max(expires_in - 60, 60))
	return token


def init_payment(merchant_transaction_id, amount, designation, notify_url, success_url, failed_url):
	token = get_access_token()
	payload = {
		"currency": "XOF",
		"merchant_transaction_id": merchant_transaction_id,
		"amount": amount,
		"lang": "fr",
		"designation": designation,
		"client_first_name": "Sitiame",
		"client_last_name": "Capital",
		"client_email": frappe.conf.get("cinetpay_default_client_email") or "contact@sitiame-capital.com",
		"success_url": success_url,
		"failed_url": failed_url,
		"notify_url": notify_url,
		"direct_pay": False,
	}
	try:
		response = requests.post(
			f"{CINETPAY_BASE_URL}/v1/payment",
			json=payload,
			headers={"Authorization": f"Bearer {token}"},
			timeout=30,
		)
	except requests.RequestException as e:
		frappe.throw(_("CinetPay injoignable (initialisation du paiement) : {0}").format(str(e)))

	data = response.json()
	if response.status_code >= 400 or data.get("status") != "OK":
		frappe.throw(_("CinetPay a refuse la demande de paiement : {0}").format(data.get("message") or data))

	return data


def get_payment_status(merchant_transaction_id):
	token = get_access_token()
	try:
		response = requests.get(
			f"{CINETPAY_BASE_URL}/v1/payment/{merchant_transaction_id}",
			headers={"Authorization": f"Bearer {token}"},
			timeout=20,
		)
	except requests.RequestException as e:
		frappe.throw(_("CinetPay injoignable (statut du paiement) : {0}").format(str(e)))

	return response.json()
```

- [ ] **Step 2: Verify it imports cleanly**

This module has no Frappe app context to unit-test standalone; verify via a syntax/import check instead:

```bash
python -c "import ast; ast.parse(open('sitiame_core/sitiame_core/cinetpay_client.py').read())"
```

Expected: no output (no syntax errors).

- [ ] **Step 3: Commit**

```bash
git add sitiame_core/sitiame_core/cinetpay_client.py
git commit -m "feat(subscription): add CinetPay HTTP client (oauth, init payment, status)"
```

---

### Task 4: ERPNext — whitelisted API endpoints + doctype buttons

**Files:**
- Create: `sitiame_core/sitiame_core/subscription_api.py`
- Create: `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.js`

**Interfaces:**
- Consumes: `sitiame_core.cinetpay_client.init_payment`/`get_payment_status` (Task 3), doctype `Subscription Payment` (Task 2), `frappe.conf.get("pme360_base_url")`/`frappe.conf.get("pme360_webhook_token")` (already configured, same pattern as `sitiame_core/api.py:1523-1526`).
- Produces: whitelisted methods `sitiame_core.subscription_api.generate_subscription_payment_link`, `sitiame_core.subscription_api.cinetpay_subscription_webhook` (allow_guest, this is CinetPay's `notify_url` target), `sitiame_core.subscription_api.resend_pme360_notification` — called from the JS in this task.

- [ ] **Step 1: Write the API module**

Create `sitiame_core/sitiame_core/subscription_api.py`:

```python
# Copyright (c) 2026, Sitiame Capital
# License: MIT

import hmac

import requests

import frappe
from frappe import _

from sitiame_core.cinetpay_client import get_payment_status, init_payment


@frappe.whitelist()
def generate_subscription_payment_link(docname):
	if "System Manager" not in frappe.get_roles():
		frappe.throw(_("Reserve aux administrateurs."), frappe.PermissionError)

	doc = frappe.get_doc("Subscription Payment", docname)
	if doc.transaction_id:
		frappe.throw(_("Un lien de paiement a deja ete genere pour ce document."))

	site_url = frappe.utils.get_url()
	notify_url = f"{site_url}/api/method/sitiame_core.subscription_api.cinetpay_subscription_webhook"
	return_url = f"{site_url}/app/subscription-payment/{doc.name}"

	result = init_payment(
		merchant_transaction_id=doc.name,
		amount=doc.amount,
		designation=f"Abonnement premium Sitiame - {doc.company}",
		notify_url=notify_url,
		success_url=return_url,
		failed_url=return_url,
	)

	doc.db_set("transaction_id", result.get("transaction_id"))
	doc.db_set("notify_token", result.get("notify_token"))
	doc.db_set("payment_url", result.get("payment_url"))
	frappe.db.commit()

	return {"payment_url": result.get("payment_url")}


def _notify_pme360(subscription_payment):
	base_url = (frappe.conf.get("pme360_base_url") or "https://sitiame-capital.com").rstrip("/")
	token = frappe.conf.get("pme360_webhook_token")
	if not token:
		frappe.log_error(
			title="Subscription Payment: pme360_webhook_token manquant",
			message=f"Impossible de notifier PME360 pour {subscription_payment.name}",
		)
		return False

	payload = {
		"company": subscription_payment.company,
		"duration_months": subscription_payment.duration_months,
		"paid_at": str(subscription_payment.paid_at),
	}
	try:
		response = requests.post(
			f"{base_url}/webhooks/erpnext/subscription-paid",
			json=payload,
			headers={"X-PME360-Webhook-Token": token},
			timeout=5,
		)
	except requests.RequestException as e:
		frappe.log_error(
			title="Subscription Payment: PME360 injoignable",
			message=f"{subscription_payment.name}: {e}",
		)
		return False

	if response.status_code >= 400:
		frappe.log_error(
			title="Subscription Payment: PME360 a refuse la notification",
			message=f"{subscription_payment.name}: {response.status_code} {response.text}",
		)
		return False

	frappe.db.set_value("Subscription Payment", subscription_payment.name, "pme360_notified_at", frappe.utils.now())
	frappe.db.commit()
	return True


@frappe.whitelist(allow_guest=True)
def cinetpay_subscription_webhook():
	data = frappe.local.form_dict
	merchant_transaction_id = data.get("merchant_transaction_id")
	notify_token = data.get("notify_token")

	if not merchant_transaction_id or not frappe.db.exists("Subscription Payment", merchant_transaction_id):
		frappe.response["http_status_code"] = 200
		return {"status": "ignored"}

	doc = frappe.get_doc("Subscription Payment", merchant_transaction_id)

	if not doc.notify_token or not notify_token or not hmac.compare_digest(str(doc.notify_token), str(notify_token)):
		frappe.response["http_status_code"] = 403
		frappe.log_error(
			title="Subscription Payment: notify_token invalide",
			message=f"{doc.name}: jeton recu invalide",
		)
		return {"status": "forbidden"}

	if doc.status == "Payé":
		frappe.response["http_status_code"] = 200
		return {"status": "already processed"}

	status_data = get_payment_status(merchant_transaction_id)
	real_status = status_data.get("status")

	if real_status == "SUCCESS":
		doc.db_set("status", "Payé")
		doc.db_set("paid_at", frappe.utils.now())
		frappe.db.commit()
		doc.reload()
		_notify_pme360(doc)
	elif real_status == "FAILED":
		doc.db_set("status", "Échoué")
		frappe.db.commit()

	frappe.response["http_status_code"] = 200
	return {"status": "ok"}


@frappe.whitelist()
def resend_pme360_notification(docname):
	if "System Manager" not in frappe.get_roles():
		frappe.throw(_("Reserve aux administrateurs."), frappe.PermissionError)

	doc = frappe.get_doc("Subscription Payment", docname)
	if doc.status != "Payé":
		frappe.throw(_("Ce document n'est pas marque comme paye."))

	sent = _notify_pme360(doc)
	if not sent:
		frappe.throw(_("PME360 injoignable, voir le journal des erreurs (Error Log)."))

	return {"status": "ok"}
```

- [ ] **Step 2: Write the doctype client script**

Create `sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.js`:

```js
// Copyright (c) 2026, Sitiame Capital
// License: MIT

frappe.ui.form.on("Subscription Payment", {
	refresh: function (frm) {
		if (frm.is_new()) return;

		if (!frm.doc.transaction_id) {
			frm.add_custom_button(__("Générer le lien de paiement"), function () {
				generate_payment_link(frm);
			});
		}

		if (frm.doc.payment_url) {
			frm.add_custom_button(__("Ouvrir le lien de paiement"), function () {
				window.open(frm.doc.payment_url, "_blank");
			});
		}

		if (frm.doc.status === "Payé" && !frm.doc.pme360_notified_at) {
			frm.add_custom_button(__("Renvoyer à PME360"), function () {
				resend_to_pme360(frm);
			});
		}
	},
});

function generate_payment_link(frm) {
	frappe.call({
		method: "sitiame_core.subscription_api.generate_subscription_payment_link",
		args: { docname: frm.doc.name },
		freeze: true,
		freeze_message: __("Génération du lien CinetPay..."),
	}).then(function () {
		frappe.show_alert({ message: __("Lien de paiement généré."), indicator: "green" });
		frm.reload_doc();
	});
}

function resend_to_pme360(frm) {
	frappe.call({
		method: "sitiame_core.subscription_api.resend_pme360_notification",
		args: { docname: frm.doc.name },
		freeze: true,
		freeze_message: __("Renvoi vers PME360..."),
	}).then(function () {
		frappe.show_alert({ message: __("PME360 notifié."), indicator: "green" });
		frm.reload_doc();
	});
}
```

- [ ] **Step 3: Verify both files parse cleanly**

```bash
python -c "import ast; ast.parse(open('sitiame_core/sitiame_core/subscription_api.py').read())"
node --check sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.js
```

Expected: no output from either command.

- [ ] **Step 4: Commit**

```bash
git add sitiame_core/sitiame_core/subscription_api.py sitiame_core/sitiame_core/doctype/subscription_payment/subscription_payment.js
git commit -m "feat(subscription): add payment-link generation, webhook receiver, and PME360 notification"
```

---

### Task 5: ERPNext — deploy to VPS, configure CinetPay credentials, create the doctype in the DB

This task takes the code from Tasks 1-4 (already committed locally) live, and configures the two site-level secrets the code depends on (`cinetpay_api_key`/`cinetpay_api_password`) that cannot live in git.

**Files:** none (deployment only).

- [ ] **Step 1: Push the sitiame_core commits**

```bash
git push origin master
```

- [ ] **Step 2: Add CinetPay credentials to site_config.json on the VPS**

The sandbox API key is `[REDACTE - voir panel.cinetpay.net]` (already in hand); the API password was set by the user directly in the CinetPay panel — ask them for its current value before running this step, do not guess it.

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com set-config cinetpay_api_key "[REDACTE - voir panel.cinetpay.net]"
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com set-config cinetpay_api_password "<the password the user provides>"
'
```

- [ ] **Step 3: Pull the new code into both containers**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
set -e
docker exec sitiame-prod-backend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
docker exec sitiame-prod-frontend-1 bash -c "cd /home/frappe/frappe-bench/apps/sitiame_core && git pull origin master"
'
```

- [ ] **Step 4: Migrate (new doctype), build, clear cache**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 '
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com migrate
docker exec sitiame-prod-backend-1 bench build --app sitiame_core
docker exec sitiame-prod-backend-1 bench --site erp.sitiame-capital.com clear-cache
'
```

- [ ] **Step 5: Restart both containers**

```bash
ssh -i ~/.ssh/id_ed25519_sitiame_vps root@31.207.36.253 'cd /root && docker compose -f sitiame-prod-compose.yml restart backend frontend'
```

- [ ] **Step 6: Verify the doctype exists and the config is set**

Write a one-off verification script locally, following the established pattern (write → scp to `/tmp` → `docker cp` into `apps/sitiame_core/sitiame_core/tmp_verify.py` → `bench execute` → delete):

```python
import frappe


def run():
	return {
		"doctype_exists": frappe.db.exists("DocType", "Subscription Payment"),
		"cinetpay_api_key_set": bool(frappe.conf.get("cinetpay_api_key")),
		"cinetpay_api_password_set": bool(frappe.conf.get("cinetpay_api_password")),
		"pme360_webhook_token_set": bool(frappe.conf.get("pme360_webhook_token")),
	}
```

Expected: all four values truthy.

---

### Task 6: ERPNext — create the "Abonnement" Desktop Icon and Workspace Sidebar

Same DB-level, not-in-git pattern already used for the Scoring and Financement tiles (a `standard=1` icon without an exported fixture gets deleted by `bench migrate`'s orphan cleanup — this bit the Scoring tile once already, so `standard=0, owner="Administrator"` is mandatory here from the start).

**Files:** none (one-off DB script, run once against the live site).

- [ ] **Step 1: Write and run the creation script**

```python
import frappe


def run():
	if not frappe.db.exists("Workspace Sidebar", "Abonnement"):
		sidebar = frappe.get_doc(
			{
				"doctype": "Workspace Sidebar",
				"title": "Abonnement",
				"items": [
					{
						"label": "Paiements d'abonnement",
						"link_type": "DocType",
						"link_to": "Subscription Payment",
					}
				],
			}
		)
		sidebar.insert(ignore_permissions=True)

	if not frappe.db.exists("Desktop Icon", "Abonnement"):
		icon = frappe.get_doc(
			{
				"doctype": "Desktop Icon",
				"label": "Abonnement",
				"standard": 0,
				"owner": "Administrator",
				"icon_type": "Link",
				"link_type": "Workspace Sidebar",
				"link_to": "Abonnement",
				"bg_color": "green",
			}
		)
		icon.insert(ignore_permissions=True)

	frappe.db.commit()
	return {
		"sidebar_created": frappe.db.exists("Workspace Sidebar", "Abonnement"),
		"icon_created": frappe.db.exists("Desktop Icon", "Abonnement"),
	}
```

Run it through the established one-off pattern: write locally, `scp` to the VPS `/tmp/`, `docker cp` into `sitiame-prod-backend-1:/home/frappe/frappe-bench/apps/sitiame_core/sitiame_core/tmp_create_abonnement_icon.py`, `bench --site erp.sitiame-capital.com execute sitiame_core.tmp_create_abonnement_icon.run`, then `rm -f` the temp file in the container.

- [ ] **Step 2: Clear the desktop_icons/bootinfo redis cache**

The Desk caches the tile list per user (this bit the green-tiles work earlier in the same session — a stale cache silently keeps showing the old tile set):

```python
import frappe


def run():
	frappe.cache().delete_keys("*desktop_icons*")
	frappe.cache().delete_keys("*bootinfo*")
	frappe.clear_cache()
```

- [ ] **Step 3: Manual verification**

Ask the user to hard-refresh `/desk` and confirm the green "Abonnement" tile appears alongside Scoring/Financement, and that clicking it opens a sidebar with "Paiements d'abonnement" leading to the `Subscription Payment` list.

---

### Task 7: End-to-end manual verification (sandbox)

No automated test can safely exercise a real CinetPay payment. Verify manually with the sandbox key already configured in Task 5:

- [ ] Create a new `Subscription Payment` for a real test Company, confirm `amount` defaults to `15000` and `duration_months` to `1`.
- [ ] Click "Générer le lien de paiement" — confirm `payment_url`/`transaction_id`/`notify_token` get filled, and that `company`/`amount`/`duration_months` become read-only (try editing one, confirm the UI blocks it).
- [ ] Open the `payment_url`, use one of CinetPay's documented sandbox test numbers (see "Numéros de Test" in their doc) to simulate a successful payment.
- [ ] Confirm the `Subscription Payment` document flips to "Payé" with `paid_at` set (webhook + GET-status re-verification worked).
- [ ] On PME360, confirm the linked `User.is_premium` is `true` and `premium_ends_at` is set to roughly one month out.
- [ ] Repeat with a sandbox failure test number, confirm the document flips to "Échoué" instead, and that PME360 is *not* notified.
- [ ] Temporarily rename `notify_token` in the DB for a pending document, trigger a fake webhook call with the old token, confirm it's rejected with 403 and the document is untouched (defends the "never trust the payload" rule end-to-end, not just in code).
