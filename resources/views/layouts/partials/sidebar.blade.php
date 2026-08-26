<nav id="sidebar" class="sidebar js-sidebar">
    <div class="sidebar-content js-simplebar">
        {{-- Bouton fermer (visible uniquement sur mobile) --}}
        <button class="sidebar-close-btn" id="sidebarCloseBtn" aria-label="Fermer le menu">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        @php
            $sidebarUser = auth()->user();
            $sidebarBrandName = config('app.name', 'Sitiame Capital');

            // Contexte d'aperçu : pour les comptes autorisés à choisir leur dashboard
            // (écran "Choisissez votre dashboard"), le sidebar doit refléter le
            // portail réellement affiché plutôt que le rôle réel du compte — sinon
            // un administrateur ouvrant par exemple le portail Commercial continue
            // de voir le menu Administration/Cabinet comptable autour, puisque tout
            // ce fichier se base normalement sur is_platform_admin/is_accountant/
            // role_key. Les variables $sidebarEff* ci-dessous portent ce rôle
            // "effectif" (celui du portail choisi si applicable, sinon le rôle réel)
            // et remplacent partout ailleurs les accès directs à ces 3 champs.
            $sidebarPreviewContext = null;
            if ($sidebarUser && \App\Http\Controllers\AuthController::isDashboardSelectorEmail($sidebarUser->email ?? null)) {
                $sidebarPreviewContextCandidate = session('dashboard_preview_context');
                if (in_array($sidebarPreviewContextCandidate, ['entreprise', 'accountant', 'commercial', 'commercial_supervisor', 'admin', 'financial_analyst'], true)) {
                    $sidebarPreviewContext = $sidebarPreviewContextCandidate;
                }
            }
            $sidebarEffIsPlatformAdmin = $sidebarPreviewContext !== null
                ? ($sidebarPreviewContext === 'admin')
                : (bool) ($sidebarUser->is_platform_admin ?? false);
            $sidebarEffIsAccountant = $sidebarPreviewContext !== null
                ? ($sidebarPreviewContext === 'accountant')
                : (bool) ($sidebarUser->is_accountant ?? false);
            $sidebarEffRoleKey = $sidebarPreviewContext !== null
                ? (in_array($sidebarPreviewContext, ['commercial', 'commercial_supervisor', 'financial_analyst'], true) ? $sidebarPreviewContext : null)
                : ($sidebarUser->role_key ?? null);

            if (
                $sidebarUser
                && ! $sidebarEffIsPlatformAdmin
                && ! $sidebarEffIsAccountant
                && $sidebarEffRoleKey !== 'commercial'
                && $sidebarEffRoleKey !== 'commercial_supervisor'
                && $sidebarEffRoleKey !== 'financial_analyst'
            ) {
                $sidebarBrandName = (string) ($sidebarUser->company_name ?: $sidebarBrandName);
            }
            $sidebarIsCommercial = $sidebarUser && $sidebarEffRoleKey === 'commercial';
            $sidebarIsCommercialSupervisor = $sidebarUser && $sidebarEffRoleKey === 'commercial_supervisor' && ! $sidebarEffIsPlatformAdmin;
            $sidebarIsFinancialAnalyst = $sidebarUser && $sidebarEffRoleKey === 'financial_analyst' && ! $sidebarEffIsPlatformAdmin;
            if ($sidebarUser && $sidebarEffIsPlatformAdmin) {
                $sidebarPremiumActive = $sidebarUser->hasActivePremiumPeriod();
                $sidebarPremiumLabel = $sidebarPremiumActive ? 'Premium Admin' : 'Administrateur';
                $sidebarPremiumBadge = $sidebarPremiumActive ? 'bg-warning text-dark' : 'bg-primary';
                $sidebarPremiumIcon = $sidebarPremiumActive ? '⭐' : '🛡️';
                $sidebarShowPremiumExpiry = $sidebarPremiumActive && ! empty($sidebarUser->premium_ends_at);
            } elseif ($sidebarUser && $sidebarEffIsAccountant) {
                $sidebarPremiumLabel = 'Comptable';
                $sidebarPremiumBadge = 'bg-info text-dark';
                $sidebarPremiumIcon = '📒';
                $sidebarPremiumActive = false;
                $sidebarShowPremiumExpiry = false;
            } elseif ($sidebarIsCommercial) {
                $sidebarPremiumLabel = 'Commercial';
                $sidebarPremiumBadge = 'bg-primary text-white';
                $sidebarPremiumIcon = '💼';
                $sidebarPremiumActive = false;
                $sidebarShowPremiumExpiry = false;
            } elseif ($sidebarIsCommercialSupervisor) {
                $sidebarPremiumLabel = 'Superviseur Commercial';
                $sidebarPremiumBadge = 'bg-info text-dark';
                $sidebarPremiumIcon = '📊';
                $sidebarPremiumActive = false;
                $sidebarShowPremiumExpiry = false;
            } elseif ($sidebarIsFinancialAnalyst) {
                $sidebarPremiumLabel = 'Analyste Financier';
                $sidebarPremiumBadge = 'bg-dark text-white';
                $sidebarPremiumIcon = '📈';
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
            $sidebarAccountantOnly = $sidebarUser && $sidebarEffIsAccountant && ! $sidebarEffIsPlatformAdmin;
            $sidebarWorkspaceOpen = $sidebarAccountantOnly && \App\Support\ClientWorkspace::isViewingClient();
        @endphp
        <a class="sidebar-brand d-flex align-items-center gap-2" href="{{ $sidebarIsCommercial ? route('commercial.dashboard') : ($sidebarIsCommercialSupervisor ? route('commercial-supervisor.dashboard') : ($sidebarIsFinancialAnalyst ? route('analyst.portfolio') : ($sidebarAccountantOnly ? route('accountant.dashboard') : route('dashboard')))) }}">
            <img src="{{ asset('images/sitiam.png') }}" alt="Logo Sitiame Capital" style="height: 28px; width: auto;">
            <span class="align-middle">{{ $sidebarBrandName }}</span>
        </a>
        <div class="px-3 pb-2">
            <span class="badge {{ $sidebarPremiumBadge }}">{{ $sidebarPremiumIcon }} {{ $sidebarPremiumLabel }}</span>
            @if($sidebarShowPremiumExpiry && $sidebarPremiumActive && !empty($sidebarUser->premium_ends_at))
                <small class="d-block text-muted mt-1">Valide jusqu'au {{ $sidebarUser->premium_ends_at->format('d/m/Y') }}</small>
            @endif
        </div>

        <ul class="sidebar-nav">
            @if($sidebarIsCommercial)
                <li class="commercial-mobile-header d-lg-none">
                    <img src="{{ ($sidebarUser->avatar) ? route('user.avatar', $sidebarUser) : asset('images/sitiam.png') }}" class="rounded-circle" width="44" height="44" alt="{{ $sidebarUser->name }}">
                    <div>
                        <div class="commercial-mobile-header-name">{{ $sidebarUser->name }}</div>
                        <span class="badge bg-primary text-white">💼 Commercial</span>
                    </div>
                </li>

                {{-- Desktop only: ces 3 sections sont déjà accessibles via la bottom nav mobile, pas besoin de les répéter ici en dessous de 992px. --}}
                <li class="sidebar-header d-none d-lg-block">Espace Commercial</li>
                <li class="sidebar-item d-none d-lg-block {{ request()->routeIs('commercial.dashboard') && !request()->has('action') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.dashboard') }}">
                        <i class="align-middle" data-feather="layout"></i> <span class="align-middle">Tableau de bord</span>
                    </a>
                </li>
                <li class="sidebar-item d-none d-lg-block {{ request()->routeIs('commercial.portefeuille') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.portefeuille') }}">
                        <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Mon Portefeuille</span>
                    </a>
                </li>
                <li class="sidebar-item d-none d-lg-block {{ request()->routeIs('commercial.prospects') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.prospects') }}">
                        <i class="align-middle" data-feather="target"></i> <span class="align-middle">Pipeline Leads CRM</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.balance') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.balance') }}">
                        <i class="align-middle" data-feather="dollar-sign"></i> <span class="align-middle">Mon Solde</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.prospections.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.prospections.index') }}">
                        <i class="align-middle" data-feather="bar-chart-2"></i> <span class="align-middle">Prospection</span>
                    </a>
                </li>

                <li class="sidebar-header">AUTRES SERVICES</li>
                <li class="sidebar-item {{ request()->routeIs('commercial.showcase') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.showcase') }}">
                        <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Offres Marketing & Service</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial.import') ? 'active' : '' }}">
                    <a class="sidebar-link text-success fw-bold" href="{{ route('commercial.import') }}">
                        <i class="align-middle text-success" data-feather="file-text"></i> <span class="align-middle">Importer & Analyser Fichier</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->get('action') === 'add-client' ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial.dashboard', ['action' => 'add-client']) }}">
                        <i class="align-middle" data-feather="user-plus"></i> <span class="align-middle">Inscrire Client / PME</span>
                    </a>
                </li>


            @elseif($sidebarIsCommercialSupervisor)
                <li class="commercial-mobile-header d-lg-none">
                    <img src="{{ ($sidebarUser->avatar) ? route('user.avatar', $sidebarUser) : asset('images/sitiam.png') }}" class="rounded-circle" width="44" height="44" alt="{{ $sidebarUser->name }}">
                    <div>
                        <div class="commercial-mobile-header-name">{{ $sidebarUser->name }}</div>
                        <span class="badge bg-info text-dark">📊 Superviseur Commercial</span>
                    </div>
                </li>

                <li class="sidebar-header">Supervision Commerciale</li>
                <li class="sidebar-item {{ request()->routeIs('commercial-supervisor.dashboard') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial-supervisor.dashboard') }}">
                        <i class="align-middle" data-feather="bar-chart-2"></i> <span class="align-middle">Tableau de bord</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial-supervisor.prospections.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial-supervisor.prospections.index') }}">
                        <i class="align-middle" data-feather="file-text"></i> <span class="align-middle">Prospections (lecture seule)</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('commercial-supervisor.prospects.*') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('commercial-supervisor.prospects.index') }}">
                        <i class="align-middle" data-feather="target"></i> <span class="align-middle">Pipeline de prospects</span>
                    </a>
                </li>
                @isset($commercial)
                    @if(request()->routeIs('commercial-supervisor.commercial.show'))
                        <li class="sidebar-header">{{ $commercial->name }}</li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="{{ url()->current() }}#commercial-portefeuille">
                                <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Portefeuille &amp; commissions</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="{{ url()->current() }}#commercial-prospections">
                                <i class="align-middle" data-feather="send"></i> <span class="align-middle">Prospections envoyées</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="{{ url()->current() }}#commercial-pipeline">
                                <i class="align-middle" data-feather="target"></i> <span class="align-middle">Pipeline de prospects</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a class="sidebar-link" href="{{ url()->current() }}#commercial-historique">
                                <i class="align-middle" data-feather="clock"></i> <span class="align-middle">Historique de connexion</span>
                            </a>
                        </li>
                    @endif
                @endisset
            @elseif($sidebarIsFinancialAnalyst)
                <li class="sidebar-header">Analyste Financier</li>
                <li class="sidebar-item {{ request()->routeIs('analyst.portfolio') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('analyst.portfolio') }}">
                        <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Portefeuille de PME</span>
                    </a>
                </li>
                <li class="sidebar-item {{ request()->routeIs('analyst.history') ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ route('analyst.history') }}">
                        <i class="align-middle" data-feather="clock"></i> <span class="align-middle">Historique de mes analyses</span>
                    </a>
                </li>
            @else
            @if($sidebarUser && ($sidebarEffIsAccountant || $sidebarEffIsPlatformAdmin))
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
                                <a class="sidebar-link {{ request()->routeIs('accountant.clients.*') && empty(request()->get('action')) ? 'active' : '' }}" href="{{ route('accountant.clients.index') }}">Dossiers clients</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.documents.*') ? 'active' : '' }}" href="{{ route('accountant.documents.index') }}">
                                    <i class="align-middle" data-feather="file-text" style="width:14px;height:14px;"></i> <span class="align-middle">Documents clients</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.files.*') ? 'active' : '' }}" href="{{ route('accountant.files.index') }}">
                                    <i class="align-middle" data-feather="folder" style="width:14px;height:14px;"></i> <span class="align-middle">Gestionnaire de fichiers</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.commercials.*') ? 'active' : '' }}" href="{{ route('accountant.commercials.index') }}">
                                    <i class="align-middle" data-feather="users" style="width:14px;height:14px;"></i> <span class="align-middle">Suivi Commerciaux</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.commercials-balance.*') ? 'active' : '' }}" href="{{ route('accountant.commercials-balance.index') }}">
                                    <i class="align-middle" data-feather="dollar-sign" style="width:14px;height:14px;"></i> <span class="align-middle">Suivi Solde Commerciaux</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.clients.*') && request()->get('action') === 'add-client' ? 'active' : '' }}" href="{{ route('accountant.clients.index', ['action' => 'add-client']) }}">
                                    <i class="align-middle" data-feather="plus-circle" style="width:14px;height:14px;"></i> <span class="align-middle">Ajouter Client / Entreprise</span>
                                </a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accountant.change-requests.*') ? 'active' : '' }}" href="{{ route('accountant.change-requests.index') }}">
                                    <i class="align-middle" data-feather="check-square" style="width:14px;height:14px;"></i> <span class="align-middle">Validation comptable</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            @if($sidebarUser && $sidebarEffIsPlatformAdmin)
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
                                <a class="sidebar-link {{ request()->routeIs('admin.billing.*') ? 'active' : '' }}" href="{{ route('admin.billing.index') }}">Facturation pro</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.logs.*') ? 'active' : '' }}" href="{{ route('admin.logs.index') }}">Journalisation plateforme</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.commercial-dashboard') ? 'active' : '' }}" href="{{ route('admin.commercial-dashboard') }}">Dashboard Commercial</a>
                            </li>
                            @isset($commercial)
                                @if(request()->routeIs('admin.commercial-dashboard.show'))
                                    <li class="sidebar-header">{{ $commercial->name }}</li>
                                    <li class="sidebar-item">
                                        <a class="sidebar-link" href="{{ url()->current() }}#commercial-portefeuille">Portefeuille &amp; commissions</a>
                                    </li>
                                    <li class="sidebar-item">
                                        <a class="sidebar-link" href="{{ url()->current() }}#commercial-prospections">Prospections envoyées</a>
                                    </li>
                                    <li class="sidebar-item">
                                        <a class="sidebar-link" href="{{ url()->current() }}#commercial-pipeline">Pipeline de prospects</a>
                                    </li>
                                    <li class="sidebar-item">
                                        <a class="sidebar-link" href="{{ url()->current() }}#commercial-historique">Historique de connexion</a>
                                    </li>
                                @endif
                            @endisset
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.prospections.*') ? 'active' : '' }}" href="{{ route('admin.prospections.index') }}">Prospections commerciales</a>
                            </li>
                            <li class="sidebar-item {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                                <a class="sidebar-link" href="{{ route('admin.backups.index') }}">Sauvegardes base de données</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.executive.*') ? 'active' : '' }}" href="{{ route('admin.executive.index') }}">Dashboard CEO/CFO</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.compliance.kyc.*') ? 'active' : '' }}" href="{{ route('admin.compliance.kyc.index') }}">Conformité KYC/KYB</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.compliance.accounting-quality.*') ? 'active' : '' }}" href="{{ route('admin.compliance.accounting-quality.index') }}">Qualité comptable</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.compliance.change-requests.*') ? 'active' : '' }}" href="{{ route('admin.compliance.change-requests.index') }}">Validations comptables</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.rbac.*') ? 'active' : '' }}" href="{{ route('admin.rbac.index') }}">RBAC rôles & permissions</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.ops.*') ? 'active' : '' }}" href="{{ route('admin.ops.index') }}">Ops Center IT Manager</a>
                            </li>

                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.financial-analysis') ? 'active' : '' }}" href="{{ route('admin.financial-analysis') }}">Analyse financière PME</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.financial-ranking') ? 'active' : '' }}" href="{{ route('admin.financial-ranking') }}">Classement solvabilité</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.scoring-parameters.*') ? 'active' : '' }}" href="{{ route('admin.scoring-parameters.index') }}">Paramètres scoring 360</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.plan-comptable.*') ? 'active' : '' }}" href="{{ route('admin.plan-comptable.index') }}">Plan comptable de référence</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.investment-requests.*') ? 'active' : '' }}" href="{{ route('admin.investment-requests.index') }}">Demandes d’investissement</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('admin.support.tickets.*') ? 'active' : '' }}" href="{{ route('admin.support.tickets.index') }}">File support clients</a>
                            </li>
                            <li class="sidebar-item">
                                <a class="sidebar-link d-flex justify-content-between align-items-center {{ request()->routeIs('admin.signalements.*') ? 'active' : '' }}" href="{{ route('admin.signalements.index') }}">
                                    <span>🚨 Signalements &amp; Bugs</span>
                                    @php
                                        $openBugCount = \App\Models\SystemBugReport::where('status', 'OPEN')->count();
                                    @endphp
                                    @if($openBugCount > 0)
                                        <span class="badge bg-danger rounded-pill ms-1" style="font-size:10px;">{{ $openBugCount }}</span>
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            @php
                // Administrateur plateforme : menu « Pages » remplacé par le déroulant « Espace métier » (navbar).
                $sidebarAdminCompactNav = $sidebarEffIsPlatformAdmin;
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
            <li class="sidebar-header">Pilotage</li>
            <li class="sidebar-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('dashboard') }}">
                    <i class="align-middle" data-feather="sliders"></i> <span class="align-middle">Dashboard</span>
                </a>
            </li>
            @if($sidebarAccountingLocked)
                <li class="sidebar-item opacity-50">
                    <a class="sidebar-link"
                       href="{{ route('payments.sandbox') }}"
                       title="Passez en Premium pour activer la comptabilité."
                       data-premium-locked-message="Vous etes actuellement en mode Gratuit. Pour acceder au module Comptabilite, passez en Enterprise Premium en effectuant le paiement de 15 000 FCFA."
                       data-premium-locked="1">
                        <i class="align-middle" data-feather="lock"></i> <span class="align-middle">Comptabilité</span>
                        <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                    </a>
                </li>
            @else
                <li class="sidebar-item {{ request()->routeIs('accounting*') ? 'active' : '' }}">
                    <a class="sidebar-link {{ request()->routeIs('accounting*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseAccounting" role="button" aria-expanded="{{ request()->routeIs('accounting*') ? 'true' : 'false' }}" aria-controls="collapseAccounting">
                        <i class="align-middle" data-feather="book"></i> <span class="align-middle">Comptabilité</span>
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
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.caisse-banque') ? 'active' : '' }}" href="{{ route('accounting.caisse-banque') }}">
                                    <span class="icon-wrapper">💰</span>
                                    <span class="align-middle">Caisse Banque</span>
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
                            <li class="sidebar-item">
                                <a class="sidebar-link {{ request()->routeIs('accounting.liasse-bceao*') ? 'active' : '' }}" href="{{ route('accounting.liasse-bceao') }}">
                                    <span class="icon-wrapper">🏛️</span>
                                    <span class="align-middle">Liasse Fiscale BCEAO</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endif
            <li class="sidebar-item {{ request()->routeIs('treasury*') ? 'active' : '' }}">
                <a class="sidebar-link {{ request()->routeIs('treasury*') ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseTreasury" role="button" aria-expanded="{{ request()->routeIs('treasury*') ? 'true' : 'false' }}" aria-controls="collapseTreasury">
                    <i class="align-middle" data-feather="dollar-sign"></i> <span class="align-middle">Trésorerie</span>
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
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('treasury.mobile-money.*') ? 'active' : '' }}" href="{{ route('treasury.mobile-money.index') }}">
                                <i class="align-middle" data-feather="smartphone" style="width:16px;height:16px;"></i>
                                <span class="align-middle">Rapprochement Mobile Money</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="sidebar-item {{ request()->routeIs('payroll.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('payroll.index') }}">
                    <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Paiement des Salaires</span>
                    <span class="badge bg-success rounded-pill ms-auto">Nouveau</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('invoicing.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('invoicing.index') }}">
                    <i class="align-middle" data-feather="file-text"></i> <span class="align-middle">Facturation</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('stock.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('stock.index') }}">
                    <i class="align-middle" data-feather="package"></i> <span class="align-middle">Stock</span>
                    <span class="badge bg-secondary rounded-pill ms-auto">Option</span>
                </a>
            </li>
            <li class="sidebar-item {{ request()->routeIs('investor.*') ? 'active' : '' }}">
                <a class="sidebar-link" href="{{ route('investor.readiness') }}">
                    <i class="align-middle" data-feather="trending-up"></i> <span class="align-middle">Investisseurs</span>
                </a>
            </li>

            @php
                $sidebarCompanyRoutes = ['profile', 'company.settings', 'profile.company.fird', 'profile.company.fird.update', 'profile.team*'];
                $sidebarCompanyActive = request()->routeIs($sidebarCompanyRoutes);
            @endphp
            <li class="sidebar-item {{ $sidebarCompanyActive ? 'active' : '' }}">
                <a class="sidebar-link {{ $sidebarCompanyActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseCompany" role="button" aria-expanded="{{ $sidebarCompanyActive ? 'true' : 'false' }}" aria-controls="collapseCompany">
                    <i class="align-middle" data-feather="briefcase"></i> <span class="align-middle">Mon entreprise</span>
                </a>
                <div class="collapse {{ $sidebarCompanyActive ? 'show' : '' }}" id="collapseCompany">
                    <ul class="sidebar-nav sidebar-nav-sub">
                        <li class="sidebar-item {{ request()->routeIs('profile') || request()->routeIs('company.settings') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('company.settings') }}">
                                <i class="align-middle" data-feather="user" style="width:16px;height:16px;"></i> <span class="align-middle">Profil & paramètres</span>
                            </a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs(['profile.company.fird', 'profile.company.fird.update']) ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('profile.company.fird') }}">
                                <i class="align-middle" data-feather="file" style="width:16px;height:16px;"></i> <span class="align-middle">Fiche entreprise (FIRD)</span>
                            </a>
                        </li>
                        @if($sidebarUser && ! $sidebarEffIsPlatformAdmin && ! $sidebarEffIsAccountant && $sidebarUser->enterprise_license_id)
                            @if($sidebarTeamLocked)
                                <li class="sidebar-item opacity-50">
                                    <a class="sidebar-link" href="{{ route('payments.sandbox') }}" title="Passez en Premium pour activer l'équipe licence.">
                                        <i class="align-middle" data-feather="lock" style="width:16px;height:16px;"></i> <span class="align-middle">Équipe (licence)</span>
                                        <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                                    </a>
                                </li>
                                <li class="sidebar-item opacity-50">
                                    <a class="sidebar-link" href="{{ route('payments.sandbox') }}" title="Passez en Premium pour activer l'historique entreprise.">
                                        <i class="align-middle" data-feather="lock" style="width:16px;height:16px;"></i> <span class="align-middle">Historique entreprise</span>
                                        <span class="badge bg-secondary rounded-pill ms-auto">Premium</span>
                                    </a>
                                </li>
                            @else
                                <li class="sidebar-item {{ request()->routeIs('profile.team*') ? 'active' : '' }}">
                                    <a class="sidebar-link" href="{{ route('profile.team') }}">
                                        <i class="align-middle" data-feather="users" style="width:16px;height:16px;"></i> <span class="align-middle">Équipe (licence)</span>
                                    </a>
                                </li>
                                <li class="sidebar-item {{ request()->routeIs('profile.team.history') ? 'active' : '' }}">
                                    <a class="sidebar-link" href="{{ route('profile.team.history') }}">
                                        <i class="align-middle" data-feather="list" style="width:16px;height:16px;"></i> <span class="align-middle">Historique entreprise</span>
                                    </a>
                                </li>
                            @endif
                        @endif
                    </ul>
                </div>
            </li>

            @php
                $sidebarSubscriptionRoutes = ['subscriptions.history', 'payments.sandbox*', 'billing.*'];
                $sidebarSubscriptionActive = request()->routeIs($sidebarSubscriptionRoutes);
            @endphp
            <li class="sidebar-item {{ $sidebarSubscriptionActive ? 'active' : '' }}">
                <a class="sidebar-link {{ $sidebarSubscriptionActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseSubscription" role="button" aria-expanded="{{ $sidebarSubscriptionActive ? 'true' : 'false' }}" aria-controls="collapseSubscription">
                    <i class="align-middle" data-feather="credit-card"></i> <span class="align-middle">Abonnement</span>
                </a>
                <div class="collapse {{ $sidebarSubscriptionActive ? 'show' : '' }}" id="collapseSubscription">
                    <ul class="sidebar-nav sidebar-nav-sub">
                        <li class="sidebar-item {{ request()->routeIs('payments.sandbox*') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('payments.sandbox') }}">
                                <i class="align-middle" data-feather="dollar-sign" style="width:16px;height:16px;"></i> <span class="align-middle">Paiement</span>
                            </a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('billing.*') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('billing.invoices') }}">
                                <i class="align-middle" data-feather="file-text" style="width:16px;height:16px;"></i> <span class="align-middle">Mes factures SITIAME</span>
                            </a>
                        </li>
                        <li class="sidebar-item {{ request()->routeIs('subscriptions.history') ? 'active' : '' }}">
                            <a class="sidebar-link" href="{{ route('subscriptions.history') }}">
                                <i class="align-middle" data-feather="clock" style="width:16px;height:16px;"></i> <span class="align-middle">Historique abonnements</span>
                            </a>
                        </li>
                    </ul>
                </div>
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
            @php
                $sidebarHelpRoutes = ['support.*', 'notifications.*', 'activity-log.*'];
                $sidebarHelpActive = request()->routeIs($sidebarHelpRoutes);
            @endphp
            <li class="sidebar-item {{ $sidebarHelpActive ? 'active' : '' }}">
                <a class="sidebar-link {{ $sidebarHelpActive ? '' : 'collapsed' }}" data-bs-toggle="collapse" href="#collapseHelp" role="button" aria-expanded="{{ $sidebarHelpActive ? 'true' : 'false' }}" aria-controls="collapseHelp">
                    <i class="align-middle" data-feather="help-circle"></i> <span class="align-middle">Aide & support</span>
                </a>
                <div class="collapse {{ $sidebarHelpActive ? 'show' : '' }}" id="collapseHelp">
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
                        <li class="sidebar-item">
                            <a class="sidebar-link {{ request()->routeIs('activity-log.*') ? 'active' : '' }}" href="{{ route('activity-log.index') }}">Journal d’activité</a>
                        </li>
                    </ul>
                </div>
            </li>
            @endif

            @if($sidebarPreviewContext !== null)
                <li class="sidebar-item">
                    <a class="sidebar-link text-warning fw-bold" href="{{ route('dashboard.selector') }}">
                        <i class="align-middle" data-feather="grid"></i> <span class="align-middle">Changer de dashboard</span>
                    </a>
                </li>
            @endif

            <li class="sidebar-item mt-3 pt-2 border-top border-secondary border-opacity-25">
                <form action="{{ route('logout') }}" method="POST" class="m-0 px-2">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center gap-2 py-2">
                        <i class="align-middle" data-feather="log-out"></i>
                        <span class="align-middle fw-semibold">Déconnexion</span>
                    </button>
                </form>
            </li>
        </ul>
    </div>
