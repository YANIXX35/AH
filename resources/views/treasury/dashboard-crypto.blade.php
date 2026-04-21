@extends('layouts.app')

@section('title', 'Dashboard Trésorerie | Sitiame Capitale')
@section('page_title', '💰 Dashboard Trésorerie')

@push('styles')
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --adminkit-primary: #4c6ef5;
            --adminkit-secondary: #6c757d;
            --adminkit-success: #10b981;
            --adminkit-danger: #f43f5e;
            --adminkit-warning: #f59e0b;
            --adminkit-info: #06b6d4;
            --adminkit-dark: #1e293b;
            --adminkit-light: #f8fafc;
            --sidebar-width: 250px;
            --header-height: 70px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--adminkit-light);
            margin: 0;
            padding: 0;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #2d3748 0%, #1a202c 100%);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            margin-bottom: 2rem;
        }

        .nav-section-title {
            color: #a0aec0;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 0 1.5rem;
            margin-bottom: 0.5rem;
        }

        .nav-item {
            display: block;
            padding: 0.75rem 1.5rem;
            color: #e2e8f0;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .nav-item:hover {
            background-color: rgba(255,255,255,0.05);
            color: white;
        }

        .nav-item.active {
            background-color: rgba(76, 110, 245, 0.1);
            border-left-color: var(--adminkit-primary);
            color: var(--adminkit-primary);
        }

        .nav-item i {
            width: 20px;
            margin-right: 0.75rem;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            transition: all 0.3s ease;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.5rem 1rem 0.5rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            width: 300px;
            transition: all 0.2s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--adminkit-primary);
            box-shadow: 0 0 0 3px rgba(76, 110, 245, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            border: 1px solid #e2e8f0;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
        }

        .icon-btn:hover {
            background-color: var(--adminkit-light);
            border-color: var(--adminkit-primary);
        }

        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--adminkit-danger);
            color: white;
            font-size: 0.7rem;
            padding: 0.1rem 0.3rem;
            border-radius: 10px;
            font-weight: 600;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .user-profile:hover {
            background-color: var(--adminkit-light);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--adminkit-primary), #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Content Area */
        .content {
            padding: 2rem;
        }

        /* Cards */
        .dashboard-card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .dashboard-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }

        .card-header-custom {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--adminkit-dark);
        }

        .card-body-custom {
            padding: 1.5rem;
        }

        /* Balance Card */
        .balance-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .balance-amount {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 1rem 0;
        }

        .balance-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        /* Exchange Rate Cards */
        .exchange-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .exchange-card:hover {
            border-color: var(--adminkit-primary);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .exchange-pair {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.5rem;
        }

        .exchange-rate {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--adminkit-dark);
            margin-bottom: 0.25rem;
        }

        .change-positive {
            color: var(--adminkit-success);
        }

        .change-negative {
            color: var(--adminkit-danger);
        }

        /* Tables */
        .data-table {
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .data-table tr:hover {
            background: #f8fafc;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--adminkit-success);
        }

        .status-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--adminkit-warning);
        }

        .status-danger {
            background: rgba(244, 63, 94, 0.1);
            color: var(--adminkit-danger);
        }

        /* Dropdown */
        .dropdown-menu-custom {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            min-width: 200px;
            z-index: 1000;
            display: none;
        }

        .dropdown-menu-custom.show {
            display: block;
        }

        .dropdown-item-custom {
            padding: 0.75rem 1rem;
            display: block;
            color: var(--adminkit-dark);
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dropdown-item-custom:hover {
            background: var(--adminkit-light);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .search-box input {
                width: 200px;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
@endpush

@section('content')
<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="sidebar-logo">
            <i class="fas fa-chart-line"></i>
            Sitiame Capitale
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Dashboards</div>
            <a href="{{ route('treasury.index') }}" class="nav-item active">
                <i class="fas fa-wallet"></i>
                Trésorerie
            </a>
            <a href="{{ route('accounting.index') }}" class="nav-item">
                <i class="fas fa-calculator"></i>
                Comptabilité
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Pages</div>
            <a href="{{ route('profile') }}" class="nav-item">
                <i class="fas fa-user"></i>
                Profil
            </a>
            <a href="{{ route('treasury.forecast') }}" class="nav-item">
                <i class="fas fa-chart-prediction"></i>
                Prévisions
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Actions</div>
            <a href="{{ route('treasury.create') }}" class="nav-item">
                <i class="fas fa-plus-circle"></i>
                Nouvelle transaction
            </a>
            <a href="{{ route('treasury.tracking') }}" class="nav-item">
                <i class="fas fa-list"></i>
                Suivi des flux
            </a>
        </div>
    </nav>
</aside>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Rechercher transactions...">
            </div>
        </div>
        
        <div class="header-right">
            <!-- Notifications -->
            <div class="icon-btn" onclick="toggleDropdown('notifications')">
                <i class="fas fa-bell"></i>
                <span class="notification-badge">4</span>
            </div>
            
            <!-- Messages -->
            <div class="icon-btn" onclick="toggleDropdown('messages')">
                <i class="fas fa-envelope"></i>
                <span class="notification-badge">3</span>
            </div>
            
            <!-- User Profile -->
            <div class="user-profile" onclick="toggleDropdown('profile')">
                <div class="user-avatar">
                    {{ substr(Auth::user()->name, 0, 1) }}
                </div>
                <div>
                    <div style="font-weight: 600;">{{ Auth::user()->name }}</div>
                    <div style="font-size: 0.875rem; color: #64748b;">Administrateur</div>
                </div>
                <i class="fas fa-chevron-down" style="margin-left: 0.5rem;"></i>
            </div>
        </div>
    </header>

    <!-- Content Area -->
    <div class="content">
        <!-- Balance Section -->
        <div class="row mb-4 fade-in">
            <div class="col-12">
                <div class="dashboard-card balance-card">
                    <div class="card-body-custom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="mb-3">Solde Total</h3>
                                <div class="balance-amount">
                                    {{ number_format($solde ?? 3400000, 0, ',', ' ') }} FCFA
                                </div>
                                <div class="balance-subtitle">
                                    {{ number_format(($solde ?? 3400000) / 650, 2, ',', ' ') }} BTC
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="mb-2">
                                    <small style="opacity: 0.8;">FCFA/BTC</small>
                                    <div class="h5 mb-0">5 230,77 FCFA</div>
                                </div>
                                <div>
                                    <small style="opacity: 0.8;">Variation 24h</small>
                                    <div class="change-positive">
                                        <i class="fas fa-arrow-up"></i> +2.34%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Exchange Rates -->
        <div class="row mb-4 fade-in">
            <div class="col-12 col-md-3 mb-3">
                <div class="exchange-card">
                    <div class="exchange-pair">FCFA/USD</div>
                    <div class="exchange-rate">0,00165 $</div>
                    <div class="change-positive">
                        <i class="fas fa-arrow-up"></i> +1.23%
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3 mb-3">
                <div class="exchange-card">
                    <div class="exchange-pair">FCFA/EUR</div>
                    <div class="exchange-rate">0,00152 €</div>
                    <div class="change-negative">
                        <i class="fas fa-arrow-down"></i> -0.87%
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3 mb-3">
                <div class="exchange-card">
                    <div class="exchange-pair">Encaissements/Décaissements</div>
                    <div class="exchange-rate">1.85</div>
                    <div class="change-positive">
                        <i class="fas fa-arrow-up"></i> +12.5%
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3 mb-3">
                <div class="exchange-card">
                    <div class="exchange-pair">Solde Projeté</div>
                    <div class="exchange-rate">{{ number_format($soldeProjecte ?? 5100000, 0, ',', ' ') }} FCFA</div>
                    <div class="change-positive">
                        <i class="fas fa-arrow-up"></i> +50%
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="row">
            <!-- Markets Section -->
            <div class="col-12 col-lg-8 mb-4">
                <div class="dashboard-card fade-in">
                    <div class="card-header-custom">
                        <h5 class="mb-0">Marchés</h5>
                    </div>
                    <div class="card-body-custom">
                        <div class="data-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($transactions ?? [] as $transaction)
                                    <tr>
                                        <td>{{ $transaction->transaction_date->format('d/m/Y') }}</td>
                                        <td>
                                            <span class="status-badge {{ $transaction->type === 'encaissement' ? 'status-success' : 'status-warning' }}">
                                                {{ ucfirst($transaction->type) }}
                                            </span>
                                        </td>
                                        <td>{{ Str::limit($transaction->description, 30) }}</td>
                                        <td>
                                            <strong class="{{ $transaction->type === 'encaissement' ? 'text-success' : 'text-danger' }}">
                                                {{ $transaction->type === 'encaissement' ? '+' : '-' }} {{ number_format($transaction->amount, 0, ',', ' ') }} FCFA
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-{{ $transaction->status === 'effectue' ? 'success' : ($transaction->status === 'planifie' ? 'warning' : 'danger') }}">
                                                {{ ucfirst($transaction->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            Aucune transaction pour l'instant
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Section -->
            <div class="col-12 col-lg-4 mb-4">
                <div class="dashboard-card fade-in">
                    <div class="card-header-custom">
                        <h5 class="mb-0">Ordres</h5>
                    </div>
                    <div class="card-body-custom">
                        <!-- Sell Orders -->
                        <div class="mb-4">
                            <h6 class="text-danger mb-3">Décaissements en attente</h6>
                            <div class="data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $pendingDecaissements = $transactions ?? collect();
                                            $pendingDecaissements = $pendingDecaissements->where('status', 'planifie')->where('type', 'decaissement')->take(3);
                                        @endphp
                                        @forelse($pendingDecaissements as $order)
                                        <tr>
                                            <td>{{ Str::limit($order->description, 20) }}</td>
                                            <td class="text-danger">{{ number_format($order->amount, 0, ',', ' ') }} FCFA</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-2">
                                                Aucun décaissement en attente
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Buy Orders -->
                        <div>
                            <h6 class="text-success mb-3">Encaissements en attente</h6>
                            <div class="data-table">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $pendingEncaissements = $transactions ?? collect();
                                            $pendingEncaissements = $pendingEncaissements->where('status', 'planifie')->where('type', 'encaissement')->take(3);
                                        @endphp
                                        @forelse($pendingEncaissements as $order)
                                        <tr>
                                            <td>{{ Str::limit($order->description, 20) }}</td>
                                            <td class="text-success">{{ number_format($order->amount, 0, ',', ' ') }} FCFA</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted py-2">
                                                Aucun encaissement en attente
                                            </td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Operations Section -->
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card fade-in">
                    <div class="card-header-custom">
                        <h5 class="mb-0">Opérations Rapides</h5>
                    </div>
                    <div class="card-body-custom">
                        <div class="row">
                            <div class="col-12 col-md-8">
                                <p class="text-muted mb-3">
                                    Placez un nouvel ordre : Le montant final pourrait changer en fonction des conditions actuelles du marché.
                                </p>
                                <form>
                                    <div class="row g-3">
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Type</label>
                                            <select class="form-select">
                                                <option>Achat</option>
                                                <option>Vente</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Montant (FCFA)</label>
                                            <input type="number" class="form-control" placeholder="0">
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">Catégorie</label>
                                            <select class="form-select">
                                                <option>Paiement client</option>
                                                <option>Paiement fournisseur</option>
                                                <option>Autre</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="submit" class="btn btn-primary w-100">
                                                <i class="fas fa-plus me-2"></i>Placer l'ordre
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="col-12 col-md-4">
                                <div class="text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-chart-line fa-3x text-primary"></i>
                                    </div>
                                    <h6>Statistiques du jour</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="text-success">
                                                <strong>{{ number_format($totalEncaissements ?? 9300000, 0, ',', ' ') }}</strong>
                                            </div>
                                            <small>Encaissements</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="text-danger">
                                                <strong>{{ number_format($totalDecaissements ?? 5900000, 0, ',', ' ') }}</strong>
                                            </div>
                                            <small>Décaissements</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dropdown Menus -->
<div id="notifications-dropdown" class="dropdown-menu-custom">
    <a href="#" class="dropdown-item-custom">
        <i class="fas fa-check-circle text-success me-2"></i>
        Transaction validée
        <small class="d-block text-muted">Il y a 30 minutes</small>
    </a>
    <a href="#" class="dropdown-item-custom">
        <i class="fas fa-info-circle text-info me-2"></i>
        Nouveau rapport disponible
        <small class="d-block text-muted">Il y a 2 heures</small>
    </a>
    <a href="#" class="dropdown-item-custom">
        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
        Solde faible alerte
        <small class="d-block text-muted">Il y a 5 heures</small>
    </a>
    <a href="#" class="dropdown-item-custom">
        <i class="fas fa-times-circle text-danger me-2"></i>
        Transaction échouée
        <small class="d-block text-muted">Il y a 14 heures</small>
    </a>
    <hr class="my-2">
    <a href="#" class="dropdown-item-custom text-center">
        <strong>Voir toutes les notifications</strong>
    </a>
</div>

<div id="messages-dropdown" class="dropdown-menu-custom">
    <a href="#" class="dropdown-item-custom">
        <div class="d-flex align-items-start">
            <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                JD
            </div>
            <div>
                <strong>Jean Dupont</strong>
                <p class="mb-0 small">Nouveau paiement client reçu</p>
                <small class="text-muted">Il y a 15 minutes</small>
            </div>
        </div>
    </a>
    <a href="#" class="dropdown-item-custom">
        <div class="d-flex align-items-start">
            <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                SM
            </div>
            <div>
                <strong>Sophie Martin</strong>
                <p class="mb-0 small">Rapport mensuel généré</p>
                <small class="text-muted">Il y a 2 heures</small>
            </div>
        </div>
    </a>
    <a href="#" class="dropdown-item-custom">
        <div class="d-flex align-items-start">
            <div class="user-avatar me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                PL
            </div>
            <div>
                <strong>Pierre Leroy</strong>
                <p class="mb-0 small">Demande de validation</p>
                <small class="text-muted">Il y a 4 heures</small>
            </div>
        </div>
    </a>
    <hr class="my-2">
    <a href="#" class="dropdown-item-custom text-center">
        <strong>Voir tous les messages</strong>
    </a>
</div>

<div id="profile-dropdown" class="dropdown-menu-custom">
    <a href="{{ route('profile') }}" class="dropdown-item-custom">
        <i class="fas fa-user me-2"></i> Profil
    </a>
    <a href="{{ route('treasury.index') }}" class="dropdown-item-custom">
        <i class="fas fa-chart-line me-2"></i> Tableau de bord
    </a>
    <a href="{{ route('treasury.settings') }}" class="dropdown-item-custom">
        <i class="fas fa-cog me-2"></i> Paramètres
    </a>
    <hr class="my-2">
    <a href="{{ route('logout') }}" class="dropdown-item-custom text-danger">
        <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
    </a>
</div>

<script>
function toggleDropdown(type) {
    // Close all dropdowns first
    document.querySelectorAll('.dropdown-menu-custom').forEach(menu => {
        menu.classList.remove('show');
    });
    
    // Toggle the selected dropdown
    const dropdown = document.getElementById(type + '-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.icon-btn') && !event.target.closest('.user-profile')) {
        document.querySelectorAll('.dropdown-menu-custom').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

// Add fade-in animation on scroll
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.fade-in').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(el);
});
</script>
@endsection
