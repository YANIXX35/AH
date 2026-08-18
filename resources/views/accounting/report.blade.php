@extends('layouts.app')

@section('title', 'Rapport comptable | Sitiame Capital')
@section('page_title', 'Rapport comptable')

@section('content')
    @php
        $reportTitles = [
            'full' => 'Rapport complet',
            'journal' => 'Journal',
            'grand-livre' => 'Grand livre',
            'balance' => 'Balance',
            'bilan' => 'Bilan',
            'resultat' => 'Compte de résultat',
            'tafire' => 'TAFIRE',
            'annexe' => 'État annexe',
        ];
        $currentReportTitle = $reportTitles[$reportType] ?? 'Rapport comptable';
    @endphp
    <style>
        .print-hidden { display: none !important; }
        .page-break { page-break-after: always; }
        .journal-table th { white-space: nowrap; }
        .journal-model-table {
            font-size: .78rem;
        }
        .journal-model-table thead th {
            background: #0b3d91;
            color: #fff;
            border-color: #0b3d91;
            font-weight: 700;
            padding: .35rem .4rem;
        }
        .journal-model-table td {
            padding: .28rem .4rem;
            vertical-align: top;
            line-height: 1.2;
        }
        .journal-model-table tbody tr:nth-child(even) td {
            background: #fafbff;
        }
        .journal-model-table .w-date { width: 72px; }
        .journal-model-table .w-journal { width: 44px; }
        .journal-model-table .w-piece { width: 74px; }
        .journal-model-table .w-compte { width: 56px; }
        .journal-model-table .w-intitule { min-width: 150px; }
        .journal-model-table .w-libelle { min-width: 190px; }
        .journal-model-table .w-montant { width: 82px; }
        .journal-model-table .w-tiers { width: 100px; }
        .journal-model-table .w-centre { width: 92px; }
        .journal-model-table .w-controle { width: 82px; }
        .journal-model-table .w-no { width: 64px; }
        .journal-model-table .w-cpte { width: 82px; }
        .journal-model-table .amount-cell {
            color: #1f3fbf;
            font-weight: 700;
        }
        .journal-model-table .control-cell {
            color: #b02a37;
            font-weight: 600;
        }
        .journal-model-table .tier-cell {
            color: #1f3fbf;
        }
        .grandlivre-account-block {
            border: 1px solid #dbe5f4;
            border-radius: .6rem;
            background: #fff;
            box-shadow: 0 4px 14px rgba(15, 35, 95, .06);
            overflow: hidden;
        }
        .grandlivre-model-title {
            background: linear-gradient(90deg, #0b3d91 0%, #1f57ba 100%);
            color: #fff;
            font-weight: 700;
            padding: .5rem .75rem;
            letter-spacing: .3px;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
            flex-wrap: wrap;
        }
        .grandlivre-model-title .subtitle {
            font-size: .72rem;
            opacity: .92;
            font-weight: 500;
            letter-spacing: .2px;
            text-transform: none;
        }
        .grandlivre-meta-table {
            width: calc(100% - 1.5rem);
            margin: .75rem;
            border-collapse: separate;
            border-spacing: 0;
            font-size: .86rem;
            border: 1px solid #e7edf7;
            border-radius: .45rem;
            overflow: hidden;
        }
        .grandlivre-meta-table td {
            border-bottom: 1px solid #e7edf7;
            border-right: 1px solid #e7edf7;
            padding: .42rem .55rem;
            vertical-align: top;
            background: #fff;
        }
        .grandlivre-meta-table tr:last-child td { border-bottom: 0; }
        .grandlivre-meta-table td:last-child { border-right: 0; }
        .grandlivre-meta-table .meta-value {
            color: #0f3c93;
            font-weight: 700;
        }
        .grandlivre-check-line {
            display: flex;
            align-items: center;
            gap: .35rem;
            flex-wrap: wrap;
        }
        .grandlivre-kpi-row {
            display: flex;
            gap: .5rem;
            margin: 0 .75rem .6rem;
            flex-wrap: wrap;
        }
        .grandlivre-kpi {
            border: 1px solid #d8e3f7;
            border-radius: 999px;
            padding: .22rem .6rem;
            font-size: .76rem;
            color: #214785;
            background: #f6f9ff;
            font-weight: 600;
        }
        .grandlivre-table-wrap {
            padding: 0 .75rem .75rem;
            max-height: 27rem;
            overflow: auto;
        }
        .grandlivre-model-table thead th {
            background: #0b3d91;
            color: #fff;
            border-color: #0b3d91;
            padding: .45rem .4rem;
            position: sticky;
            top: 0;
            z-index: 2;
            white-space: nowrap;
        }
        .grandlivre-model-table td {
            padding: .34rem .4rem;
            line-height: 1.24;
            vertical-align: middle;
        }
        .grandlivre-model-table tbody tr:hover td {
            background: #f3f7ff;
        }
        .grandlivre-model-table .journal-pill {
            display: inline-block;
            min-width: 2rem;
            text-align: center;
            border-radius: 999px;
            border: 1px solid #cad9f5;
            background: #eef4ff;
            color: #173f8f;
            font-weight: 700;
            padding: .06rem .45rem;
            font-size: .72rem;
        }
        .grandlivre-model-table .amount {
            color: #1f3fbf;
            font-weight: 700;
        }
        .grandlivre-model-table .amount.balance-negative {
            color: #b02a37;
        }
        .balance-model-table {
            font-size: .74rem;
            table-layout: auto;
            min-width: 2200px;
        }
        .balance-model-table thead th {
            background: #0b3d91;
            color: #fff;
            border-color: #0b3d91;
            padding: .25rem .3rem;
            white-space: nowrap;
        }
        .balance-model-table td {
            padding: .14rem .28rem;
            line-height: 1.1;
            vertical-align: middle;
        }
        .balance-model-table .acc-cell {
            color: #1c8b3c;
            font-weight: 700;
        }
        .balance-model-table .amount {
            color: #1f3fbf;
            font-weight: 700;
        }
        .balance-model-table .obs-none {
            color: #b8682d;
            font-weight: 600;
        }
        .balance-model-table .obs-move {
            color: #1c8b3c;
            font-weight: 700;
        }
        .balance-table-wrap {
            overflow-x: auto;
        }
        .resultat-page {
            margin-bottom: 1rem;
        }
        .resultat-head-box {
            border: 1px solid #000;
            text-align: center;
            font-weight: 700;
            padding: .15rem .4rem;
            line-height: 1.25;
            margin-bottom: .4rem;
        }
        .resultat-meta {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: .35rem;
            font-size: .86rem;
        }
        .resultat-meta td {
            border-bottom: 1px solid #000;
            padding: .12rem .25rem;
        }
        .resultat-model-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .86rem;
        }
        .resultat-model-table th,
        .resultat-model-table td {
            border: 1px solid #000;
            padding: .2rem .28rem;
            vertical-align: middle;
        }
        .resultat-model-table thead th {
            background: #fff;
            font-weight: 700;
        }
        .resultat-model-table .section-row td {
            font-weight: 700;
            background: #fff;
        }
        .resultat-model-table .total-row td {
            background: #d9d9d9;
            font-weight: 700;
        }
        .resultat-model-table .hint-row td {
            font-style: italic;
        }
        .resultat-model-table .ref-col { width: 48px; }
        .resultat-model-table .n-col,
        .resultat-model-table .n1-col { width: 120px; text-align: right; }
        .journal-print-header { display: none; }
        
        @if($reportType !== 'full')
            @if($reportType === 'journal')
                #grand-livre-section, #balance-section, #bilan-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+1) { display: none !important; }
            @elseif($reportType === 'grand-livre')
                #journal-section, #balance-section, #bilan-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(1), .page-break:nth-of-type(n+3) { display: none !important; }
            @elseif($reportType === 'balance')
                #journal-section, #grand-livre-section, #bilan-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @elseif($reportType === 'bilan')
                #journal-section, #grand-livre-section, #balance-section, #resultat-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+3) { display: none !important; }
            @elseif($reportType === 'resultat')
                #journal-section, #grand-livre-section, #balance-section, #bilan-section, #tafire-section, #annexe-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @elseif($reportType === 'tafire')
                #journal-section, #grand-livre-section, #balance-section, #bilan-section, #resultat-section, #annexe-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @elseif($reportType === 'annexe')
                #journal-section, #grand-livre-section, #balance-section, #bilan-section, #resultat-section, #tafire-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @endif
        @endif
        
        @media print {
            .no-print { display: none !important; }
            .page-break { display: block; }
        }

        @media print {
            body.print-mode-journal-section .report-section { display: none !important; }
            body.print-mode-journal-section #journal-section { display: block !important; }
            body.print-mode-journal-section #journal-section * { visibility: visible !important; }
            body.print-mode-journal-section .report-header { display: none !important; }
            body.print-mode-journal-section .journal-print-header {
                display: flex !important;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 1rem;
                border-bottom: 1px solid #000;
                padding-bottom: .75rem;
            }
            body.print-mode-journal-section .journal-print-header .meta {
                font-size: .86rem;
                line-height: 1.45;
            }
            body.print-mode-journal-section .journal-table th,
            body.print-mode-journal-section .journal-table td {
                font-size: .82rem;
                padding: .25rem .35rem;
            }
            body.print-mode-journal-section .journal-table .badge {
                border: 1px solid #000 !important;
                background: #fff !important;
                color: #000 !important;
            }
            body.print-mode-journal-section .page-break { display: none !important; }
        }

        @if($reportType === 'bilan')
            @media print {
                body * { visibility: hidden !important; }
                .report-header, #bilan-section { visibility: visible !important; }
                .report-header, #bilan-section, #bilan-section * { display: block !important; }
                .report-header .text-md-end { display: none !important; }
                .report-header { margin-bottom: 1.5rem !important; }
                .page-break { display: none !important; }
            }
        @endif

        .bilan-header {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem 2rem;
            margin-bottom: 1.5rem;
        }

        .bilan-header .field {
            font-size: .92rem;
            line-height: 1.5;
        }

        .bilan-header .field span {
            display: block;
            font-weight: 600;
            margin-bottom: .25rem;
        }

        .bilan-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .92rem;
        }

        .bilan-table th,
        .bilan-table td {
            border: 1px solid #000;
            padding: .55rem .7rem;
            vertical-align: middle;
        }

        .bilan-table th {
            background: #f1f1f1;
            font-weight: 700;
        }

        .bilan-table .section-title td {
            background: #e9ecef;
            font-weight: 700;
            border-top: 2px solid #000;
        }

        .bilan-table .text-center {
            text-align: center;
        }

        .bilan-table .text-end {
            text-align: right;
        }

        .bilan-table .subtext {
            font-size: .84rem;
            color: #6c757d;
        }
        .tafire-meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: .75rem;
            font-size: .86rem;
        }
        .tafire-meta td {
            border-bottom: 1px solid #000;
            padding: .3rem .45rem;
        }
        .tafire-model-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .84rem;
        }
        .tafire-model-table th,
        .tafire-model-table td {
            border: 1px solid #000;
            padding: .35rem .45rem;
            vertical-align: middle;
        }
        .tafire-model-table thead th {
            background: #f1f1f1;
            font-weight: 700;
            text-align: center;
        }
        .tafire-model-table .section-row td {
            background: #e9ecef;
            font-weight: 700;
        }
        .tafire-model-table .total-row td {
            background: #d9d9d9;
            font-weight: 700;
        }
        .tafire-model-table .ref-col {
            width: 58px;
            text-align: center;
            font-weight: 700;
        }
        .tafire-model-table .flux-col {
            width: 120px;
            text-align: right;
        }
        .annexe-block-title {
            border: 1px solid #000;
            background: #f8f9fa;
            padding: .45rem .6rem;
            font-weight: 700;
            margin-bottom: .5rem;
        }
        .annexe-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }
        .annexe-table th,
        .annexe-table td {
            border: 1px solid #000;
            padding: .45rem .55rem;
            vertical-align: top;
        }
        .annexe-table thead th {
            background: #f1f1f1;
        }
    </style>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <h5 class="card-title">{{ $currentReportTitle }}</h5>
                        <p class="text-muted mb-2">Document comptable structuré (OHADA / SYSCOHADA) : journal, grand livre, balance, bilan, compte de résultat, TAFIRE et état annexe.</p>
                        @php
                            $reportQs = request()->getQueryString();
                            $reportSuffix = $reportQs !== null && $reportQs !== '' ? '?'.$reportQs : '';
                        @endphp
                        <div class="d-flex flex-wrap gap-2 no-print">
                            <a href="{{ route('accounting') }}" class="btn btn-sm btn-outline-secondary">Moteur comptable</a>
                            <a href="{{ route('accounting.report.journal') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'journal' ? 'btn-primary' : 'btn-outline-primary' }}">Journal</a>
                            <a href="{{ route('accounting.report.grand-livre') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'grand-livre' ? 'btn-primary' : 'btn-outline-primary' }}">Grand livre</a>
                            <a href="{{ route('accounting.report.balance') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'balance' ? 'btn-primary' : 'btn-outline-primary' }}">Balance</a>
                            <a href="{{ route('accounting.report.bilan') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'bilan' ? 'btn-primary' : 'btn-outline-primary' }}">Bilan</a>
                            <a href="{{ route('accounting.report.resultat') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'resultat' ? 'btn-primary' : 'btn-outline-primary' }}">Compte de résultat</a>
                            <a href="{{ route('accounting.report.tafire') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'tafire' ? 'btn-primary' : 'btn-outline-primary' }}">TAFIRE</a>
                            <a href="{{ route('accounting.report.annexe') }}{{ $reportSuffix }}" class="btn btn-sm {{ $reportType === 'annexe' ? 'btn-primary' : 'btn-outline-primary' }}">État annexe</a>
                            <a href="{{ route('accounting.bank-reconciliation', array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo])) }}" class="btn btn-sm btn-outline-dark">Rapprochement</a>
                            <a href="{{ route('accounting.monthly-closing') }}" class="btn btn-sm btn-outline-dark">Clôture mensuelle</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 report-header p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                @if(!empty($companyLogo))
                    <img src="{{ route('company-documents.stream', ['user' => $companyLogoUser, 'type' => 'company_logo']) }}" alt="Logo {{ $companyName }}" style="width:120px; height:auto; object-fit:contain;" />
                @else
                    <img src="{{ asset('images/logo.svg') }}" alt="Logo par défaut" style="width:120px; height:auto; object-fit:contain;" />
                @endif
                <div class="ms-3">
                    <h2 class="mb-1">{{ $companyName }}</h2>
                    @if($companySigle)
                        <p class="mb-1 text-muted">{{ $companySigle }}</p>
                    @endif
                    @if($companyAddress)
                        <p class="mb-1 text-muted">{{ $companyAddress }}</p>
                    @endif
                    @if($companyTaxId)
                        <p class="mb-0 text-muted">N° d'identification fiscale : {{ $companyTaxId }}</p>
                    @endif
                    <p class="mb-0 text-muted">Rapport généré le {{ now()->format('d/m/Y H:i') }}</p>
                    <p class="mb-0 text-muted">Utilisateur : {{ Auth::user()->name }}</p>
                </div>
            </div>
            @if($reportType === 'bilan')
                <div class="text-md-end mt-3 mt-md-0 d-flex flex-column align-items-center">
                    <img src="{{ $qrUrl }}" alt="QR Code bilan" style="width:120px; height:120px; object-fit:contain; border:1px solid #dee2e6; background:#fff; padding:8px;" />
                    <p class="small text-muted mt-2 mb-0">Réf. {{ $bilanReference }}</p>
                </div>
            @else
                <div class="text-md-end mt-3 mt-md-0">
                    <p class="mb-1"><strong>{{ $currentReportTitle }}</strong></p>
                    <p class="mb-0">{{ $entries->count() }} écritures</p>
                </div>
            @endif
        </div>
    </div>

    <div class="journal-print-header">
        <div class="d-flex align-items-start gap-3">
            @if(!empty($companyLogo))
                <img src="{{ route('company-documents.stream', ['user' => $companyLogoUser, 'type' => 'company_logo']) }}" alt="Logo {{ $companyName }}" style="width:90px; height:auto; object-fit:contain;" />
            @else
                <img src="{{ asset('images/logo.svg') }}" alt="Logo par défaut" style="width:90px; height:auto; object-fit:contain;" />
            @endif
            <div class="meta">
                <div><strong>{{ $companyName }}</strong></div>
                @if($companySigle)<div>{{ $companySigle }}</div>@endif
                @if($companyAddress)<div>{{ $companyAddress }}</div>@endif
                @if($companyTaxId)<div>NIF : {{ $companyTaxId }}</div>@endif
            </div>
        </div>
        <div class="meta text-end">
            <div><strong>{{ strtoupper($currentReportTitle) }}</strong></div>
            <div>Généré le {{ now()->format('d/m/Y H:i') }}</div>
            <div>Utilisateur : {{ Auth::user()->name }}</div>
            <div>
                @if($entries->isNotEmpty())
                    Période : {{ $entries->last()->date->format('d/m/Y') }} - {{ $entries->first()->date->format('d/m/Y') }}
                @else
                    Période : N/A
                @endif
            </div>
        </div>
    </div>

    @if($reportType === 'bilan')
        @php
            $bilanPdfParams = array_filter(['date_from' => $dateFrom, 'date_to' => $dateTo]);
            $bilanPdfViewerUrl = route('accounting.report.bilan.viewer', $bilanPdfParams);
        @endphp
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printBilanOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le bilan
                </button>
                <a href="{{ $bilanPdfViewerUrl }}" class="btn btn-outline-dark btn-sm">
                    <i data-feather="file-text" class="me-1"></i>Visualisation le Bilan
                </a>
            </div>
        </div>
    @endif

    @if($reportType === 'journal' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">
                    Période couverte :
                    @if($entries->isNotEmpty())
                        <strong>{{ $entries->last()->date->format('d/m/Y') }}</strong> au <strong>{{ $entries->first()->date->format('d/m/Y') }}</strong>
                    @else
                        <strong>Aucune écriture</strong>
                    @endif
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printJournalOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le journal
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.journal') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="filter" class="me-1"></i>Filtrer
                    </button>
                    <a href="{{ route('accounting.report.journal') }}" class="btn btn-outline-secondary btn-sm">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    @endif

    @if($reportType === 'grand-livre' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">
                    Filtrer le grand livre par période.
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printGrandLivreOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le grand livre
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.grand-livre') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="filter" class="me-1"></i>Filtrer
                    </button>
                    <a href="{{ route('accounting.report.grand-livre') }}" class="btn btn-outline-secondary btn-sm">
                        Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    @endif

    @if($reportType === 'balance' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">Balance générale SYSCOHADA (format extraction).</div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printBalanceOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer la balance
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.balance') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i data-feather="filter" class="me-1"></i>Filtrer
                    </button>
                    <a href="{{ route('accounting.report.balance') }}" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                </div>
            </form>
        </div>
    @endif

    @if($reportType === 'resultat' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">Compte de résultat SYSCOHADA (pages 1/4 à 4/4).</div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printResultatOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le compte de résultat
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.resultat') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter" class="me-1"></i>Filtrer</button>
                    <a href="{{ route('accounting.report.resultat') }}" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                </div>
            </form>
        </div>
    @endif


    @if($reportType === 'tafire' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">TAFIRE SYSCOHADA (ressources et emplois).</div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printTafireOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le TAFIRE
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.tafire') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter" class="me-1"></i>Filtrer</button>
                    <a href="{{ route('accounting.report.tafire') }}" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                </div>
            </form>
        </div>
    @endif

    @if($reportType === 'annexe' || $reportType === 'full')
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
                <div class="small text-muted">État annexe du système normal.</div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printAnnexeOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer l'état annexe
                </button>
            </div>
            <form method="GET" action="{{ route('accounting.report.annexe') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Date début</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label mb-1">Date fin</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                </div>
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm"><i data-feather="filter" class="me-1"></i>Filtrer</button>
                    <a href="{{ route('accounting.report.annexe') }}" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
                </div>
            </form>
        </div>
    @endif

    <section id="journal-section" class="report-section mb-5">
        @php
            $journalCodeByType = [
                'Vente' => 'VT',
                'Achat' => 'AC',
                'Reçu' => 'VR',
                'Justificatif' => 'BQ',
            ];
            $journalRows = [];
            $accountLineCounters = [];
            foreach ($entries as $entry) {
                $journalCode = $journalCodeByType[$entry->document_type] ?? 'OD';
                $piece = $entry->document_reference ?: ('PJ-' . $entry->id);
                $description = trim((string) $entry->description);
                $tiers = '-';
                if (str_contains($description, '-')) {
                    $parts = explode('-', $description, 2);
                    $tiersCandidate = trim(str_replace('[OCR]', '', $parts[0]));
                    $tiers = $tiersCandidate !== '' ? $tiersCandidate : '-';
                }

                $debitParts = preg_split('/\s+/', (string) $entry->debit_account, 2);
                $creditParts = preg_split('/\s+/', (string) $entry->credit_account, 2);
                $debitCode = $debitParts[0] ?? '';
                $debitLabel = $debitParts[1] ?? $entry->debit_account;
                $creditCode = $creditParts[0] ?? '';
                $creditLabel = $creditParts[1] ?? $entry->credit_account;

                $debitAmount = $entry->amount !== null ? (float) $entry->amount : null;
                $debitCreditAmount = null;
                if ($debitCode !== '') {
                    $accountLineCounters[$debitCode] = ($accountLineCounters[$debitCode] ?? 0) + 1;
                }
                $debitNumLigne = $debitCode !== '' ? (string) $accountLineCounters[$debitCode] : '';
                $debitControle = ($debitAmount !== null && $debitCreditAmount !== null) ? 'A verifier' : 'OK';

                $journalRows[] = [
                    'date' => $entry->date->format('d/m/Y'),
                    'journal' => $journalCode,
                    'piece' => $piece,
                    'compte' => $debitCode,
                    'intitule' => $debitLabel,
                    'libelle' => $description,
                    'debit' => $debitAmount,
                    'credit' => $debitCreditAmount,
                    'tiers' => $tiers,
                    'centre' => '-',
                    'controle' => $debitControle,
                    'num_ligne' => $debitNumLigne,
                    'compte_ligne' => ($debitCode !== '' ? $debitCode . '|' . $debitNumLigne : ''),
                ];

                $creditDebitAmount = null;
                $creditAmount = $entry->amount !== null ? (float) $entry->amount : null;
                if ($creditCode !== '') {
                    $accountLineCounters[$creditCode] = ($accountLineCounters[$creditCode] ?? 0) + 1;
                }
                $creditNumLigne = $creditCode !== '' ? (string) $accountLineCounters[$creditCode] : '';
                $creditControle = ($creditDebitAmount !== null && $creditAmount !== null) ? 'A verifier' : 'OK';

                $journalRows[] = [
                    'date' => $entry->date->format('d/m/Y'),
                    'journal' => $journalCode,
                    'piece' => $piece,
                    'compte' => $creditCode,
                    'intitule' => $creditLabel,
                    'libelle' => $description,
                    'debit' => $creditDebitAmount,
                    'credit' => $creditAmount,
                    'tiers' => $tiers,
                    'centre' => '-',
                    'controle' => $creditControle,
                    'num_ligne' => $creditNumLigne,
                    'compte_ligne' => ($creditCode !== '' ? $creditCode . '|' . $creditNumLigne : ''),
                ];
            }
        @endphp
        <h4>1. Journal</h4>
        <p class="text-muted">Liste chronologique complète des écritures comptables, avec piste de contrôle OCR.</p>
        <div class="table-responsive">
            <table class="table table-bordered table-sm journal-table journal-model-table">
                <thead>
                    <tr>
                        <th class="w-date">Date</th>
                        <th class="w-journal">Journal</th>
                        <th class="w-piece">Pièce</th>
                        <th class="w-compte">Compte</th>
                        <th class="w-intitule">Intitulé compte</th>
                        <th class="w-libelle">Libellé</th>
                        <th class="w-montant">Débit</th>
                        <th class="w-montant">Crédit</th>
                        <th class="w-tiers">Tiers</th>
                        <th class="w-centre">Centre de cout</th>
                        <th class="w-controle">Contrôle</th>
                        <th class="w-no">No lig compt</th>
                        <th class="w-cpte">Cpte ligne</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($journalRows as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['journal'] }}</td>
                            <td>{{ $row['piece'] }}</td>
                            <td>{{ $row['compte'] }}</td>
                            <td>{{ $row['intitule'] }}</td>
                            <td>{{ $row['libelle'] }}</td>
                            <td class="text-end amount-cell">{{ $row['debit'] !== null ? number_format($row['debit'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount-cell">{{ $row['credit'] !== null ? number_format($row['credit'], 0, ',', ' ') : '-' }}</td>
                            <td class="tier-cell">{{ $row['tiers'] }}</td>
                            <td>{{ $row['centre'] }}</td>
                            <td class="control-cell">{{ $row['controle'] }}</td>
                            <td>{{ $row['num_ligne'] }}</td>
                            <td>{{ $row['compte_ligne'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center text-muted py-3">Aucune écriture disponible pour le journal.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if(!empty($journalRows))
                    <tfoot>
                        <tr class="table-primary">
                            <th colspan="6" class="text-end">Total</th>
                            <th class="text-end amount-cell">{{ number_format($totalDebit, 0, ',', ' ') }}</th>
                            <th class="text-end amount-cell">{{ number_format($totalCredit, 0, ',', ' ') }}</th>
                            <th colspan="5"></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>

    <div class="page-break"></div>

    <section id="grand-livre-section" class="report-section mb-5">
        @php
            $journalCodeByType = [
                'Vente' => 'VT',
                'Achat' => 'AC',
                'Reçu' => 'VR',
                'Justificatif' => 'BQ',
            ];
            $sortedEntries = $entries->sortBy(function ($entry) {
                return $entry->date->format('Y-m-d') . '|' . str_pad((string) $entry->id, 10, '0', STR_PAD_LEFT);
            });
            $grandLivreRowsByCode = [];
            $grandLivreAccounts = [];
            $journalTotalsByCode = [];

            foreach ($sortedEntries as $entry) {
                $journalCode = $journalCodeByType[$entry->document_type] ?? 'OD';
                $piece = $entry->document_reference ?: ('PJ-' . $entry->id);
                $libelle = (string) $entry->description;
                $amount = $entry->amount !== null ? (float) $entry->amount : 0.0;

                $debitParts = preg_split('/\s+/', (string) $entry->debit_account, 2);
                $creditParts = preg_split('/\s+/', (string) $entry->credit_account, 2);
                $debitCode = trim((string) ($debitParts[0] ?? ''));
                $debitLabel = trim((string) ($debitParts[1] ?? $entry->debit_account));
                $creditCode = trim((string) ($creditParts[0] ?? ''));
                $creditLabel = trim((string) ($creditParts[1] ?? $entry->credit_account));

                if ($debitCode !== '') {
                    $grandLivreAccounts[$debitCode] = $debitLabel !== '' ? $debitLabel : ($grandLivreAccounts[$debitCode] ?? $debitCode);
                    $grandLivreRowsByCode[$debitCode][] = [
                        'date' => $entry->date->format('d/m/Y'),
                        'journal' => $journalCode,
                        'piece' => $piece,
                        'libelle' => $libelle,
                        'debit' => $amount,
                        'credit' => null,
                    ];
                    $journalTotalsByCode[$debitCode]['debit'] = ($journalTotalsByCode[$debitCode]['debit'] ?? 0.0) + $amount;
                    $journalTotalsByCode[$debitCode]['credit'] = $journalTotalsByCode[$debitCode]['credit'] ?? 0.0;
                }

                if ($creditCode !== '') {
                    $grandLivreAccounts[$creditCode] = $creditLabel !== '' ? $creditLabel : ($grandLivreAccounts[$creditCode] ?? $creditCode);
                    $grandLivreRowsByCode[$creditCode][] = [
                        'date' => $entry->date->format('d/m/Y'),
                        'journal' => $journalCode,
                        'piece' => $piece,
                        'libelle' => $libelle,
                        'debit' => null,
                        'credit' => $amount,
                    ];
                    $journalTotalsByCode[$creditCode]['credit'] = ($journalTotalsByCode[$creditCode]['credit'] ?? 0.0) + $amount;
                    $journalTotalsByCode[$creditCode]['debit'] = $journalTotalsByCode[$creditCode]['debit'] ?? 0.0;
                }
            }

            $grandLivreCodes = array_keys($grandLivreRowsByCode);
            sort($grandLivreCodes, SORT_NATURAL);
        @endphp
        <h4>2. Grand livre</h4>
        <p class="text-muted">Extraction automatique par compte selon le modèle comptable.</p>

        @forelse($grandLivreCodes as $accountCode)
            @php
                $accountTitle = $grandLivreAccounts[$accountCode] ?? $accountCode;
                $running = 0.0;
                $sourceRows = $grandLivreRowsByCode[$accountCode] ?? [];
                $rows = [];
                $observedDebit = 0.0;
                $observedCredit = 0.0;
                foreach ($sourceRows as $lineIndex => $sourceRow) {
                    $debit = $sourceRow['debit'];
                    $credit = $sourceRow['credit'];
                    $running += ($debit ?? 0) - ($credit ?? 0);
                    $observedDebit += (float) ($debit ?? 0);
                    $observedCredit += (float) ($credit ?? 0);
                    $rows[] = [
                        'index' => $lineIndex + 1,
                        'date' => $sourceRow['date'],
                        'journal' => $sourceRow['journal'],
                        'piece' => $sourceRow['piece'],
                        'libelle' => $sourceRow['libelle'],
                        'debit' => $debit,
                        'credit' => $credit,
                        'solde' => $running,
                    ];
                }
                $expectedDebit = (float) ($journalTotalsByCode[$accountCode]['debit'] ?? 0.0);
                $expectedCredit = (float) ($journalTotalsByCode[$accountCode]['credit'] ?? 0.0);
                $debitOk = abs($observedDebit - $expectedDebit) < 0.01;
                $creditOk = abs($observedCredit - $expectedCredit) < 0.01;
                $isCoherent = $debitOk && $creditOk;
                $coherenceLabel = $isCoherent ? 'OK' : 'Écart';
                $coherenceClass = $isCoherent ? 'bg-light-success text-success' : 'bg-light-danger text-danger';
                $entryCount = count($rows);
                $finalBalance = $running;
                $balanceNature = $finalBalance > 0 ? 'Solde débiteur' : ($finalBalance < 0 ? 'Solde créditeur' : 'Compte soldé');
            @endphp
            @if(!empty($rows))
                <div class="mb-4 grandlivre-account-block">
                    <div class="grandlivre-model-title">
                        <span>GRAND LIVRE - EXTRACTION AUTOMATIQUE PAR COMPTE</span>
                        <span class="subtitle">Compte {{ $accountCode }} · {{ $accountTitle }}</span>
                    </div>
                    <table class="grandlivre-meta-table">
                        <tr>
                            <td><strong>Compte</strong></td>
                            <td class="meta-value">{{ $accountCode }}</td>
                            <td><strong>Intitulé</strong></td>
                            <td class="meta-value">{{ $accountTitle }}</td>
                        </tr>
                        <tr>
                            <td><strong>Contrôle cohérence</strong></td>
                            <td class="meta-value grandlivre-check-line">
                                <span class="badge {{ $coherenceClass }}">{{ $coherenceLabel }}</span>
                            </td>
                            <td><strong>Vérification</strong></td>
                            <td class="meta-value">
                                Débit GL={{ number_format($observedDebit, 0, ',', ' ') }} / Journal={{ number_format($expectedDebit, 0, ',', ' ') }}
                                · Crédit GL={{ number_format($observedCredit, 0, ',', ' ') }} / Journal={{ number_format($expectedCredit, 0, ',', ' ') }}
                            </td>
                        </tr>
                    </table>
                    <div class="grandlivre-kpi-row">
                        <span class="grandlivre-kpi">Lignes : {{ number_format($entryCount, 0, ',', ' ') }}</span>
                        <span class="grandlivre-kpi">Débit : {{ number_format($observedDebit, 0, ',', ' ') }}</span>
                        <span class="grandlivre-kpi">Crédit : {{ number_format($observedCredit, 0, ',', ' ') }}</span>
                        <span class="grandlivre-kpi">{{ $balanceNature }} : {{ number_format(abs($finalBalance), 0, ',', ' ') }}</span>
                    </div>
                    <div class="table-responsive grandlivre-table-wrap">
                        <table class="table table-bordered table-sm grandlivre-model-table">
                            <thead>
                                <tr>
                                    <th style="width:52px;">N°</th>
                                    <th style="width:90px;">Date</th>
                                    <th style="width:70px;">Journal</th>
                                    <th style="width:110px;">Pièce</th>
                                    <th>Libellé</th>
                                    <th class="text-end" style="width:110px;">Débit</th>
                                    <th class="text-end" style="width:110px;">Crédit</th>
                                    <th class="text-end" style="width:110px;">Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="text-end">{{ $row['index'] }}</td>
                                        <td>{{ $row['date'] }}</td>
                                        <td><span class="journal-pill">{{ $row['journal'] }}</span></td>
                                        <td class="meta-value">{{ $row['piece'] }}</td>
                                        <td class="meta-value">{{ $row['libelle'] }}</td>
                                        <td class="text-end amount">{{ $row['debit'] !== null ? number_format($row['debit'], 0, ',', ' ') : '-' }}</td>
                                        <td class="text-end amount">{{ $row['credit'] !== null ? number_format($row['credit'], 0, ',', ' ') : '-' }}</td>
                                        <td class="text-end amount {{ $row['solde'] < 0 ? 'balance-negative' : '' }}">
                                            {{ $row['solde'] !== 0.0 ? number_format($row['solde'], 0, ',', ' ') : '0' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @empty
            <div class="alert alert-info">Aucune donnée disponible pour le grand livre.</div>
        @endforelse
    </section>

    <div class="page-break"></div>

    <section id="balance-section" class="report-section mb-5">
        @php
            $balanceTemplate = [
                '104' => "Primes liees au capital",
                '131' => "Subventions d'investissement",
                '161' => "Emprunts aupres des etablissements de credit",
                '211' => "Terrains",
                '221' => "Batiments",
                '241' => "Materiel et outillage",
                '244' => "Materiel de bureau et informatique",
                '281' => "Amortissements des immobilisations",
                '311' => "Marchandises",
                '321' => "Matieres premieres",
                '401' => "Fournisseurs",
                '411' => "Clients",
                '421' => "Personnel, avances et acomptes",
                '431' => "Securite sociale",
                '443' => "Etat, TVA facturee",
                '444' => "Etat, TVA recuperable",
                '445' => "Etat, impots et taxes",
                '447' => "Etat, IS / BIC / autres impots",
                '521' => "Banques locales",
                '531' => "Caisse siege",
                '566' => "Virements internes",
                '601' => "Achats de marchandises",
                '602' => "Achats de matieres premieres",
                '604' => "Achats stockes de matieres et fournitures",
                '605' => "Autres achats",
                '611' => "Transports",
                '613' => "Locations",
                '616' => "Primes d'assurance",
                '618' => "Charges diverses externes",
                '621' => "Personnel exterieur",
                '622' => "Remunerations d'intermediaires et honoraires",
                '624' => "Publicite, publications, relations publiques",
                '626' => "Frais postaux et telecommunications",
                '631' => "Impots, taxes et versements assimiles",
                '641' => "Salaires et traitements",
                '645' => "Charges sociales",
                '651' => "Interets des emprunts",
                '681' => "Dotations aux amortissements",
                '701' => "Ventes de marchandises",
                '702' => "Ventes de produits finis",
                '704' => "Travaux et services vendus",
                '705' => "Produits accessoires",
                '706' => "Prestations de services",
                '707' => "Produits des activites annexes",
                '771' => "Interets et produits financiers",
                '781' => "Reprises d'amortissements et provisions",
                '811' => "Valeurs comptables des cessions d'immobilisations",
                '821' => "Produits de cessions d'immobilisations",
                '861' => "Participation des travailleurs",
                '871' => "Impot sur le resultat",
            ];

            $journalSumsByCode = [];
            foreach (($journalRows ?? []) as $journalRow) {
                $code = trim((string) ($journalRow['compte'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $journalSumsByCode[$code]['debit'] = ($journalSumsByCode[$code]['debit'] ?? 0.0) + (float) ($journalRow['debit'] ?? 0);
                $journalSumsByCode[$code]['credit'] = ($journalSumsByCode[$code]['credit'] ?? 0.0) + (float) ($journalRow['credit'] ?? 0);
            }

            $journalSumsByCodeN1 = [];
            foreach ($entries as $entryN1) {
                if ($entryN1->date->format('Y') !== (string) $previousYear) {
                    continue;
                }
                $amountN1 = $entryN1->amount !== null ? (float) $entryN1->amount : 0.0;
                $debitPartsN1 = preg_split('/\s+/', (string) $entryN1->debit_account, 2);
                $creditPartsN1 = preg_split('/\s+/', (string) $entryN1->credit_account, 2);
                $debitCodeN1 = trim((string) ($debitPartsN1[0] ?? ''));
                $creditCodeN1 = trim((string) ($creditPartsN1[0] ?? ''));

                if ($debitCodeN1 !== '') {
                    $journalSumsByCodeN1[$debitCodeN1]['debit'] = ($journalSumsByCodeN1[$debitCodeN1]['debit'] ?? 0.0) + $amountN1;
                    $journalSumsByCodeN1[$debitCodeN1]['credit'] = $journalSumsByCodeN1[$debitCodeN1]['credit'] ?? 0.0;
                }

                if ($creditCodeN1 !== '') {
                    $journalSumsByCodeN1[$creditCodeN1]['credit'] = ($journalSumsByCodeN1[$creditCodeN1]['credit'] ?? 0.0) + $amountN1;
                    $journalSumsByCodeN1[$creditCodeN1]['debit'] = $journalSumsByCodeN1[$creditCodeN1]['debit'] ?? 0.0;
                }
            }

            foreach ($ledger as $accountName => $amounts) {
                if (!preg_match('/^(\d{1,3})\s*(.*)$/', trim((string) $accountName), $m)) {
                    continue;
                }
                $code = $m[1];
                if (!array_key_exists($code, $balanceTemplate)) {
                    $balanceTemplate[$code] = trim((string) ($m[2] ?? '')) ?: ('Compte ' . $code);
                }
            }

            $allCodes = array_values(array_unique(array_merge(
                array_keys($balanceTemplate),
                array_keys($journalSumsByCode),
                array_keys($journalSumsByCodeN1)
            )));
            sort($allCodes, SORT_NATURAL);

            $balanceRows = [];
            $totalDebitBalance = 0.0;
            $totalCreditBalance = 0.0;
            $totalSoldeDebit = 0.0;
            $totalSoldeCredit = 0.0;
            $totalMouvementBalance = 0.0;
            $totalDebitBalanceN1 = 0.0;
            $totalCreditBalanceN1 = 0.0;
            $totalSoldeDebitN1 = 0.0;
            $totalSoldeCreditN1 = 0.0;
            $totalMouvementBalanceN1 = 0.0;

            foreach ($allCodes as $code) {
                $debit = (float) ($journalSumsByCode[$code]['debit'] ?? 0.0);
                $credit = (float) ($journalSumsByCode[$code]['credit'] ?? 0.0);
                $soldeDebit = max($debit - $credit, 0);
                $soldeCredit = max($credit - $debit, 0);
                $mouvement = $debit + $credit;
                $observation = $mouvement == 0.0 ? 'Aucun mouvement' : '';
                $debitN1 = (float) ($journalSumsByCodeN1[$code]['debit'] ?? 0.0);
                $creditN1 = (float) ($journalSumsByCodeN1[$code]['credit'] ?? 0.0);
                $soldeDebitN1 = max($debitN1 - $creditN1, 0);
                $soldeCreditN1 = max($creditN1 - $debitN1, 0);
                $mouvementN1 = $debitN1 + $creditN1;
                $observationN1 = $mouvementN1 == 0.0 ? 'A saisir ou importer' : '';

                $balanceRows[] = [
                    'classe' => substr((string) $code, 0, 1),
                    'code' => $code,
                    'label' => $balanceTemplate[$code] ?? ('Compte ' . $code),
                    'debit' => $debit,
                    'credit' => $credit,
                    'solde_debit' => $soldeDebit,
                    'solde_credit' => $soldeCredit,
                    'mouvement' => $mouvement,
                    'observation' => $observation,
                    'debit_n1' => $debitN1,
                    'credit_n1' => $creditN1,
                    'solde_debit_n1' => $soldeDebitN1,
                    'solde_credit_n1' => $soldeCreditN1,
                    'mouvement_n1' => $mouvementN1,
                    'observation_n1' => $observationN1,
                ];

                $totalDebitBalance += $debit;
                $totalCreditBalance += $credit;
                $totalSoldeDebit += $soldeDebit;
                $totalSoldeCredit += $soldeCredit;
                $totalMouvementBalance += $mouvement;
                $totalDebitBalanceN1 += $debitN1;
                $totalCreditBalanceN1 += $creditN1;
                $totalSoldeDebitN1 += $soldeDebitN1;
                $totalSoldeCreditN1 += $soldeCreditN1;
                $totalMouvementBalanceN1 += $mouvementN1;
            }

            $balanceStatus = abs($totalDebitBalance - $totalCreditBalance) < 0.0001 ? 'Balance equilibree' : 'Ecart a analyser';
            $balanceStatusN1 = abs($totalDebitBalanceN1 - $totalCreditBalanceN1) < 0.0001 ? 'Balance N-1 equilibree' : 'Ecart N-1 a analyser';
        @endphp

        <h4>3. Balance</h4>
        <p class="text-muted">Balance generale SYSCOHADA par compte, avec comparaison exercice N et N-1.</p>

        <div class="grandlivre-model-title mb-2">BALANCE GENERALE - SYSCOHADA</div>
        <div class="table-responsive balance-table-wrap">
            <table class="table table-bordered table-sm balance-model-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Classe</th>
                        <th style="width:70px;">Compte</th>
                        <th>Intitule</th>
                        <th class="text-end" style="width:120px;">Debit periode N</th>
                        <th class="text-end" style="width:120px;">Credit periode N</th>
                        <th class="text-end" style="width:110px;">Solde debit N</th>
                        <th class="text-end" style="width:110px;">Solde credit N</th>
                        <th class="text-end" style="width:110px;">Mouvement N</th>
                        <th style="width:130px;">Observation N</th>
                        <th class="text-end" style="width:120px;">Debit periode N-1</th>
                        <th class="text-end" style="width:120px;">Credit periode N-1</th>
                        <th class="text-end" style="width:110px;">Solde debit N-1</th>
                        <th class="text-end" style="width:110px;">Solde credit N-1</th>
                        <th class="text-end" style="width:110px;">Mouvement N-1</th>
                        <th style="width:130px;">Observation N-1</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($balanceRows as $row)
                        <tr>
                            <td>{{ $row['classe'] }}</td>
                            <td class="acc-cell">{{ $row['code'] }}</td>
                            <td class="acc-cell">{{ $row['label'] }}</td>
                            <td class="text-end amount">{{ $row['debit'] > 0 ? number_format($row['debit'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['credit'] > 0 ? number_format($row['credit'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['solde_debit'] > 0 ? number_format($row['solde_debit'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['solde_credit'] > 0 ? number_format($row['solde_credit'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['mouvement'] > 0 ? number_format($row['mouvement'], 0, ',', ' ') : '-' }}</td>
                            <td class="{{ $row['mouvement'] > 0 ? 'obs-move' : 'obs-none' }}">{{ $row['observation'] }}</td>
                            <td class="text-end amount">{{ $row['debit_n1'] > 0 ? number_format($row['debit_n1'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['credit_n1'] > 0 ? number_format($row['credit_n1'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['solde_debit_n1'] > 0 ? number_format($row['solde_debit_n1'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['solde_credit_n1'] > 0 ? number_format($row['solde_credit_n1'], 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $row['mouvement_n1'] > 0 ? number_format($row['mouvement_n1'], 0, ',', ' ') : '-' }}</td>
                            <td class="{{ $row['mouvement_n1'] > 0 ? 'obs-move' : 'obs-none' }}">{{ $row['observation_n1'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="3" class="text-end">Totaux</th>
                        <th class="text-end amount">{{ number_format($totalDebitBalance, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalCreditBalance, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalSoldeDebit, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalSoldeCredit, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalMouvementBalance, 0, ',', ' ') }}</th>
                        <th>{{ $balanceStatus }}</th>
                        <th class="text-end amount">{{ number_format($totalDebitBalanceN1, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalCreditBalanceN1, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalSoldeDebitN1, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalSoldeCreditN1, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalMouvementBalanceN1, 0, ',', ' ') }}</th>
                        <th>{{ $balanceStatusN1 }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <div class="page-break"></div>

    <section id="bilan-section" class="report-section mb-5">
        @php
            $sumByPrefixes = function (array $prefixes, string $column = 'solde_credit') use ($balanceRows): float {
                $total = 0.0;
                foreach (($balanceRows ?? []) as $row) {
                    $code = (string) ($row['code'] ?? '');
                    foreach ($prefixes as $prefix) {
                        if ($prefix !== '' && str_starts_with($code, (string) $prefix)) {
                            $total += (float) ($row[$column] ?? 0.0);
                            break;
                        }
                    }
                }
                return $total;
            };

            $sumByPrefixesN1 = function (array $prefixes, string $column = 'solde_credit_n1') use ($balanceRows): float {
                $total = 0.0;
                foreach (($balanceRows ?? []) as $row) {
                    $code = (string) ($row['code'] ?? '');
                    foreach ($prefixes as $prefix) {
                        if ($prefix !== '' && str_starts_with($code, (string) $prefix)) {
                            $total += (float) ($row[$column] ?? 0.0);
                            break;
                        }
                    }
                }
                return $total;
            };

            // PASSIF - aligné sur les agrégations des feuilles BILAN PASSIF.
            $capitalN = $sumByPrefixes(['101', '102', '103', '104']);
            $capitalN1 = $sumByPrefixesN1(['101', '102', '103', '104']);
            $primesReservesN = $sumByPrefixes(['105', '106', '111', '112', '113', '118']);
            $primesReservesN1 = $sumByPrefixesN1(['105', '106', '111', '112', '113', '118']);
            $reportANouveauN = $sumByPrefixes(['12']);
            $reportANouveauN1 = $sumByPrefixesN1(['12']);
            $equityResult = (float) ($income - $expenses) + $sumByPrefixes(['13']);
            $equityResultN1 = (float) (($incomeByYear[$previousYear] ?? 0) - ($expensesByYear[$previousYear] ?? 0)) + $sumByPrefixesN1(['13']);
            $subventionsInvestissementN = $sumByPrefixes(['14']);
            $subventionsInvestissementN1 = $sumByPrefixesN1(['14']);
            $provisionsAssimileesN = $sumByPrefixes(['15']);
            $provisionsAssimileesN1 = $sumByPrefixesN1(['15']);

            $equityPositive = $capitalN + $primesReservesN + $reportANouveauN + $equityResult + $subventionsInvestissementN + $provisionsAssimileesN;
            $equityPositiveN1 = $capitalN1 + $primesReservesN1 + $reportANouveauN1 + $equityResultN1 + $subventionsInvestissementN1 + $provisionsAssimileesN1;

            $dettesFinancieresN = $sumByPrefixes(['161', '162', '163', '164', '166', '167']);
            $dettesFinancieresN1 = $sumByPrefixesN1(['161', '162', '163', '164', '166', '167']);
            $dettesCreditBailN = $sumByPrefixes(['17']);
            $dettesCreditBailN1 = $sumByPrefixesN1(['17']);
            $dettesFinDiversesN = $sumByPrefixes(['165', '168', '18']);
            $dettesFinDiversesN1 = $sumByPrefixesN1(['165', '168', '18']);
            $provisionsFinN = $sumByPrefixes(['19']);
            $provisionsFinN1 = $sumByPrefixesN1(['19']);
            $totalDettesFinN = $dettesFinancieresN + $dettesCreditBailN + $dettesFinDiversesN + $provisionsFinN;
            $totalDettesFinN1 = $dettesFinancieresN1 + $dettesCreditBailN1 + $dettesFinDiversesN1 + $provisionsFinN1;
            $totalRessourcesStablesN = $equityPositive + $totalDettesFinN;
            $totalRessourcesStablesN1 = $equityPositiveN1 + $totalDettesFinN1;

            $avancesClientsN = $sumByPrefixes(['41']);
            $avancesClientsN1 = $sumByPrefixesN1(['41']);
            $fournisseursExploitN = $sumByPrefixes(['40']);
            $fournisseursExploitN1 = $sumByPrefixesN1(['40']);
            $dettesFiscalesN = $sumByPrefixes(['44']);
            $dettesFiscalesN1 = $sumByPrefixesN1(['44']);
            $dettesSocialesN = $sumByPrefixes(['42', '43']);
            $dettesSocialesN1 = $sumByPrefixesN1(['42', '43']);
            $autresDettesN = $sumByPrefixes(['45', '46', '47']) - $sumByPrefixes(['479']);
            $autresDettesN1 = $sumByPrefixesN1(['45', '46', '47']) - $sumByPrefixesN1(['479']);
            $risquesProvisionnesN = $sumByPrefixes(['49']);
            $risquesProvisionnesN1 = $sumByPrefixesN1(['49']);
            $passifCirculant = $avancesClientsN + $fournisseursExploitN + $dettesFiscalesN + $dettesSocialesN + $autresDettesN + $risquesProvisionnesN;
            $passifCirculantN1 = $avancesClientsN1 + $fournisseursExploitN1 + $dettesFiscalesN1 + $dettesSocialesN1 + $autresDettesN1 + $risquesProvisionnesN1;

            $tresoreriePassifPrefixes = ['50', '51', '52', '53', '54', '55', '57', '58'];
            $tresoreriePassif = $sumByPrefixes($tresoreriePassifPrefixes);
            $tresoreriePassifN1 = $sumByPrefixesN1($tresoreriePassifPrefixes);
            $ecartsConversionPassif = $sumByPrefixes(['479']);
            $ecartsConversionPassifN1 = $sumByPrefixesN1(['479']);
            $totalPassifGeneral = $totalRessourcesStablesN + $passifCirculant + $tresoreriePassif + $ecartsConversionPassif;
            $totalPassifGeneralN1 = $totalRessourcesStablesN1 + $passifCirculantN1 + $tresoreriePassifN1 + $ecartsConversionPassifN1;

            // ACTIF - aligné sur les agrégations des feuilles BILAN ACTIF.
            $chargesImmobN = max($sumByPrefixes(['20'], 'solde_debit') - $sumByPrefixes(['206'], 'solde_debit'), 0);
            $chargesImmobN1 = max($sumByPrefixesN1(['20'], 'solde_debit_n1') - $sumByPrefixesN1(['206'], 'solde_debit_n1'), 0);
            $primesRemboursementN = max($sumByPrefixes(['206'], 'solde_debit'), 0);
            $primesRemboursementN1 = max($sumByPrefixesN1(['206'], 'solde_debit_n1'), 0);
            $actifIncorporelN = max(
                ($sumByPrefixes(['203', '205', '207', '208'], 'solde_debit'))
                - ($sumByPrefixes(['2811', '291'], 'solde_credit')),
                0
            );
            $actifIncorporelN1 = max(
                ($sumByPrefixesN1(['203', '205', '207', '208'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['2811', '291'], 'solde_credit_n1')),
                0
            );
            $actifCorporelN = max(
                ($sumByPrefixes(['22', '23', '24', '25'], 'solde_debit'))
                - ($sumByPrefixes(['282', '283', '284', '292'], 'solde_credit')),
                0
            );
            $actifCorporelN1 = max(
                ($sumByPrefixesN1(['22', '23', '24', '25'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['282', '283', '284', '292'], 'solde_credit_n1')),
                0
            );
            $actifFinancierN = max(
                ($sumByPrefixes(['26', '27'], 'solde_debit'))
                - ($sumByPrefixes(['296', '297'], 'solde_credit')),
                0
            );
            $actifFinancierN1 = max(
                ($sumByPrefixesN1(['26', '27'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['296', '297'], 'solde_credit_n1')),
                0
            );

            $immobilisationsBrutes = $chargesImmobN + $primesRemboursementN + $actifIncorporelN + $actifCorporelN + $actifFinancierN;
            $immobilisationsBrutesN1 = $chargesImmobN1 + $primesRemboursementN1 + $actifIncorporelN1 + $actifCorporelN1 + $actifFinancierN1;

            $stocksActifN = max(
                ($sumByPrefixes(['31', '32', '33', '34', '35', '36', '37', '38'], 'solde_debit'))
                - ($sumByPrefixes(['39'], 'solde_credit')),
                0
            );
            $stocksActifN1 = max(
                ($sumByPrefixesN1(['31', '32', '33', '34', '35', '36', '37', '38'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['39'], 'solde_credit_n1')),
                0
            );
            $creancesActifN = max(
                ($sumByPrefixes(['40', '41', '42', '43', '44', '45', '46', '47'], 'solde_debit'))
                - ($sumByPrefixes(['478'], 'solde_debit'))
                - ($sumByPrefixes(['49'], 'solde_credit')),
                0
            );
            $creancesActifN1 = max(
                ($sumByPrefixesN1(['40', '41', '42', '43', '44', '45', '46', '47'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['478'], 'solde_debit_n1'))
                - ($sumByPrefixesN1(['49'], 'solde_credit_n1')),
                0
            );
            $actifCirculant = $stocksActifN + $creancesActifN;
            $actifCirculantN1 = $stocksActifN1 + $creancesActifN1;
            $tresorerieActifPrefixes = ['50', '51', '52', '53', '54', '55', '57', '58'];
            $tresorerieActif = max(
                ($sumByPrefixes($tresorerieActifPrefixes, 'solde_debit'))
                - ($sumByPrefixes(['59'], 'solde_credit')),
                0
            );
            $tresorerieActifN1 = max(
                ($sumByPrefixesN1($tresorerieActifPrefixes, 'solde_debit_n1'))
                - ($sumByPrefixesN1(['59'], 'solde_credit_n1')),
                0
            );
            $ecartsConversionActif = $sumByPrefixes(['478'], 'solde_debit');
            $ecartsConversionActifN1 = $sumByPrefixesN1(['478'], 'solde_debit_n1');
            $totalActifGeneral = $immobilisationsBrutes + $actifCirculant + $tresorerieActif + $ecartsConversionActif;
            $totalActifGeneralN1 = $immobilisationsBrutesN1 + $actifCirculantN1 + $tresorerieActifN1 + $ecartsConversionActifN1;
        @endphp
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="small-muted text-uppercase">BILAN</div>
            <div class="small-muted text-end">
                <div>BILAN SYSTEME NORMAL</div>
                <div>PAGE 3/4</div>
            </div>
        </div>

        <div class="bilan-header">
            <div class="field">
                <span>Dénomination sociale de l’entreprise :</span>
                {{ $companyName ?? 'Sitiame Capital' }}
            </div>
            <div class="field">
                <span>Sigle usuel :</span>
                {{ $companySigle ?? '' }}
            </div>
            <div class="field">
                <span>Adresse :</span>
                {{ $companyAddress ?? '' }}
            </div>
            <div class="field">
                <span>N° d'identification fiscale :</span>
                {{ $companyTaxId ?? '#N/A' }}
            </div>
            <div class="field">
                <span>Exercice clos le :</span>
                {{ request('date_to', now()->endOfYear()->toDateString()) }}
            </div>
            <div class="field">
                <span>Durée (en mois) :</span>
                #N/A
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="table-responsive">
                    <table class="bilan-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:6%;">Réf.</th>
                                <th>PASSIF<br><span class="subtext">(avant répartition)</span></th>
                                <th class="text-center" style="width:16%;">Exercice N</th>
                                <th class="text-center" style="width:16%;">Exercice N-1</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="section-title"><td colspan="4">CAPITAUX PROPRES ET RESSOURCES ASSIMILÉES</td></tr>
                            <tr><td class="text-center">CA</td><td>Capital</td><td class="text-end">{{ number_format($capitalN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($capitalN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">CB</td><td>Actionnaires capital non appelé</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CC</td><td>Primes et réserves</td><td class="text-end">{{ number_format($primesReservesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($primesReservesN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">CD</td><td>Primes d'apport, d'émission, de fusion</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CE</td><td>Ecarts de réévaluation</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CF</td><td>Réserves indisponibles</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CG</td><td>Réserves libres</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CH</td><td>Report à nouveau</td><td class="text-end">{{ number_format($reportANouveauN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($reportANouveauN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">CI</td><td>Résultat net de l'exercice (bénéfice + ou perte -)</td><td class="text-end">{{ number_format($equityResult, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($equityResultN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">CK</td><td>Autres capitaux propres</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CL</td><td>Subventions d'investissement</td><td class="text-end">{{ number_format($subventionsInvestissementN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($subventionsInvestissementN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">CM</td><td>Provisions réglementées et fonds assimilés</td><td class="text-end">{{ number_format($provisionsAssimileesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($provisionsAssimileesN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">CP TOTAL CAPITAUX PROPRES (I)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($equityPositive, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($equityPositiveN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DETTES FINANCIÈRES ET RESSOURCES ASSIMILÉES (II)</td></tr>
                            <tr><td class="text-center">DA</td><td>Emprunts</td><td class="text-end">{{ number_format($dettesFinancieresN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($dettesFinancieresN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DB</td><td>Dettes de crédit-bail et contrats assimilés</td><td class="text-end">{{ number_format($dettesCreditBailN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($dettesCreditBailN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DC</td><td>Dettes financières diverses</td><td class="text-end">{{ number_format($dettesFinDiversesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($dettesFinDiversesN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DD</td><td>Provisions financières pour risques et charges</td><td class="text-end">{{ number_format($provisionsFinN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($provisionsFinN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DE</td><td>(1) dont H. A. O.</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DF TOTAL DETTES FINANCIÈRES (II)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalDettesFinN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalDettesFinN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DG TOTAL RESSOURCES STABLES (I + II)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalRessourcesStablesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalRessourcesStablesN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">PASSIF CIRCULANT (III)</td></tr>
                            <tr><td class="text-center">DH</td><td>Dettes circulantes H.A.O. et ressources assimilées</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DI</td><td>Clients, avances reçues</td><td class="text-end">{{ number_format($avancesClientsN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($avancesClientsN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DJ</td><td>Fournisseurs d'exploitation</td><td class="text-end">{{ number_format($fournisseursExploitN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fournisseursExploitN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DK</td><td>Dettes fiscales</td><td class="text-end">{{ number_format($dettesFiscalesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($dettesFiscalesN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DL</td><td>Dettes sociales</td><td class="text-end">{{ number_format($dettesSocialesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($dettesSocialesN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DM</td><td>Autres dettes</td><td class="text-end">{{ number_format($autresDettesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($autresDettesN1, 0, ',', ' ') }}</td></tr>
                            <tr><td class="text-center">DN</td><td>Risques provisionnés</td><td class="text-end">{{ number_format($risquesProvisionnesN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($risquesProvisionnesN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DP TOTAL PASSIF CIRCULANT (III)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($passifCirculant, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($passifCirculantN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">TRESORERIE PASSIF (IV)</td></tr>
                            <tr><td class="text-center">DQ</td><td>Banques, crédits d'escompte</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DR</td><td>Banques, crédits de trésorerie</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DS</td><td>Banques, découverts</td><td class="text-end">{{ number_format($tresoreriePassif, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tresoreriePassifN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DT TOTAL TRESORERIE-PASSIF (IV)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($tresoreriePassif, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tresoreriePassifN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DU Ecarts de conversion-Passif (V)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($ecartsConversionPassif, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ecartsConversionPassifN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">DZ TOTAL GENERAL (I + II + III + IV + V)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalPassifGeneral, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalPassifGeneralN1, 0, ',', ' ') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="table-responsive">
                    <table class="bilan-table">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:6%;">Réf.</th>
                                <th>ACTIF<br><span class="subtext">(avant répartition)</span></th>
                                <th class="text-center" style="width:16%;">Exercice N</th>
                                <th class="text-center" style="width:16%;">Exercice N-1</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="section-title"><td colspan="4">ACTIF IMMOBILISÉ (I)</td></tr>
                            <tr><td class="text-center">AZ</td><td>Immobilisations brutes</td><td class="text-end">{{ number_format($immobilisationsBrutes, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($immobilisationsBrutesN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">ACTIF CIRCULANT (II)</td></tr>
                            <tr><td class="text-center">BA</td><td>Stocks, créances et assimilés</td><td class="text-end">{{ number_format($actifCirculant, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($actifCirculantN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">TRESORERIE ACTIF (III)</td></tr>
                            <tr><td class="text-center">BQ</td><td>Banques, caisse, valeurs à encaisser</td><td class="text-end">{{ number_format($tresorerieActif, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tresorerieActifN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">ECARTS DE CONVERSION-ACTIF (IV)</td></tr>
                            <tr><td class="text-center">BU</td><td>Ecarts de conversion-actif</td><td class="text-end">{{ number_format($ecartsConversionActif, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ecartsConversionActifN1, 0, ',', ' ') }}</td></tr>
                            <tr class="section-title"><td colspan="4">BZ TOTAL GENERAL ACTIF (I + II + III + IV)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalActifGeneral, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalActifGeneralN1, 0, ',', ' ') }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="page-break"></div>

    <section id="resultat-section" class="report-section mb-5">
        @php
            $sumResultByPrefixes = function (array $prefixes, bool $credit = false, bool $n1 = false) use ($balanceRows): float {
                $total = 0.0;
                $column = $credit
                    ? ($n1 ? 'credit_n1' : 'credit')
                    : ($n1 ? 'debit_n1' : 'debit');
                foreach (($balanceRows ?? []) as $row) {
                    $code = (string) ($row['code'] ?? '');
                    foreach ($prefixes as $prefix) {
                        if ($prefix !== '' && str_starts_with($code, (string) $prefix)) {
                            $total += (float) ($row[$column] ?? 0.0);
                            break;
                        }
                    }
                }
                return $total;
            };

            // CPTE RESULTAT 1
            $raN = $sumResultByPrefixes(['601']);
            $raN1 = $sumResultByPrefixes(['601'], false, true);
            $rbN = $sumResultByPrefixes(['6031']) - $sumResultByPrefixes(['6031'], true);
            $rbN1 = $sumResultByPrefixes(['6031'], false, true) - $sumResultByPrefixes(['6031'], true, true);
            $rcN = $sumResultByPrefixes(['602']);
            $rcN1 = $sumResultByPrefixes(['602'], false, true);
            $rdN = $sumResultByPrefixes(['6032']) - $sumResultByPrefixes(['6032'], true);
            $rdN1 = $sumResultByPrefixes(['6032'], false, true) - $sumResultByPrefixes(['6032'], true, true);
            $reN = $sumResultByPrefixes(['604', '605', '608']);
            $reN1 = $sumResultByPrefixes(['604', '605', '608'], false, true);
            $rhN = $sumResultByPrefixes(['6033']) - $sumResultByPrefixes(['6033'], true);
            $rhN1 = $sumResultByPrefixes(['6033'], false, true) - $sumResultByPrefixes(['6033'], true, true);
            $riN = $sumResultByPrefixes(['61']);
            $riN1 = $sumResultByPrefixes(['61'], false, true);
            $rjN = $sumResultByPrefixes(['62', '63']);
            $rjN1 = $sumResultByPrefixes(['62', '63'], false, true);
            $rkN = $sumResultByPrefixes(['64']);
            $rkN1 = $sumResultByPrefixes(['64'], false, true);
            $rlN = $sumResultByPrefixes(['65']);
            $rlN1 = $sumResultByPrefixes(['65'], false, true);
            $rpN = $sumResultByPrefixes(['66']);
            $rpN1 = $sumResultByPrefixes(['66'], false, true);
            $rsN = $sumResultByPrefixes(['681', '691']);
            $rsN1 = $sumResultByPrefixes(['681', '691'], false, true);
            $rwN = $raN + $rbN + $rcN + $rdN + $reN + $rhN + $riN + $rjN + $rkN + $rlN + $rpN + $rsN;
            $rwN1 = $raN1 + $rbN1 + $rcN1 + $rdN1 + $reN1 + $rhN1 + $riN1 + $rjN1 + $rkN1 + $rlN1 + $rpN1 + $rsN1;

            // CPTE RESULTAT 2
            $taN = $sumResultByPrefixes(['701'], true);
            $taN1 = $sumResultByPrefixes(['701'], true, true);
            $tbN = $taN - $raN + $rbN;
            $tbN1 = $taN1 - $raN1 + $rbN1;
            $tcN = $sumResultByPrefixes(['702', '703', '704'], true);
            $tcN1 = $sumResultByPrefixes(['702', '703', '704'], true, true);
            $tdN = $sumResultByPrefixes(['705', '706'], true);
            $tdN1 = $sumResultByPrefixes(['705', '706'], true, true);
            $teN = $sumResultByPrefixes(['73'], true);
            $teN1 = $sumResultByPrefixes(['73'], true, true);
            $tfN = $sumResultByPrefixes(['72'], true);
            $tfN1 = $sumResultByPrefixes(['72'], true, true);
            $tgN = ($tcN + $tdN + $teN + $tfN) - $rcN + $rdN;
            $tgN1 = ($tcN1 + $tdN1 + $teN1 + $tfN1) - $rcN1 + $rdN1;
            $thN = $sumResultByPrefixes(['707', '708'], true);
            $thN1 = $sumResultByPrefixes(['707', '708'], true, true);
            $tiN = $taN + $tcN + $tdN + $teN + $tfN + $thN;
            $tiN1 = $taN1 + $tcN1 + $tdN1 + $teN1 + $tfN1 + $thN1;
            $tkN = $sumResultByPrefixes(['71'], true);
            $tkN1 = $sumResultByPrefixes(['71'], true, true);
            $tlN = $sumResultByPrefixes(['75'], true);
            $tlN1 = $sumResultByPrefixes(['75'], true, true);
            $tnN = $tiN - $reN - $rhN - $riN - $rjN - $rkN - $rlN + $tkN + $tlN;
            $tnN1 = $tiN1 - $reN1 - $rhN1 - $riN1 - $rjN1 - $rkN1 - $rlN1 + $tkN1 + $tlN1;
            $tqN = $tnN - $rpN;
            $tqN1 = $tnN1 - $rpN1;
            $tsN = $sumResultByPrefixes(['79'], true);
            $tsN1 = $sumResultByPrefixes(['79'], true, true);
            $ttN = $sumResultByPrefixes(['78'], true);
            $ttN1 = $sumResultByPrefixes(['78'], true, true);
            $twN = $tiN + $tkN + $tlN + $tsN + $ttN;
            $twN1 = $tiN1 + $tkN1 + $tlN1 + $tsN1 + $ttN1;
            $txN = $twN - $rwN;
            $txN1 = $twN1 - $rwN1;

            // CPTE RESULTAT 3 (charges 2e partie)
            $saN = $sumResultByPrefixes(['67']);
            $saN1 = $sumResultByPrefixes(['67'], false, true);
            $scN = 0.0;
            $scN1 = 0.0;
            $sdN = 0.0;
            $sdN1 = 0.0;
            $sfN = $saN + $scN + $sdN;
            $sfN1 = $saN1 + $scN1 + $sdN1;
            $shN = $rwN + $sfN;
            $shN1 = $rwN1 + $sfN1;
            $skN = $sumResultByPrefixes(['81']);
            $skN1 = $sumResultByPrefixes(['81'], false, true);
            $slN = $sumResultByPrefixes(['83']);
            $slN1 = $sumResultByPrefixes(['83'], false, true);
            $smN = $sumResultByPrefixes(['85']);
            $smN1 = $sumResultByPrefixes(['85'], false, true);
            $soN = $skN + $slN + $smN;
            $soN1 = $skN1 + $slN1 + $smN1;
            $sqN = $sumResultByPrefixes(['87']);
            $sqN1 = $sumResultByPrefixes(['87'], false, true);
            $srN = $sumResultByPrefixes(['89']);
            $srN1 = $sumResultByPrefixes(['89'], false, true);
            $ssN = $sqN + $srN;
            $ssN1 = $sqN1 + $srN1;
            $stN = $shN + $soN + $ssN;
            $stN1 = $shN1 + $soN1 + $ssN1;

            // CPTE RESULTAT 4 (produits 2e partie)
            $uaN = $sumResultByPrefixes(['77'], true);
            $uaN1 = $sumResultByPrefixes(['77'], true, true);
            $ucN = 0.0;
            $ucN1 = 0.0;
            $udN = $sumResultByPrefixes(['796'], true);
            $udN1 = $sumResultByPrefixes(['796'], true, true);
            $ueN = $sumResultByPrefixes(['787'], true);
            $ueN1 = $sumResultByPrefixes(['787'], true, true);
            $ufN = $uaN + $ucN + $udN + $ueN;
            $ufN1 = $uaN1 + $ucN1 + $udN1 + $ueN1;
            $ugN = $ufN - $sfN;
            $ugN1 = $ufN1 - $sfN1;
            $uhN = $twN + $ufN;
            $uhN1 = $twN1 + $ufN1;
            $uiN = $uhN - $shN;
            $uiN1 = $uhN1 - $shN1;
            $ukN = $sumResultByPrefixes(['82'], true);
            $ukN1 = $sumResultByPrefixes(['82'], true, true);
            $ulN = $sumResultByPrefixes(['84'], true);
            $ulN1 = $sumResultByPrefixes(['84'], true, true);
            $umN = $sumResultByPrefixes(['86'], true);
            $umN1 = $sumResultByPrefixes(['86'], true, true);
            $unN = $sumResultByPrefixes(['88'], true);
            $unN1 = $sumResultByPrefixes(['88'], true, true);
            $uoN = $ukN + $ulN + $umN + $unN;
            $uoN1 = $ukN1 + $ulN1 + $umN1 + $unN1;
            $upN = $uoN - $soN;
            $upN1 = $uoN1 - $soN1;
            $utN = $uhN + $uoN;
            $utN1 = $uhN1 + $uoN1;
            $uzN = $utN - $stN;
            $uzN1 = $utN1 - $stN1;

            $nCharges = $stN;
            $nProducts = $utN;
            $n1Charges = $stN1;
            $n1Products = $utN1;
            $nResult = $uzN;
            $n1Result = $uzN1;
        @endphp

        <div class="resultat-page">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">- 12 -</div>
                <div class="resultat-head-box">COMPTE DE RESULTAT SYSTEME NORMAL<br>PAGE 1/4</div>
            </div>
            <h4 class="text-center fw-bold mb-2">COMPTE DE RESULTAT</h4>
            <table class="resultat-meta">
                <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
                <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
                <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
            </table>
            <table class="resultat-model-table">
                <thead>
                    <tr><th class="ref-col">Réf.</th><th>CHARGES (1re partie)</th><th class="n-col">Exercice N</th><th class="n1-col">Exercice N - 1</th></tr>
                </thead>
                <tbody>
                    <tr class="section-row"><td></td><td>ACTIVITE D'EXPLOITATION</td><td></td><td></td></tr>
                    <tr><td>RA</td><td>Achats de marchandises</td><td class="text-end">{{ number_format($raN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($raN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RB</td><td>- Variation de stocks (- ou +)</td><td class="text-end">{{ number_format($rbN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rbN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Marge brute sur marchandises voir TB)</td><td></td><td></td></tr>
                    <tr><td>RC</td><td>Achats de matières premières et fournitures liées</td><td class="text-end">{{ number_format($rcN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rcN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RD</td><td>- Variation de stocks (- ou +)</td><td class="text-end">{{ number_format($rdN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rdN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Marge brute sur matières voir TG)</td><td></td><td></td></tr>
                    <tr><td>RE</td><td>Autres achats</td><td class="text-end">{{ number_format($reN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($reN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RH</td><td>- Variation de stocks (- ou +)</td><td class="text-end">{{ number_format($rhN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rhN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RI</td><td>Transports</td><td class="text-end">{{ number_format($riN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($riN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RJ</td><td>Services extérieurs</td><td class="text-end">{{ number_format($rjN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rjN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RK</td><td>Impôts et taxes</td><td class="text-end">{{ number_format($rkN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rkN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>RL</td><td>Autres charges</td><td class="text-end">{{ number_format($rlN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rlN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Valeur ajoutée voir TN)</td><td></td><td></td></tr>
                    <tr><td>RP</td><td>Charges de personnel (1)</td><td class="text-end">{{ number_format($rpN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rpN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(1) dont personnel extérieur</td><td></td><td></td></tr>
                    <tr class="hint-row"><td>RQ</td><td>(Excédent brut d'exploitation voir TQ)</td><td></td><td></td></tr>
                    <tr><td>RS</td><td>Dotations aux amortissements et aux provisions</td><td class="text-end">{{ number_format($rsN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rsN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>RW</td><td>Total des charges d'exploitation</td><td class="text-end">{{ number_format($rwN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rwN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat d'exploitation voir TX)</td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>

        <div class="page-break"></div>

        <div class="resultat-page">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">- 13 -</div>
                <div class="resultat-head-box">COMPTE DE RESULTAT SYSTEME NORMAL<br>PAGE 2/4</div>
            </div>
            <h4 class="text-center fw-bold mb-2">COMPTE DE RESULTAT</h4>
            <table class="resultat-meta">
                <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
                <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
                <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
            </table>
            <table class="resultat-model-table">
                <thead>
                    <tr><th class="ref-col">Réf.</th><th>PRODUITS (1re partie)</th><th class="n-col">Exercice N</th><th class="n1-col">Exercice N - 1</th></tr>
                </thead>
                <tbody>
                    <tr class="section-row"><td></td><td>ACTIVITE D'EXPLOITATION</td><td></td><td></td></tr>
                    <tr><td>TA</td><td>Ventes de marchandises</td><td class="text-end">{{ number_format($taN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($taN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TB</td><td>MARGE BRUTE SUR MARCHANDISES</td><td class="text-end">{{ number_format($tbN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tbN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TC</td><td>Ventes de produits fabriqués</td><td class="text-end">{{ number_format($tcN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tcN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TD</td><td>Travaux, services vendus</td><td class="text-end">{{ number_format($tdN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tdN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TE</td><td>Production stockée (ou déstockage) (+ ou -)</td><td class="text-end">{{ number_format($teN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($teN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TF</td><td>Production immobilisée</td><td class="text-end">{{ number_format($tfN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tfN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TG</td><td>MARGE BRUTE SUR MATIERES</td><td class="text-end">{{ number_format($tgN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tgN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TH</td><td>Produits accessoires</td><td class="text-end">{{ number_format($thN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($thN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TI</td><td>CHIFFRE D'AFFAIRES (1)</td><td class="text-end">{{ number_format($tiN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tiN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TJ</td><td>(1) dont à l'exportation</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TK</td><td>Subventions d'exploitation</td><td class="text-end">{{ number_format($tkN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tkN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TL</td><td>Autres produits</td><td class="text-end">{{ number_format($tlN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tlN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TN</td><td>VALEUR AJOUTEE</td><td class="text-end">{{ number_format($tnN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tnN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TQ</td><td>EXCEDENT BRUT D'EXPLOITATION</td><td class="text-end">{{ number_format($tqN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tqN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TS</td><td>Reprises de provisions</td><td class="text-end">{{ number_format($tsN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tsN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>TT</td><td>Transferts de charges</td><td class="text-end">{{ number_format($ttN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ttN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>TW</td><td>Total des produits d'exploitation</td><td class="text-end">{{ number_format($twN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($twN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>TX</td><td>RESULTAT D'EXPLOITATION (Bénéfice + ; Perte -)</td><td class="text-end">{{ number_format($txN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($txN1, 0, ',', ' ') }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="page-break"></div>

        <div class="resultat-page">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">- 14 -</div>
                <div class="resultat-head-box">COMPTE DE RESULTAT SYSTEME NORMAL<br>PAGE 3/4</div>
            </div>
            <h4 class="text-center fw-bold mb-2">COMPTE DE RESULTAT</h4>
            <table class="resultat-meta">
                <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
                <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
                <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
            </table>
            <table class="resultat-model-table">
                <thead>
                    <tr><th class="ref-col">Réf.</th><th>CHARGES (2e partie)</th><th class="n-col">Exercice N</th><th class="n1-col">Exercice N - 1</th></tr>
                </thead>
                <tbody>
                    <tr><td>RW</td><td>Report Total des charges d'exploitation</td><td class="text-end">{{ number_format($rwN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($rwN1, 0, ',', ' ') }}</td></tr>
                    <tr class="section-row"><td></td><td>ACTIVITE FINANCIERE</td><td></td><td></td></tr>
                    <tr><td>SA</td><td>Frais financiers</td><td class="text-end">{{ number_format($saN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($saN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>SC</td><td>Pertes de change</td><td class="text-end">{{ number_format($scN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($scN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>SD</td><td>Dotations aux amortissements et aux provisions</td><td class="text-end">{{ number_format($sdN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($sdN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>SF</td><td>Total des charges financières</td><td class="text-end">{{ number_format($sfN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($sfN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat financier voir UG)</td><td></td><td></td></tr>
                    <tr class="total-row"><td>SH</td><td>Total des charges des activités ordinaires</td><td class="text-end">{{ number_format($shN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($shN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat des activités ordinaires voir UI)</td><td></td><td></td></tr>
                    <tr class="section-row"><td></td><td>HORS ACTIVITES ORDINAIRES (H.A.O.)</td><td></td><td></td></tr>
                    <tr><td>SK</td><td>Valeurs comptables des cessions d'immobilisations</td><td class="text-end">{{ number_format($skN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($skN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>SL</td><td>Charges H.A.O.</td><td class="text-end">{{ number_format($slN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($slN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>SM</td><td>Dotations H.A.O.</td><td class="text-end">{{ number_format($smN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($smN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>SO</td><td>Total des charges H.A.O.</td><td class="text-end">{{ number_format($soN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($soN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat H.A.O. voir UP)</td><td></td><td></td></tr>
                    <tr><td>SQ</td><td>Participation des travailleurs</td><td class="text-end">{{ number_format($sqN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($sqN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>SR</td><td>Impôts sur le résultat</td><td class="text-end">{{ number_format($srN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($srN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>SS</td><td>Total participation et impôts</td><td class="text-end">{{ number_format($ssN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ssN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>ST</td><td>TOTAL GENERAL DES CHARGES</td><td class="text-end">{{ number_format($stN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($stN1, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat net voir UZ)</td><td></td><td></td></tr>
                </tbody>
            </table>
        </div>

        <div class="page-break"></div>

        <div class="resultat-page">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div class="fw-bold">- 15 -</div>
                <div class="resultat-head-box">COMPTE DE RESULTAT SYSTEME NORMAL<br>PAGE 4/4</div>
            </div>
            <h4 class="text-center fw-bold mb-2">COMPTE DE RESULTAT</h4>
            <table class="resultat-meta">
                <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
                <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
                <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
            </table>
            <table class="resultat-model-table">
                <thead>
                    <tr><th class="ref-col">Réf.</th><th>PRODUITS (2e partie)</th><th class="n-col">Exercice N</th><th class="n1-col">Exercice N - 1</th></tr>
                </thead>
                <tbody>
                    <tr><td>TW</td><td>Report Total des produits d'exploitation</td><td class="text-end">{{ number_format($twN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($twN1, 0, ',', ' ') }}</td></tr>
                    <tr class="section-row"><td></td><td>ACTIVITE FINANCIERE</td><td></td><td></td></tr>
                    <tr><td>UA</td><td>Revenus financiers</td><td class="text-end">{{ number_format($uaN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uaN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UC</td><td>Gains de change</td><td class="text-end">{{ number_format($ucN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ucN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UD</td><td>Reprises de provisions</td><td class="text-end">{{ number_format($udN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($udN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UE</td><td>Transferts de charges</td><td class="text-end">{{ number_format($ueN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ueN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UF</td><td>Total des produits financiers</td><td class="text-end">{{ number_format($ufN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ufN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UG</td><td>RESULTAT FINANCIER (+ ou -)</td><td class="text-end">{{ number_format($ugN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ugN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UH</td><td>Total des produits des activités ordinaires</td><td class="text-end">{{ number_format($uhN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uhN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UI</td><td>RESULTAT DES ACTIVITES ORDINAIRES (+ ou -)</td><td class="text-end">{{ number_format($uiN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uiN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UJ</td><td>(1) dont impôt correspondant</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="section-row"><td></td><td>HORS ACTIVITES ORDINAIRES (H.A.O.)</td><td></td><td></td></tr>
                    <tr><td>UK</td><td>Produits des cessions d'immobilisations</td><td class="text-end">{{ number_format($ukN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ukN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UL</td><td>Produits H.A.O.</td><td class="text-end">{{ number_format($ulN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ulN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UM</td><td>Reprises H.A.O.</td><td class="text-end">{{ number_format($umN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($umN1, 0, ',', ' ') }}</td></tr>
                    <tr><td>UN</td><td>Transferts de charges</td><td class="text-end">{{ number_format($unN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($unN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UO</td><td>Total des produits H.A.O.</td><td class="text-end">{{ number_format($uoN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uoN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UP</td><td>RESULTAT H.A.O. (+ ou -)</td><td class="text-end">{{ number_format($upN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($upN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UT</td><td>TOTAL GENERAL DES PRODUITS</td><td class="text-end">{{ number_format($utN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($utN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UZ</td><td>RESULTAT NET (Bénéfice + ; Perte -)</td><td class="text-end">{{ number_format($uzN, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uzN1, 0, ',', ' ') }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section id="tafire-section" class="report-section mb-5">
        @php
            $tafireCafgN = ($uzN ?? 0) + ($rsN ?? 0) + ($sdN ?? 0) + ($smN ?? 0) - ($tsN ?? 0) - ($udN ?? 0) - ($umN ?? 0) - ($ukN ?? 0) + ($skN ?? 0);
            $tafireCafgN1 = ($uzN1 ?? 0) + ($rsN1 ?? 0) + ($sdN1 ?? 0) + ($smN1 ?? 0) - ($tsN1 ?? 0) - ($udN1 ?? 0) - ($umN1 ?? 0) - ($ukN1 ?? 0) + ($skN1 ?? 0);
            $tafireAfN = $tafireCafgN;
            $tafireAfN1 = $tafireCafgN1;
            $tafireVarStocksN = ($rbN ?? 0) + ($rdN ?? 0) + ($rhN ?? 0);
            $tafireVarStocksN1 = ($rbN1 ?? 0) + ($rdN1 ?? 0) + ($rhN1 ?? 0);
            $tafireVarCreancesN = ($creancesActifN ?? 0) - ($creancesActifN1 ?? 0);
            $tafireVarCreancesN1 = 0.0;
            $tafireVarDettesN = ($passifCirculant ?? 0) - ($passifCirculantN1 ?? 0);
            $tafireVarDettesN1 = 0.0;
            $tafireVarBfeN = $tafireVarStocksN + $tafireVarCreancesN + $tafireVarDettesN;
            $tafireVarBfeN1 = $tafireVarStocksN1 + $tafireVarCreancesN1 + $tafireVarDettesN1;
            $tafireEteN = ($tqN ?? 0) - $tafireVarBfeN - ($tfN ?? 0);
            $tafireEteN1 = ($tqN1 ?? 0) - $tafireVarBfeN1 - ($tfN1 ?? 0);
            $splitFlux = static function (float $value): array {
                if ($value >= 0) {
                    return ['emploi' => $value, 'ressource' => 0.0];
                }
                return ['emploi' => 0.0, 'ressource' => abs($value)];
            };

            $faN = $chargesImmobN ?? 0;
            $faN1 = $chargesImmobN1 ?? 0;
            $fbN = $actifIncorporelN ?? 0;
            $fbN1 = $actifIncorporelN1 ?? 0;
            $fcN = $actifCorporelN ?? 0;
            $fcN1 = $actifCorporelN1 ?? 0;
            $fdN = $actifFinancierN ?? 0;
            $fdN1 = $actifFinancierN1 ?? 0;

            $ffN = $faN + $fbN + $fcN + $fdN;
            $ffN1 = $faN1 + $fbN1 + $fcN1 + $fdN1;
            $fgN = $tafireVarBfeN;
            $fgN1 = $tafireVarBfeN1;
            $fhN = $ffN + $fgN;
            $fhN1 = $ffN1 + $fgN1;

            $fiN = $upN ?? 0;
            $fiN1 = $upN1 ?? 0;
            $fjN = 0.0;
            $fjN1 = 0.0;

            $fiFluxN = $splitFlux($fiN);
            $fiFluxN1 = $splitFlux($fiN1);
            $fjFluxN = $splitFlux($fjN);
            $fjFluxN1 = $splitFlux($fjN1);

            $fgFluxN = $splitFlux($fgN);
            $fgFluxN1 = $splitFlux($fgN1);
            $fkEmploiN = $ffN + $fgFluxN['emploi'] + $fiFluxN['emploi'] + $fjFluxN['emploi'];
            $fkEmploiN1 = $ffN1 + $fgFluxN1['emploi'] + $fiFluxN1['emploi'] + $fjFluxN1['emploi'];

            $flN = $tafireAfN;
            $flN1 = $tafireAfN1;
            $fmN = max(0.0, ($capitalN ?? 0) - ($capitalN1 ?? 0));
            $fmN1 = 0.0;
            $fnN = $subventionsInvestissementN ?? 0;
            $fnN1 = $subventionsInvestissementN1 ?? 0;
            $fpN = max(0.0, ($capitalN1 ?? 0) - ($capitalN ?? 0));
            $fpN1 = 0.0;
            $fqN = $dettesFinancieresN ?? 0;
            $fqN1 = $dettesFinancieresN1 ?? 0;
            $frN = $dettesFinDiversesN ?? 0;
            $frN1 = $dettesFinDiversesN1 ?? 0;

            $splitFluxRessource = static function (float $value): array {
                if ($value >= 0) {
                    return ['emploi' => 0.0, 'ressource' => $value];
                }
                return ['emploi' => abs($value), 'ressource' => 0.0];
            };

            $flFluxN = $splitFluxRessource($flN);
            $flFluxN1 = $splitFluxRessource($flN1);
            $fmFluxN = $splitFluxRessource($fmN);
            $fmFluxN1 = $splitFluxRessource($fmN1);
            $fnFluxN = $splitFluxRessource($fnN);
            $fnFluxN1 = $splitFluxRessource($fnN1);
            $fpFluxN = $splitFlux($fpN);
            $fpFluxN1 = $splitFlux($fpN1);
            $fqFluxN = $splitFluxRessource($fqN);
            $fqFluxN1 = $splitFluxRessource($fqN1);
            $frFluxN = $splitFluxRessource($frN);
            $frFluxN1 = $splitFluxRessource($frN1);

            $fsRessourceN = $flFluxN['ressource'] + $fmFluxN['ressource'] + $fnFluxN['ressource'] + $fqFluxN['ressource'] + $frFluxN['ressource'];
            $fsRessourceN1 = $flFluxN1['ressource'] + $fmFluxN1['ressource'] + $fnFluxN1['ressource'] + $fqFluxN1['ressource'] + $frFluxN1['ressource'];

            $ftN = $fsRessourceN - $fkEmploiN;
            $ftN1 = $fsRessourceN1 - $fkEmploiN1;
            $ftFluxN = $splitFlux(-$ftN);
            $ftFluxN1 = $splitFlux(-$ftN1);

            $fuN = ($tresorerieActif ?? 0) - ($tresoreriePassif ?? 0);
            $fuN1 = ($tresorerieActifN1 ?? 0) - ($tresoreriePassifN1 ?? 0);
            $fvN = $fuN1;
            $fvN1 = 0.0;
            $fwN = $fuN - $fvN;
            $fwN1 = $fuN1 - $fvN1;
            $fwFluxN = $splitFlux($fwN);
            $fwFluxN1 = $splitFlux($fwN1);

            $tafireControlN = abs($ftN + $fwN) < 1 ? 'OK' : 'Écart';
            $tafireControlN1 = abs($ftN1 + $fwN1) < 1 ? 'OK' : 'Écart';
        @endphp
        <h4>6. TAFIRE</h4>
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-bold">TABLEAU FINANCIER DES RESSOURCES ET DES EMPLOIS (TAFIRE)</div>
            <div class="text-end small"><div>TAFIRE SYSTEME NORMAL</div><div>PAGE 3/4 - 4/4</div></div>
        </div>
        <table class="tafire-meta">
            <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
            <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
            <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
        </table>

        <div class="table-responsive mb-3">
            <table class="tafire-model-table">
                <thead>
                    <tr>
                        <th class="ref-col">Réf.</th>
                        <th>Libellé</th>
                        <th class="flux-col">Emplois N</th>
                        <th class="flux-col">Ressources N</th>
                        <th class="flux-col">Emplois N-1</th>
                        <th class="flux-col">Ressources N-1</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="section-row"><td class="ref-col"></td><td colspan="5">I. INVESTISSEMENTS ET DESINVESTISSEMENTS</td></tr>
                    <tr><td class="ref-col">FA</td><td>Charges immobilisées (augmentations dans l'exercice)</td><td class="text-end">{{ number_format($faN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($faN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                    <tr><td class="ref-col">FB</td><td>Acquisitions/Cessions d'immobilisations incorporelles</td><td class="text-end">{{ number_format($fbN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($fbN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                    <tr><td class="ref-col">FC</td><td>Acquisitions/Cessions d'immobilisations corporelles</td><td class="text-end">{{ number_format($fcN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($fcN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                    <tr><td class="ref-col">FD</td><td>Acquisitions/Cessions d'immobilisations financières</td><td class="text-end">{{ number_format($fdN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($fdN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                    <tr class="total-row"><td class="ref-col">FF</td><td>INVESTISSEMENT TOTAL</td><td class="text-end">{{ number_format($ffN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($ffN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>

                    <tr class="section-row"><td class="ref-col">FG</td><td>II. VARIATION DU BESOIN DE FINANCEMENT D'EXPLOITATION</td><td class="text-end">{{ number_format($splitFlux($fgN)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fgN)['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fgN1)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fgN1)['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td class="ref-col">FH</td><td>A - EMPLOIS ECONOMIQUES A FINANCER (FF + FG)</td><td class="text-end">{{ number_format($splitFlux($fhN)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fhN)['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fhN1)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fhN1)['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FI</td><td>III. EMPLOIS/RESSOURCES (B.F., H.A.O.)</td><td class="text-end">{{ number_format($fiFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fiFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fiFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fiFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FJ</td><td>IV. EMPLOIS FINANCIERS CONTRAINTS</td><td class="text-end">{{ number_format($fjFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fjFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fjFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fjFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td class="ref-col">FK</td><td>B - EMPLOIS TOTAUX A FINANCER</td><td class="text-end">{{ number_format($fkEmploiN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($fkEmploiN1, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                </tbody>
            </table>
        </div>

        <div class="table-responsive">
            <table class="tafire-model-table">
                <thead>
                    <tr>
                        <th class="ref-col">Réf.</th>
                        <th>Libellé</th>
                        <th class="flux-col">Emplois N</th>
                        <th class="flux-col">Ressources N</th>
                        <th class="flux-col">Emplois N-1</th>
                        <th class="flux-col">Ressources N-1</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="section-row"><td class="ref-col"></td><td colspan="5">V. VI. VII. FINANCEMENT</td></tr>
                    <tr><td class="ref-col">FL</td><td>Dividendes (emplois) / C.A.F.G. (ressources)</td><td class="text-end">{{ number_format($flFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($flFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($flFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($flFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FM</td><td>Augmentations de capital par apports nouveaux</td><td class="text-end">{{ number_format($fmFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fmFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fmFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fmFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FN</td><td>Subventions d'investissement</td><td class="text-end">{{ number_format($fnFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fnFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fnFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fnFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FP</td><td>Prélèvements sur le capital</td><td class="text-end">{{ number_format($fpFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fpFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fpFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fpFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FQ</td><td>Emprunts</td><td class="text-end">{{ number_format($fqFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fqFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fqFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fqFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FR</td><td>Autres dettes financières</td><td class="text-end">{{ number_format($frFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($frFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($frFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($frFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td class="ref-col">FS</td><td>C - RESSOURCES NETTES DE FINANCEMENT</td><td class="text-end">0</td><td class="text-end">{{ number_format($fsRessourceN, 0, ',', ' ') }}</td><td class="text-end">0</td><td class="text-end">{{ number_format($fsRessourceN1, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td class="ref-col">FT</td><td>D - EXCEDENT OU INSUFFISANCE DE RESSOURCES (C - B)</td><td class="text-end">{{ number_format($ftFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ftFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ftFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($ftFluxN1['ressource'], 0, ',', ' ') }}</td></tr>

                    <tr class="section-row"><td class="ref-col"></td><td colspan="5">VIII. VARIATION DE LA TRESORERIE</td></tr>
                    <tr><td class="ref-col">FU</td><td>Trésorerie nette à la clôture de l'exercice (+/-)</td><td class="text-end">{{ number_format($splitFlux($fuN)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fuN)['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fuN1)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fuN1)['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr><td class="ref-col">FV</td><td>Trésorerie nette à l'ouverture de l'exercice (+/-)</td><td class="text-end">{{ number_format($splitFlux($fvN)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fvN)['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fvN1)['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($splitFlux($fvN1)['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td class="ref-col">FW</td><td>Variation de trésorerie (+ si Emploi ; - si Ressources)</td><td class="text-end">{{ number_format($fwFluxN['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fwFluxN['ressource'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fwFluxN1['emploi'], 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fwFluxN1['ressource'], 0, ',', ' ') }}</td></tr>
                    <tr>
                        <td class="ref-col"></td>
                        <td>Contrôle : D = VIII avec signe opposé</td>
                        <td colspan="2" class="text-center"><span class="badge {{ $tafireControlN === 'OK' ? 'bg-success' : 'bg-danger' }}">N : {{ $tafireControlN }}</span></td>
                        <td colspan="2" class="text-center"><span class="badge {{ $tafireControlN1 === 'OK' ? 'bg-success' : 'bg-danger' }}">N-1 : {{ $tafireControlN1 }}</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="small text-muted mt-2">Nota : I, IV, V, VI, VII en termes de flux ; II, III, VIII en différences bilantielles.</div>
        <div class="small text-muted mt-1">Synthèse soldes : CAFG N={{ number_format($tafireCafgN, 0, ',', ' ') }} / N-1={{ number_format($tafireCafgN1, 0, ',', ' ') }} ; ETE N={{ number_format($tafireEteN, 0, ',', ' ') }} / N-1={{ number_format($tafireEteN1, 0, ',', ' ') }}</div>
    </section>

    <section id="annexe-section" class="report-section mb-5">
        <h4>7. État annexe</h4>
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="fw-bold">ETAT ANNEXE DU SYSTEME NORMAL</div>
            <div class="text-end small"><div>ETAT ANNEXE</div><div>PAGE 1/28 (synthèse structurée)</div></div>
        </div>
        <table class="tafire-meta mb-3">
            <tr><td>Dénomination sociale de l'entreprise :</td><td>{{ $companyName }}</td><td>Sigle usuel :</td><td>{{ $companySigle ?? '' }}</td></tr>
            <tr><td>Adresse :</td><td>{{ $companyAddress ?? '' }}</td><td></td><td></td></tr>
            <tr><td>N° d'identification fiscale :</td><td>{{ $companyTaxId ?? '#N/A' }}</td><td>Exercice clos le : {{ $exerciseEnd }} &nbsp;&nbsp; Durée (en mois) : {{ $durationMonths }}</td><td></td></tr>
        </table>

        <div class="annexe-block-title">I - INFORMATIONS OBLIGATOIRES</div>
        <table class="annexe-table mb-3">
            <thead>
                <tr><th style="width: 16%">Référence</th><th style="width: 34%">Rubrique</th><th>Contenu</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>I - A1</td>
                    <td>METHODES GENERALES D'EVALUATION APPLIQUEES PAR L'ENTREPRISE</td>
                    <td>Référentiel SYSCOHADA appliqué, classement des comptes selon plan OHADA, valorisation des actifs et passifs à partir des écritures validées de la période.</td>
                </tr>
                <tr>
                    <td>I - A2</td>
                    <td>METHODES SPECIFIQUES D'EVALUATION APPLIQUEES PAR L'ENTREPRISE</td>
                    <td>Immobilisations, stocks, créances, dettes et provisions calculés sur la Balance N / N-1. Les variations sont reprises dans le Bilan, le Compte de résultat et le TAFIRE.</td>
                </tr>
                <tr>
                    <td>I - A3</td>
                    <td>DEROGATIONS UTILISEES PAR L'ENTREPRISE</td>
                    <td>À documenter si des dérogations existent ; en l'absence de cas spécifique, conservation des règles standards du système normal.</td>
                </tr>
            </tbody>
        </table>

        <div class="annexe-block-title">II - INDICATEURS DE COHERENCE FINANCIERE</div>
        <table class="annexe-table mb-3">
            <thead>
                <tr><th>Indicateur</th><th class="text-end">Exercice N</th><th class="text-end">Exercice N-1</th></tr>
            </thead>
            <tbody>
                <tr><td>Chiffre d'affaires (TI)</td><td class="text-end">{{ number_format($tiN ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($tiN1 ?? 0, 0, ',', ' ') }}</td></tr>
                <tr><td>Résultat d'exploitation (TX)</td><td class="text-end">{{ number_format($txN ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($txN1 ?? 0, 0, ',', ' ') }}</td></tr>
                <tr><td>Résultat net (UZ)</td><td class="text-end">{{ number_format($uzN ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($uzN1 ?? 0, 0, ',', ' ') }}</td></tr>
                <tr><td>Total Actif (BZ)</td><td class="text-end">{{ number_format($totalActifGeneral ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalActifGeneralN1 ?? 0, 0, ',', ' ') }}</td></tr>
                <tr><td>Total Passif (DZ)</td><td class="text-end">{{ number_format($totalPassifGeneral ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($totalPassifGeneralN1 ?? 0, 0, ',', ' ') }}</td></tr>
                <tr><td>Variation de trésorerie (FW)</td><td class="text-end">{{ number_format($fwN ?? 0, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($fwN1 ?? 0, 0, ',', ' ') }}</td></tr>
            </tbody>
        </table>

        <div class="small text-muted">N.B. : en cas d'insuffisance des espaces réservés, les explications détaillées peuvent être complétées dans une annexe descriptive jointe à la liasse.</div>
    </section>

    <div class="text-end no-print mb-4">
        <a href="{{ route('accounting') }}" class="btn btn-outline-secondary">Retour au module comptabilité</a>
    </div>

    <script>
        function printJournalOnly() {
            const header = document.querySelector('.journal-print-header');
            const journalSection = document.querySelector('#journal-section');
            const table = journalSection ? journalSection.querySelector('.journal-table') : null;

            if (!header || !table) {
                alert("Impossible de preparer l'impression du journal.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Journal</title>
                    <style>
                        @page { size: A4 portrait; margin: 12mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; }
                        .journal-print-header {
                            display: flex;
                            justify-content: space-between;
                            align-items: flex-start;
                            gap: 16px;
                            border-bottom: 1px solid #000;
                            padding-bottom: 10px;
                            margin-bottom: 10px;
                        }
                        .journal-print-header .meta { font-size: 12px; line-height: 1.4; }
                        .journal-table { width: 100%; border-collapse: collapse; font-size: 10px; table-layout: fixed; }
                        .journal-table th, .journal-table td { border: 1px solid #000; padding: 4px 5px; vertical-align: top; }
                        .journal-table thead { display: table-header-group; }
                        .journal-table tfoot { display: table-row-group; }
                        .journal-table tbody tr:nth-child(even) td { background: #f8faff; }
                        .journal-table th { background: #0b3d91; color: #fff; border-color: #0b3d91; white-space: nowrap; }
                        .journal-table .text-end { text-align: right; }
                        .journal-table .amount-cell { color: #1f3fbf; font-weight: 700; }
                        .journal-table .control-cell { color: #b02a37; font-weight: 600; }
                        .journal-table .tier-cell { color: #1f3fbf; }
                        .journal-table th:nth-child(1), .journal-table td:nth-child(1) { width: 70px; }
                        .journal-table th:nth-child(2), .journal-table td:nth-child(2) { width: 40px; }
                        .journal-table th:nth-child(3), .journal-table td:nth-child(3) { width: 70px; }
                        .journal-table th:nth-child(4), .journal-table td:nth-child(4) { width: 54px; }
                        .journal-table th:nth-child(5), .journal-table td:nth-child(5) { width: 140px; }
                        .journal-table th:nth-child(6), .journal-table td:nth-child(6) { width: 190px; }
                        .journal-table th:nth-child(7), .journal-table td:nth-child(7),
                        .journal-table th:nth-child(8), .journal-table td:nth-child(8) { width: 78px; }
                        .journal-table th:nth-child(9), .journal-table td:nth-child(9) { width: 95px; }
                        .journal-table th:nth-child(10), .journal-table td:nth-child(10) { width: 88px; }
                        .journal-table th:nth-child(11), .journal-table td:nth-child(11) { width: 78px; }
                        .journal-table th:nth-child(12), .journal-table td:nth-child(12) { width: 60px; }
                        .journal-table th:nth-child(13), .journal-table td:nth-child(13) { width: 78px; }
                        .journal-table .badge {
                            display: inline-block;
                            border: 1px solid #000;
                            padding: 1px 4px;
                            border-radius: 2px;
                            background: #fff;
                            color: #000;
                            font-size: 10px;
                        }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    <table class="journal-table">${table.innerHTML}</table>
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }

            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();

            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printGrandLivreOnly() {
            const header = document.querySelector('.journal-print-header');
            const grandLivre = document.querySelector('#grand-livre-section');

            if (!header || !grandLivre) {
                alert("Impossible de preparer l'impression du grand livre.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Grand Livre</title>
                    <style>
                        @page { size: A4 portrait; margin: 10mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 11px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 8px; }
                        .journal-print-header .meta { font-size: 11px; line-height: 1.4; }
                        .grandlivre-model-title { background: #0b3d91; color: #fff; font-weight: 700; padding: 4px 6px; text-transform: uppercase; }
                        .grandlivre-meta-table { border-collapse: collapse; margin-bottom: 6px; }
                        .grandlivre-meta-table td { border: 1px solid #d9d9d9; padding: 2px 5px; }
                        .meta-value { color: #1c8b3c; font-weight: 700; }
                        .grandlivre-model-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px; margin-bottom: 12px; }
                        .grandlivre-model-table th, .grandlivre-model-table td { border: 1px solid #000; padding: 3px 4px; }
                        .grandlivre-model-table th { background: #0b3d91; color: #fff; border-color: #0b3d91; }
                        .grandlivre-model-table tbody tr:nth-child(even) td { background: #f8faff; }
                        .amount { color: #1f3fbf; font-weight: 700; }
                        .text-end { text-align: right; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    ${grandLivre.innerHTML}
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }

            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printBalanceOnly() {
            const header = document.querySelector('.journal-print-header');
            const balance = document.querySelector('#balance-section');
            if (!header || !balance) {
                alert("Impossible de preparer l'impression de la balance.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Balance</title>
                    <style>
                        @page { size: A4 portrait; margin: 8mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 10px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #000; margin-bottom: 8px; padding-bottom: 6px; }
                        .journal-print-header .meta { font-size: 10px; line-height: 1.3; }
                        .grandlivre-model-title { background: #0b3d91; color: #fff; font-weight: 700; padding: 4px 6px; text-transform: uppercase; }
                        .balance-model-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9px; }
                        .balance-model-table th, .balance-model-table td { border: 1px solid #000; padding: 2px 3px; }
                        .balance-model-table thead th { background: #0b3d91; color: #fff; border-color: #0b3d91; }
                        .balance-model-table .acc-cell { color: #1c8b3c; font-weight: 700; }
                        .balance-model-table .amount { color: #1f3fbf; font-weight: 700; }
                        .balance-model-table .obs-none { color: #b8682d; font-weight: 600; }
                        .balance-model-table .obs-move { color: #1c8b3c; font-weight: 700; }
                        .text-end { text-align: right; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    ${balance.innerHTML}
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printResultatOnly() {
            const header = document.querySelector('.journal-print-header');
            const resultat = document.querySelector('#resultat-section');
            if (!header || !resultat) {
                alert("Impossible de preparer l'impression du compte de resultat.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Compte de resultat</title>
                    <style>
                        @page { size: A4 portrait; margin: 8mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 10px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #000; margin-bottom: 8px; padding-bottom: 6px; }
                        .journal-print-header .meta { font-size: 10px; line-height: 1.3; }
                        .resultat-head-box { border: 1px solid #000; text-align: center; font-weight: 700; padding: 2px 6px; line-height: 1.25; margin-bottom: 6px; }
                        .resultat-meta { border-collapse: collapse; width: 100%; margin-bottom: 6px; font-size: 10px; }
                        .resultat-meta td { border-bottom: 1px solid #000; padding: 2px 4px; }
                        .resultat-model-table { width: 100%; border-collapse: collapse; font-size: 9px; }
                        .resultat-model-table th, .resultat-model-table td { border: 1px solid #000; padding: 2px 3px; }
                        .resultat-model-table thead th { background: #fff; font-weight: 700; }
                        .resultat-model-table .section-row td { font-weight: 700; background: #fff; }
                        .resultat-model-table .total-row td { background: #d9d9d9; font-weight: 700; }
                        .resultat-model-table .hint-row td { font-style: italic; }
                        .resultat-model-table .n-col, .resultat-model-table .n1-col { text-align: right; }
                        .page-break { page-break-after: always; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    ${resultat.innerHTML}
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printTafireOnly() {
            const header = document.querySelector('.journal-print-header');
            const tafire = document.querySelector('#tafire-section');
            if (!header || !tafire) {
                alert("Impossible de preparer l'impression du TAFIRE.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression TAFIRE</title>
                    <style>
                        @page { size: A4 portrait; margin: 10mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 10px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #000; margin-bottom: 8px; padding-bottom: 6px; }
                        .journal-print-header .meta { font-size: 10px; line-height: 1.3; }
                        .card { border: 1px solid #ddd; margin-bottom: 10px; }
                        .card-header { background: #f5f5f5; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #ddd; }
                        .table { width: 100%; border-collapse: collapse; }
                        .table th, .table td { border: 1px solid #000; padding: 4px 6px; }
                        .table th { background: #f7f7f7; }
                        .text-end { text-align: right; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    ${tafire.innerHTML}
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printAnnexeOnly() {
            const header = document.querySelector('.journal-print-header');
            const annexe = document.querySelector('#annexe-section');
            if (!header || !annexe) {
                alert("Impossible de preparer l'impression de l'etat annexe.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Etat annexe</title>
                    <style>
                        @page { size: A4 portrait; margin: 10mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 10px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #000; margin-bottom: 8px; padding-bottom: 6px; }
                        .journal-print-header .meta { font-size: 10px; line-height: 1.3; }
                        .card { border: 1px solid #ddd; margin-bottom: 10px; }
                        .card-header { background: #f5f5f5; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #ddd; }
                        .table { width: 100%; border-collapse: collapse; }
                        .table th, .table td { border: 1px solid #000; padding: 4px 6px; }
                        .text-end { text-align: right; }
                        .alert { border: 1px solid #0dcaf0; padding: 8px; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    ${annexe.innerHTML}
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printBilanOnly() {
            const header = document.querySelector('.journal-print-header');
            const bilan = document.querySelector('#bilan-section');
            if (!header || !bilan) {
                alert("Impossible de preparer l'impression du bilan.");
                return;
            }

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Impression Bilan</title>
                    <style>
                        @page { size: A4 landscape; margin: 8mm; }
                        body { font-family: Arial, sans-serif; color: #111; margin: 0; font-size: 9px; }
                        .journal-print-header { display: flex; justify-content: space-between; gap: 10px; border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 8px; }
                        .journal-print-header .meta { font-size: 10px; line-height: 1.35; }
                        .bilan-print-wrap { width: 100%; }
                        .bilan-print-wrap .d-flex { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
                        .bilan-header { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; margin-bottom: 10px; font-size: 9px; }
                        .bilan-header .field span { display: block; font-weight: 600; margin-bottom: 2px; }
                        .bilan-print-row { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-start; }
                        .bilan-print-col { flex: 1 1 48%; min-width: 280px; }
                        .bilan-table { width: 100%; border-collapse: collapse; font-size: 8px; }
                        .bilan-table th, .bilan-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }
                        .bilan-table th { background: #f1f1f1; font-weight: 700; }
                        .bilan-table .section-title td { background: #e9ecef; font-weight: 700; border-top: 2px solid #000; }
                        .bilan-table .text-end { text-align: right; }
                        .bilan-table .text-center { text-align: center; }
                    </style>
                </head>
                <body>
                    ${header.outerHTML}
                    <div class="bilan-print-wrap">
                        ${bilan.innerHTML}
                    </div>
                </body>
                </html>`;

            const printWindow = window.open('', '_blank', 'width=1200,height=900,scrollbars=yes');
            if (!printWindow) {
                alert("Impossible d'ouvrir la fenetre d'impression. Verifiez le bloqueur de pop-ups.");
                return;
            }
            printWindow.document.open();
            printWindow.document.write(html);
            printWindow.document.close();
            printWindow.onload = function () {
                printWindow.focus();
                printWindow.print();
            };
        }

        function printSection(sectionId) {
            const allSections = document.querySelectorAll('.report-section');
            const header = document.querySelector('.report-header');
            const noPrintItems = document.querySelectorAll('.no-print');
            const printClass = `print-mode-${sectionId}`;

            allSections.forEach(section => {
                if (section.id !== sectionId && sectionId !== 'full-report') {
                    section.classList.add('print-hidden');
                } else {
                    section.classList.remove('print-hidden');
                }
            });

            noPrintItems.forEach(item => item.classList.add('print-hidden'));
            document.body.classList.add(printClass);
            window.print();
            allSections.forEach(section => section.classList.remove('print-hidden'));
            if (header) header.classList.remove('print-hidden');
            noPrintItems.forEach(item => item.classList.remove('print-hidden'));
            document.body.classList.remove(printClass);
        }

        function previewBilan() {
            const header = document.querySelector('.report-header');
            const bilanSection = document.querySelector('#bilan-section');
            const styles = Array.from(document.querySelectorAll('style')).map(style => style.outerHTML).join('');

            const html = `<!doctype html>
                <html lang="fr">
                <head>
                    <meta charset="utf-8">
                    <title>Prévisualisation du bilan</title>
                    ${styles}
                    <style>
                        body { margin: 0; padding: 24px; font-family: Arial, sans-serif; background: #f5f5f5; }
                        .report-preview { background: #fff; padding: 24px; max-width: 1200px; margin: 0 auto; }
                        .report-header, #bilan-section { page-break-inside: avoid; }
                        .report-header { margin-bottom: 24px; }
                    </style>
                </head>
                <body>
                    <div class="report-preview">
                        ${header ? header.outerHTML : ''}
                        ${bilanSection ? bilanSection.outerHTML : ''}
                    </div>
                </body>
                </html>`;

            const previewWindow = window.open('', '_blank', 'width=1100,height=800,scrollbars=yes');
            if (!previewWindow) {
                alert('Impossible d’ouvrir la fenêtre de prévisualisation. Vérifiez votre bloqueur de pop-ups.');
                return;
            }
            previewWindow.document.write(html);
            previewWindow.document.close();
        }

        function downloadBilan() {
            printBilanOnly();
        }
    </script>
@endsection
