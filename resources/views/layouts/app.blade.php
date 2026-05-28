<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="Tableau de bord {{ config('app.name') }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="icon" type="image/png" href="{{ asset('images/sitiam.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/sitiam.png') }}">

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="{{ asset('css/adminkit-app.css') }}" rel="stylesheet">
    @stack('styles')
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-brand span { font-weight: 600; }
        .navbar-bg { background-color: #f8f9fa; }
        .footer { background-color: #fff; }
        .global-toast-container {
            z-index: 1080;
            pointer-events: none;
        }
        .global-toast-container .toast {
            pointer-events: auto;
        }
        .toast-notification {
            min-width: 320px;
            max-width: 420px;
            border: 1px solid #e9ecef;
            border-left-width: 4px;
            border-radius: .55rem;
            overflow: hidden;
            background: #fff;
            backdrop-filter: blur(2px);
            transform: translateY(-8px);
            opacity: 0;
            transition: transform .22s ease, opacity .22s ease;
        }
        .toast-notification.show,
        .toast-notification.showing {
            transform: translateY(0);
            opacity: 1;
        }
        .toast-notification .toast-header {
            border-bottom: 1px solid #f1f3f5;
        }
        .toast-notification .toast-body {
            color: #495057;
            font-size: .92rem;
        }
        .toast-close-btn {
            border-radius: 999px;
            padding: .35rem;
            transition: background-color .15s ease, box-shadow .15s ease;
        }
        .toast-close-btn:hover {
            background-color: #f1f3f5;
            box-shadow: 0 0 0 1px #dee2e6 inset;
        }
        .toast-close-btn span {
            display: inline-block;
            font-size: 1rem;
            line-height: 1;
            color: #495057;
            margin-top: -1px;
        }
        .toast-notification-success { border-left-color: #198754; }
        .toast-notification-warning { border-left-color: #f59f00; }
        .toast-notification-info { border-left-color: #0dcaf0; }
        .toast-notification-error { border-left-color: #dc3545; }
        .toast-progress {
            height: 3px;
            width: 100%;
            background: rgba(0, 0, 0, .04);
        }
        .toast-progress-bar {
            height: 100%;
            width: 100%;
            transform-origin: left center;
            transition-property: width;
            transition-timing-function: linear;
            transition-duration: var(--toast-delay, 4500ms);
        }
        .toast-notification-success .toast-progress-bar { background: #198754; }
        .toast-notification-warning .toast-progress-bar { background: #f59f00; }
        .toast-notification-info .toast-progress-bar { background: #0dcaf0; }
        .toast-notification-error .toast-progress-bar { background: #dc3545; }
        .gt_float_switcher {
            display: none !important;
        }
        .gtranslate-global-host {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            overflow: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        /* ===== ADMINKIT EXACT SIDEBAR DESIGN ===== */

        /* SIDEBAR CONTAINER - AdminKit Exact */
        .sidebar {
            min-width: 260px;
            max-width: 260px;
            direction: ltr;
            background: #222e3c;
            transition: margin-left .35s ease-in-out, left .35s ease-in-out,
                        margin-right .35s ease-in-out, right .35s ease-in-out;
        }

        /* SIDEBAR CONTENT WRAPPER - AdminKit Exact */
        .sidebar-content {
            display: flex;
            height: 100vh;
            flex-direction: column;
            background: #222e3c;
            overflow: hidden;
            transition: margin-left .35s ease-in-out, left .35s ease-in-out,
                        margin-right .35s ease-in-out, right .35s ease-in-out;
        }

        /* Force un scroll vertical fiable dans le menu latéral, même avec les sections dépliées. */
        .sidebar .simplebar-content-wrapper {
            overflow-y: auto !important;
            overflow-x: hidden !important;
            max-height: 100vh;
        }

        /* Espace en bas pour éviter que le dernier item colle au bord. */
        .sidebar-nav {
            padding-bottom: 1rem;
        }

        /* SIDEBAR NAVIGATION LIST - AdminKit Exact */
        .sidebar-nav {
            padding-left: 0;
            margin-bottom: 0;
            list-style: none;
            flex-grow: 1;
        }

        /* SIDEBAR BRAND (LOGO) - AdminKit Exact */
        .sidebar-brand {
            display: block;
            padding: 1.15rem 1.5rem;
            font-weight: 600;
            font-size: 1.15rem;
            color: #f8f9fa;
            text-decoration: none;
        }

        .sidebar-brand:hover {
            text-decoration: none;
            color: #f8f9fa;
        }

        .sidebar-brand:focus {
            outline: 0;
        }

        /* SIDEBAR HEADER (SECTION LABELS) - AdminKit Exact */
        .sidebar-header {
            background: transparent;
            padding: 1.5rem 1.5rem 0.375rem 1.5rem;
            font-size: 0.75rem;
            color: #ced4da;
            font-weight: 400;
            text-transform: none;
        }

        /* SIDEBAR LINKS - DEFAULT STATE - AdminKit Exact */
        .sidebar-link,
        a.sidebar-link {
            display: block;
            padding: 0.625rem 1.625rem;
            font-weight: 400;
            color: rgba(233, 236, 239, 0.5);
            background: #222e3c;
            border-left: 3px solid transparent;
            cursor: pointer;
            position: relative;
            text-decoration: none;
            transition: background 0.1s ease-in-out;
        }

        /* Icons in sidebar links - AdminKit Exact */
        .sidebar-link i,
        .sidebar-link svg,
        a.sidebar-link i,
        a.sidebar-link svg {
            margin-right: 0.75rem;
            color: rgba(233, 236, 239, 0.5);
        }

        /* SIDEBAR LINKS - FOCUS STATE - AdminKit Exact */
        .sidebar-link:focus {
            outline: 0;
        }

        /* SIDEBAR LINKS - HOVER STATE - AdminKit Exact */
        .sidebar-link:hover {
            color: rgba(233, 236, 239, 0.75);
            background: #222e3c;
            border-left-color: transparent;
        }

        .sidebar-link:hover i,
        .sidebar-link:hover svg {
            color: rgba(233, 236, 239, 0.75);
        }

        /* SIDEBAR ITEMS - ACTIVE STATE - AdminKit Exact */
        .sidebar-item.active > .sidebar-link,
        .sidebar-item.active .sidebar-link:hover {
            background: linear-gradient(90deg,
                        rgba(59, 125, 221, 0.1) 0%,
                        rgba(59, 125, 221, 0.0875) 50%,
                        transparent 100%);
            border-left-color: #3b7ddd;
            color: #e9ecef;
        }

        .sidebar-item.active > .sidebar-link i,
        .sidebar-item.active > .sidebar-link svg,
        .sidebar-item.active .sidebar-link:hover i,
        .sidebar-item.active .sidebar-link:hover svg {
            color: #e9ecef;
        }

        /* COLLAPSE/SUBMENU HANDLING - AdminKit Exact */
        .sidebar-item > .collapse {
            background: transparent;
            border: none;
            border-radius: 0;
            margin-top: 0;
            overflow: visible;
            box-shadow: none;
            padding-left: 0;
        }

        /* SUBMENU NAVIGATION - AdminKit Exact */
        .sidebar-nav-sub {
            padding: 0;
            margin: 0;
            list-style: none;
        }

        /* SUBMENU ITEMS - AdminKit Exact */
        .sidebar-nav-sub .sidebar-item {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        /* SUBMENU LINKS - AdminKit Exact (same as main links) */
        .sidebar-nav-sub .sidebar-link {
            padding: 0.625rem 1.625rem;
            font-weight: 400;
            color: rgba(233, 236, 239, 0.5);
            background: #222e3c;
            border-left: 3px solid transparent;
            transition: background 0.1s ease-in-out;
        }

        .sidebar-nav-sub .sidebar-link:hover {
            color: rgba(233, 236, 239, 0.75);
            background: #222e3c;
            border-left-color: transparent;
        }

        .sidebar-nav-sub .sidebar-item.active > .sidebar-link {
            background: linear-gradient(90deg,
                        rgba(59, 125, 221, 0.1) 0%,
                        rgba(59, 125, 221, 0.0875) 50%,
                        transparent 100%);
            border-left-color: #3b7ddd;
            color: #e9ecef;
        }

        /* CHEVRON FOR COLLAPSE - AdminKit Exact */
        .sidebar-link[data-bs-toggle="collapse"]::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-left: auto;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='rgba(233,236,239,0.5)' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 7l3 3 3-3'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-size: 12px;
            transition: transform 0.15s ease;
            flex-shrink: 0;
        }

        .sidebar-link[data-bs-toggle="collapse"]:not(.collapsed)::after {
            transform: rotate(180deg);
        }

        /* BADGES - AdminKit Exact */
        .sidebar-link .badge {
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            font-size: 0.6875rem;
            border-radius: 0.25rem;
            margin-left: auto;
            background-color: rgba(59, 125, 221, 0.2);
            color: #3b7ddd;
        }

        /* DIVIDER - AdminKit Exact */
        .sidebar-divider {
            border-top: 1px solid rgba(233, 236, 239, 0.1);
            margin: 0.25rem 1rem;
            height: 1px;
        }

        /* EMOJI ICONS - Custom but AdminKit compatible */
        .icon-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            height: 16px;
            font-size: 12px;
            margin-right: 0.75rem;
            flex-shrink: 0;
            opacity: 0.7;
        }

        /* CATEGORY HEADERS - Custom but AdminKit compatible */
        .sidebar-item-category {
            padding: 1.5rem 1.5rem 0.375rem 1.5rem;
            font-size: 0.75rem;
            color: #ced4da;
            font-weight: 400;
            text-transform: none;
            margin: 0;
            display: block;
        }

        .sidebar-item-category:first-child {
            margin-top: 0;
        }

        .sidebar-nav-sub .sidebar-link.active:hover {
            background-color: #dbeafe;
            color: #1d4ed8;
        }

        @media print {
            body * { visibility: hidden; }
            .content, .content * { visibility: visible; }
            .content { position: absolute; top: 0; left: 0; width: 100%; }
            .no-print { display: none !important; }
            .sidebar, .navbar, .footer { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="gtranslate_wrapper gtranslate-global-host" aria-hidden="true"></div>
    <div class="wrapper">
        @include('layouts.partials.sidebar')

        <div class="main">
            <nav class="navbar navbar-expand navbar-light navbar-bg">
                <a class="sidebar-toggle js-sidebar-toggle">
                    <i class="hamburger align-self-center"></i>
                </a>

                <div class="navbar-collapse collapse d-flex flex-wrap align-items-center">
                    @auth
                        @if(auth()->user()?->is_platform_admin)
                            <ul class="navbar-nav me-auto align-items-center order-0">
                                @include('layouts.partials.nav-admin-app-menu-dropdown')
                            </ul>
                        @endif
                    @endauth
                    <ul class="navbar-nav navbar-align ms-auto order-1">
                        @php
                            $topbarNotifications = $topbarNotifications ?? collect();
                            $unreadNotificationsCount = $unreadNotificationsCount ?? 0;
                            $topbarSupportTickets = $topbarSupportTickets ?? collect();
                            $openSupportTicketsCount = $openSupportTicketsCount ?? 0;
                        @endphp
                        <li class="nav-item dropdown">
                            <a class="nav-icon dropdown-toggle" href="#" id="alertsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="position-relative">
                                    <i class="align-middle" data-feather="bell"></i>
                                    @if($unreadNotificationsCount > 0)
                                        <span class="indicator">{{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}</span>
                                    @endif
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="alertsDropdown">
                                <div class="dropdown-menu-header">Notifications @if($unreadNotificationsCount > 0)({{ $unreadNotificationsCount }} non lues)@endif</div>
                                <div class="list-group list-group-flush">
                                    @forelse($topbarNotifications as $n)
                                        <a href="{{ route('notifications.go', $n) }}" class="list-group-item list-group-item-action {{ $n->read_at ? '' : 'bg-light' }}">
                                            <div class="d-flex align-items-start gap-2">
                                                <div class="flex-shrink-0 mt-1">
                                                    @if(($n->type ?? '') === 'warning')
                                                        <i class="text-warning" data-feather="alert-triangle"></i>
                                                    @elseif(($n->type ?? '') === 'success')
                                                        <i class="text-success" data-feather="check-circle"></i>
                                                    @else
                                                        <i class="text-primary" data-feather="info"></i>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1 min-w-0">
                                                    <div class="text-dark fw-semibold small">{{ \Illuminate\Support\Str::limit($n->title, 80) }}</div>
                                                    @if($n->body)
                                                        <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($n->body, 120) }}</div>
                                                    @endif
                                                    <div class="text-muted small mt-1">{{ $n->created_at?->diffForHumans() }}</div>
                                                </div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="list-group-item text-muted small py-4 text-center">Aucune notification.</div>
                                    @endforelse
                                </div>
                                <div class="dropdown-menu-footer">
                                    <a class="text-muted" href="{{ route('notifications.index') }}">Voir toutes les notifications</a>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-icon dropdown-toggle" href="#" id="messagesDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="position-relative">
                                    <i class="align-middle" data-feather="message-square"></i>
                                    @if($openSupportTicketsCount > 0)
                                        <span class="indicator">{{ $openSupportTicketsCount > 9 ? '9+' : $openSupportTicketsCount }}</span>
                                    @endif
                                </div>
                            </a>
                            <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-0" aria-labelledby="messagesDropdown">
                                <div class="dropdown-menu-header">Support @if($openSupportTicketsCount > 0)({{ $openSupportTicketsCount }} ouvert(s))@endif</div>
                                <div class="list-group list-group-flush">
                                    @forelse($topbarSupportTickets as $t)
                                        <a href="{{ route('support.tickets.show', $t) }}" class="list-group-item list-group-item-action">
                                            <div class="text-dark fw-semibold small">{{ \Illuminate\Support\Str::limit($t->subject, 55) }}</div>
                                            @if($t->latestMessage)
                                                <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($t->latestMessage->body, 90) }}</div>
                                            @endif
                                            <div class="text-muted small mt-1">{{ $t->updated_at?->diffForHumans() }}</div>
                                        </a>
                                    @empty
                                        <div class="list-group-item text-muted small py-4 text-center">Aucun fil support. <a href="{{ route('support.tickets.create') }}">Écrire au support</a></div>
                                    @endforelse
                                </div>
                                <div class="dropdown-menu-footer d-flex justify-content-between gap-2 px-2 py-2">
                                    <a class="text-muted small" href="{{ route('support.tickets') }}">Toutes les demandes</a>
                                    <a class="text-muted small" href="{{ route('support.index') }}">Aide</a>
                                </div>
                            </div>
                        </li>
                        <li class="nav-item dropdown">
                            @php
                                $topbarUser = Auth::user();
                                if ($topbarUser && $topbarUser->is_platform_admin) {
                                    $topbarPremiumActive = false;
                                    $topbarPremiumBadge = 'bg-primary';
                                    $topbarPremiumIcon = '🛡️';
                                    $topbarPremiumLabel = 'Administrateur';
                                    $topbarShowExpiry = false;
                                } else {
                                    $topbarPremiumActive = $topbarUser
                                        && ($topbarUser->is_premium ?? false)
                                        && (empty($topbarUser->premium_ends_at) || $topbarUser->premium_ends_at->isFuture());
                                    $topbarPremiumBadge = $topbarPremiumActive ? 'bg-warning text-dark' : 'bg-success';
                                    $topbarPremiumIcon = $topbarPremiumActive ? '⭐' : '🟢';
                                    $topbarPremiumLabel = $topbarPremiumActive ? 'Premium' : 'Gratuit';
                                    $topbarShowExpiry = true;
                                }
                            @endphp
                            <a class="nav-link dropdown-toggle d-none d-sm-inline-block" href="#" data-bs-toggle="dropdown">
                                <img src="{{ Auth::user()->avatar ? asset('storage/' . Auth::user()->avatar) : 'https://via.placeholder.com/40' }}" class="avatar img-fluid rounded me-1" alt="{{ Auth::user()->name }}" /> <span class="text-dark">{{ explode(' ', Auth::user()->name)[0] }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <div class="dropdown-item-text small text-muted">
                                    Abonnement: <span class="badge {{ $topbarPremiumBadge }}">{{ $topbarPremiumIcon }} {{ $topbarPremiumLabel }}</span>
                                    @if($topbarShowExpiry && $topbarPremiumActive && !empty($topbarUser->premium_ends_at))
                                        <div>Validité: {{ $topbarUser->premium_ends_at->format('d/m/Y H:i') }}</div>
                                    @endif
                                </div>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="{{ route('profile') }}"><i class="align-middle me-1" data-feather="user"></i> Profil</a>
                                <a class="dropdown-item" href="{{ route('profile') }}"><i class="align-middle me-1" data-feather="settings"></i> Paramètres</a>
                                <a class="dropdown-item" href="{{ route('subscriptions.history') }}"><i class="align-middle me-1" data-feather="clock"></i> Historique abonnements</a>
                                <a class="dropdown-item" href="{{ route('notifications.index') }}"><i class="align-middle me-1" data-feather="bell"></i> Notifications</a>
                                <a class="dropdown-item" href="{{ route('support.index') }}"><i class="align-middle me-1" data-feather="help-circle"></i> Aide & support</a>
                                <div class="dropdown-divider"></div>
                                <form action="{{ route('logout') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="align-middle me-1" data-feather="log-out"></i> Déconnexion</button>
                                </form>
                            </div>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="content">
                <div class="container-fluid p-0">
                    @auth
                        @php
                            $workspaceBannerUser = auth()->user();
                            $workspaceShow = \App\Support\ClientWorkspace::isViewingClient();
                            $workspaceTarget = $workspaceShow ? \App\Support\ClientWorkspace::workspaceTarget() : null;
                            $workspaceModeLabel = ($workspaceBannerUser->is_platform_admin ?? false)
                                ? 'Administrateur plateforme'
                                : (($workspaceBannerUser->is_accountant ?? false) ? 'Comptable cabinet' : '');
                        @endphp
                        @if($workspaceShow && $workspaceTarget)
                            <div class="alert alert-info border-0 rounded-0 mb-0 d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 px-3">
                                <span class="small">
                                    @if($workspaceModeLabel !== '')
                                        <span class="badge bg-dark me-1">{{ $workspaceModeLabel }}</span>
                                    @endif
                                    <strong>Dossier ouvert :</strong>
                                    {{ $workspaceTarget->company_name ?: $workspaceTarget->name }}
                                    <span class="text-muted">({{ $workspaceTarget->email }})</span>
                                </span>
                                <form method="post" action="{{ route('accountant.workspace.clear') }}" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-dark">Quitter le dossier</button>
                                </form>
                            </div>
                        @endif
                    @endauth
                    @php
                        $popupMessage = null;
                        $popupTitle = null;
                        $popupType = 'success';

                        if (session('error')) {
                            $popupMessage = (string) session('error');
                            $popupTitle = 'Erreur';
                            $popupType = 'error';
                        } elseif (session('warning')) {
                            $popupMessage = (string) session('warning');
                            $popupTitle = 'Attention';
                            $popupType = 'warning';
                        } elseif (session('info')) {
                            $popupMessage = (string) session('info');
                            $popupTitle = 'Information';
                            $popupType = 'info';
                        } elseif (session('success')) {
                            $popupMessage = (string) session('success');
                            $popupTitle = 'Succès';
                            $popupType = 'success';
                        } elseif (session('status')) {
                            $popupMessage = (string) session('status');
                            if (session('ocr_retry_error') || session('ocr_reset')) {
                                $popupTitle = 'Information OCR';
                                $popupType = 'warning';
                            } else {
                                $popupTitle = 'Succès';
                                $popupType = 'success';
                            }
                        } elseif ($errors->any()) {
                            $popupMessage = (string) $errors->first();
                            $popupTitle = 'Erreur';
                            $popupType = 'error';
                        }

                        $popupAccentClass = match ($popupType) {
                            'error' => 'border-danger',
                            'warning' => 'border-warning',
                            'info' => 'border-info',
                            default => 'border-success',
                        };
                        $popupIconClass = match ($popupType) {
                            'error' => 'text-danger',
                            'warning' => 'text-warning',
                            'info' => 'text-info',
                            default => 'text-success',
                        };
                        $popupFeatherIcon = match ($popupType) {
                            'error' => 'x-circle',
                            'warning' => 'alert-triangle',
                            'info' => 'info',
                            default => 'check-circle',
                        };
                        $popupFallbackAlertClass = match ($popupType) {
                            'error' => 'danger',
                            'warning' => 'warning',
                            'info' => 'info',
                            default => 'success',
                        };
                        $popupAutoHideDelay = in_array($popupType, ['error', 'warning'], true) ? 7000 : 4500;
                    @endphp

                    @if($popupMessage)
                        <div class="toast-container position-fixed top-0 end-0 p-3 global-toast-container">
                            <div id="globalFeedbackToast"
                                 class="toast toast-notification toast-notification-{{ $popupType }} border-2 {{ $popupAccentClass }} shadow-sm"
                                 role="alert"
                                 aria-live="assertive"
                                 aria-atomic="true"
                                 data-bs-autohide="false"
                                 data-toast-delay="{{ $popupAutoHideDelay }}"
                                 style="--toast-delay: {{ $popupAutoHideDelay }}ms;">
                                <div class="toast-header bg-white">
                                    <i data-feather="{{ $popupFeatherIcon }}" class="me-2 {{ $popupIconClass }}" style="width: 16px; height: 16px;"></i>
                                    <strong class="me-auto">{{ $popupTitle }}</strong>
                                    <small class="text-muted">maintenant</small>
                                    <button type="button" class="btn btn-sm btn-light ms-2 mb-1 toast-close-btn" data-bs-dismiss="toast" aria-label="Fermer la notification">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="toast-body bg-white">
                                    {{ $popupMessage }}
                                </div>
                                <div class="toast-progress">
                                    <div class="toast-progress-bar"></div>
                                </div>
                            </div>
                        </div>

                        <div id="globalFeedbackFallback"
                             class="alert alert-{{ $popupFallbackAlertClass }} m-3 d-none"
                             role="alert">
                            {{ $popupMessage }}
                        </div>
                    @endif

                    @yield('content')
                </div>
            </main>

            <footer class="footer">
                <div class="container-fluid">
                    <div class="row text-muted align-items-center">
                        <div class="col-md-6 text-start">
                            <p class="mb-0">
                                <strong>{{ config('app.name') }}</strong>
                                &middot; v{{ config('app.version') }}
                                &middot; &copy; {{ date('Y') }}
                            </p>
                            <p class="mb-0 small mt-1">
                                Interface basée sur <a class="text-muted" href="https://adminkit.io/" target="_blank" rel="noopener">AdminKit</a>
                            </p>
                        </div>
                        <div class="col-md-6 text-md-end mt-2 mt-md-0">
                            <ul class="list-inline mb-0 small">
                                <li class="list-inline-item"><a class="text-muted" href="https://adminkit.io/" target="_blank" rel="noopener">Documentation AdminKit</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @auth
        @include('layouts.partials.admin-global-chatbot')
    @endauth

    @include('partials.webcam-modal')
    <script src="{{ asset('js/adminkit-app.js') }}"></script>
    <script src="{{ asset('js/webcam-capture.js') }}" defer></script>
    <script>
        (function () {
            var supported = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'it', 'nl'];
            var preferred = localStorage.getItem('preferred_locale') || "{{ app()->getLocale() }}";
            if (!supported.includes(preferred)) {
                preferred = 'fr';
            }

            // GTranslate/Google Translate relies on this cookie to persist target language.
            document.cookie = "googtrans=/fr/" + preferred + ";path=/";
            document.cookie = "googtrans=/auto/" + preferred + ";path=/";

            window.gtranslateSettings = {
                default_language: "fr",
                native_language_names: true,
                detect_browser_language: false,
                url_structure: "none",
                languages: supported,
                wrapper_selector: ".gtranslate_wrapper"
            };
        })();
    </script>
    <script src="https://cdn.gtranslate.net/widgets/latest/dropdown.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.feather) {
                window.feather.replace();
            }
            document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
                el.addEventListener('shown.bs.dropdown', function () {
                    if (window.feather) {
                        window.feather.replace();
                    }
                });
            });

            const feedbackToast = document.getElementById('globalFeedbackToast');
            if (feedbackToast) {
                if (window.bootstrap && window.bootstrap.Toast) {
                    const toast = new window.bootstrap.Toast(feedbackToast);
                    const progressBar = feedbackToast.querySelector('.toast-progress-bar');
                    const progressContainer = feedbackToast.querySelector('.toast-progress');
                    const configuredDelay = parseInt(feedbackToast.getAttribute('data-toast-delay') || '4500', 10);
                    let remainingDelay = Number.isFinite(configuredDelay) ? configuredDelay : 4500;
                    let timerId = null;
                    let startedAt = 0;

                    const readProgressPercent = function () {
                        if (!progressBar || !progressContainer) return 0;
                        const containerWidth = progressContainer.getBoundingClientRect().width;
                        if (!containerWidth) return 0;
                        const barWidth = progressBar.getBoundingClientRect().width;
                        return Math.max(0, Math.min(100, (barWidth / containerWidth) * 100));
                    };

                    const setProgress = function (percent, durationMs) {
                        if (!progressBar) return;
                        progressBar.style.transitionDuration = '0ms';
                        progressBar.style.width = percent + '%';
                        void progressBar.offsetWidth;
                        progressBar.style.transitionDuration = durationMs + 'ms';
                        progressBar.style.width = '0%';
                    };

                    const stopAutoHide = function () {
                        if (!timerId) return;
                        clearTimeout(timerId);
                        timerId = null;
                        const elapsed = Date.now() - startedAt;
                        remainingDelay = Math.max(0, remainingDelay - elapsed);
                        const currentPercent = readProgressPercent();
                        if (progressBar) {
                            progressBar.style.transitionDuration = '0ms';
                            progressBar.style.width = currentPercent + '%';
                        }
                    };

                    const startAutoHide = function () {
                        if (remainingDelay <= 0) {
                            toast.hide();
                            return;
                        }
                        startedAt = Date.now();
                        const currentPercent = readProgressPercent() || 100;
                        setProgress(currentPercent, remainingDelay);
                        timerId = window.setTimeout(function () {
                            toast.hide();
                        }, remainingDelay);
                    };

                    feedbackToast.addEventListener('mouseenter', stopAutoHide);
                    feedbackToast.addEventListener('mouseleave', startAutoHide);
                    feedbackToast.addEventListener('focusin', stopAutoHide);
                    feedbackToast.addEventListener('focusout', startAutoHide);
                    feedbackToast.addEventListener('hidden.bs.toast', function () {
                        if (timerId) {
                            clearTimeout(timerId);
                            timerId = null;
                        }
                    });

                    toast.show();
                    window.setTimeout(startAutoHide, 60);
                } else {
                    const fallback = document.getElementById('globalFeedbackFallback');
                    if (fallback) {
                        fallback.classList.remove('d-none');
                    }
                }
            }
        });
    </script>
    @stack('scripts')
</body>
</html>
