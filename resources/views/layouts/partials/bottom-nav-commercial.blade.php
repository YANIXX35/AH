<nav id="commercialBottomNav" class="bottom-nav d-lg-none">
    <a href="{{ route('commercial.dashboard') }}" class="bottom-nav-item {{ request()->routeIs('commercial.dashboard') && !request()->has('action') ? 'active' : '' }}">
        <i data-feather="layout"></i>
        <span>Tableau de bord</span>
    </a>
    <a href="{{ route('commercial.portefeuille') }}" class="bottom-nav-item {{ request()->routeIs('commercial.portefeuille') ? 'active' : '' }}">
        <i data-feather="briefcase"></i>
        <span>Portefeuille</span>
    </a>
    <a href="{{ route('commercial.prospects') }}" class="bottom-nav-item {{ request()->routeIs('commercial.prospects') ? 'active' : '' }}">
        <i data-feather="target"></i>
        <span>Prospects</span>
    </a>
    <a href="#" class="bottom-nav-item js-sidebar-toggle">
        <i data-feather="menu"></i>
        <span>Menu</span>
    </a>
</nav>
