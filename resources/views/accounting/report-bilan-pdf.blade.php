<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bilan simplifié</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #212529;
            margin: 24px;
            background: #fff;
        }
        .report-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            margin-bottom: 24px;
            border-bottom: 2px solid #333;
            padding-bottom: 16px;
        }
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 16px;
        }
        .logo-block {
            flex-shrink: 0;
        }
        .logo-block img {
            display: block;
            width: 100px;
            height: auto;
            max-height: 100px;
        }
        .company-info {
            flex: 1;
        }
        .company-details {
            margin-bottom: 8px;
        }
        .company-details strong {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-details div {
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }
        .company-meta {
            font-size: 10px;
            color: #666;
            margin-top: 6px;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
        .qr-block {
            flex-shrink: 0;
            text-align: center;
        }
        .qr-block img {
            display: block;
            width: 110px;
            height: 110px;
            border: 1px solid #999;
            background: #fff;
            padding: 4px;
        }
        .qr-reference {
            margin-top: 4px;
            font-size: 9px;
            color: #666;
        }
        .section-title {
            margin-bottom: 16px;
            font-size: 14px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #343a40;
        }
        .bilan-header {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 24px;
            margin-bottom: 24px;
        }
        .bilan-header .field {
            font-size: 12px;
            line-height: 1.5;
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        .bilan-header .field span {
            display: block;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .bilan-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 24px;
        }
        .bilan-table th,
        .bilan-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: middle;
        }
        .bilan-table th {
            background: #f1f1f1;
            font-weight: 700;
            text-align: left;
        }
        .bilan-table .section-title-row td {
            background: #e9ecef;
            font-weight: 700;
        }
        .text-center {
            text-align: center;
        }
        .text-end {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="header">
            <div class="header-top">
                <div class="logo-block">
                    @if(!empty($companyLogo))
                        <img src="{{ $companyLogo }}" alt="Logo">
                    @endif
                </div>
                
                <div class="company-info">
                    <div class="company-details">
                        <strong>{{ $companyName }}</strong>
                        @if($companySigle)<div>{{ $companySigle }}</div>@endif
                        @if($companyAddress)<div>{{ $companyAddress }}</div>@endif
                        @if($companyTaxId)<div>N° d'identification fiscale : {{ $companyTaxId }}</div>@endif
                    </div>
                    <div class="company-meta">
                        <div>Rapport généré le {{ now()->format('d/m/Y H:i') }}</div>
                        <div>Utilisateur : {{ Auth::user()->name }}</div>
                    </div>
                </div>

                @if(!empty($qrUrl))
                    <div class="qr-block">
                        <img src="{{ $qrUrl }}" alt="QR Code">
                        <div class="qr-reference">Réf. {{ $bilanReference }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="section-title">Bilan simplifié</div>

        <div class="bilan-header">
            <div class="field">
                <span>Dénomination sociale de l’entreprise</span>
                {{ $companyName ?? 'Sitiame Capital' }}
            </div>
            <div class="field">
                <span>Sigle usuel</span>
                {{ $companySigle ?? '' }}
            </div>
            <div class="field">
                <span>Adresse</span>
                {{ $companyAddress ?? '' }}
            </div>
            <div class="field">
                <span>N° d'identification fiscale</span>
                {{ $companyTaxId ?? '#N/A' }}
            </div>
            <div class="field">
                <span>Exercice clos le</span>
                {{ $exerciseEnd }}
            </div>
            <div class="field">
                <span>Durée (en mois)</span>
                {{ $durationMonths ?? '#N/A' }}
            </div>
        </div>

        <table class="bilan-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:60px;">Réf.</th>
                    <th>PASSIF<br><span style="font-size:10px; color:#6c757d;">(avant répartition)</span></th>
                    <th class="text-center" style="width:110px;">Exercice N</th>
                    <th class="text-center" style="width:110px;">Exercice N-1</th>
                </tr>
            </thead>
            <tbody>
                <tr class="section-title-row"><td colspan="4">CAPITAUX PROPRES ET RESSOURCES ASSIMILÉES</td></tr>
                <tr><td class="text-center">CA</td><td>Capital</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CB</td><td>Actionnaires capital non appelé</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CC</td><td>Primes et réserves</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CD</td><td>Primes d'apport, d'émission, de fusion</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CE</td><td>Ecarts de réévaluation</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CF</td><td>Réserves indisponibles</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CG</td><td>Réserves libres</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CH</td><td>Report à nouveau</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">CI</td><td>Résultat net de l'exercice (bénéfice + ou perte -)</td><td class="text-end">{{ number_format($income - $expenses, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr class="text-center"><td colspan="4">TOTAL CAPITAUX PROPRES (I)</td></tr>
                <tr><td></td><td></td><td class="text-end">{{ number_format(max($income - $expenses, 0), 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr class="section-title-row"><td colspan="4">DETTES FINANCIÈRES ET RESSOURCES ASSIMILÉES (1)</td></tr>
                <tr><td class="text-center">DA</td><td>Emprunts</td><td class="text-end">{{ number_format($liabilities, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">DB</td><td>Dettes de crédit-bail et contrats assimilés</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">DC</td><td>Dettes financières diverses</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">DD</td><td>Provisions financières pour risques et charges</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">DE</td><td>(1) dont H. A. O.</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr class="section-title-row"><td colspan="4">DF TOTAL DETTES FINANCIÈRES (II)</td></tr>
                <tr><td></td><td></td><td class="text-end">{{ number_format($liabilities, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr class="section-title-row"><td colspan="4">DG TOTAL RESSOURCES STABLES (I + II)</td></tr>
                <tr><td></td><td></td><td class="text-end">{{ number_format(max($liabilities + max($income - $expenses, 0), 0), 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
            </tbody>
        </table>

        <table class="bilan-table">
            <thead>
                <tr>
                    <th class="text-center" style="width:60px;">Réf.</th>
                    <th>ACTIF<br><span style="font-size:10px; color:#6c757d;">(avant répartition)</span></th>
                    <th class="text-center" style="width:110px;">Exercice N</th>
                    <th class="text-center" style="width:110px;">Exercice N-1</th>
                </tr>
            </thead>
            <tbody>
                <tr class="section-title-row"><td colspan="4">ACTIF</td></tr>
                <tr><td class="text-center">AA</td><td>Immobilisations</td><td class="text-end">{{ number_format($assets, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">AB</td><td>Disponibilités</td><td class="text-end">{{ number_format(max($totalDebit - $assets, 0), 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
                <tr><td class="text-center">AC</td><td>Autres actifs</td><td class="text-end">0</td><td class="text-end">0</td></tr>
                <tr class="section-title-row"><td colspan="4">TOTAL ACTIF</td></tr>
                <tr><td></td><td></td><td class="text-end">{{ number_format($totalDebit, 0, ',', ' ') }}</td><td class="text-end">0</td></tr>
            </tbody>
        </table>
    </div>
</body>
</html>
