{{-- Outils du dossier client ouvert (mêmes routes que l’entreprise, contexte session). --}}
<li class="sidebar-item {{ request()->routeIs('accounting*') ? 'active' : '' }}">
    <a class="sidebar-link {{ request()->routeIs('accounting*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseAccountingCabinet" role="button" aria-expanded="{{ request()->routeIs('accounting*') ? 'true' : 'false' }}" aria-controls="collapseAccountingCabinet">
        <span class="icon-wrapper">📚</span> <span class="align-middle">Comptabilité</span>
    </a>
    <div class="collapse {{ request()->routeIs('accounting*') ? 'show' : '' }}" id="collapseAccountingCabinet">
        <ul class="sidebar-nav sidebar-nav-sub">
            <li class="sidebar-item-category">Saisie des données</li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting') && !request()->routeIs('accounting.*') ? 'active' : '' }}" href="{{ route('accounting') }}"><span class="icon-wrapper">✍️</span> <span class="align-middle">Gestion des écritures</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.documents') ? 'active' : '' }}" href="{{ route('accounting.documents') }}"><span class="icon-wrapper">📄</span> <span class="align-middle">Gestion des documents</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.plan') ? 'active' : '' }}" href="{{ route('accounting.plan') }}"><span class="icon-wrapper">📋</span> <span class="align-middle">Plan comptable OHADA</span></a>
            </li>
            <li class="sidebar-item-category">Moteur comptable</li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.bank-reconciliation') ? 'active' : '' }}" href="{{ route('accounting.bank-reconciliation') }}"><span class="icon-wrapper">🏦</span> <span class="align-middle">Rapprochement bancaire</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.monthly-closing') ? 'active' : '' }}" href="{{ route('accounting.monthly-closing') }}"><span class="icon-wrapper">📅</span> <span class="align-middle">Clôture mensuelle</span></a>
            </li>
            <li class="sidebar-divider"></li>
            <li class="sidebar-item-category">Rapports et analyses</li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.report.journal') ? 'active' : '' }}" href="{{ route('accounting.report.journal') }}"><span class="icon-wrapper">📖</span> <span class="align-middle">Journal</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.report.grand-livre') ? 'active' : '' }}" href="{{ route('accounting.report.grand-livre') }}"><span class="icon-wrapper">📑</span> <span class="align-middle">Grand livre</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.report.balance') ? 'active' : '' }}" href="{{ route('accounting.report.balance') }}"><span class="icon-wrapper">⚖️</span> <span class="align-middle">Balance</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.report.bilan') ? 'active' : '' }}" href="{{ route('accounting.report.bilan') }}"><span class="icon-wrapper">🔶</span> <span class="align-middle">Bilan simplifié</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('accounting.report.resultat') ? 'active' : '' }}" href="{{ route('accounting.report.resultat') }}"><span class="icon-wrapper">📊</span> <span class="align-middle">Compte de résultat</span></a>
            </li>
        </ul>
    </div>
</li>
<li class="sidebar-item {{ request()->routeIs('treasury*') ? 'active' : '' }}">
    <a class="sidebar-link {{ request()->routeIs('treasury*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseTreasuryCabinet" role="button" aria-expanded="{{ request()->routeIs('treasury*') ? 'true' : 'false' }}" aria-controls="collapseTreasuryCabinet">
        <span class="icon-wrapper">💰</span> <span class="align-middle">Trésorerie</span>
    </a>
    <div class="collapse {{ request()->routeIs('treasury*') ? 'show' : '' }}" id="collapseTreasuryCabinet">
        <ul class="sidebar-nav sidebar-nav-sub">
            <li class="sidebar-item-category">Vue d'ensemble</li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('treasury.tracking') ? 'active' : '' }}" href="{{ route('treasury.tracking') }}"><span class="icon-wrapper">📊</span> <span class="align-middle">Tableau de bord</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('treasury.balance') ? 'active' : '' }}" href="{{ route('treasury.balance') }}"><span class="icon-wrapper">💳</span> <span class="align-middle">Solde de trésorerie</span></a>
            </li>
            <li class="sidebar-item">
                <a class="sidebar-link {{ request()->routeIs('treasury.forecast') ? 'active' : '' }}" href="{{ route('treasury.forecast') }}"><span class="icon-wrapper">🔮</span> <span class="align-middle">Prévisions</span></a>
            </li>
        </ul>
    </div>
</li>
<li class="sidebar-item {{ request()->routeIs('investor.*') ? 'active' : '' }}">
    <a class="sidebar-link" href="{{ route('investor.readiness') }}"><span class="icon-wrapper">📈</span> <span class="align-middle">Investisseurs</span></a>
</li>
<li class="sidebar-item {{ request()->routeIs(['profile.company.fird', 'profile.company.fird.update']) ? 'active' : '' }}">
    <a class="sidebar-link" href="{{ route('profile.company.fird') }}"><i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Fiche entreprise (FIRD)</span></a>
</li>
