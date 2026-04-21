@extends('layouts.app')

@section('title', 'Rapport comptable | Sitiame Capitale')
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
        .grandlivre-model-title {
            background: #0b3d91;
            color: #fff;
            font-weight: 700;
            padding: .35rem .5rem;
            letter-spacing: .3px;
            text-transform: uppercase;
        }
        .grandlivre-meta-table {
            font-size: .9rem;
            border-collapse: collapse;
            margin-bottom: .5rem;
        }
        .grandlivre-meta-table td {
            border: 1px solid #d9d9d9;
            padding: .15rem .35rem;
        }
        .grandlivre-meta-table .meta-value {
            color: #1c8b3c;
            font-weight: 700;
        }
        .grandlivre-model-table thead th {
            background: #0b3d91;
            color: #fff;
            border-color: #0b3d91;
            padding: .32rem .38rem;
        }
        .grandlivre-model-table td {
            padding: .24rem .38rem;
            line-height: 1.2;
        }
        .grandlivre-model-table .amount {
            color: #1f3fbf;
            font-weight: 700;
        }
        .balance-model-table {
            font-size: .74rem;
            table-layout: fixed;
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
                #grand-livre-section, #balance-section, #bilan-section, #resultat-section, 
                .page-break:nth-of-type(n+1) { display: none !important; }
            @elseif($reportType === 'grand-livre')
                #journal-section, #balance-section, #bilan-section, #resultat-section,
                .page-break:nth-of-type(1), .page-break:nth-of-type(n+3) { display: none !important; }
            @elseif($reportType === 'balance')
                #journal-section, #grand-livre-section, #bilan-section, #resultat-section,
                .page-break:nth-of-type(n+2) { display: none !important; }
            @elseif($reportType === 'bilan')
                #journal-section, #grand-livre-section, #balance-section, #resultat-section,
                .page-break:nth-of-type(n+3) { display: none !important; }
            @elseif($reportType === 'resultat')
                #journal-section, #grand-livre-section, #balance-section, #bilan-section,
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
    </style>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <h5 class="card-title">{{ $currentReportTitle }}</h5>
                        <p class="text-muted mb-2">Document comptable structuré (OHADA / SYSCOHADA) : journal, grand livre, balance, bilan et compte de résultat.</p>
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
                    <img src="{{ asset('storage/' . $companyLogo) }}" alt="Logo {{ $companyName }}" style="width:120px; height:auto; object-fit:contain;" />
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
                <img src="{{ asset('storage/' . $companyLogo) }}" alt="Logo {{ $companyName }}" style="width:90px; height:auto; object-fit:contain;" />
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
        <div class="card mb-4 p-3 no-print">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="printBilanOnly()">
                    <i data-feather="printer" class="me-1"></i>Imprimer le bilan
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="previewBilan()">
                    <i data-feather="eye" class="me-1"></i>Visualiser le bilan
                </button>
                <a href="{{ route('accounting.report.bilan.download') }}" class="btn btn-primary btn-sm">
                    <i data-feather="download" class="me-1"></i>Télécharger le bilan (PDF)
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

    <section id="journal-section" class="report-section mb-5">
        @php
            $journalCodeByType = [
                'Vente' => 'VT',
                'Achat' => 'AC',
                'Reçu' => 'VR',
                'Justificatif' => 'BQ',
            ];
            $journalRows = [];
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

                $journalRows[] = [
                    'date' => $entry->date->format('d/m/Y'),
                    'journal' => $journalCode,
                    'piece' => $piece,
                    'compte' => $debitCode,
                    'intitule' => $debitLabel,
                    'libelle' => $description,
                    'debit' => $entry->amount,
                    'credit' => null,
                    'tiers' => $tiers,
                    'centre' => '-',
                    'controle' => 'A verifier',
                    'num_ligne' => '1',
                    'compte_ligne' => $entry->id . '|1',
                ];

                $journalRows[] = [
                    'date' => $entry->date->format('d/m/Y'),
                    'journal' => $journalCode,
                    'piece' => $piece,
                    'compte' => $creditCode,
                    'intitule' => $creditLabel,
                    'libelle' => $description,
                    'debit' => null,
                    'credit' => $entry->amount,
                    'tiers' => $tiers,
                    'centre' => '-',
                    'controle' => 'A verifier',
                    'num_ligne' => '2',
                    'compte_ligne' => $entry->id . '|2',
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
        @endphp
        <h4>2. Grand livre</h4>
        <p class="text-muted">Extraction automatique par compte selon le modèle comptable.</p>

        @forelse($ledger as $account => $amounts)
            @php
                $parts = preg_split('/\s+/', (string) $account, 2);
                $accountCode = $parts[0] ?? $account;
                $accountTitle = $parts[1] ?? $account;
                $running = 0.0;
                $rows = [];
                foreach ($sortedEntries as $entry) {
                    $isDebit = $entry->debit_account === $account;
                    $isCredit = $entry->credit_account === $account;
                    if (! $isDebit && ! $isCredit) {
                        continue;
                    }
                    $debit = $isDebit ? (float) $entry->amount : null;
                    $credit = $isCredit ? (float) $entry->amount : null;
                    $running += ($debit ?? 0) - ($credit ?? 0);
                    $rows[] = [
                        'date' => $entry->date->format('d/m/Y'),
                        'journal' => $journalCodeByType[$entry->document_type] ?? 'OD',
                        'piece' => $entry->document_reference ?: ('PJ-' . $entry->id),
                        'libelle' => $entry->description,
                        'debit' => $debit,
                        'credit' => $credit,
                        'solde' => $running,
                    ];
                }
            @endphp
            @if(!empty($rows))
                <div class="mb-4 grandlivre-account-block">
                    <div class="grandlivre-model-title">GRAND LIVRE - EXTRACTION AUTOMATIQUE PAR COMPTE</div>
                    <table class="grandlivre-meta-table">
                        <tr>
                            <td><strong>Compte</strong></td>
                            <td class="meta-value">{{ $accountCode }}</td>
                            <td><strong>Intitulé</strong></td>
                            <td class="meta-value">{{ $accountTitle }}</td>
                        </tr>
                    </table>
                    <div class="table-responsive">
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
                                @foreach($rows as $index => $row)
                                    <tr>
                                        <td class="text-end">{{ $index + 1 }}</td>
                                        <td>{{ $row['date'] }}</td>
                                        <td class="meta-value">{{ $row['journal'] }}</td>
                                        <td class="meta-value">{{ $row['piece'] }}</td>
                                        <td class="meta-value">{{ $row['libelle'] }}</td>
                                        <td class="text-end amount">{{ $row['debit'] !== null ? number_format($row['debit'], 0, ',', ' ') : '-' }}</td>
                                        <td class="text-end amount">{{ $row['credit'] !== null ? number_format($row['credit'], 0, ',', ' ') : '-' }}</td>
                                        <td class="text-end amount">{{ $row['solde'] > 0 ? number_format($row['solde'], 0, ',', ' ') : '-' }}</td>
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

            $ledgerByCode = [];
            foreach ($ledger as $accountName => $amounts) {
                if (preg_match('/^(\d{1,3})\s*(.*)$/', trim((string) $accountName), $m)) {
                    $code = $m[1];
                    $label = trim((string) ($m[2] ?? ''));
                    $ledgerByCode[$code] = [
                        'label' => $label !== '' ? $label : ($balanceTemplate[$code] ?? $accountName),
                        'debit' => (float) ($amounts['debit'] ?? 0),
                        'credit' => (float) ($amounts['credit'] ?? 0),
                    ];
                }
            }

            // On conserve le référentiel modèle + les comptes réellement utilisés hors référentiel.
            $allCodes = array_values(array_unique(array_merge(array_keys($balanceTemplate), array_keys($ledgerByCode))));
            sort($allCodes, SORT_NATURAL);
        @endphp

        <h4>3. Balance</h4>
        <p class="text-muted">Balance generale SYSCOHADA par compte, avec soldes nets debit/credit.</p>

        <div class="grandlivre-model-title mb-2">BALANCE GENERALE - SYSCOHADA</div>
        <div class="table-responsive">
            <table class="table table-bordered table-sm balance-model-table">
                <thead>
                    <tr>
                        <th style="width:60px;">Classe</th>
                        <th style="width:70px;">Compte</th>
                        <th>Intitule</th>
                        <th class="text-end" style="width:120px;">Debit periode</th>
                        <th class="text-end" style="width:120px;">Credit periode</th>
                        <th class="text-end" style="width:110px;">Solde debit</th>
                        <th class="text-end" style="width:110px;">Solde credit</th>
                        <th class="text-end" style="width:110px;">Mouvement</th>
                        <th style="width:130px;">Observation</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($allCodes as $code)
                        @php
                            $row = $ledgerByCode[$code] ?? null;
                            $label = $row['label'] ?? ($balanceTemplate[$code] ?? 'Compte');
                            $debit = (float) ($row['debit'] ?? 0);
                            $credit = (float) ($row['credit'] ?? 0);
                            $soldeDebit = max($debit - $credit, 0);
                            $soldeCredit = max($credit - $debit, 0);
                            $mouvement = $debit + $credit;
                            $observation = $mouvement > 0 ? 'Mouvement constate' : 'Aucun mouvement';
                        @endphp
                        <tr>
                            <td>{{ substr((string) $code, 0, 1) }}</td>
                            <td class="acc-cell">{{ $code }}</td>
                            <td class="acc-cell">{{ $label }}</td>
                            <td class="text-end amount">{{ $debit > 0 ? number_format($debit, 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $credit > 0 ? number_format($credit, 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $soldeDebit > 0 ? number_format($soldeDebit, 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $soldeCredit > 0 ? number_format($soldeCredit, 0, ',', ' ') : '-' }}</td>
                            <td class="text-end amount">{{ $mouvement > 0 ? number_format($mouvement, 0, ',', ' ') : '-' }}</td>
                            <td class="{{ $mouvement > 0 ? 'obs-move' : 'obs-none' }}">{{ $observation }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th colspan="3" class="text-end">Totaux</th>
                        <th class="text-end amount">{{ number_format($totalDebit, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalCredit, 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format(max($totalDebit - $totalCredit, 0), 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format(max($totalCredit - $totalDebit, 0), 0, ',', ' ') }}</th>
                        <th class="text-end amount">{{ number_format($totalDebit + $totalCredit, 0, ',', ' ') }}</th>
                        <th>{{ abs($balance) < 0.0001 ? 'Equilibre' : 'Ecart a analyser' }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <div class="page-break"></div>

    <section id="bilan-section" class="report-section mb-5">
        @php
            $netByPrefix = [
                '1' => ['debit' => 0.0, 'credit' => 0.0],
                '2' => ['debit' => 0.0, 'credit' => 0.0],
                '3' => ['debit' => 0.0, 'credit' => 0.0],
                '4' => ['debit' => 0.0, 'credit' => 0.0],
                '5' => ['debit' => 0.0, 'credit' => 0.0],
            ];
            foreach ($ledger as $accountName => $amounts) {
                if (preg_match('/^([1-5])/', trim((string) $accountName), $m)) {
                    $p = $m[1];
                    $netByPrefix[$p]['debit'] += (float) ($amounts['debit'] ?? 0);
                    $netByPrefix[$p]['credit'] += (float) ($amounts['credit'] ?? 0);
                }
            }

            $equityResult = (float) ($income - $expenses);
            $equityPositive = max($equityResult, 0);
            $dettesFinancieres = max($netByPrefix['1']['credit'] - $netByPrefix['1']['debit'], 0);
            $passifCirculant = max($netByPrefix['4']['credit'] - $netByPrefix['4']['debit'], 0);
            $tresoreriePassif = max($netByPrefix['5']['credit'] - $netByPrefix['5']['debit'], 0);
            $ecartsConversionPassif = 0.0;

            $immobilisationsBrutes = max($netByPrefix['2']['debit'] - $netByPrefix['2']['credit'], 0);
            $actifCirculant = max(($netByPrefix['3']['debit'] - $netByPrefix['3']['credit']) + ($netByPrefix['4']['debit'] - $netByPrefix['4']['credit']), 0);
            $tresorerieActif = max($netByPrefix['5']['debit'] - $netByPrefix['5']['credit'], 0);
            $ecartsConversionActif = 0.0;

            $totalPassifGeneral = max($equityPositive + $dettesFinancieres + $passifCirculant + $tresoreriePassif + $ecartsConversionPassif, 0);
            $totalActifGeneral = max($immobilisationsBrutes + $actifCirculant + $tresorerieActif + $ecartsConversionActif, 0);
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
                {{ $companyName ?? 'Sitiame Capitale' }}
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
                            <tr><td class="text-center">CA</td><td>Capital</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CB</td><td>Actionnaires capital non appelé</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CC</td><td>Primes et réserves</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CD</td><td>Primes d'apport, d'émission, de fusion</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CE</td><td>Ecarts de réévaluation</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CF</td><td>Réserves indisponibles</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CG</td><td>Réserves libres</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CH</td><td>Report à nouveau</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CI</td><td>Résultat net de l'exercice (bénéfice + ou perte -)</td><td class="text-end">{{ number_format($equityResult, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CK</td><td>Autres capitaux propres</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CL</td><td>Subventions d'investissement</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">CM</td><td>Provisions réglementées et fonds assimilés</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">CP TOTAL CAPITAUX PROPRES (I)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($equityPositive, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DETTES FINANCIÈRES ET RESSOURCES ASSIMILÉES (II)</td></tr>
                            <tr><td class="text-center">DA</td><td>Emprunts</td><td class="text-end">{{ number_format($dettesFinancieres, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DB</td><td>Dettes de crédit-bail et contrats assimilés</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DC</td><td>Dettes financières diverses</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DD</td><td>Provisions financières pour risques et charges</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DE</td><td>(1) dont H. A. O.</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DF TOTAL DETTES FINANCIÈRES (II)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($dettesFinancieres, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DG TOTAL RESSOURCES STABLES (I + II)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($equityPositive + $dettesFinancieres, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">PASSIF CIRCULANT (III)</td></tr>
                            <tr><td class="text-center">DH</td><td>Dettes circulantes H.A.O. et ressources assimilées</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DI</td><td>Clients, avances reçues</td><td class="text-end">{{ number_format($passifCirculant, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DJ</td><td>Fournisseurs d'exploitation</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DK</td><td>Dettes fiscales</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DL</td><td>Dettes sociales</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DM</td><td>Autres dettes</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DN</td><td>Risques provisionnés</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DP TOTAL PASSIF CIRCULANT (III)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($passifCirculant, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">TRESORERIE PASSIF (IV)</td></tr>
                            <tr><td class="text-center">DQ</td><td>Banques, crédits d'escompte</td><td class="text-end">{{ number_format($tresoreriePassif, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DR</td><td>Banques, crédits de trésorerie</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr><td class="text-center">DS</td><td>Banques, découverts</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DT TOTAL TRESORERIE-PASSIF (IV)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($tresoreriePassif, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DU Ecarts de conversion-Passif (V)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($ecartsConversionPassif, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">DZ TOTAL GENERAL (I + II + III + IV + V)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalPassifGeneral, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
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
                            <tr><td class="text-center">AZ</td><td>Immobilisations brutes</td><td class="text-end">{{ number_format($immobilisationsBrutes, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">ACTIF CIRCULANT (II)</td></tr>
                            <tr><td class="text-center">BA</td><td>Stocks, créances et assimilés</td><td class="text-end">{{ number_format($actifCirculant, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">TRESORERIE ACTIF (III)</td></tr>
                            <tr><td class="text-center">BQ</td><td>Banques, caisse, valeurs à encaisser</td><td class="text-end">{{ number_format($tresorerieActif, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">ECARTS DE CONVERSION-ACTIF (IV)</td></tr>
                            <tr><td class="text-center">BU</td><td>Ecarts de conversion-actif</td><td class="text-end">{{ number_format($ecartsConversionActif, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                            <tr class="section-title"><td colspan="4">BZ TOTAL GENERAL ACTIF (I + II + III + IV)</td></tr>
                            <tr><td></td><td></td><td class="text-end">{{ number_format($totalActifGeneral, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>

    <div class="page-break"></div>

    <section id="resultat-section" class="report-section mb-5">
        @php
            $nCharges = (float) ($expensesByYear[$exerciseYear] ?? $expenses);
            $nProducts = (float) ($incomeByYear[$exerciseYear] ?? $income);
            $n1Charges = (float) ($expensesByYear[$previousYear] ?? 0);
            $n1Products = (float) ($incomeByYear[$previousYear] ?? 0);
            $nResult = $nProducts - $nCharges;
            $n1Result = $n1Products - $n1Charges;
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
                    <tr><td>RA</td><td>Achats de marchandises</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RB</td><td>- Variation de stocks (- ou +)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(Marge brute sur marchandises voir TB)</td><td></td><td></td></tr>
                    <tr><td>RC</td><td>Achats de matières premières et fournitures liées</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RD</td><td>- Variation de stocks (- ou +)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(Marge brute sur matières voir TG)</td><td></td><td></td></tr>
                    <tr><td>RE</td><td>Autres achats</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RH</td><td>- Variation de stocks (- ou +)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RI</td><td>Transports</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RJ</td><td>Services extérieurs</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RK</td><td>Impôts et taxes</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>RL</td><td>Autres charges</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(Valeur ajoutée voir TN)</td><td></td><td></td></tr>
                    <tr><td>RP</td><td>Charges de personnel (1)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(1) dont personnel extérieur</td><td></td><td></td></tr>
                    <tr class="hint-row"><td>RQ</td><td>(Excédent brut d'exploitation voir TQ)</td><td></td><td></td></tr>
                    <tr><td>RS</td><td>Dotations aux amortissements et aux provisions</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>RW</td><td>Total des charges d'exploitation</td><td class="text-end">{{ number_format($nCharges, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Charges, 0, ',', ' ') }}</td></tr>
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
                    <tr><td>TA</td><td>Ventes de marchandises</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TB</td><td>MARGE BRUTE SUR MARCHANDISES</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TC</td><td>Ventes de produits fabriqués</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TD</td><td>Travaux, services vendus</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TE</td><td>Production stockée (ou déstockage) (+ ou -)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TF</td><td>Production immobilisée</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TG</td><td>MARGE BRUTE SUR MATIERES</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TH</td><td>Produits accessoires</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TI</td><td>CHIFFRE D'AFFAIRES (1)</td><td class="text-end">{{ number_format($nProducts, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Products, 0, ',', ' ') }}</td></tr>
                    <tr><td>TJ</td><td>(1) dont à l'exportation</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TK</td><td>Subventions d'exploitation</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TL</td><td>Autres produits</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TN</td><td>VALEUR AJOUTEE</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TQ</td><td>EXCEDENT BRUT D'EXPLOITATION</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TS</td><td>Reprises de provisions</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>TT</td><td>Transferts de charges</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>TW</td><td>Total des produits d'exploitation</td><td class="text-end">{{ number_format($nProducts, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Products, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>TX</td><td>RESULTAT D'EXPLOITATION (Bénéfice + ; Perte -)</td><td class="text-end">{{ number_format($nResult, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Result, 0, ',', ' ') }}</td></tr>
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
                    <tr><td>RW</td><td>Report Total des charges d'exploitation</td><td class="text-end">{{ number_format($nCharges, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Charges, 0, ',', ' ') }}</td></tr>
                    <tr class="section-row"><td></td><td>ACTIVITE FINANCIERE</td><td></td><td></td></tr>
                    <tr><td>SA</td><td>Frais financiers</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>SC</td><td>Pertes de change</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>SD</td><td>Dotations aux amortissements et aux provisions</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>SF</td><td>Total des charges financières</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat financier voir UG)</td><td></td><td></td></tr>
                    <tr class="total-row"><td>SH</td><td>Total des charges des activités ordinaires</td><td class="text-end">{{ number_format($nCharges, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Charges, 0, ',', ' ') }}</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat des activités ordinaires voir UI)</td><td></td><td></td></tr>
                    <tr class="section-row"><td></td><td>HORS ACTIVITES ORDINAIRES (H.A.O.)</td><td></td><td></td></tr>
                    <tr><td>SK</td><td>Valeurs comptables des cessions d'immobilisations</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>SL</td><td>Charges H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>SM</td><td>Dotations H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>SO</td><td>Total des charges H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="hint-row"><td></td><td>(Résultat H.A.O. voir UP)</td><td></td><td></td></tr>
                    <tr><td>SQ</td><td>Participation des travailleurs</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>SR</td><td>Impôts sur le résultat</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>SS</td><td>Total participation et impôts</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>ST</td><td>TOTAL GENERAL DES CHARGES</td><td class="text-end">{{ number_format($nCharges, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Charges, 0, ',', ' ') }}</td></tr>
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
                    <tr><td>TW</td><td>Report Total des produits d'exploitation</td><td class="text-end">{{ number_format($nProducts, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Products, 0, ',', ' ') }}</td></tr>
                    <tr class="section-row"><td></td><td>ACTIVITE FINANCIERE</td><td></td><td></td></tr>
                    <tr><td>UA</td><td>Revenus financiers</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UC</td><td>Gains de change</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UD</td><td>Reprises de provisions</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UE</td><td>Transferts de charges</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UF</td><td>Total des produits financiers</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UG</td><td>RESULTAT FINANCIER (+ ou -)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UH</td><td>Total des produits des activités ordinaires</td><td class="text-end">{{ number_format($nProducts, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Products, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UI</td><td>RESULTAT DES ACTIVITES ORDINAIRES (+ ou -)</td><td class="text-end">{{ number_format($nResult, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Result, 0, ',', ' ') }}</td></tr>
                    <tr><td>UJ</td><td>(1) dont impôt correspondant</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="section-row"><td></td><td>HORS ACTIVITES ORDINAIRES (H.A.O.)</td><td></td><td></td></tr>
                    <tr><td>UK</td><td>Produits des cessions d'immobilisations</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UL</td><td>Produits H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UM</td><td>Reprises H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr><td>UN</td><td>Transferts de charges</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UO</td><td>Total des produits H.A.O.</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UP</td><td>RESULTAT H.A.O. (+ ou -)</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    <tr class="total-row"><td>UT</td><td>TOTAL GENERAL DES PRODUITS</td><td class="text-end">{{ number_format($nProducts, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Products, 0, ',', ' ') }}</td></tr>
                    <tr class="total-row"><td>UZ</td><td>RESULTAT NET (Bénéfice + ; Perte -)</td><td class="text-end">{{ number_format($nResult, 0, ',', ' ') }}</td><td class="text-end">{{ number_format($n1Result, 0, ',', ' ') }}</td></tr>
                </tbody>
            </table>
        </div>
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
