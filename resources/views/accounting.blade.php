@extends('layouts.app')

@section('title', 'Comptabilité | Sitiame Capital')
@section('page_title', 'Moteur comptable')

@push('styles')
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
@endpush

@section('content')
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        /* AdminKit DataTables Styling */
        .dataTables_wrapper {
            padding: 0;
        }

        .dataTables_wrapper .dataTables_filter {
            text-align: right;
            margin-bottom: 1rem;
        }

        .dataTables_wrapper .dataTables_filter label {
            font-weight: 400;
            white-space: nowrap;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5rem;
        }

        .dt-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .dt-button {
            display: inline-block;
            margin-right: 0;
        }

        .table-density-switch {
            display: inline-flex;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            overflow: hidden;
            margin-left: 0.5rem;
        }

        .table-density-switch .btn {
            border: 0;
            border-radius: 0;
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }

        .table-density-switch .btn.active {
            background-color: #0d6efd;
            color: #fff;
        }

        .dt-button.btn-gray-800 {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.15s ease;
            cursor: pointer;
        }

        .dt-button.btn-gray-800:hover {
            background-color: #5a6268;
            border-color: #5a6268;
        }

        /* Table styling */
        .table {
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            padding: 1rem;
            background-color: #f8f9fa;
            cursor: pointer;
            position: relative;
            padding-right: 1.6rem;
        }

        .table thead th.sorting::after,
        .table thead th.sorting_asc::after,
        .table thead th.sorting_desc::after {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.68rem;
            color: #adb5bd;
        }

        .table thead th.sorting::after { content: "↕"; }
        .table thead th.sorting_asc::after { content: "↑"; color: #0d6efd; }
        .table thead th.sorting_desc::after { content: "↓"; color: #0d6efd; }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        #journalTable.table-compact tbody td {
            padding: 0.5rem 0.65rem;
            font-size: 0.82rem;
        }

        #journalTable.table-compact thead th {
            padding-top: 0.6rem;
            padding-bottom: 0.6rem;
            font-size: 0.7rem;
        }

        .ocr-detail-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }

        .ocr-detail-table td {
            padding: 0.35rem 0.5rem;
            border-bottom: 1px dashed #e9ecef;
            vertical-align: top;
            font-size: 0.82rem;
        }

        .ocr-detail-table td:first-child {
            width: 130px;
            color: #6c757d;
            font-weight: 600;
        }

        .table tbody td:last-child {
            text-align: center;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table.dataTable tbody tr {
            background-color: white;
        }

        .table.dataTable tbody tr.odd {
            background-color: #f8f9fa;
        }

        /* Actions column */
        .header-actions,
        .table-actions {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.5rem;
            align-items: center;
            justify-content: flex-end;
        }

        .table-actions {
            justify-content: center;
            flex-wrap: nowrap;
            white-space: nowrap;
            gap: 0.35rem;
        }

        .header-actions .btn,
        .table-actions .btn {
            flex-shrink: 0;
            min-height: 38px;
        }

        .header-actions {
            white-space: nowrap;
            overflow-x: auto;
            scrollbar-width: thin;
            max-width: 100%;
            flex: 0 0 auto;
            display: inline-flex;
            justify-content: flex-start;
            padding-bottom: 0.1rem;
        }

        .table-actions .btn {
            width: 34px;
            height: 34px;
            min-height: 34px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.45rem;
        }

        .table-actions .btn svg {
            width: 15px;
            height: 15px;
            stroke: currentColor;
            fill: currentColor;
        }

        .table-actions .btn[title="Visualiser"] {
            color: #0d6efd;
        }

        .table-actions .btn[title="Modifier"] {
            color: #198754;
        }

        .table-actions .btn[title="Relancer OCR"] {
            color: #6f42c1;
        }

        .table-actions .btn[title="OCR automatique"] {
            color: #6f42c1;
        }

        .table-actions .btn.btn-ocr-action {
            width: auto;
            min-width: 72px;
            padding: 0 0.55rem;
            gap: 0.3rem;
            color: #6f42c1;
            font-size: 0.76rem;
            font-weight: 600;
        }

        .table-actions .btn.btn-ocr-action svg {
            width: 13px;
            height: 13px;
        }

        .table-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .collapsible-section {
            width: 100%;
        }

        .collapsible-section summary {
            list-style: none;
            cursor: pointer;
        }

        .collapsible-section summary::-webkit-details-marker {
            display: none;
        }

        .collapsible-section-toggle {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 600;
        }

        .collapsible-chevron {
            transition: transform 0.2s ease;
        }

        details[open] .collapsible-chevron {
            transform: rotate(90deg);
        }

        .ocr-facture-table td {
            padding: 0.4rem 0.55rem;
            font-size: 0.85rem;
            border-bottom: 1px solid #eef1f4;
            vertical-align: top;
        }

        .ocr-facture-table td:first-child {
            width: 220px;
            color: #6c757d;
            font-weight: 600;
        }

        .header-actions form,
        .table-actions form {
            margin: 0;
        }

        .d-inline {
            display: inline !important;
        }

        .d-inline form {
            display: inline !important;
        }

        /* Badge light colors */
        .bg-light-primary {
            background-color: #e7f1ff !important;
            color: #0c5ff4 !important;
        }

        .bg-light-success {
            background-color: #e7f6ed !important;
            color: #198754 !important;
        }

        .bg-light-warning {
            background-color: #fff3cd !important;
            color: #ff6b6b !important;
        }

        .bg-light-danger {
            background-color: #f8d7da !important;
            color: #dc3545 !important;
        }

        .bg-light-secondary {
            background-color: #e9ecef !important;
            color: #6c757d !important;
        }

        /* Button white style */
        .btn-white {
            background-color: #fff;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 0.375rem 0.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.25rem;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .btn-white:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
            color: #212529;
            text-decoration: none;
        }

        .btn-white svg {
            width: 16px;
            height: 16px;
        }

        .btn-white svg:not([data-feather]) {
            margin: 0;
        }

        .btn-white.text-danger:hover {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #dc3545;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            box-shadow: 0 12px 35px rgba(15, 39, 71, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(15, 39, 71, 0.12);
            border-color: rgba(15, 39, 71, 0.15);
        }

        .summary-card::before {
            content: "";
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(15, 39, 71, 0.04) 0%, rgba(255, 255, 255, 0) 70%);
            top: -30px;
            right: -30px;
            pointer-events: none;
        }

        .summary-card::after {
            content: "";
            position: absolute;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 1px dashed rgba(15, 39, 71, 0.08);
            top: 20px;
            right: 30px;
            pointer-events: none;
        }

        .summary-card .card-body {
            position: relative;
            z-index: 2;
        }

        .summary-card .card-title {
            color: #0F2747;
            font-weight: 600;
        }

        .summary-label {
            font-size: 0.825rem;
            color: #6c757d;
        }

        .summary-value {
            font-size: 1.55rem;
            font-weight: 700;
            background: linear-gradient(135deg, #0F2747 0%, #1d4ed8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .summary-value-orange {
            background: linear-gradient(135deg, #ea580c 0%, #f97316 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .summary-value-green {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .summary-value-blue {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .summary-badge {
            background-color: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(15, 39, 71, 0.1);
            color: #0F2747;
            font-weight: 500;
        }

        /* Code style */
        code {
            background-color: transparent;
            color: #495057;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Pagination */
        .dataTables_paginate {
            margin-top: 1rem;
        }

        .pagination {
            margin-bottom: 0;
        }

        .paginate_button.active a {
            background-color: #0c5ff4;
            border-color: #0c5ff4;
            color: white;
        }

        /* Pagination */
        .dataTables_paginate {
            margin-top: 1rem;
        }

        .pagination {
            margin-bottom: 0;
            gap: 0.25rem;
            display: flex;
        }

        .page-item {
            margin-right: 0;
        }

        .page-link {
            color: #0c5ff4;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }

        .page-link:hover {
            background-color: #f8f9fa;
            color: #0c5ff4;
        }

        .page-item.active .page-link {
            background-color: #0c5ff4;
            border-color: #0c5ff4;
            color: white;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            cursor: not-allowed;
            opacity: 0.5;
        }

        /* Info text */
        .dataTables_info {
            padding: 1rem 0;
            font-size: 0.875rem;
            color: #6c757d;
        }

        @media (max-width: 767.98px) {
            #journalTable thead {
                display: none;
            }

            #journalTable tbody,
            #journalTable tr,
            #journalTable td {
                display: block;
                width: 100%;
            }

            #journalTable tbody tr {
                border: 1px solid #e9ecef;
                border-radius: 0.5rem;
                margin-bottom: 0.75rem;
                background: #fff;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
            }

            #journalTable tbody td {
                border-bottom: 1px solid #f1f3f5;
                text-align: left !important;
                padding: 0.5rem 0.75rem;
            }

            #journalTable tbody td:last-child {
                border-bottom: 0;
            }

            #journalTable tbody td[data-label]::before {
                content: attr(data-label);
                display: block;
                font-size: 0.7rem;
                text-transform: uppercase;
                letter-spacing: 0.35px;
                color: #6c757d;
                margin-bottom: 0.25rem;
            }

            #journalTable tbody td[data-label="Sélection"]::before {
                margin-bottom: 0;
            }

            .table-actions {
                justify-content: flex-start;
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 0.15rem;
            }

            .table-actions .btn {
                width: 36px;
                height: 36px;
            }

            .table-actions .btn.btn-ocr-action {
                width: auto;
                min-width: 70px;
                height: 36px;
                padding: 0 0.5rem;
            }
        }
    </style>

    <div class="mondays-container pb-4">
        <!-- HERO MONDAYS HEADER -->
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-2">
                <div>
                    <div class="mondays-hero-date text-muted small fw-semibold">
                        <i data-feather="calendar" class="me-1" style="width:14px; height:14px;"></i>
                        {{ \Carbon\Carbon::now()->locale('fr')->isoFormat('dddd D MMMM YYYY') }}
                    </div>
                    <h1 class="mondays-hero-title fw-bold text-dark h2 mt-1 mb-2">
                        Moteur Comptable & Saisie — {{ explode(' ', auth()->user()?->name ?? 'Utilisateur')[0] }} 👋
                    </h1>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <a href="{{ route('accounting.documents') }}" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold">
                        <i data-feather="folder" class="me-1" style="width:14px; height:14px;"></i> File Documents
                    </a>
                    <a href="{{ route('accounting.report.journal') }}" class="btn btn-sm btn-light border rounded-pill px-3 fw-semibold text-dark">
                        <i data-feather="file-text" class="me-1" style="width:14px; height:14px;"></i> Journal
                    </a>
                    <a href="{{ route('accounting.liasse-bceao') }}" class="btn btn-sm btn-success rounded-pill px-3 fw-semibold">
                        <i data-feather="award" class="me-1" style="width:14px; height:14px;"></i> Liasse BCEAO
                    </a>
                </div>
            </div>

            <!-- BARRE DE PILULES KPI EN-TÊTE -->
            <div class="mondays-pill-bar d-inline-flex align-items-center gap-3 bg-white border rounded-pill px-4 py-2 shadow-sm flex-wrap">
                <div class="mondays-pill-item small fw-bold text-dark">
                    <span class="text-primary">📖</span> <strong>Écritures :</strong> {{ number_format($accountingEntriesCount ?? 0) }} saisies
                </div>
                <div class="text-muted">|</div>
                <div class="mondays-pill-item small fw-bold text-dark">
                    <span class="text-success">⚖️</span> <strong>Débit :</strong> {{ number_format($totalDebit ?? 0, 0, ',', ' ') }} FCFA
                </div>
                <div class="text-muted">|</div>
                <div class="mondays-pill-item small fw-bold text-dark">
                    <span class="text-warning">⚖️</span> <strong>Crédit :</strong> {{ number_format($totalCredit ?? 0, 0, ',', ' ') }} FCFA
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <div class="card mondays-card border-0">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
                            <a href="{{ route('accounting.documents') }}" class="btn btn-outline-secondary btn-sm"><i data-feather="folder" class="me-1"></i>Documents</a>
                            <a href="{{ route('accounting.plan') }}" class="btn btn-outline-secondary btn-sm"><i data-feather="layers" class="me-1"></i>Plan OHADA</a>
                            <a href="{{ route('accounting.bank-reconciliation') }}" class="btn btn-outline-secondary btn-sm"><i data-feather="shuffle" class="me-1"></i>Rapprochement</a>
                            <a href="{{ route('accounting.monthly-closing') }}" class="btn btn-outline-secondary btn-sm"><i data-feather="calendar" class="me-1"></i>Clôture</a>
                            <a href="{{ route('accounting.report.journal') }}" class="btn btn-outline-primary btn-sm"><i data-feather="file-text" class="me-1"></i>Rapports</a>
                        </div>
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h6 class="mb-1"><i data-feather="upload" class="me-1"></i>Import rapide: uniquement le document</h6>
                            <p class="text-muted mb-0 small">Dépose le justificatif (PDF/Image/Excel/CSV), puis le système extrait les données OCR pour créer les écritures et les mouvements bancaires après validation. Les PDF et images sont analysés via le service OCR.space.</p>
                        </div>
                        <form action="{{ route('accounting.documents.upload') }}" method="POST" enctype="multipart/form-data" class="d-flex flex-column flex-sm-row align-items-stretch gap-2">
                            @csrf
                            <input type="file" name="documents[]" class="form-control form-control-sm @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.csv" required>
                            <button type="submit" class="btn btn-primary btn-sm text-nowrap">Charger le document</button>
                        </form>
                    </div>
                    @error('documents')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                    @error('documents.*')
                        <div class="text-danger small mt-2">{{ $message }}</div>
                    @enderror
                    <div class="mt-2">
                        <a href="{{ route('accounting.documents') }}" class="small">Ouvrir la file de validation des documents</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="#moteur-ecritures" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Génération d’écritures</h6>
                        <p class="text-muted small mb-0">Saisie, pièces jointes, OCR et validation documentaire.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.report.journal') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Journal</h6>
                        <p class="text-muted small mb-0">Liste chronologique des écritures sur la période.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.report.grand-livre') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Grand livre</h6>
                        <p class="text-muted small mb-0">Mouvements regroupés par compte.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.report.balance') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Balance</h6>
                        <p class="text-muted small mb-0">Soldes débit / crédit et équilibre.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.bank-reconciliation') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Rapprochement bancaire</h6>
                        <p class="text-muted small mb-0">Trésorerie effectuée vs classe 5 (indicatif).</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.monthly-closing') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Clôture mensuelle</h6>
                        <p class="text-muted small mb-0">Contrôles et enregistrement de clôture métier.</p>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="{{ route('accounting.plan') }}" class="text-decoration-none text-reset">
                <div class="card summary-card h-100 border shadow-sm">
                    <div class="card-body py-3">
                        <h6 class="card-title text-primary mb-1">Plan comptable OHADA</h6>
                        <p class="text-muted small mb-0">Import, mise à jour et réinitialisation du référentiel.</p>
                    </div>
                </div>
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <a href="{{ route('accounting.report.journal') }}" class="text-decoration-none text-reset">
            <div class="card summary-card">
                <div class="card-body">
                    <h5 class="card-title">Journal</h5>
                    <p class="text-muted">Enregistrer et visualiser toutes les écritures.</p>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <span class="summary-value summary-value-orange">{{ number_format($entriesCount ?? $entries->count(), 0, ',', ' ') }}</span>
                        <span class="badge summary-badge">Écritures</span>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('accounting.report.grand-livre') }}" class="text-decoration-none text-reset">
            <div class="card summary-card">
                <div class="card-body">
                    <h5 class="card-title">Grand livre</h5>
                    <p class="text-muted">Regrouper les écritures par compte.</p>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <span class="summary-value summary-value-blue">{{ number_format($ledgerCount ?? count($ledger), 0, ',', ' ') }}</span>
                        <span class="badge summary-badge">Comptes</span>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('accounting.report.balance') }}" class="text-decoration-none text-reset">
            <div class="card summary-card">
                <div class="card-body">
                    <h5 class="card-title">Balance</h5>
                    <p class="text-muted">Vérifier l’équilibre débit/crédit.</p>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="small text-muted">Actif</span>
                            <strong class="summary-value summary-value-green"><i data-feather="dollar-sign" class="me-1"></i>{{ number_format($assets, 2, ',', ' ') }} FCFA</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Passif</span>
                            <strong class="summary-value summary-value-orange"><i data-feather="dollar-sign" class="me-1"></i>{{ number_format($liabilities, 2, ',', ' ') }} FCFA</strong>
                        </div>
                    </div>
                </div>
            </div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="{{ route('accounting.report.resultat') }}" class="text-decoration-none text-reset">
            <div class="card summary-card">
                <div class="card-body">
                    <h5 class="card-title">Compte de résultat</h5>
                    <p class="text-muted">Synthèse des produits et charges.</p>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small text-muted">Produits</span>
                            <strong class="summary-value summary-value-green"><i data-feather="dollar-sign" class="me-1"></i>{{ number_format($income, 2, ',', ' ') }} FCFA</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Charges</span>
                            <strong class="summary-value summary-value-orange"><i data-feather="dollar-sign" class="me-1"></i>{{ number_format($expenses, 2, ',', ' ') }} FCFA</strong>
                        </div>
                    </div>
                </div>
            </div>
            </a>
        </div>
    </div>

    @php
        $currentUser = auth()->user();
        $canEditAccounting = $currentUser && ($currentUser->isPlatformAdmin() || $currentUser->isAccountant());
    @endphp

    @if($canEditAccounting)
    <div class="row g-4 mb-4" id="moteur-ecritures">
        <div class="col-xl-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4"><i data-feather="plus-circle" class="me-2" style="width: 20px; height: 20px;"></i>Génération d’écritures — saisie d’une nouvelle écriture</h5>
                    @if($prefillDocument && $prefillData)
                        <div class="alert alert-info d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2">
                            <div>
                                <strong>Pré-remplissage OCR actif</strong><br>
                                <span class="small">
                                    Document source : <strong>{{ $prefillDocument->original_name }}</strong>
                                    | Type : {{ $prefillDocument->document_type }}
                                    | Confiance : {{ number_format((float) $prefillDocument->confidence, 2, ',', ' ') }}%
                                </span>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('accounting.documents.viewer', $prefillDocument) }}" class="btn btn-sm btn-outline-primary">Voir le document</a>
                                <a href="{{ route('accounting') }}#moteur-ecritures" class="btn btn-sm btn-outline-secondary">Retirer le pré-remplissage</a>
                            </div>
                        </div>
                    @endif
                    
                    <form action="{{ route('accounting.entries.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @error('access_denied')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror
                        <input type="hidden" name="document_id" id="documentIdValue" value="{{ old('document_id', $prefillData['document_id'] ?? '') }}">
                        <div id="entryPrefillStatus" class="mb-3" style="display:none;"></div>
                        @php
                            $prefillExtracted = (array) ($prefillDocument?->extracted_data ?? []);
                            $prefillRich = (array) ($prefillExtracted['ocr_detected_fields'] ?? []);
                            $prefillPrimary = (array) ($prefillRich['primary'] ?? []);
                            $prefillContacts = (array) ($prefillRich['contacts'] ?? []);
                            $prefillAddresses = (array) ($prefillRich['addresses'] ?? []);
                            $prefillIdentifiers = (array) ($prefillRich['identifiers'] ?? []);
                            $prefillPayment = (array) ($prefillRich['payment'] ?? []);
                            $prefillBanking = (array) ($prefillRich['banking'] ?? []);
                        @endphp
                        
                        <!-- SECTION 1: INFORMATIONS DOCUMENT -->
                        <div class="card mb-4 border-start border-primary border-3">
                            <div class="card-body">
                                <details class="collapsible-section js-accounting-section" data-section="document" open>
                                    <summary class="card-title text-primary mb-0">
                                        <span class="collapsible-section-toggle">
                                            <i class="collapsible-chevron">▸</i>
                                            <i data-feather="file" class="me-1" style="width: 18px; height: 18px;"></i>
                                            1. Informations du document
                                        </span>
                                    </summary>
                                <div class="pt-3">
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label fw-500">Type</label>
                                        <select id="documentType" name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                                            <option value="">--</option>
                                            <option value="Vente" {{ old('document_type', $prefillData['document_type'] ?? '') === 'Vente' ? 'selected' : '' }}>Facture vente</option>
                                            <option value="Achat" {{ old('document_type', $prefillData['document_type'] ?? '') === 'Achat' ? 'selected' : '' }}>Facture achat</option>
                                            <option value="Reçu" {{ old('document_type', $prefillData['document_type'] ?? '') === 'Reçu' ? 'selected' : '' }}>Reçu</option>
                                            <option value="Justificatif" {{ old('document_type', $prefillData['document_type'] ?? '') === 'Justificatif' ? 'selected' : '' }}>Justificatif</option>
                                        </select>
                                        @error('document_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label fw-500">Source</label>
                                        <select class="form-select" id="source">
                                            <option value="upload">Upload</option>
                                            <option value="manual">Manual</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Fichier</label>
                                        <div class="d-flex gap-2">
                                            <input type="file" name="attachment" class="form-control @error('attachment') is-invalid @enderror" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx,.zip">
                                            <button type="button" id="importAndAnalyzeBtn" class="btn btn-outline-primary text-nowrap" title="Analyser le fichier par OCR et pré-remplir le formulaire" disabled>
                                                <i data-feather="zap" style="width: 16px; height: 16px;"></i> Importer
                                            </button>
                                        </div>
                                        @error('attachment')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted d-block mt-1">Choisissez un fichier puis cliquez sur « Importer » pour pré-remplir automatiquement l'écriture.</small>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Partenaire</label>
                                        <input type="text" name="partner_name" value="{{ old('partner_name', $prefillData['partner_name'] ?? '') }}" class="form-control" placeholder="Nom fournisseur/client">
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <label class="form-label fw-500">Date facture</label>
                                        <input type="date" name="date" value="{{ old('date', $prefillData['date'] ?? now()->toDateString()) }}" class="form-control @error('date') is-invalid @enderror" required>
                                        @error('date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                
                                <div class="row g-3 mt-2">
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">N° facture</label>
                                        <input type="text" name="document_reference" value="{{ old('document_reference', $prefillData['document_reference'] ?? '') }}" class="form-control @error('document_reference') is-invalid @enderror" placeholder="FAC-2026-001" required>
                                        @error('document_reference')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="col-md-9">
                                        <label class="form-label fw-500">Description</label>
                                        <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="1" placeholder="Détails de la transaction" required>{{ old('description', $prefillData['description'] ?? '') }}</textarea>
                                        @error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                </div>
                                </details>
                            </div>
                        </div>
                        
                        <!-- SECTION 2: MONTANTS ET TVA -->
                        <div class="card mb-4 border-start border-warning border-3">
                            <div class="card-body">
                                <details class="collapsible-section js-accounting-section" data-section="amounts" open>
                                    <summary class="card-title text-warning mb-0">
                                        <span class="collapsible-section-toggle">
                                            <i class="collapsible-chevron">▸</i>
                                            <i data-feather="dollar-sign" class="me-1" style="width: 18px; height: 18px;"></i>
                                            2. Montants et TVA
                                        </span>
                                    </summary>
                                <div class="pt-3">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Montant HT</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" name="amount" id="htAmount" value="{{ old('amount', $prefillData['amount'] ?? '') }}" class="form-control @error('amount') is-invalid @enderror" placeholder="0.00" required>
                                            <span class="input-group-text">FCFA</span>
                                        </div>
                                        @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Montant TVA</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" id="tvaAmount" name="amount_tva" value="{{ old('amount_tva', $prefillData['amount_tva'] ?? '0.00') }}" class="form-control" placeholder="0.00" readonly>
                                            <span class="input-group-text">FCFA</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Montant TTC</label>
                                        <div class="input-group">
                                            <input type="number" step="0.01" min="0" id="ttcAmount" name="ttc_amount" value="{{ old('ttc_amount', $prefillData['ttc_amount'] ?? '0.00') }}" class="form-control" placeholder="0.00" readonly>
                                            <span class="input-group-text">FCFA</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row g-3 mt-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-500">Taux TVA %</label>
                                        <input type="number" step="0.01" min="0" max="100" id="tvaRate" name="tva_rate" value="{{ old('tva_rate', $prefillData['tva_rate'] ?? '18') }}" class="form-control" placeholder="18">
                                    </div>
                                    
                                    <div class="col-md-9">
                                        <label class="form-label fw-500">Taux rapides</label>
                                        <div class="d-flex gap-2 flex-wrap">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(0)">0%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(5)">5%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(10)">10%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(18)">18%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(20)">20%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(25)">25%</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setTvaRate(35)">35%</button>
                                        </div>
                                    </div>
                                </div>
                                </div>
                                </details>
                            </div>
                        </div>
                        
                        <!-- SECTION 3: MOUVEMENTS COMPTABLES -->
                        <div class="card mb-4 border-start border-success border-3">
                            <div class="card-body">
                                <details class="collapsible-section js-accounting-section" data-section="accounts" open>
                                    <summary class="card-title text-success mb-0">
                                        <span class="collapsible-section-toggle">
                                            <i class="collapsible-chevron">▸</i>
                                            <i data-feather="arrow-right-left" class="me-1" style="width: 18px; height: 18px;"></i>
                                            3. Mouvements comptables
                                        </span>
                                    </summary>
                                <div class="pt-3">
                                <div class="row g-3">
                                    <div class="col-md-6 position-relative">
                                        <label class="form-label fw-500"><i data-feather="arrow-down-circle" class="me-2 text-danger" style="width: 16px; height: 16px;"></i>Compte DÉBIT</label>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" style="max-width: 90px;" data-account-class-filter="debit">
                                                <option value="">Toutes</option>
                                                @foreach(range(1, 9) as $classeOption)
                                                    <option value="{{ $classeOption }}">Cl. {{ $classeOption }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" class="form-control" placeholder="Rechercher un compte (code ou libellé)…" autocomplete="off" data-account-search="debit">
                                        </div>
                                        <input type="hidden" name="debit_account" id="debitAccountValue" value="{{ old('debit_account') }}" required>
                                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; display:none; max-height: 260px; overflow-y: auto;" data-account-results="debit"></div>
                                        <small class="form-text text-muted d-block mt-1">Recherchez et sélectionnez un compte du plan comptable de l'entreprise.</small>
                                    </div>

                                    <div class="col-md-6 position-relative">
                                        <label class="form-label fw-500"><i data-feather="arrow-up-circle" class="me-2 text-success" style="width: 16px; height: 16px;"></i>Compte CRÉDIT</label>
                                        <div class="d-flex gap-2">
                                            <select class="form-select" style="max-width: 90px;" data-account-class-filter="credit">
                                                <option value="">Toutes</option>
                                                @foreach(range(1, 9) as $classeOption)
                                                    <option value="{{ $classeOption }}">Cl. {{ $classeOption }}</option>
                                                @endforeach
                                            </select>
                                            <input type="text" class="form-control" placeholder="Rechercher un compte (code ou libellé)…" autocomplete="off" data-account-search="credit">
                                        </div>
                                        <input type="hidden" name="credit_account" id="creditAccountValue" value="{{ old('credit_account') }}" required>
                                        <div class="list-group position-absolute w-100 shadow-sm" style="z-index: 20; display:none; max-height: 260px; overflow-y: auto;" data-account-results="credit"></div>
                                        <small class="form-text text-muted d-block mt-1">Recherchez et sélectionnez un compte du plan comptable de l'entreprise.</small>
                                    </div>
                                </div>
                                </div>
                                </details>
                            </div>
                        </div>
                        
                        <!-- SECTION 4: CONTRÔLES DE COHÉRENCE -->
                        <div class="card mb-4 border-start border-info border-3">
                            <div class="card-body">
                                <details class="collapsible-section js-accounting-section" data-section="ocr" open>
                                    <summary class="card-title text-info mb-0">
                                        <span class="collapsible-section-toggle">
                                            <i class="collapsible-chevron">▸</i>
                                            <i data-feather="check-circle" class="me-1" style="width: 18px; height: 18px;"></i>
                                            4. Contrôles de cohérence
                                        </span>
                                    </summary>
                                <div class="pt-3">
                                <!-- Sous-section: Vérification cohérence montants -->
                                <div class="row g-3 mb-3">
                                    <div class="col-12">
                                        <div id="coherence-info" class="alert alert-success">
                                            <i data-feather="check" class="me-2" style="width: 16px; height: 16px; display: inline;"></i>
                                            <span>Aucune incohérence bloquante détectée.</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Sous-section: Statut OCR -->
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                            <label class="form-label fw-500 mb-0"><i data-feather="camera" class="me-2" style="width: 16px; height: 16px;"></i>Vérification du document (OCR)</label>
                                            <a href="{{ route('accounting.documents') }}" class="btn btn-sm btn-outline-info">
                                                <i data-feather="camera" class="me-1" style="width: 14px; height: 14px;"></i>OCR
                                            </a>
                                        </div>
                                        <div id="ocr-status" class="alert alert-light border">
                                            <i data-feather="info" class="me-2" style="width: 16px; height: 16px; display: inline;"></i>
                                            <span id="ocr-message">
                                                @if($prefillDocument)
                                                    Document OCR sélectionné : <strong>{{ $prefillDocument->original_name }}</strong>. Vous pouvez créer l’écriture sans recharger le fichier.
                                                @else
                                                    Aucun fichier uploadé
                                                @endif
                                            </span>
                                        </div>
                                        <small class="form-text text-muted d-block">L'OCR vérifiera automatiquement que le montant du document correspond au montant saisi.</small>
                                    </div>
                                </div>
                                @if($prefillDocument)
                                    <div class="row g-3 mt-1">
                                        <div class="col-12">
                                            <details class="collapsible-section" open>
                                                <summary class="text-info fw-semibold">
                                                    <span class="collapsible-section-toggle">
                                                        <i class="collapsible-chevron">▸</i>
                                                        Informations facture détectées (OCR)
                                                    </span>
                                                </summary>
                                                <div class="table-responsive mt-2">
                                                    <table class="table table-sm ocr-facture-table mb-0">
                                                        <tbody>
                                                            <tr>
                                                                <td>Document source</td>
                                                                <td>{{ $prefillDocument->original_name }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Référence facture</td>
                                                                <td>{{ $prefillExtracted['invoice_number'] ?? $prefillPrimary['invoice_number'] ?? 'Non détectée' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Date facture</td>
                                                                <td>{{ $prefillExtracted['invoice_date'] ?? $prefillPrimary['invoice_date'] ?? 'Non détectée' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Partenaire principal</td>
                                                                <td>{{ $prefillExtracted['partner'] ?? $prefillPrimary['partner_name'] ?? 'Non détecté' }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Fournisseur / Client</td>
                                                                <td>
                                                                    Fournisseur: {{ $prefillPrimary['supplier_name'] ?? 'N/A' }}<br>
                                                                    Client: {{ $prefillPrimary['client_name'] ?? 'N/A' }}
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Montants</td>
                                                                <td>
                                                                    HT: {{ isset($prefillExtracted['amount_ht']) ? number_format((float) $prefillExtracted['amount_ht'], 2, ',', ' ') : 'N/A' }}<br>
                                                                    TVA: {{ isset($prefillExtracted['tva']) ? number_format((float) $prefillExtracted['tva'], 2, ',', ' ') : 'N/A' }}<br>
                                                                    TTC: {{ isset($prefillExtracted['amount_ttc']) ? number_format((float) $prefillExtracted['amount_ttc'], 2, ',', ' ') : 'N/A' }} {{ $prefillExtracted['currency'] ?? $prefillPrimary['currency'] ?? 'FCFA' }}
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Contacts</td>
                                                                <td>
                                                                    Emails: {{ !empty($prefillContacts['emails']) ? implode(', ', (array) $prefillContacts['emails']) : 'N/A' }}<br>
                                                                    Téléphones: {{ !empty($prefillContacts['phones']) ? implode(', ', (array) $prefillContacts['phones']) : 'N/A' }}
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Identifiants</td>
                                                                <td>
                                                                    Fiscaux: {{ !empty($prefillIdentifiers['tax_ids']) ? implode(', ', (array) $prefillIdentifiers['tax_ids']) : 'N/A' }}<br>
                                                                    Business: {{ !empty($prefillIdentifiers['business_ids']) ? implode(', ', (array) $prefillIdentifiers['business_ids']) : 'N/A' }}
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Adresse</td>
                                                                <td>
                                                                    Fournisseur: {{ $prefillAddresses['supplier_address'] ?? 'N/A' }}<br>
                                                                    Client: {{ $prefillAddresses['client_address'] ?? 'N/A' }}
                                                                </td>
                                                            </tr>
                                                            <tr>
                                                                <td>Paiement / Banque</td>
                                                                <td>
                                                                    Conditions: {{ !empty($prefillPayment['terms']) ? implode(' | ', (array) $prefillPayment['terms']) : 'N/A' }}<br>
                                                                    Échéances: {{ !empty($prefillPayment['due_dates']) ? implode(', ', (array) $prefillPayment['due_dates']) : 'N/A' }}<br>
                                                                    IBAN: {{ !empty($prefillBanking['iban']) ? implode(', ', (array) $prefillBanking['iban']) : 'N/A' }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </details>
                                        </div>
                                    </div>
                                @endif
                                </div>
                                </details>
                            </div>
                        </div>
                        
                        <!-- BOUTONS D'ACTION -->
                        <div class="d-flex gap-2 justify-content-end">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i data-feather="x-circle" class="me-1"></i>Réinitialiser
                            </button>
                            <button type="submit" id="createEntrySubmitBtn" class="btn btn-primary">
                                <i data-feather="check-circle" class="me-1"></i>Créer entrée
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="alert alert-warning border-0 shadow-sm rounded-3 d-flex align-items-center gap-3 p-3">
                <i data-feather="lock" class="text-warning flex-shrink-0" style="width: 24px; height: 24px;"></i>
                <div>
                    <strong class="d-block text-dark">Mode Consultation Uniquement (Lecture Seule)</strong>
                    <span class="small text-muted">Seuls les administrateurs et les comptables du cabinet peuvent saisir ou modifier directement des écritures comptables. Vous pouvez consulter les rapports et transmettre vos pièces justificatives.</span>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        // Gestion du statut OCR
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || '';
            const ocrStatus = document.getElementById('ocr-status');
            
            if (fileName) {
                ocrStatus.className = 'alert alert-info border';
                document.getElementById('ocr-message').innerHTML = 
                    '<i data-feather="loader" class="me-2" style="width: 16px; height: 16px; display: inline; animation: spin 1s linear infinite;"></i>' +
                    'OCR en cours pour: <strong>' + fileName + '</strong><br>' +
                    '<small class="mt-2 d-block">Le montant sera vérifié après la création de l\'entrée</small>';
            } else {
                ocrStatus.className = 'alert alert-light border';
                document.getElementById('ocr-message').innerHTML = 'Aucun fichier uploadé';
            }
            
            // Redessiner les icônes Feather si nouveau DOM
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });

        // Calcul automatique HT, TVA, TTC
        function calculateAmounts() {
            const ht = parseFloat(document.getElementById('htAmount').value) || 0;
            const tvaRate = parseFloat(document.getElementById('tvaRate').value) || 0;
            const tva = ht * (tvaRate / 100);
            const ttc = ht + tva;
            
            document.getElementById('tvaAmount').value = tva.toFixed(2);
            document.getElementById('ttcAmount').value = ttc.toFixed(2);
        }

        // Boutons rapides TVA
        function setTvaRate(rate) {
            document.getElementById('tvaRate').value = rate;
            calculateAmounts();
        }

        // Event listeners pour calcul en temps réel
        document.getElementById('htAmount').addEventListener('input', calculateAmounts);
        document.getElementById('tvaRate').addEventListener('input', calculateAmounts);

        // Recherche/sélection de compte dans le plan comptable de l'entreprise
        // (autocomplétion serveur, ne charge jamais les 1455 comptes d'un coup).
        (function setupAccountPickers() {
            ['debit', 'credit'].forEach(function (side) {
                const searchInput = document.querySelector('[data-account-search="' + side + '"]');
                const classFilter = document.querySelector('[data-account-class-filter="' + side + '"]');
                const resultsBox = document.querySelector('[data-account-results="' + side + '"]');
                const hiddenInput = document.getElementById(side + 'AccountValue');
                if (!searchInput || !resultsBox || !hiddenInput) {
                    return;
                }

                if (hiddenInput.value) {
                    searchInput.value = hiddenInput.value;
                }

                let debounceTimer = null;
                let abortController = null;

                function runSearch() {
                    const q = searchInput.value.trim();
                    const classe = classFilter ? classFilter.value : '';

                    if (abortController) {
                        abortController.abort();
                    }
                    abortController = new AbortController();

                    const params = new URLSearchParams();
                    if (q) params.set('q', q);
                    if (classe) params.set('classe', classe);

                    fetch('{{ route('accounting.comptes.search') }}?' + params.toString(), {
                        signal: abortController.signal,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (accounts) {
                            resultsBox.innerHTML = '';
                            if (!accounts.length) {
                                resultsBox.style.display = 'none';
                                return;
                            }
                            accounts.forEach(function (account) {
                                const item = document.createElement('button');
                                item.type = 'button';
                                item.className = 'list-group-item list-group-item-action py-1';
                                item.innerHTML = '<strong>' + account.numero_compte + '</strong> — ' + account.libelle_compte;
                                item.addEventListener('click', function () {
                                    hiddenInput.value = account.label;
                                    searchInput.value = account.label;
                                    resultsBox.style.display = 'none';
                                });
                                resultsBox.appendChild(item);
                            });
                            resultsBox.style.display = 'block';
                        })
                        .catch(function () { /* requête annulée ou erreur réseau, on ignore */ });
                }

                searchInput.addEventListener('input', function () {
                    hiddenInput.value = '';
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(runSearch, 200);
                });
                searchInput.addEventListener('focus', runSearch);
                if (classFilter) {
                    classFilter.addEventListener('change', runSearch);
                }

                document.addEventListener('click', function (event) {
                    if (!resultsBox.contains(event.target) && event.target !== searchInput) {
                        resultsBox.style.display = 'none';
                    }
                });
            });
        })();

        // Auto-sélection des comptes débit/crédit selon le type de document choisi
        // en étape 1, pour éviter d'avoir à rechercher les comptes à la main à
        // chaque écriture. L'utilisateur reste libre de corriger ensuite via la
        // recherche existante (taper dans le champ écrase la valeur proposée).
        (function setupDocumentTypeAccountDefaults() {
            const documentTypeSelect = document.getElementById('documentType');
            const accountDefaults = @json($documentTypeAccountDefaults ?? []);
            if (!documentTypeSelect || !accountDefaults) {
                return;
            }

            function applyDefaults(force) {
                const defaults = accountDefaults[documentTypeSelect.value];
                if (!defaults) {
                    return;
                }

                ['debit', 'credit'].forEach(function (side) {
                    const hiddenInput = document.getElementById(side + 'AccountValue');
                    const searchInput = document.querySelector('[data-account-search="' + side + '"]');
                    if (!hiddenInput || !searchInput) {
                        return;
                    }
                    if (!force && hiddenInput.value) {
                        return;
                    }
                    hiddenInput.value = defaults[side];
                    searchInput.value = defaults[side];
                });
            }

            documentTypeSelect.addEventListener('change', function () {
                applyDefaults(true);
            });

            // Au chargement (ex. type déjà pré-rempli depuis un document OCR), on
            // ne complète que les comptes encore vides pour ne jamais écraser une
            // valeur déjà choisie (ancienne saisie après erreur de validation...).
            applyDefaults(false);
        })();

        // Import ponctuel + analyse OCR depuis le formulaire de saisie : évite
        // d'avoir à passer par la page "Documents" pour pré-remplir l'écriture.
        (function setupImportAndAnalyze() {
            const fileInput = document.getElementById('fileInput');
            const importBtn = document.getElementById('importAndAnalyzeBtn');
            const statusBox = document.getElementById('entryPrefillStatus');
            const documentIdInput = document.getElementById('documentIdValue');
            if (!fileInput || !importBtn || !statusBox || !documentIdInput) {
                return;
            }

            function showStatus(message, variant) {
                statusBox.className = 'mb-3 alert alert-' + variant;
                statusBox.textContent = message;
                statusBox.style.display = 'block';
            }

            function setFieldValue(selector, value) {
                if (value === undefined || value === null || value === '') {
                    return;
                }
                const el = typeof selector === 'string' ? document.querySelector(selector) : selector;
                if (el) {
                    el.value = value;
                }
            }

            function fillAccountField(side, label) {
                if (!label) {
                    return;
                }
                const hiddenInput = document.getElementById(side + 'AccountValue');
                const searchInput = document.querySelector('[data-account-search="' + side + '"]');
                if (hiddenInput) hiddenInput.value = label;
                if (searchInput) searchInput.value = label;
            }

            fileInput.addEventListener('change', function () {
                importBtn.disabled = !fileInput.files.length;
            });

            importBtn.addEventListener('click', function () {
                if (!fileInput.files.length) {
                    return;
                }

                const formData = new FormData();
                formData.append('document', fileInput.files[0]);

                const csrfInput = importBtn.closest('form').querySelector('input[name="_token"]');

                importBtn.disabled = true;
                importBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Analyse en cours…';
                showStatus('Analyse OCR du document en cours, merci de patienter…', 'info');

                fetch('{{ route('accounting.documents.prefill') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfInput ? csrfInput.value : '',
                    },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.document_id) {
                            documentIdInput.value = data.document_id;
                        }

                        if (!data.success) {
                            showStatus(data.message || "L'analyse OCR a échoué. Complétez l'écriture manuellement.", 'warning');
                            return;
                        }

                        if (data.document_type) {
                            const typeSelect = document.getElementById('documentType');
                            if (typeSelect) typeSelect.value = data.document_type;
                        }
                        setFieldValue('input[name="partner_name"]', data.partner_name);
                        setFieldValue('input[name="date"]', data.date);
                        setFieldValue('input[name="document_reference"]', data.document_reference);
                        setFieldValue('textarea[name="description"]', data.description);
                        setFieldValue('#htAmount', data.amount);
                        setFieldValue('#tvaAmount', data.amount_tva);
                        setFieldValue('#ttcAmount', data.ttc_amount);
                        setFieldValue('#tvaRate', data.tva_rate);
                        fillAccountField('debit', data.debit_account);
                        fillAccountField('credit', data.credit_account);

                        showStatus(
                            data.review_required
                                ? '✓ Document analysé — champs pré-remplis. Certaines informations sont incertaines, merci de les vérifier avant d\'enregistrer.'
                                : '✓ Document analysé — champs pré-remplis. Vérifiez avant d\'enregistrer.',
                            data.review_required ? 'warning' : 'success'
                        );
                    })
                    .catch(function () {
                        showStatus("Erreur réseau pendant l'analyse du document. Réessayez.", 'danger');
                    })
                    .finally(function () {
                        importBtn.disabled = false;
                        importBtn.innerHTML = '<i data-feather="zap" style="width: 16px; height: 16px;"></i> Importer';
                        if (window.feather) feather.replace();
                    });
            });
        })();

        // Mode ultra ergonomique: une seule section du formulaire ouverte à la fois.
        (function setupAccountingAccordion() {
            const sections = Array.from(document.querySelectorAll('#moteur-ecritures details.js-accounting-section'));
            if (!sections.length) {
                return;
            }

            const openOnly = function (target) {
                sections.forEach(function (section) {
                    if (section !== target) {
                        section.removeAttribute('open');
                    }
                });
                target.setAttribute('open', 'open');
            };

            sections.forEach(function (section) {
                section.addEventListener('toggle', function () {
                    if (section.open) {
                        openOnly(section);
                    }
                });
            });

            const invalidField = document.querySelector('#moteur-ecritures .is-invalid');
            if (invalidField) {
                const invalidSection = invalidField.closest('details.js-accounting-section');
                if (invalidSection) {
                    openOnly(invalidSection);
                    return;
                }
            }

            @if($prefillDocument)
                const ocrSection = sections.find(function (section) {
                    return section.dataset.section === 'ocr';
                });
                if (ocrSection) {
                    openOnly(ocrSection);
                    return;
                }
            @endif

            openOnly(sections[0]);

            // Un champ obligatoire (ex: compte débit/crédit) resté dans une
            // section repliée par l'accordéon empêchait la validation HTML5
            // native de s'afficher au clic sur « Créer entrée » : le
            // navigateur bloquait l'envoi sans rien montrer à l'écran (pas de
            // rechargement, pas de message). On ouvre donc tout juste avant
            // l'envoi pour que la validation (et son message éventuel) reste
            // toujours visible.
            const submitBtn = document.getElementById('createEntrySubmitBtn');
            if (submitBtn) {
                submitBtn.addEventListener('click', function () {
                    sections.forEach(function (section) {
                        section.setAttribute('open', 'open');
                    });
                });
            }
        })();

        // Initial calculation
        calculateAmounts();
    </script>

    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <i data-feather="file-text" class="text-primary" style="width: 28px; height: 28px;"></i>
                    </div>
                    <h5 class="card-title">Écritures totales</h5>
                    <p class="text-muted small">{{ $entries->count() }} opérations enregistrées</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <i data-feather="arrow-down" class="text-danger" style="width: 28px; height: 28px;"></i>
                    </div>
                    <h5 class="card-title">Total débit</h5>
                    <p class="text-danger fw-bold">{{ number_format($totalDebit, 2, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <i data-feather="arrow-up" class="text-success" style="width: 28px; height: 28px;"></i>
                    </div>
                    <h5 class="card-title">Total crédit</h5>
                    <p class="text-success fw-bold">{{ number_format($totalCredit, 2, ',', ' ') }} FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-light border-0">
                <div class="card-body">
                    <div class="mb-3">
                        <i data-feather="percent" class="text-info" style="width: 28px; height: 28px;"></i>
                    </div>
                    <h5 class="card-title">Écart débit − crédit</h5>
                    <p class="text-info fw-bold mb-0">{{ number_format($balance, 2, ',', ' ') }} FCFA</p>
                    <p class="small text-muted mb-0 mt-1">Doit rester à 0 si chaque écriture est équilibrée.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i data-feather="list" class="me-2" style="width: 20px; height: 20px;"></i>Journal comptable</h5>
                </div>
                <div class="card-body">
                    <div id="journalTable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                        <div class="row mb-3">
                            <div class="col-sm-12 col-md-6">
                                <div class="dt-buttons flex-wrap">
                                    <div class="table-density-switch" role="group" aria-label="Densité tableau">
                                        <button type="button" id="densityComfortBtn" class="btn btn-light active">Confort</button>
                                        <button type="button" id="densityCompactBtn" class="btn btn-light">Compact</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-6">
                                <div id="journalTable_filter" class="dataTables_filter d-flex justify-content-end">
                                <form action="{{ route('accounting') }}" method="GET" class="d-flex flex-wrap align-items-end gap-2 m-0">
                                    <label class="mb-0 d-flex flex-column">
                                        <span class="small text-muted">Recherche</span>
                                        <input type="search" name="q" class="form-control form-control-sm" placeholder="Texte libre..." value="{{ request('q', '') }}">
                                    </label>

                                    <label class="mb-0 d-flex flex-column">
                                        <span class="small text-muted">Type</span>
                                        <select name="document_type" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">Tous</option>
                                            <option value="Vente" {{ request('document_type') === 'Vente' ? 'selected' : '' }}>Vente</option>
                                            <option value="Achat" {{ request('document_type') === 'Achat' ? 'selected' : '' }}>Achat</option>
                                            <option value="Reçu" {{ request('document_type') === 'Reçu' ? 'selected' : '' }}>Reçu</option>
                                            <option value="Justificatif" {{ request('document_type') === 'Justificatif' ? 'selected' : '' }}>Justificatif</option>
                                        </select>
                                    </label>

                                    <label class="mb-0 d-flex flex-column">
                                        <span class="small text-muted">Compte</span>
                                        <input type="text" name="account" class="form-control form-control-sm" placeholder="Débit / Crédit" value="{{ request('account', '') }}">
                                    </label>

                                    <label class="mb-0 d-flex flex-column">
                                        <span class="small text-muted">Du</span>
                                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from', '') }}">
                                    </label>

                                    <label class="mb-0 d-flex flex-column">
                                        <span class="small text-muted">Au</span>
                                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to', '') }}">
                                    </label>

                                    <button type="submit" class="btn btn-sm btn-primary">Filtrer</button>
                                    <a href="{{ route('accounting') }}" class="btn btn-sm btn-outline-secondary">Effacer</a>
                                </form>
                            </div>
                            </div>
                        </div>
                        <div id="bulkActionBar" class="alert alert-light border d-none d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <strong><span id="bulkSelectedCount">0</span></strong> écriture(s) sélectionnée(s)
                            </div>
                            <div class="d-flex gap-2">
                                <button type="button" id="bulkRetryBtn" class="btn btn-sm btn-outline-warning">
                                    <i data-feather="refresh-cw" class="me-1"></i>Relancer OCR (lot)
                                </button>
                                <button type="button" id="bulkDeleteBtn" class="btn btn-sm btn-outline-danger">
                                    <i data-feather="trash-2" class="me-1"></i>Supprimer (lot)
                                </button>
                            </div>
                        </div>
                        <div class="row dt-row">
                            <div class="col-sm-12">
                                <div class="table-responsive">
                                    <table id="journalTable" class="table table-striped table-hover dataTable no-footer" role="grid" aria-describedby="journalTable_info">
                                        <thead>
                                            <tr role="row">
                                                <th class="sorting_disabled" rowspan="1" colspan="1">
                                                    <input type="checkbox" id="selectAllEntries" title="Tout sélectionner">
                                                </th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Date</th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Document</th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Description</th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Débit</th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Crédit</th>
                                                <th class="sorting text-end" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">Montant</th>
                                                <th class="sorting" tabindex="0" aria-controls="journalTable" rowspan="1" colspan="1">OCR</th>
                                                <th class="sorting_disabled" rowspan="1" colspan="1">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($entries as $index => $entry)
                                                <tr class="odd">
                                                    <td data-label="Sélection">
                                                        <input type="checkbox" class="entry-select-checkbox" value="{{ $entry->id }}">
                                                    </td>
                                                    <td data-label="Date">{{ $entry->date->format('d/m/Y') }}</td>
                                                    <td data-label="Document">
                                                        @php
                                                            $documentName = $entry->getSourceDocumentName();
                                                            $viewerUrl = $documentName ? route('accounting.entries.document.viewer', $entry) : null;
                                                        @endphp
                                                        <span class="badge bg-light-primary text-primary">{{ substr($entry->document_type, 0, 3) }}</span>
                                                        @if($documentName)
                                                            <div class="small mt-1">
                                                                @if($viewerUrl)
                                                                    <a href="{{ $viewerUrl }}" class="text-decoration-none">
                                                                        {{ strlen($documentName) > 32 ? substr($documentName, 0, 32) . '...' : $documentName }}
                                                                    </a>
                                                                @else
                                                                    {{ strlen($documentName) > 32 ? substr($documentName, 0, 32) . '...' : $documentName }}
                                                                @endif
                                                            </div>
                                                        @else
                                                            <div class="small text-muted mt-1">Aucun fichier lié</div>
                                                        @endif
                                                    </td>
                                                    <td data-label="Description">{{ substr($entry->description, 0, 40) }}{{ strlen($entry->description) > 40 ? '...' : '' }}</td>
                                                    <td data-label="Débit"><code>{{ $entry->debit_account }}</code></td>
                                                    <td data-label="Crédit"><code>{{ $entry->credit_account }}</code></td>
                                                    <td data-label="Montant" class="text-end"><strong class="text-success">{{ number_format($entry->amount, 2, ',', ' ') }} FCFA</strong></td>
                                                    <td data-label="OCR">
                                                        @php
                                                            $badge = $entry->getOcrBadge();
                                                            $ocrTableDetail = $entry->getOcrTableDetail();
                                                            $badgeColor = [
                                                                'success' => 'bg-light-success text-success',
                                                                'warning' => 'bg-light-warning text-warning',
                                                                'danger' => 'bg-light-danger text-danger',
                                                                'secondary' => 'bg-light-secondary text-secondary'
                                                            ];
                                                        @endphp
                                                        <span class="badge {{ $badgeColor[$badge['color']] }}">{{ $badge['text'] }}</span>
                                                        @if($ocrTableDetail)
                                                            <div class="small mt-1 text-muted">
                                                                {{ strlen($ocrTableDetail) > 60 ? substr($ocrTableDetail, 0, 60) . '...' : $ocrTableDetail }}
                                                            </div>
                                                        @endif
                                                        @if($viewerUrl)
                                                            <div class="small mt-1">
                                                                <a href="{{ $viewerUrl }}" class="text-decoration-none">
                                                                    Voir le document OCR
                                                                </a>
                                                            </div>
                                                        @else
                                                            <div class="small text-muted mt-1">Aucun document OCR</div>
                                                        @endif
                                                    </td>
                                                    <td data-label="Actions">
                                                        <div class="table-actions">
                                                            <a href="{{ route('accounting.entries.show', $entry) }}" class="btn btn-sm btn-white" title="Visualiser" aria-label="Visualiser">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.12 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                                                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                                                </svg>
                                                            </a>
                                                            @if($entry->ocr_status === 'failed')
                                                                <a href="{{ route('accounting.entries.show', $entry) }}" class="btn btn-sm btn-white text-warning" title="Détail OCR" aria-label="Détail OCR">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                        <path d="M7.938 2.016a.13.13 0 0 1 .125 0 .13.13 0 0 1 .054.054l6.857 11.667c.046.078.048.138.03.175a.17.17 0 0 1-.078.072.2.2 0 0 1-.093.016H1.167a.2.2 0 0 1-.093-.016.17.17 0 0 1-.078-.072c-.017-.037-.015-.097.03-.175L7.884 2.07a.13.13 0 0 1 .054-.054M8 4.5a.5.5 0 0 0-.5.5v4a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5m0 7a.75.75 0 1 0 0-1.5A.75.75 0 0 0 8 11.5"/>
                                                                    </svg>
                                                                </a>
                                                            @endif
                                                            @if($entry->attachment_path && !in_array($entry->ocr_status, ['verified', 'manual_verified'], true))
                                                                <form action="{{ route('accounting.entries.ocr.retry', $entry) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    <button type="submit" class="btn btn-sm btn-white btn-ocr-action" title="OCR automatique" aria-label="OCR automatique">
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                            <path d="M11.534 7h3.932a.5.5 0 0 0 .392-.812l-2.028-2.5a.5.5 0 0 0-.778.624L14.434 6h-2.9A5.5 5.5 0 0 0 1.07 7.2a.5.5 0 1 0 .98.196A4.5 4.5 0 0 1 11.534 7"/>
                                                                            <path d="M4.466 9H.534a.5.5 0 0 0-.392.812l2.028 2.5a.5.5 0 1 0 .778-.624L1.566 10h2.9a4.5 4.5 0 0 1 4.484 4.1.5.5 0 0 0 .995-.102A5.5 5.5 0 0 0 4.466 9"/>
                                                                        </svg>
                                                                        <span>OCR</span>
                                                                    </button>
                                                                </form>
                                                            @elseif($entry->attachment_path && in_array($entry->ocr_status, ['verified', 'manual_verified'], true))
                                                                <span class="badge bg-light-success text-success" title="OCR déjà validé">Déjà vérifié</span>
                                                            @endif
                                                            @if(in_array($entry->ocr_status, ['mismatch', 'mismatched'], true) && $viewerUrl)
                                                                <a href="{{ route('accounting.entries.show', $entry) }}" class="btn btn-sm btn-white text-success" title="Aperçu correction OCR" aria-label="Aperçu correction OCR">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                        <path d="M8 0a.5.5 0 0 1 .5.5v2.036a5.5 5.5 0 0 1 4.964 4.964H15.5a.5.5 0 0 1 0 1h-2.036a5.5 5.5 0 0 1-4.964 4.964V15.5a.5.5 0 0 1-1 0v-2.036A5.5 5.5 0 0 1 2.536 8.5H.5a.5.5 0 0 1 0-1h2.036A5.5 5.5 0 0 1 7.5 2.536V.5A.5.5 0 0 1 8 0m0 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8m1.354 2.146a.5.5 0 0 1 0 .708L8.207 8l1.147 1.146a.5.5 0 0 1-.708.708L7.146 8.354a.5.5 0 0 1 0-.708l1.5-1.5a.5.5 0 0 1 .708 0"/>
                                                                    </svg>
                                                                </a>
                                                            @endif
                                                            <a href="{{ route('accounting.entries.edit', $entry) }}" class="btn btn-sm btn-white" title="Modifier" aria-label="Modifier">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706l-1.793 1.793-2.147-2.147 1.793-1.793a.5.5 0 0 1 .707 0z"/>
                                                                    <path d="m1 13.5 8.06-8.06 2.147 2.147L3.146 15.646A.5.5 0 0 1 2.793 15H1.5a.5.5 0 0 1-.5-.5v-1.293a.5.5 0 0 1 .146-.353"/>
                                                                </svg>
                                                            </a>
                                                            <form action="{{ route('accounting.entries.destroy', $entry) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-sm btn-white text-danger" title="Supprimer" aria-label="Supprimer" onclick="return confirm('Confirmer la suppression de cette écriture ?');">
                                                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                                                                        <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0A.5.5 0 0 1 8.5 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                                                                        <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 1 1 0-2H5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1h2.5a1 1 0 0 1 1 1M6 2v1h4V2z"/>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-12 col-md-5">
                                <div class="dataTables_info" id="journalTable_info" role="status" aria-live="polite">
                                </div>
                            </div>
                            <div class="col-sm-12 col-md-7">
                                <div class="dataTables_paginate paging_simple_numbers" id="journalTable_paginate">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-4">
        <div class="col-xl-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i data-feather="book" class="me-2" style="width: 20px; height: 20px;"></i>Grand livre simplifié</h5></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Regrouper les écritures par compte.</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>Compte</th>
                                    <th class="text-end">Débit</th>
                                    <th class="text-end">Crédit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ledger as $account => $amounts)
                                    <tr>
                                        <td>{{ $account }}</td>
                                        <td class="text-end">{{ number_format($amounts['debit'], 2, ',', ' ') }} FCFA</td>
                                        <td class="text-end">{{ number_format($amounts['credit'], 2, ',', ' ') }} FCFA</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0"><i data-feather="bar-chart-2" class="me-2" style="width: 20px; height: 20px;"></i>Compte de résultat</h5></div>
                <div class="card-body">
                    <p class="text-muted mb-3">Synthèse des produits et charges.</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td>Produits</td>
                                    <td class="text-end">{{ number_format($income, 2, ',', ' ') }} FCFA</td>
                                </tr>
                                <tr>
                                    <td>Charges</td>
                                    <td class="text-end">{{ number_format($expenses, 2, ',', ' ') }} FCFA</td>
                                </tr>
                                <tr class="border-top">
                                    <th>Résultat net</th>
                                    <th class="text-end">{{ number_format($income - $expenses, 2, ',', ' ') }} FCFA</th>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            const bulkActionBar = document.getElementById('bulkActionBar');
            const bulkSelectedCount = document.getElementById('bulkSelectedCount');
            const selectAllEntries = document.getElementById('selectAllEntries');
            const bulkRetryBtn = document.getElementById('bulkRetryBtn');
            const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

            function getSelectedEntryIds() {
                return Array.from(document.querySelectorAll('.entry-select-checkbox:checked')).map(function (el) {
                    return el.value;
                });
            }

            function updateBulkBar() {
                const ids = getSelectedEntryIds();
                if (bulkSelectedCount) bulkSelectedCount.textContent = ids.length;
                if (bulkActionBar) bulkActionBar.classList.toggle('d-none', ids.length === 0);
                if (selectAllEntries) selectAllEntries.checked = ids.length > 0 && ids.length === document.querySelectorAll('.entry-select-checkbox').length;
            }

            function submitBulkAction(url, ids) {
                if (!ids.length) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;

                const csrf = document.createElement('input');
                csrf.type = 'hidden';
                csrf.name = '_token';
                csrf.value = '{{ csrf_token() }}';
                form.appendChild(csrf);

                ids.forEach(function (id) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'entry_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }

            (function() {
                var stateKey = 'DataTables_journalTable_' + document.location.pathname;
                try {
                    var saved = JSON.parse(localStorage.getItem(stateKey));
                    var actualColumnCount = document.querySelectorAll('#journalTable thead th').length;
                    if (saved && Array.isArray(saved.columns) && saved.columns.length !== actualColumnCount) {
                        localStorage.removeItem(stateKey);
                    }
                } catch (e) {
                    localStorage.removeItem(stateKey);
                }
            })();

            var table = $('#journalTable').DataTable({
                dom: 'Brtip',
                buttons: [
                    {
                        extend: 'copy',
                        text: '<i data-feather="copy" class="icon icon-xs me-2"></i>Copier',
                        className: 'dt-button buttons-copy buttons-html5 btn btn-gray-800'
                    },
                    {
                        extend: 'colvis',
                        text: '<i data-feather="columns" class="icon icon-xs me-2"></i>Colonnes',
                        className: 'dt-button buttons-colvis btn btn-gray-800'
                    },
                    {
                        extend: 'print',
                        text: '<i data-feather="printer" class="icon icon-xs me-2"></i>Imprimer',
                        className: 'dt-button buttons-print btn btn-gray-800'
                    }
                ],
                language: {
                    lengthMenu: "",
                    info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                    infoEmpty: "Affichage de 0 à 0 sur 0 entrées",
                    infoFiltered: "",
                    emptyTable: "Aucune écriture comptable enregistrée.",
                    zeroRecords: "Aucune écriture ne correspond à la recherche.",
                    paginate: {
                        first: "Premier",
                        last: "Dernier",
                        next: "Suivant",
                        previous: "Précédent"
                    }
                },
                pageLength: 10,
                order: [[1, 'desc']],
                stateSave: true,
                responsive: {
                    details: {
                        type: 'inline',
                        renderer: function (api, rowIdx, columns) {
                            const hiddenRows = columns
                                .filter(function (col) {
                                    return col.hidden;
                                })
                                .map(function (col) {
                                    const title = (col.title || '').replace(/<[^>]*>/g, '').trim();
                                    if (!title) return '';
                                    return '<tr><td>' + title + '</td><td>' + col.data + '</td></tr>';
                                })
                                .join('');

                            if (!hiddenRows) {
                                return false;
                            }

                            return $('<table class="ocr-detail-table"/>').append(hiddenRows);
                        }
                    }
                },
                columnDefs: [
                    { orderable: false, targets: [0, 8] }
                ],
                initComplete: function() {
                    feather.replace();
                    $('.dt-buttons').appendTo('.dataTables_wrapper .row:eq(0) .col-sm-6:eq(0)');
                }
            });

            const journalTable = document.getElementById('journalTable');
            const densityComfortBtn = document.getElementById('densityComfortBtn');
            const densityCompactBtn = document.getElementById('densityCompactBtn');
            const densityStorageKey = 'accounting_table_density';

            function setDensity(mode) {
                if (!journalTable || !densityComfortBtn || !densityCompactBtn) {
                    return;
                }

                const isCompact = mode === 'compact';
                journalTable.classList.toggle('table-compact', isCompact);
                densityComfortBtn.classList.toggle('active', !isCompact);
                densityCompactBtn.classList.toggle('active', isCompact);
                window.localStorage.setItem(densityStorageKey, mode);

                table.columns.adjust();
                if (table.responsive && typeof table.responsive.recalc === 'function') {
                    table.responsive.recalc();
                }
            }

            densityComfortBtn.addEventListener('click', function () {
                setDensity('comfort');
            });

            densityCompactBtn.addEventListener('click', function () {
                setDensity('compact');
            });

            const savedDensity = window.localStorage.getItem(densityStorageKey);
            setDensity(savedDensity === 'compact' ? 'compact' : 'comfort');

            document.addEventListener('change', function (event) {
                if (event.target.matches('.entry-select-checkbox')) {
                    updateBulkBar();
                }
            });

            if (selectAllEntries) {
                selectAllEntries.addEventListener('change', function () {
                    const checked = selectAllEntries.checked;
                    document.querySelectorAll('.entry-select-checkbox').forEach(function (checkbox) {
                        checkbox.checked = checked;
                    });
                    updateBulkBar();
                });
            }

            if (bulkDeleteBtn) {
                bulkDeleteBtn.addEventListener('click', function () {
                    const ids = getSelectedEntryIds();
                    if (!ids.length) return;
                    if (!confirm('Confirmer la suppression de ' + ids.length + ' écriture(s) ?')) return;
                    submitBulkAction('{{ route('accounting.entries.bulk.delete') }}', ids);
                });
            }

            if (bulkRetryBtn) {
                bulkRetryBtn.addEventListener('click', function () {
                    const ids = getSelectedEntryIds();
                    if (!ids.length) return;
                    if (!confirm('Relancer OCR pour ' + ids.length + ' écriture(s) ?')) return;
                    submitBulkAction('{{ route('accounting.entries.bulk.ocr.retry') }}', ids);
                });
            }
        });
    </script>
    </div>
@endpush
@endsection
