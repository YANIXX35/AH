# Unification Bilan/Résultat vers Liasse BCEAO Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/accounting/report/bilan` and `/accounting/report/resultat` redirect to the already-correct `/accounting/liasse-bceao` page instead of rendering a simplified, incorrect calculation, then remove the now-dead code.

**Architecture:** Two new thin controller methods (`reportBilanRedirect`, `reportResultatRedirect`) replace `report()` as the handler for the `accounting.report.bilan`/`accounting.report.resultat` routes, each doing a one-line `redirect()->route('accounting.liasse-bceao', $request->query())` — identical to the existing `downloadBilan()`/`viewBilanPdf()`/`showBilanPdfViewer()` pattern already in production. Then the now-unreachable `bilan`/`resultat` branches are removed from `report()` and `report.blade.php`.

**Tech Stack:** Laravel 13, Blade, PHP 8.4 (production; local dev machine has PHP 8.2, so PHPUnit cannot run locally — verify via `php artisan tinker` and manual browser testing instead, then re-verify in production after deploy).

## Global Constraints

- No changes to `BceaoLiasseService` or `accounting.liasse-bceao`/`liasseBceao()`/`buildLiassePayload()` — they are already correct.
- No changes to `ErpNextAccountingTestController` or any ERPNext-related code — out of scope.
- `date_from`/`date_to` query params must be forwarded unchanged on redirect — both routes already use the identical param names/shapes, confirmed by reading both methods (see design spec `docs/superpowers/specs/2026-09-15-bilan-resultat-liasse-unification-design.md`).
- Every deletion step must be immediately followed by a `php -l` syntax check (`AccountingController.php`) and a Blade render smoke test (for `report.blade.php`), since this plan removes large chunks of a 3600+ line controller and a 2500+ line Blade view — a syntax mistake here breaks every accounting report tab, not just Bilan/Résultat.
- All deletions in `report.blade.php` are given as exact `sed` line-range commands with the **line numbers verified against the file as of this plan being written** — before running each `sed -i 'START,ENDd'`, re-run the `grep -n` verification shown in that step to confirm the anchor lines still match (earlier deletions in this same task shift line numbers for everything below them, which is why every step in Task 2 works **top-to-bottom by descending line number**, i.e. highest line numbers deleted first).

---

### Task 1: Controller — add redirect methods, wire routes, remove dead `report()` branches

**Files:**
- Modify: `app/Http/Controllers/AccountingController.php:2059-2153`
- Modify: `routes/web.php:627-628`

**Interfaces:**
- Produces: `AccountingController::reportBilanRedirect(Request $request): RedirectResponse` and `AccountingController::reportResultatRedirect(Request $request): RedirectResponse` — both redirect to route `accounting.liasse-bceao` forwarding `$request->query()`. These are the new handlers bound to routes `accounting.report.bilan` and `accounting.report.resultat`.
- Consumes: nothing new — reuses the existing `accounting.liasse-bceao` route name, already registered at `routes/web.php:634` and already fully functional (`liasseBceao()`/`buildLiassePayload()`, untouched by this plan).

- [ ] **Step 1: Remove `bilan`/`resultat` from the `$reportType` detection chain in `report()`**

In `app/Http/Controllers/AccountingController.php`, find this exact block (currently lines 2057-2067):

```php
        } elseif ($request->routeIs('accounting.report.balance')) {
            $reportType = 'balance';
        } elseif ($request->routeIs('accounting.report.bilan')) {
            $reportType = 'bilan';
        } elseif ($request->routeIs('accounting.report.resultat')) {
            $reportType = 'resultat';
        } elseif ($request->routeIs('accounting.report.tafire')) {
            $reportType = 'tafire';
        } elseif ($request->routeIs('accounting.report.annexe')) {
            $reportType = 'annexe';
        }
```

Replace with:

```php
        } elseif ($request->routeIs('accounting.report.balance')) {
            $reportType = 'balance';
        } elseif ($request->routeIs('accounting.report.tafire')) {
            $reportType = 'tafire';
        } elseif ($request->routeIs('accounting.report.annexe')) {
            $reportType = 'annexe';
        }
```

(Use the Edit tool with this exact `old_string`/`new_string` pair — the surrounding `elseif` chain makes this block unique in the file.)

- [ ] **Step 2: Remove the now-unreachable `bilan` branch (QR/verification generation) from `report()`**

