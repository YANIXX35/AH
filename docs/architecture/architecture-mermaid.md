# Architecture plateforme (Mermaid)

Ce document représente l'architecture applicative telle qu'elle existe aujourd'hui dans le code, avec la cible modulaire préparée dans `app/Domain`.

Diagrammes séparés :
- `docs/architecture/01-vue-ensemble-mermaid.md`
- `docs/architecture/02-flux-critique-mermaid.md`
- `docs/architecture/03-contrats-integrite-mermaid.md`
- `docs/architecture/04-modele-graphe-global-ia.md`
- Pack UML (composants/classes/séquences/états) : `docs/uml/README.md`

## Vue en couches

```mermaid
flowchart TD
    U[Utilisateurs]
    B[Navigateur / UI Blade]

    subgraph H[Entrée HTTP Laravel]
        R[Routes web.php]
        M[Middlewares web<br/>EnsureAccountNotSuspended<br/>SanitizeClientWorkspaceSession<br/>EnforceAccountantPortalPolicy<br/>LogMenuNavigation<br/>AttachRequestContext]
        RL[Rate limiting<br/>auth-sensitive / ocr-intensive / investor-actions / finance-write / stripe-webhook]
    end

    subgraph C[Contrôleurs]
        DC[DashboardController]
        AC[AccountingController + AccountingDocumentController]
        TC[TreasuryController]
        IC[InvestorController]
        ADC[Admin* Controllers]
        ACC[Accountant* Controllers]
        SCC[Support / Notifications / Auth / Profile]
    end

    subgraph S[Services et support applicatif]
        DMS[DashboardMetricsService]
        OCR[OcrService / OcrPipelineService]
        IRS[InvestorReadinessService]
        IDS[InvestmentDossierChecklistService]
        SFR[SmeFinancialRatioService]
        TBS[TreasuryBalanceService]
        STS[StripeTreasuryService / FedaPaySandboxService]
        CWS[ClientWorkspace]
        OSTS[OcrStatus]
        FRC[FinancialRatioServiceContract]
    end

    subgraph D[Données]
        MOD[Modèles Eloquent]
        DB[(Base de données)]
        MIG[Migrations]
    end

    subgraph X[Systèmes externes]
        STRIPE[Stripe]
        FEDAPAY[FedaPay]
    end

    U --> B --> R
    R --> M
    R --> RL
    M --> C
    RL -. protège .-> C

    DC --> DMS
    AC --> OCR
    AC --> CWS
    TC --> TBS
    TC --> STS
    TC --> CWS
    IC --> IRS
    IC --> IDS
    IRS --> FRC
    FRC --> SFR
    ACC --> CWS

    C --> MOD
    S --> MOD
    MIG --> DB
    MOD --> DB

    STS --> STRIPE
    STS --> FEDAPAY
```

## Vue par domaines métier

```mermaid
flowchart LR
    subgraph P[Plateforme]
        AUTH[Auth / Register / Profile]
        ADMIN[Administration plateforme]
        DOC[Documentation]
        SUP[Support + Notifications]
    end

    subgraph A[Comptabilité premium]
        A1[Documents comptables]
        A2[OCR]
        A3[Écritures]
        A4[Plan comptable]
        A5[États financiers]
    end

    subgraph T[Trésorerie]
        T1[Transactions]
        T2[Solde et prévisions]
        T3[Verrous de période]
        T4[Rapprochement paiement]
    end

    subgraph I[Parcours investisseur]
        I1[Investor readiness]
        I2[Dossier et checklist]
        I3[Demandes d'investissement]
        I4[Analyse financière]
    end

    subgraph PAY[Paiement et abonnement]
        PY1[FedaPay sandbox]
        PY2[Stripe webhook]
        PY3[PaymentTransaction]
        PY4[SubscriptionHistory]
    end

    subgraph CAB[Cabinet comptable]
        C1[Espace comptable]
        C2[Sélection client]
        C3[Portée de données via ClientWorkspace]
    end

    AUTH --> A
    AUTH --> T
    AUTH --> I
    AUTH --> PAY
    ADMIN --> PAY
    ADMIN --> I
    CAB --> A
    CAB --> T
    CAB --> I

    A --> T
    I --> A
    I --> T
    PAY --> T
    SUP --> AUTH
```

## Flux transverse de portée et gouvernance

```mermaid
flowchart LR
    L[EnterpriseLicense]
    U[Utilisateur connecté]
    CW[ClientWorkspace]
    AP[Politiques et middlewares]

    subgraph DOM[Domaines protégés]
        A[Accounting]
        T[Treasury]
        I[Investor]
        AD[Admin]
    end

    L --> U
    U --> CW
    CW --> A
    CW --> T
    CW --> I
    AP -. contrôle l'accès .-> A
    AP -. contrôle l'accès .-> T
    AP -. contrôle l'accès .-> I
    AP -. contrôle l'accès .-> AD
```

## Cible modulaire progressive

```mermaid
flowchart LR
    subgraph NOW[État actuel]
        WEB[Routes + Controllers]
        SRV[Services]
        ELO[Models Eloquent]
    end

    subgraph TARGET[Cible]
        DACC[Domain/Accounting/UseCases]
        DTRE[Domain/Treasury/UseCases]
        DINV[Domain/Investor/UseCases]
        DPAY[Domain/Payment/UseCases]
        DADM[Domain/Admin/UseCases]
        CTR[Contracts]
        SUPP[Support transverse]
    end

    WEB --> SRV --> ELO

    WEB -. migration progressive .-> DACC
    WEB -. migration progressive .-> DTRE
    WEB -. migration progressive .-> DINV
    WEB -. migration progressive .-> DPAY
    WEB -. migration progressive .-> DADM

    CTR --> DACC
    CTR --> DINV
    SUPP --> DACC
    SUPP --> DTRE
    SUPP --> DINV
```

## Lecture rapide

- L'application reste un monolithe Laravel structuré autour de `routes/web.php`, des middlewares `web`, des contrôleurs HTTP, des services applicatifs et des modèles Eloquent.
- La séparation métier la plus visible concerne `Accounting`, `Treasury`, `Investor`, `Payment`, `Admin` et le portail `Accountant`.
- `ClientWorkspace` est un mécanisme transverse important pour la portée des données multi-clients.
- `FinancialRatioServiceContract` et `app/Domain/*` montrent une transition vers une architecture plus modulaire, mais la majorité du code d'exécution reste encore dans `Http`, `Services` et `Models`.

