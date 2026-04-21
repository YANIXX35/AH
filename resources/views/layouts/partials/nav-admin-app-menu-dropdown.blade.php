{{-- Visible pour tout administrateur plateforme : même périmètre que l’ancienne section « Pages » du menu latéral. --}}
<li class="nav-item dropdown me-2 admin-app-metier-nav">
    <a class="nav-link dropdown-toggle fw-semibold text-dark py-1 px-2 rounded border bg-white shadow-sm"
       href="#" id="adminAppMetierDropdown" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"
       title="Accès rapide : dashboard, comptabilité, trésorerie, profil…">
        <span class="me-1" aria-hidden="true">📋</span> Espace métier
    </a>
    <div class="dropdown-menu dropdown-menu-start shadow border-0 mt-1 py-0" aria-labelledby="adminAppMetierDropdown" style="min-width: 19rem; max-height: min(85vh, 32rem); overflow-y: auto;">
        <a class="dropdown-item py-2" href="{{ route('dashboard') }}"><span class="me-2">🎛️</span> Dashboard</a>
        <div class="dropdown-divider m-0"></div>
        <h6 class="dropdown-header text-uppercase small mb-0 py-2">Comptabilité</h6>
        <div class="px-2 pb-1">
            <span class="text-muted small d-block px-2 mb-1">Saisie des données</span>
            <a class="dropdown-item py-1 small" href="{{ route('accounting') }}">✍️ Gestion des écritures</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.documents') }}">📄 Gestion des documents</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.plan') }}">📋 Plan comptable OHADA</a>
            <span class="text-muted small d-block px-2 mt-2 mb-1">Moteur comptable</span>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.bank-reconciliation') }}">🏦 Rapprochement bancaire</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.monthly-closing') }}">📅 Clôture mensuelle</a>
            <span class="text-muted small d-block px-2 mt-2 mb-1">Rapports et analyses</span>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.report.journal') }}">📖 Journal</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.report.grand-livre') }}">📑 Grand livre</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.report.balance') }}">⚖️ Balance</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.report.bilan') }}">🔶 Bilan simplifié</a>
            <a class="dropdown-item py-1 small" href="{{ route('accounting.report.resultat') }}">📊 Compte de résultat</a>
        </div>
        <div class="dropdown-divider m-0"></div>
        <h6 class="dropdown-header text-uppercase small mb-0 py-2">Trésorerie</h6>
        <a class="dropdown-item py-2 small" href="{{ route('treasury.tracking') }}">📊 Tableau de bord</a>
        <a class="dropdown-item py-2 small" href="{{ route('treasury.balance') }}">💳 Solde de trésorerie</a>
        <a class="dropdown-item py-2 small" href="{{ route('treasury.forecast') }}">🔮 Prévisions</a>
        <div class="dropdown-divider m-0"></div>
        <a class="dropdown-item py-2" href="{{ route('investor.readiness') }}"><span class="me-2">📈</span> Investisseurs</a>
        <a class="dropdown-item py-2" href="{{ route('company.settings') }}"><span class="me-2">👤</span> Profil &amp; paramètres</a>
        <a class="dropdown-item py-2" href="{{ route('profile.company.fird') }}"><span class="me-2">💼</span> Fiche entreprise (FIRD)</a>
        <a class="dropdown-item py-2" href="{{ route('subscriptions.history') }}"><span class="me-2">🕐</span> Historique abonnements</a>
        <a class="dropdown-item py-2" href="{{ route('activity-log.index') }}"><span class="me-2">📜</span> Journal d’activité</a>
    </div>
</li>