Immediately below the block from Step 1, find this exact block (currently lines 2069-2112):

```php
        $bilanReference = null;
        $qrUrl = null;
        if ($reportType === 'bilan') {
            $referenceInput = sprintf(
                '%s|%s|%s|%s|%s|%s|%s',
                $companyName,
                $companySigle,
                $companyTaxId,
                $exerciseDate->format('Y'),
                $periodEnd ? $periodEnd->format('Y-m-d') : '',
                $this->workspaceUserId(),
                now()->format('Y-m-d H:i:s')
            );

            $bilanReference = strtoupper(Str::substr(hash('sha256', $referenceInput), 0, 16));

            $liasseForVerification = $liasseService->generateLiasse($entries);

            try {
                DocumentVerification::updateOrCreate(
                    ['reference' => $bilanReference],
                    [
                        'type' => 'bilan',
                        'user_id' => $this->workspaceUserId(),
                        'company_name' => $companyName,
                        'company_sigle' => $companySigle,
                        'company_tax_id' => $companyTaxId,
                        'exercise_year' => $exerciseDate->format('Y'),
                        'total_actif' => $liasseForVerification['actif']['total']['net_n'] ?? null,
                        'total_passif' => $liasseForVerification['passif']['total']['net_n'] ?? null,
                        'resultat_net' => $liasseForVerification['resultat']['totals']['XZ']['net_n'] ?? null,
                        'generated_at' => now(),
                    ]
                );
            } catch (\Throwable $exception) {
                Log::warning('document_verification_write_failed', [
                    'reference' => $bilanReference,
                    'error' => $exception->getMessage(),
                ]);
            }

            $verificationUrl = route('documents.verify', $bilanReference);
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='.urlencode($verificationUrl);
        }
```

Replace with just:

```php
        $bilanReference = null;
        $qrUrl = null;
```

(`$bilanReference`/`$qrUrl` stay declared as `null` because they're still passed into the view's `array_merge(...)` a few lines below and still referenced by `report.blade.php` in a spot Task 2 will simplify to an unconditional branch — declaring them as always-`null` here is correct and required, not a leftover.)