</nav>

@push('scripts')
<script>
    (function () {
        const links = document.querySelectorAll('a[data-premium-locked="1"]');
        if (!links.length) return;

        const showPremiumToast = function (message, paymentUrl) {
            const existing = document.getElementById('premium-lock-card');
            if (existing) {
                return;
            }

            const container = document.createElement('div');
            container.id = 'premium-lock-card';
            container.style.position = 'fixed';
            container.style.top = '18px';
            container.style.right = '18px';
            container.style.zIndex = '1090';
            container.style.maxWidth = '380px';
            container.style.width = 'calc(100vw - 24px)';
            container.innerHTML = ''
                + '<div style="background:#fff;border:1px solid #e9ecef;border-left:4px solid #f59f00;border-radius:12px;box-shadow:0 12px 28px rgba(0,0,0,.14);overflow:hidden;">'
                + '  <div style="padding:12px 14px;display:flex;align-items:flex-start;gap:10px;">'
                + '    <div style="font-size:18px;line-height:1;">⚠️</div>'
                + '    <div style="flex:1;">'
                + '      <div style="font-weight:700;color:#212529;font-size:.95rem;margin-bottom:4px;">Acces Comptabilite bloque</div>'
                + '      <div style="color:#495057;font-size:.88rem;line-height:1.4;">' + message + '</div>'
                + '      <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">'
                + '        <button type="button" data-action="go-premium" style="border:0;background:#f59f00;color:#fff;font-weight:600;font-size:.82rem;border-radius:8px;padding:7px 10px;cursor:pointer;">Passer en Premium</button>'
                + '      </div>'
                + '    </div>'
                + '    <button type="button" aria-label="Fermer" style="border:0;background:transparent;color:#6c757d;font-size:18px;line-height:1;cursor:pointer;padding:0 2px;">&times;</button>'
                + '  </div>'
                + '  <div style="height:3px;background:linear-gradient(90deg,#f59f00,#ffd43b);"></div>'
                + '</div>';
            document.body.appendChild(container);

            const closeBtn = container.querySelector('button[aria-label="Fermer"]');
            const goPremiumBtn = container.querySelector('button[data-action="go-premium"]');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    if (container.parentNode) container.parentNode.removeChild(container);
                });
            }
            if (goPremiumBtn && paymentUrl) {
                goPremiumBtn.addEventListener('click', function () {
                    window.location.href = paymentUrl;
                });
            }
        };

        links.forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                const message = link.getAttribute('data-premium-locked-message') || '';
                const href = link.getAttribute('href') || '';
                if (message) {
                    showPremiumToast(message, href);
                }
            });
        });
    })();
</script>
@endpush
