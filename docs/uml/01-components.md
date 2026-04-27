# UML — Diagramme de composants (vue logique)

```mermaid
flowchart TD
    %% Acteurs / UI
    U[Utilisateur] --> UI[Navigateur / UI Blade]

    %% Entrée HTTP Laravel
    UI --> R[routes/web.php]
    R --> MW[Middlewares web]
    R --> RL[Rate limiting]
    MW --> C[Contrôleurs HTTP]
    RL -. protège .-> C

    %% Contrôleurs (façades domaine)
    C --> DC[DashboardController]
    C --> AC[AccountingController + viewers]
    C --> TC[TreasuryController]
    C --> IC[InvestorController]
    C --> ADC[Admin* Controllers]
    C --> ACC[Accountant* Controllers]

    %% Services applicatifs
    DC --> DMS[DashboardMetricsService]
    AC --> OCR[OcrService]
    AC --> CWS[ClientWorkspace]
    TC --> TBS[TreasuryBalanceService]
    TC --> STS[StripeTreasuryService]
    TC --> CWS
    IC --> IRS[InvestorReadinessService]
    IC --> IDS[InvestmentDossierChecklistService]
    IRS --> FRC[FinancialRatioServiceContract]
    FRC --> SFR[SmeFinancialRatioService]
    ACC --> CWS

    %% Données / modèles
    subgraph DATA[Données (Eloquent + migrations)]
      DB[(Base de données)]
      MIG[Migrations]
      UDB[User]
      AE[AccountingEntry]
      AD[AccountingDocument]
      TT[TreasuryTransaction]
      PT[PaymentTransaction]
      SH[SubscriptionHistory]
      EL[EnterpriseLicense]
      IP[InvestorProfile]
    end

    MIG --> DB
    UDB --> DB
    AE --> DB
    AD --> DB
    TT --> DB
    PT --> DB
    SH --> DB
    EL --> DB
    IP --> DB

    %% Services -> modèles
    CWS --> UDB
    OCR --> AE
    OCR --> AD
    STS --> TT
    TBS --> TT
    IRS --> IP

    %% Intégrations externes
    subgraph EXT[Intégrations externes]
      STRIPE[Stripe]
      FEDAPAY[FedaPay]
      STORAGE[Stockage fichiers]
    end

    STS --> STRIPE
    TC -. sandbox .-> FEDAPAY
    AC -. pièces jointes .-> STORAGE
    TC -. reçus/preuves .-> STORAGE
```

