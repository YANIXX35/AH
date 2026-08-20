<style>
    :root {
        --pme-navy: #0F2747;
        --pme-navy-soft: #17325a;
        --pme-gold: #F2D89B;
    }

    .soft-dashboard-body {
        background-color: #eef2f6;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
        padding: 24px;
    }

    .soft-dashboard-container {
        background: #f8fafc;
        border-radius: 32px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06);
        padding: 20px;
    }

    /* Soft White Cards */
    .mockup-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03), 0 4px 12px rgba(15, 23, 42, 0.02);
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .mockup-card:hover {
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.06);
        border-color: #cbd5e1;
    }

    /* Top Navigation Header */
    .mockup-header-bar {
        background: #ffffff;
        border-radius: 24px;
        padding: 12px 18px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.02);
    }

    .pill-tab-btn {
        border-radius: 9999px;
        font-weight: 600;
        font-size: 0.85rem;
        padding: 8px 22px;
        border: none;
        transition: all 0.2s ease;
    }
    .pill-tab-btn-active {
        background: var(--pme-navy);
        color: #ffffff !important;
        box-shadow: 0 4px 12px rgba(15, 39, 71, 0.25);
    }
    .pill-tab-btn-inactive {
        background: transparent;
        color: #64748b;
    }
    .pill-tab-btn-inactive:hover {
        color: var(--pme-navy);
    }

    /* Mockup Status Badges */
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        border-radius: 16px;
        font-size: 0.78rem;
        font-weight: 700;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .status-pill-green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
    }
    .status-pill-purple {
        background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        color: #ffffff;
    }
    .status-pill-blue {
        background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
        color: #ffffff;
    }

    /* Mini Grid Cards (leads / prospects) */
    .mini-user-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 16px;
        text-align: center;
        transition: all 0.2s ease;
    }
    .mini-user-card:hover {
        background: #ffffff;
        border-color: #cbd5e1;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.04);
    }

    /* 3D Orb AI Copilot Card */
    .copilot-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        padding: 24px;
        text-align: center;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03);
    }
    .copilot-orb-3d {
        width: 84px;
        height: 84px;
        margin: 0 auto 16px auto;
        background: radial-gradient(circle at 35% 35%, var(--pme-gold) 0%, var(--pme-navy-soft) 55%, var(--pme-navy) 100%);
        border-radius: 50%;
        box-shadow: 0 14px 35px rgba(15, 39, 71, 0.35), inset -8px -8px 16px rgba(0, 0, 0, 0.25), inset 8px 8px 16px rgba(255, 255, 255, 0.5);
        animation: floatOrb 4s ease-in-out infinite alternate;
    }
    @keyframes floatOrb {
        0% { transform: translateY(0px) scale(1); }
        100% { transform: translateY(-8px) scale(1.04); }
    }

    /* Portfolio KPI Stats */
    .portfolio-kpi-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 20px;
        padding: 20px 22px;
        transition: all 0.22s ease;
        position: relative;
        overflow: hidden;
    }
    .portfolio-kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        border-radius: 20px 20px 0 0;
    }
    .portfolio-kpi-card.kpi-total::before    { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
    .portfolio-kpi-card.kpi-trial::before    { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
    .portfolio-kpi-card.kpi-converted::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .portfolio-kpi-card.kpi-churned::before  { background: linear-gradient(90deg, #ef4444, #f87171); }
    .portfolio-kpi-card:hover {
        box-shadow: 0 12px 28px -4px rgba(15,23,42,0.08);
        transform: translateY(-2px);
        border-color: #cbd5e1;
    }
    .kpi-number {
        font-size: 2.2rem;
        font-weight: 800;
        line-height: 1;
        letter-spacing: -0.5px;
    }
    .kpi-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #94a3b8;
        margin-bottom: 4px;
    }
    .kpi-sub {
        font-size: 0.78rem;
        color: #64748b;
        margin-top: 4px;
    }
    .retention-bar-outer {
        background: #f1f5f9;
        border-radius: 99px;
        height: 12px;
        overflow: hidden;
        position: relative;
        display: flex;
    }
    .retention-bar-converted {
        background: linear-gradient(90deg, #10b981, #34d399);
        height: 100%;
        border-radius: 99px 0 0 99px;
        transition: width 1s cubic-bezier(0.4,0,0.2,1);
    }
    .retention-bar-churned {
        background: linear-gradient(90deg, #ef4444, #f87171);
        height: 100%;
        border-radius: 0 99px 99px 0;
        transition: width 1s cubic-bezier(0.4,0,0.2,1);
    }
    .retention-section-header {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #94a3b8;
        border-bottom: 1px solid #f1f5f9;
        padding-bottom: 8px;
        margin-bottom: 16px;
    }

    /* ============================================================ */
    /* Carte "Solde" (hero widget)                                   */
    /* ============================================================ */
    .hero-balance-card {
        background: linear-gradient(135deg, var(--pme-navy) 0%, var(--pme-navy-soft) 100%);
        border-radius: 24px;
        padding: 28px;
        color: #ffffff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 20px 40px -10px rgba(15, 39, 71, 0.35);
    }
    .hero-balance-card::after {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 220px; height: 220px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(242, 216, 155, 0.16) 0%, rgba(242, 216, 155, 0) 70%);
    }
    .hero-balance-label {
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 6px;
    }
    .hero-balance-figure {
        font-size: 2.4rem;
        font-weight: 800;
        letter-spacing: -0.5px;
        line-height: 1;
    }
    .hero-balance-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(242, 216, 155, 0.16);
        color: var(--pme-gold);
        border-radius: 999px;
        padding: 5px 12px;
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 10px;
    }
    .hero-sparkline-wrap {
        height: 56px;
    }
    .hero-balance-actions .btn {
        border-radius: 999px;
        font-weight: 700;
        font-size: 0.82rem;
    }
    .hero-balance-actions .btn-hero-primary {
        background: var(--pme-gold);
        color: var(--pme-navy);
        border: none;
    }
    .hero-balance-actions .btn-hero-primary:hover {
        background: #eecb7f;
        color: var(--pme-navy);
    }
    .hero-balance-actions .btn-hero-outline {
        background: rgba(255, 255, 255, 0.08);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.25);
    }
    .hero-balance-actions .btn-hero-outline:hover {
        background: rgba(255, 255, 255, 0.16);
        color: #ffffff;
    }

    /* ============================================================ */
    /* Listes (activité récente, échéances, prospects rapides)       */
    /* ============================================================ */
    .activity-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 14px;
        background: #f8fafc;
        border: 1px solid #eef2f6;
        border-radius: 16px;
    }
    .activity-avatar,
    .quick-avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.78rem;
        flex-shrink: 0;
        background: var(--pme-navy);
        color: var(--pme-gold);
        object-fit: cover;
    }
    .expiry-list-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 12px 14px;
        border-radius: 16px;
        border: 1px solid #eef2f6;
        background: #f8fafc;
    }
    .expiry-list-item.is-urgent {
        background: #fff7ed;
        border-color: #fed7aa;
    }
    .quick-prospect-row {
        display: flex;
        align-items: center;
        gap: -8px;
    }
    .quick-avatar {
        width: 44px;
        height: 44px;
        font-size: 0.85rem;
        margin-right: -10px;
        border: 2px solid #ffffff;
    }
    .quick-avatar-add {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: 2px dashed #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        background: #ffffff;
        flex-shrink: 0;
    }
    .quick-avatar-add:hover {
        border-color: var(--pme-navy);
        color: var(--pme-navy);
    }

    /* ============================================================ */
    /* MOBILE — surcharge locale garantie (indépendante du cache CSS externe) */
    /* ============================================================ */
    @media (max-width: 767.98px) {
        .soft-dashboard-body {
            min-height: auto;
            padding: 10px 8px;
        }
        .soft-dashboard-container {
            padding: 0;
            border-radius: 0;
            border: none;
            box-shadow: none;
            background: transparent;
        }

        /* Grille d'actions rapides : 3 colonnes icône + libellé, compacte */
        .mobile-quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }
        .mobile-quick-actions .btn {
            flex-direction: column;
            gap: 4px;
            height: 64px;
            padding: 6px 4px;
            font-size: 0.72rem;
            line-height: 1.1;
            white-space: normal;
        }
        .mobile-quick-actions .btn i,
        .mobile-quick-actions .btn svg {
            width: 18px;
            height: 18px;
        }

        .mobile-stat-card {
            padding: 12px 10px;
        }
        .mobile-stat-card .fs-3 {
            font-size: 1.5rem !important;
        }

        .mobile-section-card {
            padding: 14px;
            margin-bottom: 12px;
        }

        .hero-balance-card {
            padding: 20px;
            border-radius: 20px;
        }
        .hero-balance-figure {
            font-size: 1.9rem;
        }

        /* Barre d'actions du formulaire "Nouveau Client" : empilée en pleine largeur,
           bouton principal en premier, plutôt qu'une ligne qui déborde de l'écran */
        .wizard-footer-actions {
            flex-direction: column-reverse;
            align-items: stretch;
            gap: 10px;
        }
        .wizard-footer-actions > .wizard-footer-btn-group {
            flex-direction: column;
            width: 100%;
            gap: 10px;
        }
        .wizard-footer-actions .btn {
            width: 100%;
        }
    }
</style>
