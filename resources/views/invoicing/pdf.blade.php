<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 35px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1E293B;
            font-size: 12px;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #0F2747;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .footer {
            border-top: 1px solid #E2E8F0;
            font-size: 10px;
            color: #94A3B8;
            padding-top: 8px;
            margin-top: 20px;
        }
        .row { width: 100%; border-collapse: collapse; }
        .row td { border: none; padding: 0; vertical-align: top; }
        .col-left { width: 58%; }
        .col-right { width: 40%; text-align: right; }
        .brand-table { border-collapse: collapse; margin-bottom: 4px; }
        .brand-table td { border: none; padding: 0; vertical-align: middle; }
        .brand-logo-cell { width: 60px; padding-right: 12px !important; }
        .brand-logo { max-height: 56px; max-width: 56px; }
        .brand-name { font-size: 19px; font-weight: 700; color: #0F2747; margin: 0; }
        .brand-sub { margin: 2px 0 0; color: #64748B; font-size: 10px; }
        .invoice-title { font-size: 22px; font-weight: 700; color: #0F2747; letter-spacing: 1px; margin: 14px 0 2px; }
        .muted { color: #64748B; }
        .meta-card { border: 1px solid #E2E8F0; background: #F8FAFC; border-radius: 6px; padding: 11px 13px; margin-bottom: 12px; }
        .meta-label { color: #94A3B8; font-size: 9px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 2px; }
        .meta-value { font-size: 12px; font-weight: 600; color: #1E293B; margin-bottom: 8px; }
        .spacer { height: 10px; clear: both; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #E2E8F0; padding: 8px; text-align: left; color: #1E293B; }
        th { background: #0F2747; color: #ffffff; font-size: 10.5px; font-weight: 700; letter-spacing: 0.3px; }
        tbody tr:nth-child(even) td { background: #F8FAFC; }
        .totals { margin-top: 14px; width: 45%; margin-left: auto; }
        .totals th { width: 55%; background: #ffffff; color: #475569; font-weight: 600; }
        .totals td { font-weight: 600; }
        .totals .balance-row th, .totals .balance-row td {
            font-weight: 700; font-size: 13px; color: #ffffff; background: #0F2747; border-color: #0F2747;
        }
        .bank-table { width: 100%; margin-top: 8px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <table class="row"><tr>
            <td class="col-left">
                @if ($companyLogo)
                    <table class="brand-table"><tr>
                        <td class="brand-logo-cell"><img src="{{ $companyLogo }}" alt="Logo" class="brand-logo"></td>
                        <td><p class="brand-name">{{ $invoice->user->company_name ?? $invoice->user->name ?? 'Emetteur' }}</p></td>
                    </tr></table>
                @else
                    <p class="brand-name">{{ $invoice->user->company_name ?? $invoice->user->name ?? 'Emetteur' }}</p>
                @endif
                @if ($invoice->user->company_sigle)
                    <p class="brand-sub">{{ $invoice->user->company_sigle }}</p>
                @endif
                @php
                    $issuerAddress = $invoice->user->full_geographic_address
                        ?: trim(collect([$invoice->user->address ?? null, $invoice->user->city ?? null])->filter()->implode(', '));
                @endphp
                @if ($issuerAddress)
                    <p class="brand-sub">{{ $issuerAddress }}</p>
                @endif
                @if ($invoice->user->phone)
                    <p class="brand-sub">Tel : {{ $invoice->user->phone }}</p>
                @endif
                <p class="brand-sub">{{ $invoice->user->email ?? '' }}</p>
                @if ($invoice->user->rccm)
                    <p class="brand-sub">RCCM : {{ $invoice->user->rccm }}</p>
                @endif
                @if ($invoice->user->company_tax_id)
                    <p class="brand-sub">NIF : {{ $invoice->user->company_tax_id }}</p>
                @endif
            </td>
            <td class="col-right">
                <p class="brand-sub">Date d'emission</p>
                <p style="margin:0; font-weight:700; color:#0F2747;">{{ $invoice->issue_date->format('d/m/Y') }}</p>
                <p class="brand-sub" style="margin-top:8px;">Reference facture</p>
                <p style="margin:0; font-weight:700; color:#0F2747;">{{ $invoice->invoice_number }}</p>
            </td>
        </tr></table>
    </div>

    <div class="invoice-title">FACTURE</div>
    <p class="muted" style="margin:0 0 12px;">Echeance de paiement : {{ $invoice->due_date->format('d/m/Y') }}</p>

    <table class="row"><tr>
        <td class="col-left" style="padding-right:12px !important;">
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
        </td>
        <td class="col-right">
            <div class="meta-card" style="text-align:left;">
                <div class="meta-label">Statut</div>
                @php
                    $statusColor = match ((string) $invoice->status) {
                        'paid' => '#15803D',
                        'partially_paid', 'unpaid' => '#B45309',
                        'cancelled' => '#64748B',
                        default => '#0F2747',
                    };
                @endphp
                <div class="meta-value" style="color: {{ $statusColor }};">{{ strtoupper((string) $invoice->status) }}</div>

                <div class="meta-label">Devise</div>
                <div class="meta-value">{{ $invoice->currency }}</div>

                <div class="meta-label">Solde du</div>
                <div class="meta-value">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</div>
            </div>
        </td>
    </tr></table>
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
        <tr><th>Montant paye</th><td class="text-right">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
        <tr class="balance-row"><th>Solde du</th><td class="text-right">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
    </table>

    @php
        $bankAccounts = collect($invoice->user->company_bank_accounts ?? [])
            ->filter(fn ($row) => !empty($row['bank']) || !empty($row['account_number']));
    @endphp
    @if ($bankAccounts->isNotEmpty())
        <div class="spacer"></div>
        <p class="muted" style="margin:0 0 4px; font-weight:700;">Coordonnees de paiement</p>
        <table class="bank-table">
            <thead>
                <tr>
                    <th>Banque</th>
                    <th>Numero de compte</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($bankAccounts as $bank)
                    <tr>
                        <td>{{ $bank['bank'] ?? '-' }}</td>
                        <td>{{ $bank['account_number'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($invoice->notes)
        <div class="spacer"></div>
        <p class="muted">{{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Document genere via {{ config('app.name') }} - Facturation, le {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
