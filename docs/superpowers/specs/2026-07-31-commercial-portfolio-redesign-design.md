# Design Specification: Commercial Portfolio & Dashboard Separation

## Goal
The goal of this task is to split the commercial interface into two separate pages to resolve the cluttered/stacked layout ("collées" issue):
1. **Tableau de Bord** (Dashboard Overview): Focusing on analytics, the client growth chart, and the chronological trial planning calendar.
2. **Mon Portefeuille** (Client Portfolio): Focusing on the detailed list of users and enterprises added by the commercial agent, equipped with search, status modification, and key portfolio statistics.

---

## User Review Required
No major breaking changes. The navigation tabs in the commercial portal will be expanded to include "Mon Portefeuille".

---

## Proposed Changes

### 1. Routing (`routes/web.php`)
We will register a new route under the `commercial` prefix:
- `GET /commercial/portefeuille` mapped to `CommercialController@portefeuille` with name `commercial.portefeuille`.

---

### 2. Controller (`app/Http/Controllers/CommercialController.php`)
- **`index` action (Dashboard)**:
  - Fetches the same statistics but focuses the view variables on dashboard requirements: `clients`, `prospects`, `totalClients`, `activeTrials`, `growthLabels`, `growthData`, `totalProspects`.
- **`portefeuille` action (New method)**:
  - Fetches clients, calculates portfolio KPIs (`activeTrials`, `portfolioTrialExpired`, `portfolioConverted`, `portfolioChurned`, `conversionRate`), and manages search query input to filter the client list.
  - Returns `commercial.portefeuille` view.

---

### 3. Views (`resources/views/commercial/*`)

#### [NEW] `resources/views/commercial/portefeuille.blade.php`
A brand new view styled using the Soft-UI design guidelines of the application:
- **Header Navigation Bar**: Highlighting "Mon Portefeuille" as active.
- **KPI Cards Section**:
  - Total Portefeuille clients.
  - Active Trials (⌛ En Essai).
  - Converted (✓ Abonnés).
  - Churned (✗ Partis).
  - Retention/Conversion rate progress bar.
- **Client List Table**:
  - Search bar to search by name/company/email.
  - Responsive table showing Avatar/Initials, Client Name, Company Name/Sector, Contact Details (Email/Phone), Status Badge (Trial Active vs Expired), and Actions (Edit, Delete).
  - Edit Client Modal inline for each client in the loop.

#### [MODIFY] `resources/views/commercial/dashboard.blade.php`
- Modify the navigation tabs in the header bar to include a link to the new Portefeuille route.
- Keep the KPI overview summary cards.
- Keep the cumulative client growth chart.
- Keep the 1-Month Trial Planning Calendar.
- **Remove** Section 3 (Liste des clients / entreprises ajoutés) from this view entirely.

---

## Verification Plan

### Manual Verification
- Access `/commercial/dashboard` and verify that the client list is no longer displayed. Verify the layout feels clean and the charts/calendar display correctly.
- Click the "Mon Portefeuille" tab in the header. Verify it navigates to `/commercial/portefeuille`.
- Verify the portfolio statistics cards display correct numbers matching the database.
- Test the client search, edit modal, and delete functions on the new page.
