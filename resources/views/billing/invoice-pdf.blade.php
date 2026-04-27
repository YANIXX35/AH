<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 120px 40px 90px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 0;
        }
        .header {
            position: fixed;
            top: -95px;
            left: 0;
            right: 0;
            height: 85px;
            border-bottom: 1px solid #d9dee7;
            padding-bottom: 8px;
        }
        .footer {
            position: fixed;
            bottom: -68px;
            left: 0;
            right: 0;
            height: 60px;
            border-top: 1px solid #d9dee7;
            font-size: 10px;
            color: #6b7280;
            padding-top: 8px;
        }
        .row {
            width: 100%;
            clear: both;
        }
        .col-left {
            float: left;
            width: 58%;
        }
        .col-right {
            float: right;
            width: 40%;
            text-align: right;
        }
        .brand-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }
        .brand-sub {
            margin: 4px 0 0;
            color: #6b7280;
            font-size: 10px;
        }
        .invoice-title {
            font-size: 20px;
            font-weight: 700;
            margin: 14px 0 2px;
        }
        .muted {
            color: #666;
        }
        .meta-card {
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .meta-label {
            color: #6b7280;
            font-size: 10px;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .spacer {
            height: 10px;
            clear: both;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        th, td {
            border: 1px solid #e5e7eb;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f8fafc;
            color: #334155;
            font-size: 11px;
        }
        .totals {
            margin-top: 14px;
            width: 45%;
            margin-left: auto;
        }
        .totals th {
            width: 55%;
        }
        .text-right {
            text-align: right;
        }
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>
    @php
        $logoData = null;
        $logoPath = public_path('images/sitiam.png');
        if (is_file($logoPath)) {
            $logoData = 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath));
        }
    @endphp

    <div class="header">
        <div class="row">
            <div class="col-left">
                @if($logoData)
                    <img src="{{ $logoData }}" alt="Sitiame Capital" style="height:34px; margin-bottom:8px;">
                @endif
                <p class="brand-name">Sitiame Capital</p>
                <p class="brand-sub">Plateforme de gestion financiere et comptable</p>
            </div>
            <div class="col-right">
                <p class="brand-sub">Date d'emission</p>
                <p style="margin:0; font-weight:700;">{{ optional($invoice->issued_at)->format('d/m/Y H:i') }}</p>
                <p class="brand-sub" style="margin-top:8px;">Reference facture</p>
                <p style="margin:0; font-weight:700;">{{ $invoice->invoice_number }}</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="row">
            <div class="col-left">
                Document genere par {{ config('app.name') }} v{{ config('app.version') }} - Merci pour votre confiance.
            </div>
            <div class="col-right">
                Page <span class="page-number"></span>
            </div>
        </div>
        <div style="clear: both; margin-top: 6px;">
            Sitiame Capital - Facture a conserver pour votre historique fiscal et comptable.
        </div>
    </div>

    <div class="invoice-title">FACTURE CLIENT</div>
    <p class="muted" style="margin:0 0 12px;">Echeance de paiement: {{ optional($invoice->due_at)->format('d/m/Y H:i') ?? 'N/A' }}</p>

    <div class="row">
        <div class="col-left">
            <div class="meta-card">
                <div class="meta-label">Client</div>
                <div class="meta-value">{{ $invoice->user->name ?? 'N/A' }}</div>

                <div class="meta-label">Email</div>
                <div class="meta-value">{{ $invoice->user->email ?? 'N/A' }}</div>

                <div class="meta-label">Societe</div>
                <div class="meta-value">{{ $invoice->user->company_name ?? 'Non renseigne' }}</div>
            </div>
        </div>
        <div class="col-right">
            <div class="meta-card" style="text-align:left;">
                <div class="meta-label">Statut</div>
                <div class="meta-value">{{ strtoupper((string) $invoice->status) }}</div>

                <div class="meta-label">Devise</div>
                <div class="meta-value">{{ $invoice->currency }}</div>

                <div class="meta-label">Date de paiement</div>
                <div class="meta-value">{{ optional($invoice->paid_at)->format('d/m/Y H:i') ?? 'Non reglee' }}</div>
            </div>
        </div>
    </div>
    <div class="spacer"></div>

    <table>
        <thead>
            <tr>
                <th>Libelle</th>
                <th class="text-right">Quantite</th>
                <th class="text-right">Prix unitaire</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>{{ $item->label }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                    <td class="text-right">{{ number_format((float) $item->total_price, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>Sous-total</th><td class="text-right">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr><th>Taxes</th><td class="text-right">{{ number_format((float) $invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr><th>Total TTC</th><td class="text-right"><strong>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</strong></td></tr>
    </table>
</body>
</html>
