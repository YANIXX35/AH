# PME360 Global Platform Improvements Design Document

**Date:** 2026-07-22  
**Status:** Approved  
**Scope:** Global Platform Upgrade across 4 Key Pillars (UX/UI Dashboard, SYSCOHADA & OCR Accounting, AI Business Advisor, Security & Infra Performance)

---

## 1. Overview & Objectives

PME360 is a comprehensive financial and ERP management solution tailored for West African SMEs (PMEs). This document outlines the technical design for a full platform enhancement organized in four sequential phases to maximize impact, maintain stability, and guarantee a premium user experience.

---

## 2. Phase Breakdown & Architecture

### Phase 1: UX/UI Modernization & Executive Dashboard
* **Design System & Aesthetics:**
  * Modern Dark / Light theme toggle with Glassmorphism highlights.
  * Curated HSL color palette featuring deep navy background (`hsl(222, 47%, 11%)`), institutional teal accents (`hsl(175, 84%, 32%)`), and golden alerts (`hsl(38, 92%, 50%)`).
  * Typography updated using Google Fonts (*Inter* / *Outfit*).
* **Blade Partial Components (`resources/views/partials/`):**
  * `kpi-card.blade.php`: Reusable card component supporting trend badges (+X%), status icons, and sparklines.
  * `data-table.blade.php`: Standardized table layout with fast search, column sorting, status badges, and AJAX pagination.
  * `modal.blade.php` & `badge.blade.php`: Styled dialogs and status tags (Paid, Pending, Overdue, Critical Cash Alert).
* **Executive Dashboard Redesign (`resources/views/dashboard.blade.php`):**
  * Top bar showing Net Cashflow, Total Revenue, Mobile Money balance (FedaPay / CinetPay / Stripe), and Unreconciled Items.
  * Interactive ApexCharts for 12-month cash trend and SYSCOHADA expense categorization.
  * Quick-action bar: 1-click OCR Liasse Scanner, Instant Invoice Generator, AI Advisor Quick Prompt.

### Phase 2: SYSCOHADA Accounting & Automated Reconciliation
* **Hybrid OCR Liasse Fiscale Engine:**
  * High-precision extraction of SYSCOHADA Révisé tax return documents (Balance Sheet / Bilan Actif & Passif, Income Statement / Compte de Résultat, Intermediate Management Balances / SIG).
  * Interactive verification modal displaying original PDF side-by-side with parsed fields, confidence scores (%), and field highlighting for rapid validation.
* **Smart Reconciliation Module:**
  * Auto-matching algorithm matching accounting ledger entries against FedaPay, CinetPay, Stripe, and uploaded bank statements (CSV/XLSX).
  * Matching criteria: Transaction ID, exact amount, date range (+/- 3 days), invoice number.
  * Dedicated reconciliation interface with 1-click matching ("Match", "Create Expense", "Ignore").
* **Compliance & Legal Exports:**
  * 1-click export of 6-column SYSCOHADA Trial Balance (Balance à 6 colonnes), General Ledger, and General Journal in PDF/Excel.

### Phase 3: AI Business Advisor & Predictive Analytics
* **Predictive Cashflow Engine:**
  * 30/60/90-day cashflow forecast based on recurring customer invoices, fixed operational costs, and supplier due dates.
  * Early warning system triggering alerts if projected cash drops below safety thresholds within 30 days.
* **AI Business Advisor (`AiBusinessAdvisorController`):**
  * Live Insights widget on Dashboard offering daily actionable recommendations (e.g., overdue invoice reminders, cost reduction opportunities).
  * Natural language conversational interface allowing executives to query financial metrics ("What is our debt ratio?", "How much did we spend on transport this month?").
  * Strict multi-tenant isolation: AI prompts leverage localized context with zero data leakage between accounts.
* **Automated Executive Summaries:**
  * Monthly 1-page PDF summary generated for management and investors.
  * In-app & email notifications for critical financial threshold events.

### Phase 4: RBAC, Security & Infrastructure Performance
* **Granular Role-Based Access Control (`EnterpriseTeamController`):**
  * Roles: Executive/Admin, Accountant, Sales Operator, Investor (read-only).
  * Comprehensive Audit Trail (`MenuActivityLogController` & Platform Log) tracking user actions, timestamps, IP addresses, and modified resources.
* **Security & Data Protection:**
  * End-to-end file encryption for stored financial documents and tax filings.
  * Strict rate limiting on authentication routes and webhooks (FedaPay, CinetPay, Stripe).
* **Performance & Infrastructure:**
  * Redis/Laravel cache layer for heavy financial aggregations, keeping dashboard load times under 100ms.
  * 1-click Disaster Recovery & Data Export (ZIP archive containing invoices, ledger Excel, and reconciliation logs).

---

## 3. Verification & Testing Strategy

* **Unit & Feature Tests (PHPUnit):**
  * Test route accessibility and permission checks across roles.
  * Test OCR parsing output accuracy on sample SYSCOHADA PDFs.
  * Test Mobile Money reconciliation algorithm matching logic.
  * Test AI Advisor context builder & response formatting.
* **Manual Verification:**
  * Verify UI responsiveness on mobile and desktop viewports.
  * Verify dark/light theme switching and visual rendering.
  * Verify PDF view and side-by-side OCR editor.
