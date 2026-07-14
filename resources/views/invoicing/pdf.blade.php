<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 110px 40px 80px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 0;
        }
        .header {
            position: fixed;
            top: -85px;
            left: 0;
            right: 0;
            height: 75px;
            border-bottom: 1px solid #d9dee7;
            padding-bottom: 8px;
        }
        .footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            height: 50px;
            border-top: 1px solid #d9dee7;
            font-size: 10px;
            color: #6b7280;
            padding-top: 8px;
        }
        .row { width: 100%; clear: both; }
        .col-left { float: left; width: 58%; }
        .col-right { float: right; width: 40%; text-align: right; }
        .brand-name { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0; }
        .brand-sub { margin: 4px 0 0; color: #6b7280; font-size: 10px; }
        .invoice-title { font-size: 20px; font-weight: 700; margin: 14px 0 2px; }
        .muted { color: #666; }
        .meta-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
        .meta-label { color: #6b7280; font-size: 10px; margin-bottom: 2px; }
        .meta-value { font-size: 12px; font-weight: 600; margin-bottom: 8px; }
        .spacer { height: 10px; clear: both; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; color: #334155; font-size: 11px; }
        .totals { margin-top: 14px; width: 45%; margin-left: auto; }
        .totals th { width: 55%; }
        .text-right { text-align: right; }
        .page-number:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="header">
        <div class="row">
            <div class="col-left">
                <p class="brand-name">{{ $invoice->user->company_name ?? $invoice->user->name ?? 'Emetteur' }}</p>
                <p class="brand-sub">{{ $invoice->user->email ?? '' }}</p>
            </div>
            <div class="col-right">
                <p class="brand-sub">Date d'emission</p>
                <p style="margin:0; font-weight:700;">{{ $invoice->issue_date->format('d/m/Y') }}</p>
                <p class="brand-sub" style="margin-top:8px;">Reference facture</p>
                <p style="margin:0; font-weight:700;">{{ $invoice->invoice_number }}</p>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="row">
            <div class="col-left">Document genere via {{ config('app.name') }} - Facturation.</div>
            <div class="col-right">Page <span class="page-number"></span></div>
        </div>
    </div>

    <div class="invoice-title">FACTURE</div>
    <p class="muted" style="margin:0 0 12px;">Echeance de paiement : {{ $invoice->due_date->format('d/m/Y') }}</p>

    <div class="row">
        <div class="col-left">
            <div class="meta-card">
                <div class="meta-label">Client</div>
                <div class="meta-value">{{ $invoice->client_name }}</div>

                <div class="meta-label">Contact</div>
                <div class="meta-value">{{ $invoice->client_contact ?? 'Non renseigne' }}</div>

                <div class="meta-label">Adresse</div>
                <div class="meta-value">{{ $invoice->client_address ?? 'Non renseignee' }}</div>

                @if ($invoice->client_tax_id)
                    <div class="meta-label">NIF</div>
                    <div class="meta-value">{{ $invoice->client_tax_id }}</div>
                @endif
            </div>
        </div>
        <div class="col-right">
            <div class="meta-card" style="text-align:left;">
                <div class="meta-label">Statut</div>
                <div class="meta-value">{{ strtoupper((string) $invoice->status) }}</div>

                <div class="meta-label">Devise</div>
                <div class="meta-value">{{ $invoice->currency }}</div>

                <div class="meta-label">Solde du</div>
                <div class="meta-value">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</div>
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
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="text-right">{{ $item->quantity }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                    <td class="text-right">{{ number_format((float) $item->line_total, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><th>Sous-total</th><td class="text-right">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr><th>TVA ({{ $invoice->tax_rate }}%)</th><td class="text-right">{{ number_format((float) $invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr><th>Total TTC</th><td class="text-right"><strong>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</strong></td></tr>
    </table>

    @if ($invoice->notes)
        <div class="spacer"></div>
        <p class="muted">{{ $invoice->notes }}</p>
    @endif
</body>
</html>
