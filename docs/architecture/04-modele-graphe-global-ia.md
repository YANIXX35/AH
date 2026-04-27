# Modèle en graphe global (plateforme + IA)

Ce graphe illustre l'ensemble de la plateforme avec les flux métier principaux et les ajouts IA transverses.

## Graphe global

```mermaid
graph TD
    %% Acteurs
    U1[Utilisateur entreprise]
    U2[Comptable cabinet]
    U3[Admin plateforme]

    %% Interface et entrée HTTP
    UI[Interface Blade + AdminKit]
    R[Routes web.php]
    MW[Middlewares / Policies / Rate limits]

    %% Dashboards
    D1[Dashboard utilisateur]
    D2[Dashboard comptable]
    D3[Dashboard admin]
    OPS[Ops Center IT Manager]

    %% Domaines métier
    A[Comptabilité]
    T[Trésorerie]
    I[Investor / Financement]
    P[Paiements / Abonnements]
    S[Support / Notifications]

    %% Services applicatifs
    SVC1[DashboardMetricsService]
    SVC2[InvestorReadinessService]
    SVC3[InvestmentDossierChecklistService]
    SVC4[SmeFinancialRatioService]
    SVC5[StripeTreasuryService / FedaPaySandboxService]
    SVC6[OcrService / Pipeline OCR]
    SVC7[ClientWorkspace]

    %% IA
    AI0[HuggingFaceOpsAssistantService]
    AI1[Chatbot global role-aware]
    AI2[IA live utilisateur]
    AI3[IA live comptable]
    AI4[IA live admin]
    AI5[IA live Ops Center]
    AI6[AiBusinessAdvisorController]
    AI7[AdminApprovalRequest + IA autonome]
    AI8[AiActionTask backlog]

    %% Données
    M[Models Eloquent]
    DB[(Base de données)]
    LOG[Logs / Audit trails]

    %% Intégrations
    STRIPE[Stripe]
    FEDAPAY[FedaPay]

    %% Accès
    U1 --> UI
    U2 --> UI
    U3 --> UI
    UI --> R --> MW
    MW --> D1
    MW --> D2
    MW --> D3
    MW --> OPS

    %% Dashboards vers domaines
    D1 --> A
    D1 --> T
    D2 --> A
    D2 --> T
    D2 --> I
    D3 --> A
    D3 --> T
    D3 --> I
    D3 --> P
    D3 --> S
    OPS --> S
    OPS --> P
    OPS --> I

    %% Domaines vers services
    D1 --> SVC1
    D2 --> SVC1
    D3 --> SVC1
    I --> SVC2
    I --> SVC3
    I --> SVC4
    A --> SVC6
    T --> SVC5
    A --> SVC7
    T --> SVC7
    I --> SVC7

    %% Couche IA
    UI --> AI1
    AI1 --> AI0
    D1 --> AI2 --> AI0
    D2 --> AI3 --> AI0
    D3 --> AI4 --> AI0
    OPS --> AI5 --> AI0
    AI1 --> AI6
    AI6 --> A
    AI6 --> T
    AI5 --> AI7
    AI5 --> AI8
    AI7 --> LOG
    AI8 --> LOG

    %% Persistance
    A --> M
    T --> M
    I --> M
    P --> M
    S --> M
    M --> DB
    LOG --> DB

    %% Externes
    SVC5 --> STRIPE
    SVC5 --> FEDAPAY
```

## Lecture rapide

- Les trois profils (utilisateur, comptable, admin) passent tous par la même entrée HTTP Laravel.
- Les domaines coeur restent `Comptabilité`, `Trésorerie`, `Investor`, `Paiements`, `Support`.
- L'IA est désormais transverse : chatbot global + IA live par dashboard.
- L'IA autonome est bornée par un mécanisme de gouvernance (`AdminApprovalRequest`) et tracée dans les logs/audits.
