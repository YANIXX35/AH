<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        @php
            $sidebarUser = auth()->user();
            if ($sidebarUser && $sidebarUser->is_platform_admin) {
                $sidebarPremiumLabel = 'Administrateur';
                $sidebarPremiumBadge = 'bg-primary';
                $sidebarPremiumIcon = '🛡️';
                $sidebarPremiumActive = false;
                $sidebarShowPremiumExpiry = false;
            } elseif ($sidebarUser && ($sidebarUser->is_accountant ?? false)) {
                $sidebarPremiumLabel = 'Comptable';
                $sidebarPremiumBadge = 'bg-info text-dark';
                $sidebarPremiumIcon = '📒';
                $sidebarPremiumActive = false;
                $sidebarShowPremiumExpiry = false;
            } else {
                $sidebarPremiumActive = $sidebarUser
                    && ($sidebarUser->is_premium ?? false)
                    && (empty($sidebarUser->premium_ends_at) || $sidebarUser->premium_ends_at->isFuture());
                $sidebarPremiumLabel = $sidebarPremiumActive ? 'Premium' : 'Gratuit';
                $sidebarPremiumBadge = $sidebarPremiumActive ? 'bg-warning text-dark' : 'bg-success';
                $sidebarPremiumIcon = $sidebarPremiumActive ? '⭐' : '🟢';
                $sidebarShowPremiumExpiry = true;
            }
            $sidebarAccountantOnly = $sidebarUser && ($sidebarUser->is_accountant ?? false) && ! ($sidebarUser->is_platform_admin ?? false);
            $sidebarWorkspaceOpen = $sidebarAccountantOnly && \App\Support\ClientWorkspace::isViewingClient();
        @endphp
        <a class="sidebar-brand" href="{{ $sidebarAccountantOnly ? route('accountant.dashboard') : route('dashboard') }}">
            <span class="align-middle">Sitiame Capitale</span>
        </a>
        <div class="px-3 pb-2">
            <span class="badge {{ $sidebarPremiumBadge }}">{{ $sidebarPremiumIcon }} {{ $sidebarPremiumLabel }}</span>
            @if($sidebarShowPremiumExpiry && $sidebarPremiumActive && !empty($sidebarUser->premium_ends_at))
                <small class="d-block text-muted mt-1">Valide jusqu'au {{ $sidebarUser->premium_ends_at->format('d/m/Y') }}</small>
            @endif
        </div>

        <ul class="sidebar-nav">
            @if($sidebarUser && (($sidebarUser->is_accountant ?? false) || $sidebarUser->is_platform_admin))
                {{-- Cabinet comptable : dossiers clients et synthèse. --}}
                <li class="sidebar-item {{ request()->routeIs('accountant.*') ? 'active' : '' }}">
                    <a class="sidebar-link {{ request()->routeIs('accountant.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseAccountant" role="button" aria-expanded="{{ request()->routeIs('accountant.*') ? 'true' : 'false' }}" aria-controls="collapseAccountant">
                        <i class="align-middle text-info" data-feather="briefcase" style="width:18px;height:18px;"></i>
                        <span class="align-middle ms-1">Cabinet comptable</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('accountant.*') ? 'show' : '' }}" id="collapseAccountant">
                        <ul class="sidebar-nav sidebar-nav-sub">
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.dashboard') ? 'active' : '' }}" href="{{ route('accountant.dashboard') }}">Tableau de bord</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.clients.*') ? 'active' : '' }}" href="{{ route('accountant.clients.index') }}">Dossiers clients</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            @if($sidebarUser && $sidebarUser->is_platform_admin)
                {{-- Administration : tableau de bord, annuaire, analyse financière PME. --}}
                <li class="sidebar-item {{ request()->routeIs('admin.*') ? 'active' : '' }}">
                    <a class="sidebar-link {{ request()->routeIs('admin.*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseAdmin" role="button" aria-expanded="{{ request()->routeIs('admin.*') ? 'true' : 'false' }}" aria-controls="collapseAdmin">
                        <i class="align-middle text-primary" data-feather="shield" style="width:18px;height:18px;"></i>
                        <span class="align-middle ms-1">Administration</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.*') ? 'show' : '' }}" id="collapseAdmin">
                        <ul class="sidebar-nav sidebar-nav-sub">
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Tableau de bord admin</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}">Utilisateurs &amp; entreprises</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.licenses.*') ? 'active' : '' }}" href="{{ route('admin.licenses.index') }}">Licences entreprise</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}" href="{{ route('admin.payments.index') }}">Gestion paiements</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">Journalisation plateforme</a>
                            </li>

                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.financial-analysis') ? 'active' : '' }}" href="{{ route('admin.financial-analysis') }}">Analyse financière PME</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.financial-ranking') ? 'active' : '' }}" href="{{ route('admin.financial-ranking') }}">Classement solvabilité</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.investment-requests.*') ? 'active' : '' }}" href="{{ route('admin.investment-requests.index') }}">Demandes d’investissement</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            @php
                // Administrateur plateforme : menu « Pages » remplacé par le déroulant « Espace métier » (navbar).
                $sidebarAdminCompactNav = (bool) ($sidebarUser?->is_platform_admin ?? false);
                $sidebarAccountingLocked = $sidebarUser
                    && ! $sidebarUser->isPlatformAdmin()
                    && ! $sidebarUser->isAccountant()
                    && ! $sidebarUser->hasActivePremiumPeriod();
                $sidebarTeamLocked = $sidebarUser
                    && ! $sidebarUser->isPlatformAdmin()
                    && ! $sidebarUser->isAccountant()
                    && ! $sidebarUser->hasActivePremiumPeriod();
            @endphp
            @if(! $sidebarAccountantOnly)
            @unless($sidebarAdminCompactNav)
            <li class="sidebar-header">Pages</li>
            <li class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('dashboard') }}">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>
            @if($sidebarAccountingLocked)
                <li class="sidebar-item opacity-50">
                    <a class="sidebar-link" href="{{ route('profile') }}" title="Passez en Premium pour activer la comptabilité.">
                        <span class="icon-wrapper">🔒</span> <span class="align-middle">Comptabilité</span>
                        <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                    </a>
                </li>
            @else
                <li class="sidebar-item {{ request()->routeIs('accounting*') ? 'active' : '' }}">
                    <a class="sidebar-link {{ request()->routeIs('accounting*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseAccounting" role="button" aria-expanded="{{ request()->routeIs('accounting*') ? 'true' : 'false' }}" aria-controls="collapseAccounting">
                        <span class="icon-wrapper">📚</span> <span class="align-middle">Comptabilité</span>
                        <span class="badge bg-primary rounded-pill ms-auto">10</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('accounting*') ? 'show' : '' }}" id="collapseAccounting">
                        <ul class="sidebar-nav sidebar-nav-sub">
                            <li class="sidebar-item-category">Saisie des données</li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting') && !request()->routeIs('accounting.*') ? 'active' : '' }}" href="{{ route('accounting') }}">
                                    <span class="icon-wrapper">✍️</span>
                                    <span class="align-middle">Gestion des écritures</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.documents') ? 'active' : '' }}" href="{{ route('accounting.documents') }}">
                                    <span class="icon-wrapper">📄</span>
                                    <span class="align-middle">Gestion des documents</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.plan') ? 'active' : '' }}" href="{{ route('accounting.plan') }}">
                                    <span class="icon-wrapper">📋</span>
                                    <span class="align-middle">Plan comptable OHADA</span>
                                </a>
                            </li>
                            <li class="sidebar-item-category">Moteur comptable</li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.bank-reconciliation') ? 'active' : '' }}" href="{{ route('accounting.bank-reconciliation') }}">
                                    <span class="icon-wrapper">🏦</span>
                                    <span class="align-middle">Rapprochement bancaire</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.monthly-closing') ? 'active' : '' }}" href="{{ route('accounting.monthly-closing') }}">
                                    <span class="icon-wrapper">📅</span>
                                    <span class="align-middle">Clôture mensuelle</span>
                                </a>
                            </li>
                            <li class="sidebar-divider"></li>
                            <li class="sidebar-item-category">Rapports et analyses</li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.report.journal') ? 'active' : '' }}" href="{{ route('accounting.report.journal') }}">
                                    <span class="icon-wrapper">📖</span>
                                    <span class="align-middle">Journal</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.report.grand-livre') ? 'active' : '' }}" href="{{ route('accounting.report.grand-livre') }}">
                                    <span class="icon-wrapper">📑</span>
                                    <span class="align-middle">Grand livre</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.report.balance') ? 'active' : '' }}" href="{{ route('accounting.report.balance') }}">
                                    <span class="icon-wrapper">⚖️</span>
                                    <span class="align-middle">Balance</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.report.bilan') ? 'active' : '' }}" href="{{ route('accounting.report.bilan') }}">
                                    <span class="icon-wrapper">🔶</span>
                                    <span class="align-middle">Bilan simplifié</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.report.resultat') ? 'active' : '' }}" href="{{ route('accounting.report.resultat') }}">
                                    <span class="icon-wrapper">📊</span>
                                    <span class="align-middle">Compte de résultat</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            <li class="sidebar-item {{ request()->routeIs('treasury*') ? 'active' : '' }}">
                <a class="sidebar-link {{ request()->routeIs('treasury*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseTreasury" role="button" aria-expanded="{{ request()->routeIs('treasury*') ? 'true' : 'false' }}" aria-controls="collapseTreasury">
                    <span class="icon-wrapper">💰</span> <span class="align-middle">Trésorerie</span>
                    <span class="badge bg-success rounded-pill ms-auto">4</span>
                </a>
                <div class="collapse {{ request()->routeIs('treasury*') ? 'show' : '' }}" id="collapseTreasury">
                    <ul class="sidebar-nav sidebar-nav-sub">
                        <li class="sidebar-item-category">Vue d'ensemble</li>
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('treasury.tracking') ? 'active' : '' }}" href="{{ route('treasury.tracking') }}">
                                <span class="icon-wrapper">📊</span>
                                <span class="align-middle">Tableau de bord</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('treasury.balance') ? 'active' : '' }}" href="{{ route('treasury.balance') }}">
                                <span class="icon-wrapper">💳</span>
                                <span class="align-middle">Solde de trésorerie</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('treasury.forecast') ? 'active' : '' }}" href="{{ route('treasury.forecast') }}">
                                <span class="icon-wrapper">🔮</span>
                                <span class="align-middle">Prévisions</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="sidebar-item {{ request()->routeIs('investor.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('investor.readiness') }}">
                    <span class="icon-wrapper">📈</span> <span class="align-middle">Investisseurs</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('profile') || request()->routeIs('company.settings') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('company.settings') }}">
                    <i class="align-middle" data-feather="user"></i> <span class="align-middle">Profil & paramètres</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs(['profile.company.fird', 'profile.company.fird.update']) ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('profile.company.fird') }}">
                    <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Fiche entreprise (FIRD)</span>
                </a>
            </li>
            @if($sidebarUser && ! $sidebarUser->is_platform_admin && ! ($sidebarUser->is_accountant ?? false) && $sidebarUser->enterprise_license_id)
                @if($sidebarTeamLocked)
                    <li class="sidebar-item opacity-50">
                        <a class="sidebar-link" href="{{ route('payments.sandbox') }}" title="Passez en Premium pour activer l'équipe licence.">
                            <span class="icon-wrapper">🔒</span> <span class="align-middle">Équipe (licence)</span>
                            <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                        </a>
                    </li>
                    <li class="sidebar-item opacity-50">
                        <a class="sidebar-link" href="{{ route('payments.sandbox') }}" title="Passez en Premium pour activer l'historique entreprise.">
                            <span class="icon-wrapper">🔒</span> <span class="align-middle">Historique entreprise</span>
                            <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                        </a>
                    </li>
                @else
                    <li class="sidebar-item {{ request()->routeIs('profile.team*') ? 'active' : '' }}">
                        <a class="sidebar-link" href="{{ route('profile.team') }}">
                            <i class="align-middle" data-feather="users"></i> <span class="align-middle">Équipe (licence)</span>
                        </a>
                    </li>
                    <li class="sidebar-item {{ request()->routeIs('profile.team.history') ? 'active' : '' }}">
                        <a class="sidebar-link" href="{{ route('profile.team.history') }}">
                            <i class="align-middle" data-feather="list"></i> <span class="align-middle">Historique entreprise</span>
                        </a>
                    </li>
                @endif
            @endif
            <li class="sidebar-item {{ request()->routeIs('subscriptions.history') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('subscriptions.history') }}">
                    <i class="align-middle" data-feather="clock"></i> <span class="align-middle">Historique abonnements</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('payments.sandbox*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('payments.sandbox') }}">
                    <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Paiement sandbox</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('activity-log.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('activity-log.index') }}">
                    <i class="align-middle" data-feather="activity"></i> <span class="align-middle">Journal d’activité</span>
                </a>
            </li>
            @else
            <li class="sidebar-header">Espace métier</li>
            <li class="sidebar-item px-3 py-2">
                <span class="text-muted small">Menu compact : utilisez <strong class="text-body">Espace métier</strong> dans la barre supérieure (Dashboard, comptabilité, trésorerie, profil…).</span>
            </li>
            @endunless
            @else
                {{-- Comptable (sans rôle admin) : pas de menu « entreprise » générique ; outils visibles seulement avec un dossier ouvert. --}}
                @if($sidebarWorkspaceOpen)
                    <li class="sidebar-header">Dossier ouvert</li>
                    @include('layouts.partials.sidebar-accountant-dossier-tools')
                @else
                    <li class="sidebar-item px-3 py-3">
                        <span class="text-muted small">Allez dans <strong>Cabinet comptable → Dossiers clients</strong>, ouvrez une fiche puis <strong>Ouvrir — Comptabilité</strong> (ou Trésorerie, etc.) pour afficher ici les outils du <strong>client sélectionné</strong>.</span>
                    </li>
                @endif
                <li class="sidebar-header">Mon compte</li>
                <li class="sidebar-item {{ request()->routeIs('profile') || request()->routeIs('company.settings') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('company.settings') }}">
                        <i class="align-middle" data-feather="user"></i> <span class="align-middle">Profil & paramètres</span>
                    </a>
                </li>
            @endif
            <li class="sidebar-item {{ request()->routeIs(['support.*', 'notifications.*']) ? 'active' : '' }}">
                <a class="sidebar-link {{ request()->routeIs(['support.*', 'notifications.*']) ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseHelp" role="button" aria-expanded="{{ request()->routeIs(['support.*', 'notifications.*']) ? 'true' : 'false' }}" aria-controls="collapseHelp">
                    <i class="align-middle" data-feather="help-circle"></i> <span class="align-middle">Aide & support</span>
                </a>
                <div class="collapse {{ request()->routeIs(['support.*', 'notifications.*']) ? 'show' : '' }}" id="collapseHelp">
                    <ul class="sidebar-nav sidebar-nav-sub">
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('support.index') ? 'active' : '' }}" href="{{ route('support.index') }}">Centre d’aide</a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('support.tickets*') ? 'active' : '' }}" href="{{ route('support.tickets') }}">Mes demandes</a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('notifications.index') ? 'active' : '' }}" href="{{ route('notifications.index') }}">Notifications</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</nav>
