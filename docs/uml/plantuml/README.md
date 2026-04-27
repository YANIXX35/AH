## UML (PlantUML)

Ce dossier fournit une version **PlantUML** des diagrammes d’architecture, afin de pouvoir les **prévisualiser** et **exporter** facilement (PNG/SVG) dans l’IDE.

### Thèmes techniques couverts (issus du code)

- **Laravel** : routes (`routes/web.php`), middlewares, contrôleurs HTTP, validation `Request`, sessions/auth.
- **Blade** : vues et parcours UI.
- **Eloquent + migrations** : modèles et persistance relationnelle.
- **Rate limiting** : `throttle:*` (auth, OCR, finance, Stripe webhook…).
- **Stockage** : `Storage::disk('public')` pour pièces jointes / documents.
- **OCR local** : runner **PaddleOCR** (Python) exécuté via `Symfony Process`, parsing tableur via `PhpSpreadsheet`.
- **Paiements** : **Stripe** (checkout + payouts + webhook signé), **FedaPay sandbox** (Mobile Money + fallback page).
- **E-mail** : OTP et confirmation (Mailables).
- **Gouvernance & audit** : verrous de période (`TreasuryPeriodLock`) et logs (`TreasuryAudit`).

### Sommaire

#### Vue composants
- `01-components.puml`
- `05-global-ia-components.puml` (vue composants globale avec couche IA)

#### Vue classes
- `02-classes.puml`

#### Vues séquence (par fonctionnalité)
- `03-seq-global.puml`
- `03-seq-global-toutes-fonctionnalites.puml`
- `03-seq-ocr-accounting.puml`
- `03-seq-stripe-webhook.puml`
- `03-seq-accountant-workspace.puml`
- `03-seq-auth-login.puml`
- `03-seq-auth-reset-otp.puml`
- `03-seq-profile-update.puml`
- `03-seq-profile-subscription-simulate.puml`
- `03-seq-profile-team-add-member.puml`
- `03-seq-accounting-upload-documents.puml`
- `03-seq-accounting-manual-ocr-validation.puml`
- `03-seq-accounting-upload-plan-comptable.puml`
- `03-seq-treasury-transaction-store.puml`
- `03-seq-treasury-period-lock-validate.puml`
- `03-seq-treasury-stripe-checkout.puml`
- `03-seq-treasury-stripe-payout.puml`
- `03-seq-treasury-fedapay-checkout.puml`
- `03-seq-investor-submit-request.puml`
- `03-seq-investor-update-workflow.puml`
- `03-seq-support-create-ticket.puml`
- `03-seq-support-add-message.puml`
- `03-seq-notifications-read.puml`
- `03-seq-admin-activate-premium.puml`

#### Vues états (statuts métier)
- `04-state-ocr-status.puml`
- `04-state-premium-cycle.puml`
- `04-state-investment-request-workflow.puml`
- `04-state-treasury-period-lock.puml`

#### Tout-en-un (export multi-pages)
- `99-all-in-one.puml`

### Prévisualisation

Dans Cursor/VS Code, installe une extension PlantUML (ex. “PlantUML”) puis ouvre un fichier `.puml` et utilise l’aperçu.

