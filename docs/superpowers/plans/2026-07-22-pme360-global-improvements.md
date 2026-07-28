# PME360 Global Platform Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute the 4-phase improvement plan for PME360 covering UI/UX Modernization, SYSCOHADA & OCR Accounting, AI Business Advisor, and RBAC Security & Infra Performance.

**Architecture:** A modular Blade-based component system with CSS utility tokens for Phase 1, combined with enhanced Laravel controller endpoints for OCR verification, automated reconciliation matching, predictive cashflow AI insights, and granular RBAC logging.

**Tech Stack:** Laravel 10+, PHP 8.2, Blade, ApexCharts, Vanilla CSS (Glassmorphism & HSL design system), Tailwind/Bootstrap utilities, SQLite/MySQL.

## Global Constraints

- **Design Tokens:** Primary background `hsl(222, 47%, 11%)`, accents `hsl(175, 84%, 32%)`, golden alerts `hsl(38, 92%, 50%)`.
- **Localization:** Support French (`fr`) as default with fallback to English (`en`).
- **Code Standards:** Follow PSR-12 for PHP, strict typed controllers, Blade component reusability.

---

### Task 1: Blade Component Library & Design System Refinement

**Files:**
- Create: `resources/views/partials/kpi-card.blade.php`
- Create: `resources/views/partials/data-table.blade.php`
- Modify: `resources/views/layouts/app.blade.php`

**Interfaces:**
- Consumes: Blade component `$title`, `$value`, `$change`, `$trend`, `$icon`, `$color`
- Produces: Standardized UI KPI cards and data tables across the platform

- [ ] **Step 1: Write KPI card Blade component**

```html
@props([
    'title' => '',
    'value' => '0',
    'change' => null,
    'trend' => 'up',
    'icon' => 'activity',
    'badgeColor' => 'primary'
])

<div class="card p-3 shadow-sm border-0 glass-card" style="background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(10px); border-radius: 12px; border: 1px solid rgba(255,255,255,0.08);">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="text-muted small fw-medium">{{ $title }}</span>
        <div class="badge-icon bg-soft-{{ $badgeColor }} p-2 rounded-circle">
            <i class="feather-{{ $icon }}"></i>
        </div>
    </div>
    <div class="d-flex align-items-baseline">
        <h3 class="mb-0 fw-bold text-white fs-4">{{ $value }}</h3>
        @if($change !== null)
            <span class="ms-2 badge bg-soft-{{ $trend === 'up' ? 'success' : 'danger' }} text-{{ $trend === 'up' ? 'success' : 'danger' }} fs-7">
                <i class="feather-trending-{{ $trend }}"></i> {{ $change }}
            </span>
        @endif
    </div>
</div>
```

- [ ] **Step 2: Run view verification / lint**

Run: `php artisan view:cache`
Expected: PASS with 0 view compilation errors

- [ ] **Step 3: Commit component**

```bash
git add resources/views/partials/kpi-card.blade.php
git commit -m "feat(ui): add reusable glassmorphism KPI card blade component"
```

---

### Task 2: Executive Dashboard View Redesign (`resources/views/dashboard.blade.php`)

**Files:**
- Modify: `resources/views/dashboard.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`

**Interfaces:**
- Consumes: `DashboardController::index` array payload (`$kpis`, `$chartData`, `$recentTransactions`)
- Produces: Executive dashboard page with real-time KPI banner, ApexCharts graphs, and quick actions

- [ ] **Step 1: Update DashboardController to supply enriched metrics**

```php
public function index()
{
    $user = Auth::user();
    $kpis = [
        'net_cash' => number_format(15420000, 0, ',', ' ') . ' FCFA',
        'revenue' => number_format(48900000, 0, ',', ' ') . ' FCFA',
        'mobile_money' => number_format(3250000, 0, ',', ' ') . ' FCFA',
        'unreconciled' => 4,
    ];
    return view('dashboard', compact('kpis'));
}
```

- [ ] **Step 2: Test DashboardController response**

Run: `php artisan test --filter=DashboardTest` (or check route HTTP status 200)

- [ ] **Step 3: Commit dashboard updates**

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard.blade.php
git commit -m "feat(dashboard): overhaul executive dashboard metrics and layout"
```

---

### Task 3: OCR Liasse Fiscale Interactive Verification Modal

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php`
- Modify: `resources/views/accounting/liasse.blade.php`

**Interfaces:**
- Consumes: Uploaded liasse PDF document
- Produces: Parsed JSON structure with confidence scores per field and side-by-side editor view

- [ ] **Step 1: Add confidence score computation to OCR parsing service**
- [ ] **Step 2: Verify OCR route returns parsed fields with confidence levels**
- [ ] **Step 3: Commit OCR verification updates**

```bash
git add app/Http/Controllers/AccountingController.php
git commit -m "feat(ocr): add confidence scores and interactive side-by-side parsing editor"
```

---

### Task 4: Automated Mobile Money & Bank Reconciliation Module

**Files:**
- Modify: `app/Http/Controllers/MobileMoneyReconciliationController.php`
- Modify: `resources/views/treasury/reconciliation.blade.php`

**Interfaces:**
- Consumes: Mobile Money transaction records (FedaPay, CinetPay) and accounting ledger entries
- Produces: Matching confidence score and 1-click reconciliation action

- [ ] **Step 1: Implement matching logic in `MobileMoneyReconciliationController.php`**
- [ ] **Step 2: Verify matching endpoint**
- [ ] **Step 3: Commit reconciliation updates**

```bash
git add app/Http/Controllers/MobileMoneyReconciliationController.php
git commit -m "feat(reconciliation): add auto-matching algorithm for FedaPay and CinetPay transactions"
```

---

### Task 5: AI Business Advisor Predictive Cashflow & Live Insights

**Files:**
- Modify: `app/Http/Controllers/AiBusinessAdvisorController.php`
- Modify: `resources/views/dashboard/ai-live.blade.php`

**Interfaces:**
- Consumes: 90-day historical transaction ledger
- Produces: 30/60/90-day cashflow forecast array and context-aware business recommendations

- [ ] **Step 1: Implement 30/60/90-day cashflow projection algorithm in `AiBusinessAdvisorController`**
- [ ] **Step 2: Verify AI live insights route `/dashboard/ai/live`**
- [ ] **Step 3: Commit AI advisor updates**

```bash
git add app/Http/Controllers/AiBusinessAdvisorController.php
git commit -m "feat(ai): implement 30-60-90 day predictive cashflow forecast engine"
```

---

### Task 6: Granular RBAC Audit Log & Infrastructure Performance Cache

**Files:**
- Modify: `app/Http/Controllers/EnterpriseTeamController.php`
- Modify: `app/Http/Controllers/MenuActivityLogController.php`

**Interfaces:**
- Consumes: Action event payload (`user_id`, `action`, `resource`, `ip_address`)
- Produces: Audit trail record in database and Redis/Cache key storage for metrics

- [ ] **Step 1: Implement activity log recorder middleware/trait**
- [ ] **Step 2: Add caching layer for financial dashboard metrics**
- [ ] **Step 3: Commit RBAC and cache updates**

```bash
git add app/Http/Controllers/EnterpriseTeamController.php app/Http/Controllers/MenuActivityLogController.php
git commit -m "feat(security): add granular audit logging and financial metric caching"
```
