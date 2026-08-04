# Commercial Mobile Menu Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the mobile off-canvas menu for the Commercial space (avatar header, grouped icon list with a "AUTRES SERVICES" divider, red logout) and add a new fixed bottom tab bar (Tableau de bord / Portefeuille / Prospects / Menu), without touching desktop layout, other roles, or the site's color palette.

**Architecture:** Everything lives in the existing Blade layout stack. The Commercial block inside the shared `layouts/partials/sidebar.blade.php` is restructured and gains two Bootstrap-utility-hidden-on-desktop (`d-lg-none`) elements (avatar header, logout item). A new partial `layouts/partials/bottom-nav-commercial.blade.php` renders the bottom bar and is included from `layouts/app.blade.php`, gated by a `$isCommercialUser` flag computed in that same file (variables set inside `@include`d templates don't leak back to the parent, so the flag can't be reused from the sidebar partial). New CSS for the bottom bar (and the content padding it requires) is added to the existing `public/css/mobile-responsive.css`, which is a plain static file — no build step.

**Tech Stack:** Laravel 13 / Blade, Bootstrap 5 utility classes, Feather Icons (`data-feather`), PHPUnit Feature tests (`RefreshDatabase`, `actingAs`).

## Global Constraints

- Keep the site's current color palette — no new brand colors, only Bootstrap utilities (`text-danger`, `text-primary`, `var(--bs-primary)`) and existing CSS conventions.
- Only reorganize features that already exist under the `commercial.*` route names — no new pages.
- Desktop (`≥ 992px`) rendering must be visually unchanged: same sidebar content, no bottom bar.
- Reuse the existing sidebar open/close mechanism (`.js-sidebar-toggle`, `#sidebar`, `#sidebarBackdrop`) — do not introduce a second toggle system.
- Every new file/markup change must be covered by a Feature test using `assertSee`/`assertDontSee` against real routes with `actingAs`, following the existing pattern in `tests/Feature/CommercialPortalTest.php`.

---

### Task 1: Restructure the Commercial sidebar block (header, grouped items, divider, logout)

**Files:**
- Modify: `resources/views/layouts/partials/sidebar.blade.php:62-104`
- Test: `tests/Feature/CommercialMobileMenuTest.php` (create)