Note: the second parameter of `report(Request $request, BceaoLiasseService $liasseService)` (the `$liasseService` argument) is no longer used anywhere in the method after this deletion. **Do not remove the parameter** — Laravel resolves it via method injection when routing to `report()`, and removing an unused-but-injected parameter is out of scope for this fix (it's harmless to leave; changing the method signature risks breaking something unrelated). Leave it as-is.

- [ ] **Step 3: Add the two new redirect methods**

Immediately after `showBilanPdfViewer()` and before `liasseBceao()`, find this exact anchor (currently lines 2150-2155):

```php
    public function showBilanPdfViewer(Request $request)
    {
        return redirect()->route('accounting.liasse-bceao', array_merge($request->query(), ['_' => 'actif']));
    }

    public function liasseBceao(Request $request, BceaoLiasseService $liasseService)
```

Replace with:

```php
    public function showBilanPdfViewer(Request $request)
    {
        return redirect()->route('accounting.liasse-bceao', array_merge($request->query(), ['_' => 'actif']));
    }

    /**
     * @deprecated Cet onglet "Bilan" calculait ses lignes à partir de seulement
     * 2 agrégats globaux (actifs/passifs), pas des vraies masses SYSCOHADA par
     * compte — au contraire de BceaoLiasseService, déjà correct et déjà utilisé
     * par la Liasse BCEAO. On y redirige plutôt que de dupliquer/corriger ce calcul.
     */
    public function reportBilanRedirect(Request $request)
    {
        return redirect()->route('accounting.liasse-bceao', $request->query());
    }

    /**
     * @deprecated Même raison que reportBilanRedirect() ci-dessus, appliquée au
     * Compte de résultat.
     */
    public function reportResultatRedirect(Request $request)
    {
        return redirect()->route('accounting.liasse-bceao', $request->query());
    }

    public function liasseBceao(Request $request, BceaoLiasseService $liasseService)
```

- [ ] **Step 4: Lint-check the controller**

Run:
```bash
php -l app/Http/Controllers/AccountingController.php
```
Expected: `No syntax errors detected in app/Http/Controllers/AccountingController.php`

If PHP 8.2 (the local dev version) rejects a construct that's valid in 8.4, note it and stop — this plan does not introduce any 8.4-only syntax, so a failure here means a mistake in the edits above, not a version mismatch.

- [ ] **Step 5: Wire the routes to the new methods**

In `routes/web.php`, find these exact 2 lines (currently 627-628):

```php
        Route::get('/accounting/report/bilan', [AccountingController::class, 'report'])->name('accounting.report.bilan');
        Route::get('/accounting/report/resultat', [AccountingController::class, 'report'])->name('accounting.report.resultat');
```

Replace with:

```php
        Route::get('/accounting/report/bilan', [AccountingController::class, 'reportBilanRedirect'])->name('accounting.report.bilan');
        Route::get('/accounting/report/resultat', [AccountingController::class, 'reportResultatRedirect'])->name('accounting.report.resultat');
```

Then run:
```bash
php artisan route:list --name=accounting.report.bilan
php artisan route:list --name=accounting.report.resultat
```
Expected: both show `AccountingController@reportBilanRedirect` / `AccountingController@reportResultatRedirect` as their action, and `php artisan route:list` overall must not error (would indicate a route-file syntax problem).

- [ ] **Step 6: Manual verification (local)**

Since PHPUnit cannot run locally (PHP 8.2 vs 8.4 required by dependencies), verify manually:

```bash
php artisan tinker --execute="
\$request = Illuminate\Http\Request::create('/accounting/report/bilan?date_from=2026-01-01&date_to=2026-12-31', 'GET');
app()->instance('request', \$request);
echo (new App\Http\Controllers\AccountingController)->reportBilanRedirect(\$request)->getTargetUrl();
"
```
Expected output: a URL like `http://<host>/accounting/liasse-bceao?date_from=2026-01-01&date_to=2026-12-31` (host depends on local `.env` `APP_URL`).

Repeat the same for `reportResultatRedirect` (same expected shape).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AccountingController.php routes/web.php
git commit -m "$(cat <<'EOF'
fix(accounting): redirect Bilan/Resultat report tabs to Liasse BCEAO

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Blade cleanup — remove dead Bilan/Résultat sections and conditionals from `report.blade.php`

**Files:**
- Modify: `resources/views/accounting/report.blade.php`

**Interfaces:**
- Consumes: nothing from Task 1 — this task only removes now-unreachable Blade code (the `bilan`/`resultat` values of `$reportType` can no longer be produced by the controller after Task 1, but this task is self-contained and independently verifiable even if run alone).
- Produces: a `report.blade.php` that no longer references `#bilan-section`, `#resultat-section`, `printBilanOnly()`, `printResultatOnly()`, `previewBilan()`, `downloadBilan()` (the JS function, not the controller method), or the `bilan`/`resultat` branches of the `$reportType` CSS switch.

**Important:** Do all `sed` deletions in this task **in the exact order given (highest line numbers first)**. Each deletion changes every line number below it in the file, so later steps in this list — which target lower line numbers — remain valid only if you go top-to-bottom through this task's steps (which is bottom-to-top through the file). Before each `sed -i` command, run its paired `grep -n` verification first; if the printed lines don't match what's shown in this plan, STOP and re-locate the block by content before deleting (something upstream shifted).

- [ ] **Step 1: Verify and delete the `downloadBilan()` JS function (not the controller method — the JS function of the same name at the bottom of this Blade file)**

Verify:
```bash
grep -n "function downloadBilan" resources/views/accounting/report.blade.php
sed -n '2573,2578p' resources/views/accounting/report.blade.php
```
Expected `sed` output:
```
        }

        function downloadBilan() {
            printBilanOnly();
        }
    </script>
```

Delete:
```bash
sed -i '2575,2577d' resources/views/accounting/report.blade.php
```

This removes:
```js
        function downloadBilan() {
            printBilanOnly();
        }
```

- [ ] **Step 2: Delete the `previewBilan()` JS function**

Verify:
```bash
sed -n '2540,2541p' resources/views/accounting/report.blade.php
sed -n '2572,2573p' resources/views/accounting/report.blade.php
```
Expected: line 2540 is `        function previewBilan() {`, and lines 2572-2573 are the function's closing `}` and a blank line.

Delete:
```bash
sed -i '2540,2573d' resources/views/accounting/report.blade.php
```

- [ ] **Step 3: Delete the `printBilanOnly()` JS function**

Verify:
```bash
sed -n '2463,2464p' resources/views/accounting/report.blade.php
sed -n '2515,2516p' resources/views/accounting/report.blade.php
```
Expected: line 2463 is `        function printBilanOnly() {`, line 2515 is the function's closing `}`, line 2516 blank.

Delete:
```bash
sed -i '2463,2516d' resources/views/accounting/report.blade.php
```

(This range includes the trailing blank line so `printSection()`, which follows, doesn't end up with a double blank line before it — not required for correctness, just tidiness.)

- [ ] **Step 4: Delete the `printResultatOnly()` JS function**

Verify:
```bash
sed -n '2320,2321p' resources/views/accounting/report.blade.php
sed -n '2369,2370p' resources/views/accounting/report.blade.php
```
Expected: line 2320 is `        function printResultatOnly() {`, line 2369 is the function's closing `}`, line 2370 blank.

Delete:
```bash
sed -i '2320,2370d' resources/views/accounting/report.blade.php
```

- [ ] **Step 5: Lint-check after the JS deletions**

```bash
php -l resources/views/accounting/report.blade.php
```
Expected: `No syntax errors detected` (Blade files are valid PHP once compiled, but raw `php -l` on the `.blade.php` source still catches unbalanced braces in `@php` blocks and inline PHP — it will NOT catch Blade-directive mismatches like an orphaned `@endif`, which Step 12 checks separately).

- [ ] **Step 6: Delete the `#bilan-section` and `#resultat-section` HTML blocks together with their surrounding page-breaks**

Verify:
```bash
sed -n '1276,1280p' resources/views/accounting/report.blade.php
sed -n '1886,1889p' resources/views/accounting/report.blade.php
```
Expected:
```
    </section>

    <div class="page-break"></div>

    <section id="bilan-section" class="report-section mb-5">
```
and
```
    </section>


    <section id="tafire-section" class="report-section mb-5">
```

Delete:
```bash
sed -i '1278,1888d' resources/views/accounting/report.blade.php
```

This removes the page-break before `#bilan-section`, the entire `#bilan-section` (SYSCOHADA Bilan table markup, ~287 lines), the page-break between Bilan and Compte de Résultat, and the entire `#resultat-section` (~318 lines), leaving `#balance-section`'s closing `</section>` immediately followed by a blank line and then `#tafire-section` opening.

- [ ] **Step 7: Delete the résultat print/filter toolbar block**

Verify:
```bash
sed -n '662,663p' resources/views/accounting/report.blade.php
sed -n '686,689p' resources/views/accounting/report.blade.php
```
Expected:
```
    (blank line)
    @if($reportType === 'resultat' || $reportType === 'full')
```
and
```
    @endif
    (blank)
    (blank)
    @if($reportType === 'tafire' || $reportType === 'full')
```

Delete:
```bash
sed -i '663,688d' resources/views/accounting/report.blade.php
```

- [ ] **Step 8: Delete the bilan print/viewer toolbar block**

Verify:
```bash
sed -n '551,552p' resources/views/accounting/report.blade.php
sed -n '567,569p' resources/views/accounting/report.blade.php
```
Expected:
```
    (blank line)
    @if($reportType === 'bilan')
```
and
```
    @endif
    (blank)
    @if($reportType === 'journal' || $reportType === 'full')
```

Delete:
```bash
sed -i '552,568d' resources/views/accounting/report.blade.php
```

- [ ] **Step 9: Replace the header QR/title conditional with its unconditional else-branch**

This one is an `@if`/`@else`/`@endif`, not a plain deletable block — Bilan's branch must go, but the "else" content (the normal report title block, still used by every other report type including `full`) must remain, unconditional.

Verify:
```bash
sed -n '510,520p' resources/views/accounting/report.blade.php
```
Expected exact match:
```php
            @if($reportType === 'bilan')
                <div class="text-md-end mt-3 mt-md-0 d-flex flex-column align-items-center">
                    <img src="{{ $qrUrl }}" alt="QR Code bilan" style="width:120px; height:120px; object-fit:contain; border:1px solid #dee2e6; background:#fff; padding:8px;" />
                    <p class="small text-muted mt-2 mb-0">Réf. {{ $bilanReference }}</p>
                </div>
            @else
                <div class="text-md-end mt-3 mt-md-0">
                    <p class="mb-1"><strong>{{ $currentReportTitle }}</strong></p>
                    <p class="mb-0">{{ $entries->count() }} écritures</p>
                </div>
            @endif
```

If it matches exactly, use the Edit tool on `resources/views/accounting/report.blade.php` with:

`old_string`:
```
            @if($reportType === 'bilan')
                <div class="text-md-end mt-3 mt-md-0 d-flex flex-column align-items-center">
                    <img src="{{ $qrUrl }}" alt="QR Code bilan" style="width:120px; height:120px; object-fit:contain; border:1px solid #dee2e6; background:#fff; padding:8px;" />
                    <p class="small text-muted mt-2 mb-0">Réf. {{ $bilanReference }}</p>
                </div>
            @else
                <div class="text-md-end mt-3 mt-md-0">
                    <p class="mb-1"><strong>{{ $currentReportTitle }}</strong></p>
                    <p class="mb-0">{{ $entries->count() }} écritures</p>
                </div>
            @endif
```

`new_string`:
```
            <div class="text-md-end mt-3 mt-md-0">
                <p class="mb-1"><strong>{{ $currentReportTitle }}</strong></p>
                <p class="mb-0">{{ $entries->count() }} écritures</p>
            </div>
```

(`$qrUrl` and `$bilanReference` are no longer referenced anywhere in the file after this edit — that's expected; they're still passed from the controller as always-`null`, which is harmless.)

- [ ] **Step 10: Delete the bilan-only print `@media` CSS block**

Verify:
```bash
sed -n '326,338p' resources/views/accounting/report.blade.php
```
Expected:
```
(blank)
        @if($reportType === 'bilan')
            @media print {
                body * { visibility: hidden !important; }
                .report-header, #bilan-section { visibility: visible !important; }
                .report-header, #bilan-section, #bilan-section * { display: block !important; }
                .report-header .text-md-end { display: none !important; }
                .report-header { margin-bottom: 1.5rem !important; }
                .page-break { display: none !important; }
            }
        @endif

        .bilan-header {
```

Delete:
```bash
sed -i '327,337d' resources/views/accounting/report.blade.php
```

- [ ] **Step 11: Delete the `bilan`/`resultat` branches from the CSS display-switch**

Verify:
```bash
sed -n '275,282p' resources/views/accounting/report.blade.php
```
Expected:
```php
            @elseif($reportType === 'balance')
                #journal-section, #grand-livre-section, #bilan-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @elseif($reportType === 'bilan')
                #journal-section, #grand-livre-section, #balance-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+3) { display: none !important; }
            @elseif($reportType === 'resultat')
                #journal-section, #grand-livre-section, #balance-section, #bilan-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
```

Delete lines 276-281 only (do not touch the `balance` branch on 273-275):
```bash
sed -i '276,281d' resources/views/accounting/report.blade.php
```

Also, in the surviving CSS branches (journal, grand-livre, balance, tafire, annexe — lines above/below the deleted block), the selector lists still mention `#bilan-section` and `#resultat-section` as elements to hide (e.g. `#grand-livre-section, #balance-section, #bilan-section, #resultat-section, #tafire-section, ...`). **Leave these as-is.** They are harmless: CSS selectors for elements that no longer exist in the DOM simply match nothing. Removing them adds risk (many near-identical lines to edit precisely) for zero functional benefit — YAGNI.

- [ ] **Step 12: Full Blade-directive balance check**

```bash
grep -c "@if" resources/views/accounting/report.blade.php
grep -c "@elseif" resources/views/accounting/report.blade.php
grep -c "@endif" resources/views/accounting/report.blade.php
```
There's no fixed expected count (this file has many unrelated `@if`s), but this is a sanity gate: if you suspect a mismatch, cross-check by counting on the pre-edit version via `git show HEAD:resources/views/accounting/report.blade.php | grep -c '@if'` etc. and confirming the delta matches exactly the blocks removed in Steps 6-11 (Step 6 removes 0 net `@if`/`@endif` pairs — the deleted sections had none directly; Steps 7, 8, 10 each remove exactly one matched `@if`/`@endif` pair; Step 9 removes one `@if`/`@else`/`@endif` set entirely; Step 11 removes 2 `@elseif`s, net `@if`/`@endif` count unchanged since the surrounding chain still has its own single `@if`/`@endif`).

- [ ] **Step 13: Render smoke test (local)**

```bash
php artisan tinker --execute="
echo view('accounting.report', [
    'entries' => collect(),
    'companyName' => 'Test SARL',
    'companySigle' => 'TS',
    'companyAddress' => 'Abidjan',
    'companyTaxId' => '123456',
    'companyLogo' => null,
    'companyLogoUser' => new App\Models\User(['name' => 'Test']),
    'bilanReference' => null,
    'qrUrl' => null,
    'exerciseEnd' => '',
    'exerciseYear' => '2026',
    'previousYear' => '2025',
    'durationMonths' => '',
    'dateFrom' => null,
    'dateTo' => null,
    'reportType' => 'journal',
    'ledger' => [],
    'assets' => 0,
    'liabilities' => 0,
    'expenses' => 0,
    'income' => 0,
])->render() ? 'RENDER OK' : 'RENDER FAILED';
"
```
Expected: `RENDER OK` with no exception thrown. (If `summarizeEntries()`'s return shape includes more keys than `ledger`/`assets`/`liabilities`/`expenses`/`income`, this tinker call will throw an `Undefined array key` — if so, open `AccountingController::summarizeEntries()`, list its actual returned keys, and add them to this test payload; do not change `report.blade.php` to work around a missing test key.)

Repeat with `'reportType' => 'tafire'` and `'reportType' => 'annexe'` to confirm the two report types adjacent to the deleted Bilan/Résultat sections still render (the routes that use `'full'`/`'journal'`/`'grand-livre'`/`'balance'` are lower-risk since Task 2 touched nothing inside those sections, but TAFIRE's section immediately follows the deleted block, so it's the one most likely to break if Step 6's line range was off by one).

- [ ] **Step 14: Commit**

```bash
git add resources/views/accounting/report.blade.php
git commit -m "$(cat <<'EOF'
fix(accounting): remove dead Bilan/Resultat sections from report.blade.php

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Deploy and live verification

**Files:** none (deployment + manual browser verification only).

**Interfaces:**
- Consumes: the deployed state of Task 1 + Task 2's commits.

- [ ] **Step 1: Deploy to LWS**

```bash
bash deploy.sh
```
Expected: deploy script completes without error (same script used for every prior sub-project this session — Stock/Invoicing/Accounting/Sport webhooks).

- [ ] **Step 2: Live verification — Bilan redirect**

In a browser, logged in as a real PME (e.g. "NotifyMails #69", used for all prior live verifications this session), visit:
```
https://sitiame-capital.com/accounting/report/bilan
```
Expected: the browser lands on `/accounting/liasse-bceao` (URL bar changes), showing the real SYSCOHADA Bilan (Actif/Passif with AA/AB/AC… line references), not the old simplified 2-aggregate version.

Repeat with a date filter, e.g. `/accounting/report/bilan?date_from=2026-01-01&date_to=2026-12-31`, and confirm the redirect target carries the same `date_from`/`date_to` query params.

- [ ] **Step 3: Live verification — Compte de Résultat redirect**

Visit:
```
https://sitiame-capital.com/accounting/report/resultat
```
Expected: redirects to `/accounting/liasse-bceao`, same as Bilan (the Liasse BCEAO page shows Bilan/Compte de Résultat/TAFIRE together — confirm the Compte de Résultat section is present and populated).

- [ ] **Step 4: Non-regression — other report tabs**

Visit each of:
```
https://sitiame-capital.com/accounting/report/journal
https://sitiame-capital.com/accounting/report/grand-livre
https://sitiame-capital.com/accounting/report/balance
https://sitiame-capital.com/accounting/report/tafire
https://sitiame-capital.com/accounting/report/annexe
```
Expected: all 5 render exactly as before this change (no PHP error page, no missing section, no console JS error) — none of these were supposed to change.

- [ ] **Step 5: Non-regression — Bilan PDF buttons**

From the `/accounting/liasse-bceao` page reached in Step 2, use any print/download PDF controls. Also directly visit:
```
https://sitiame-capital.com/accounting/report/bilan/viewer
https://sitiame-capital.com/accounting/report/bilan/view
https://sitiame-capital.com/accounting/report/bilan/download
```
Expected: all 3 still redirect to their `accounting.liasse-bceao.*` targets exactly as before — these routes/methods (`showBilanPdfViewer`, `viewBilanPdf`, `downloadBilan` the controller method) were not touched by this plan.

- [ ] **Step 6: Report back to the user**

Confirm to the user, in the conversation, exactly what was verified live (which URLs, which PME, what was seen) — matching the verification style used for every prior sub-project this session (Stock/Invoicing/Accounting/Sport webhooks).