**Interfaces:**
- Consumes: `$sidebarUser` (already defined at `sidebar.blade.php:9`, an `App\Models\User|null`), `$sidebarIsCommercial` (already defined at `sidebar.blade.php:19`, bool), Laravel's `Auth::user()` and `route()` helpers, `request()->routeIs()`.
- Produces: no new PHP symbols. Produces stable markup strings other tasks/tests rely on: the literal text `AUTRES SERVICES`, the CSS class `sidebar-logout-item` on the logout `<li>`, and the CSS class `commercial-mobile-header` on the new avatar header `<li>`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/CommercialMobileMenuTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommercialMobileMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_commercial_sidebar_shows_mobile_header_grouped_items_and_red_logout(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial', 'name' => 'Awa Traoré']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);

        // Mobile-only avatar header
        $response->assertSee('class="commercial-mobile-header d-lg-none"', false);
        $response->assertSee('Awa Traoré');

        // Section divider grouping secondary items
        $response->assertSee('AUTRES SERVICES');

        // Primary items appear before the divider, secondary items after
        $html = $response->getContent();
        $dividerPos = strpos($html, 'AUTRES SERVICES');
        $this->assertNotFalse($dividerPos);
        $this->assertLessThan($dividerPos, strpos($html, 'Pipeline Leads CRM'));
        $this->assertGreaterThan($dividerPos, strpos($html, 'Offres Marketing & Service'));
        $this->assertGreaterThan($dividerPos, strpos($html, 'Inscrire Client / PME'));

        // Red logout item, mobile-only, distinct from the desktop navbar logout
        $response->assertSee('sidebar-logout-item', false);
        $response->assertSee('class="sidebar-link text-danger"', false);
    }

    public function test_non_commercial_dashboard_has_no_commercial_mobile_header(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('commercial-mobile-header', false);
        $response->assertDontSee('sidebar-logout-item', false);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: FAIL — `commercial-mobile-header`, `AUTRES SERVICES`, and `sidebar-logout-item` are not found in the response body.

- [ ] **Step 3: Replace the Commercial block in the sidebar partial**

In `resources/views/layouts/partials/sidebar.blade.php`, replace lines 62-104 (from `@if($sidebarIsCommercial)` down to the blank lines just before `@else`) with:

```blade
            @if($sidebarIsCommercial)
                <li class="commercial-mobile-header d-lg-none">
                    <img src="{{ ($sidebarUser->avatar) ? asset('storage/' . $sidebarUser->avatar) : asset('images/sitiam.png') }}" class="rounded-circle" width="44" height="44" alt="{{ $sidebarUser->name }}">
                    <div>
                        <div class="commercial-mobile-header-name">{{ $sidebarUser->name }}</div>
                        <span class="badge bg-primary text-white">💼 Commercial</span>
                    </div>
                </li>

                <li class="sidebar-header">Espace Commercial</li>
                <li class="sidebar-item {{ request()->routeIs('commercial.dashboard') && !request()->has('action') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.dashboard') }}">
                        <i class="align-middle" data-feather="layout"></i> <span class="align-middle">Tableau de bord</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.portefeuille') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.portefeuille') }}">
                        <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Mon Portefeuille</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.prospects') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.prospects') }}">
                        <i class="align-middle" data-feather="target"></i> <span class="align-middle">Pipeline Leads CRM</span>
                    </a>
                </li>

                <li class="sidebar-header">AUTRES SERVICES</li>
                <li class="sidebar-item {{ request()->routeIs('commercial.showcase') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.showcase') }}">
                        <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Offres Marketing & Service</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.guides') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.guides') }}">
                        <i class="align-middle" data-feather="book-open"></i> <span class="align-middle">Guides & Lead Magnets</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.club') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.club') }}">
                        <i class="align-middle" data-feather="users"></i> <span class="align-middle">Sitiame Finance Club</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.import') ? 'active' : '' }}">
                    <a class="sidebar-link text-success fw-bold" href="{{ route('commercial.import') }}">
                        <i class="align-middle text-success" data-feather="file-text"></i> <span class="align-middle">Importer & Analyser Fichier</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->get('action') === 'add-client' ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}">
                        <i class="align-middle" data-feather="user-plus"></i> <span class="align-middle">Inscrire Client / PME</span>
                    </a>
                </li>

                <li class="sidebar-item sidebar-logout-item d-lg-none">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="sidebar-link text-danger">
                            <i class="align-middle" data-feather="log-out"></i> <span class="align-middle">Déconnexion</span>
                        </button>
                    </form>
                </li>

            @else
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: PASS (both tests).

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/partials/sidebar.blade.php tests/Feature/CommercialMobileMenuTest.php
git commit -m "feat(commercial): restyle mobile sidebar with avatar header, grouped items, red logout"
```

---

### Task 2: Style the mobile avatar header and logout button

**Files:**
- Modify: `public/css/mobile-responsive.css`

**Interfaces:**
- Consumes: CSS classes produced by Task 1 (`commercial-mobile-header`, `commercial-mobile-header-name`, `sidebar-logout-item`).
- Produces: visual styling only — no new class names other tasks depend on.

There is no automated test for pure CSS visual styling in this codebase (confirmed: no existing CSS snapshot/visual-regression tooling in `tests/`). This task is verified manually in Task 5's manual verification step. Skipping a test step here is intentional and matches the codebase's existing practice for CSS-only changes (e.g. commits `c1b879b`, `d5ce403`).

- [ ] **Step 1: Add the header/logout styles**

In `public/css/mobile-responsive.css`, immediately after the existing block `12. SIDEBAR STYLES — Hiérarchie & Design` (after the `.sidebar-close-btn:hover { ... }` rule, i.e. right after line 512, before the `13. PAGES SPÉCIFIQUES` comment), insert:

```css
/* Avatar header — Commercial mobile menu */
.commercial-mobile-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    margin-bottom: 0.5rem;
}

.commercial-mobile-header img {
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.25);
}

.commercial-mobile-header-name {
    color: #fff;
    font-weight: 600;
    font-size: 0.95rem;
    margin-bottom: 0.15rem;
}

/* Logout item — mobile Commercial menu */
.sidebar-logout-item {
    margin-top: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.sidebar-logout-item form {
    margin: 0;
}

.sidebar-logout-item .sidebar-link {
    width: 100%;
    background: none;
    border: none;
    text-align: left;
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/mobile-responsive.css
git commit -m "style(commercial): add avatar header and logout styling for mobile sidebar"
```

---

### Task 3: Compute the shared Commercial flag in the main layout

**Files:**
- Modify: `resources/views/layouts/app.blade.php:379-383`
- Test: `tests/Feature/CommercialMobileMenuTest.php` (extend)

**Interfaces:**
- Consumes: `Auth::user()` (already used at `app.blade.php:509`).
- Produces: `$isCommercialUser` (bool), available in `app.blade.php` from the point it's declared onward, and the `has-bottom-nav` class on `<body>`. Task 4 consumes both.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CommercialMobileMenuTest.php`:

```php
    public function test_commercial_dashboard_body_has_bottom_nav_class(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);
        $response->assertSee('<body class="has-bottom-nav">', false);
    }

    public function test_non_commercial_dashboard_body_has_no_bottom_nav_class(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('<body>', false);
        $response->assertDontSee('has-bottom-nav', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: FAIL — `<body>` currently has no class attribute at all (checked: `app.blade.php:379` is a bare `<body>` tag), so both the "has" and "has-no" assertions on the exact expected markup fail against the current unconditional `<body>`.

- [ ] **Step 3: Update the body tag**

In `resources/views/layouts/app.blade.php`, locate line 379 (`<body>`) and the block right before it. Replace:

```blade
<body>
```

with:

```blade
@php
    $isCommercialUser = Auth::check() && Auth::user()->role_key === 'commercial';
@endphp
<body class="{{ $isCommercialUser ? 'has-bottom-nav' : '' }}">
```

Note: Blade renders `class=""` (empty string) for non-commercial users, not a bare `<body>` — update the second test's expectation accordingly before running it (see Step 3b).

- [ ] **Step 3b: Fix the non-commercial test expectation**

The empty-class case renders `<body class="">`, not `<body>`. Update the second test in `tests/Feature/CommercialMobileMenuTest.php` from Step 1:

```php
    public function test_non_commercial_dashboard_body_has_no_bottom_nav_class(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('<body class="">', false);
        $response->assertDontSee('has-bottom-nav', false);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: PASS (all four tests so far).

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/app.blade.php tests/Feature/CommercialMobileMenuTest.php
git commit -m "feat(commercial): flag commercial users on body tag for bottom-nav layout"
```

---

### Task 4: Build and include the bottom tab bar partial

**Files:**
- Create: `resources/views/layouts/partials/bottom-nav-commercial.blade.php`
- Modify: `resources/views/layouts/app.blade.php` (include point, right after `</footer>` inside `.main`, i.e. after the current line 702)
- Test: `tests/Feature/CommercialMobileMenuTest.php` (extend)

**Interfaces:**
- Consumes: `$isCommercialUser` (bool, from Task 3), `request()->routeIs()`, `route()`.
- Produces: markup with `id="commercialBottomNav"` and class `bottom-nav`, four `.bottom-nav-item` links/buttons (`Tableau de bord`, `Portefeuille`, `Prospects`, `Menu`), the `Menu` one carrying class `js-sidebar-toggle` (reusing the existing toggle JS at `app.blade.php:816`, which does `document.querySelectorAll('.js-sidebar-toggle')` at DOM-ready time, so any element with this class present in the initial HTML is wired up automatically — no new JS needed).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/CommercialMobileMenuTest.php`:

```php
    public function test_commercial_dashboard_shows_bottom_nav_with_four_items(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="commercialBottomNav"', false);
        $response->assertSee('class="bottom-nav-item js-sidebar-toggle"', false);

        $html = $response->getContent();
        $navStart = strpos($html, 'id="commercialBottomNav"');
        $this->assertNotFalse($navStart);
        $navHtml = substr($html, $navStart, 1200);
        $this->assertStringContainsString('Tableau de bord', $navHtml);
        $this->assertStringContainsString('Portefeuille', $navHtml);
        $this->assertStringContainsString('Prospects', $navHtml);
        $this->assertStringContainsString('Menu', $navHtml);
    }

    public function test_commercial_portefeuille_bottom_nav_marks_portefeuille_active(): void
    {
        $commercial = User::factory()->create(['role_key' => 'commercial']);

        $response = $this->actingAs($commercial)->get('/commercial/portefeuille');

        $response->assertStatus(200);
        $html = $response->getContent();
        $navStart = strpos($html, 'id="commercialBottomNav"');
        $navHtml = substr($html, $navStart, 1200);
        $this->assertStringContainsString('bottom-nav-item active', $navHtml);
    }

    public function test_non_commercial_dashboard_has_no_bottom_nav(): void
    {
        $manager = User::factory()->create(['role_key' => 'manager']);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee('commercialBottomNav', false);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: FAIL — `commercialBottomNav` does not exist anywhere yet.

- [ ] **Step 3: Create the bottom nav partial**

Create `resources/views/layouts/partials/bottom-nav-commercial.blade.php`:

```blade
<nav id="commercialBottomNav" class="bottom-nav d-lg-none">
    <a href="{{ route('commercial.dashboard') }}" class="bottom-nav-item {{ request()->routeIs('commercial.dashboard') && !request()->has('action') ? 'active' : '' }}">
        <i data-feather="layout"></i>
        <span>Tableau de bord</span>
    </a>
    <a href="{{ route('commercial.portefeuille') }}" class="bottom-nav-item {{ request()->routeIs('commercial.portefeuille') ? 'active' : '' }}">
        <i data-feather="briefcase"></i>
        <span>Portefeuille</span>
    </a>
    <a href="{{ route('commercial.prospects') }}" class="bottom-nav-item {{ request()->routeIs('commercial.prospects') ? 'active' : '' }}">
        <i data-feather="target"></i>
        <span>Prospects</span>
    </a>
    <a href="#" class="bottom-nav-item js-sidebar-toggle">
        <i data-feather="menu"></i>
        <span>Menu</span>
    </a>
</nav>
```

- [ ] **Step 4: Include the partial in the main layout**

In `resources/views/layouts/app.blade.php`, right after the closing `</footer>` tag (currently line 702, just before the `</div>` that closes `.main`), add:

```blade
            @if($isCommercialUser)
                @include('layouts.partials.bottom-nav-commercial')
            @endif
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: PASS (all seven tests).

- [ ] **Step 6: Commit**

```bash
git add resources/views/layouts/partials/bottom-nav-commercial.blade.php resources/views/layouts/app.blade.php tests/Feature/CommercialMobileMenuTest.php
git commit -m "feat(commercial): add fixed bottom tab bar for mobile"
```

---

### Task 5: Style the bottom tab bar and reserve space for it in page content

**Files:**
- Modify: `public/css/mobile-responsive.css`

**Interfaces:**
- Consumes: `bottom-nav`, `bottom-nav-item`, `active` classes from Task 4; `has-bottom-nav` body class from Task 3; `.content` element from `app.blade.php:553`.
- Produces: visual styling only.

No automated test here for the same reason as Task 2 (pure CSS, no visual-regression tooling in this repo). Verified manually below.

- [ ] **Step 1: Add the bottom nav styles**

At the end of `public/css/mobile-responsive.css`, after the last existing rule block, append:

```css
/* ---------------------------------------------------------------
   15. BOTTOM NAV — Commercial (mobile)
--------------------------------------------------------------- */
@media (max-width: 991.98px) {
    .bottom-nav {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1030;
        display: flex;
        justify-content: space-around;
        align-items: stretch;
        background: #fff;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        box-shadow: 0 -0.25rem 1rem rgba(0, 0, 0, 0.08);
        padding-bottom: env(safe-area-inset-bottom, 0);
    }

    .bottom-nav-item {
        flex: 1 1 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.15rem;
        padding: 0.5rem 0.25rem;
        font-size: 0.68rem;
        color: #64748B;
        text-decoration: none;
        background: none;
        border: none;
    }

    .bottom-nav-item svg {
        width: 20px;
        height: 20px;
    }

    .bottom-nav-item.active {
        color: var(--bs-primary, #3B7DDD);
        font-weight: 600;
    }

    body.has-bottom-nav .content {
        padding-bottom: 4.5rem !important;
    }
}

@media (min-width: 992px) {
    .bottom-nav {
        display: none !important;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add public/css/mobile-responsive.css
git commit -m "style(commercial): add bottom tab bar layout and content spacing"
```

---

### Task 6: Full-suite regression check and manual verification

**Files:** none (verification only)

**Interfaces:** none.

- [ ] **Step 1: Run the full Commercial test suites**

Run: `php artisan test --filter=CommercialPortalTest`
Expected: PASS (all pre-existing tests still pass — confirms the sidebar/layout restructuring didn't break existing assertions like `assertSee('Client A')`).

Run: `php artisan test --filter=CommercialMobileMenuTest`
Expected: PASS (all 7 new tests).

- [ ] **Step 2: Run the full test suite**

Run: `php artisan test`
Expected: PASS — no regressions in other roles' dashboards (Admin, Accountant, Investor, etc.), since all changes are gated behind `$sidebarIsCommercial` / `$isCommercialUser`.

- [ ] **Step 3: Manual responsive check**

Start the dev server (`php artisan serve` or `composer dev`), log in as a `commercial`-role user, open the 7 Commercial pages (`dashboard`, `portefeuille`, `showcase`, `guides`, `club`, `prospects`, `import`) in a browser at a mobile viewport width (e.g. DevTools device toolbar, 390×844):
- Confirm the hamburger opens the restyled off-canvas menu: avatar header, "Tableau de bord / Mon Portefeuille / Pipeline Leads CRM", "Autres services" divider, the four secondary items, red "Déconnexion" at the bottom.
- Confirm the bottom tab bar is visible with 4 icons, the current page's tab highlighted, and tapping "Menu" opens the same off-canvas as the hamburger.
- Confirm page content never hides behind the bottom bar (no overlap at the bottom of any of the 7 pages).
- Resize to desktop width (`≥ 992px`): confirm the bottom bar disappears and the sidebar looks exactly as before this change (no avatar header, no red logout row, same item order as pre-change... note: item order changed — Pipeline Leads CRM now appears third instead of sixth; confirm this reordering is acceptable on desktop too, since Task 1 reorders items for both breakpoints).
- Log in as a non-commercial user (e.g. `manager`, `is_platform_admin`): confirm no bottom bar, no avatar header, no red logout row appear anywhere.

- [ ] **Step 4: Report results to the user**

Summarize pass/fail for each check above before considering this plan complete.
