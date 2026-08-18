<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 32px 38px 40px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1E293B;
            font-size: 10.5px;
            line-height: 1.5;
            margin: 0;
        }
        p { margin: 0; }

        /* En-tête */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .header-table td { border: none; padding: 0; vertical-align: top; }
        .header-right { text-align: right; }

        .brand-table { border-collapse: collapse; }
        .brand-table td { border: none; padding: 0; vertical-align: middle; }
        .brand-logo-cell { width: 52px; padding-right: 10px !important; }
        .brand-logo { max-height: 48px; max-width: 48px; }
        .brand-name { font-size: 16px; font-weight: 700; color: #0F2747; }
        .brand-sigle { font-size: 9.5px; color: #64748B; margin-top: 1px; }

        .issuer-details { margin-top: 8px; color: #64748B; font-size: 9px; line-height: 1.6; }
        .issuer-details span.sep { color: #CBD5E1; margin: 0 4px; }

        .doc-title { font-size: 22px; font-weight: 700; color: #0F2747; letter-spacing: 1px; }
        .doc-number { font-size: 11px; color: #64748B; margin-top: 3px; }
        .doc-number strong { color: #1E293B; }

        .header-rule { border-bottom: 2px solid #0F2747; margin: 14px 0 16px; }

        /* Bandeau statut + échéance */
        .status-band { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .status-band td { border: none; padding: 0; vertical-align: middle; }
        .due-note { color: #64748B; font-size: 9.5px; }
        .due-note strong { color: #1E293B; }
        .status-pill {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #ffffff;
            background: #64748B;
        }
        .status-unpaid { background: #B45309; }
        .status-partially_paid { background: #B45309; }
        .status-paid { background: #15803D; }
        .status-cancelled { background: #64748B; }

        /* Cartes émetteur / client */
        .meta-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta-table td { border: none; padding: 0; vertical-align: top; width: 50%; }
        .meta-table td.gap { width: 16px; padding: 0; }
        .meta-card {
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 11px 13px;
        }
        .meta-card-title {
            font-size: 8.5px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            color: #94A3B8;
            margin-bottom: 7px;
        }
        .meta-row { margin-bottom: 5px; }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label { font-size: 8.5px; color: #94A3B8; }
        .meta-value { font-size: 10.5px; font-weight: 700; color: #1E293B; }

        /* Tableau des articles */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        .items-table thead th {
            background: #0F2747;
            color: #ffffff;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            text-align: left;
            padding: 8px 10px;
            border: none;
        }
        .items-table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #E2E8F0;
            color: #1E293B;
            font-size: 10.5px;
        }
        .items-table tbody tr:nth-child(even) td { background: #F8FAFC; }
        .text-right { text-align: right; }

        /* Totaux */
        .totals-wrap { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .totals-wrap td { border: none; padding: 0; vertical-align: top; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 5px 0; font-size: 10px; color: #475569; border: none; }
        .totals td.label { text-align: left; }
        .totals td.value { text-align: right; font-weight: 600; color: #1E293B; }
        .totals tr.total-ttc td { border-top: 1px solid #E2E8F0; padding-top: 8px; font-size: 12px; font-weight: 700; color: #0F2747; }
        .totals tr.balance-row td {
            font-size: 12px;
            font-weight: 700;
            color: #ffffff;
            background: #0F2747;
            padding: 8px 10px;
        }
        .totals tr.balance-row td.label { border-radius: 4px 0 0 4px; }
        .totals tr.balance-row td.value { border-radius: 0 4px 4px 0; }

        /* Coordonnées bancaires / notes */
        .section-title { font-size: 9px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: #94A3B8; margin: 20px 0 6px; }
        .bank-table { width: 100%; border-collapse: collapse; }
        .bank-table th {
            text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.4px;
            color: #94A3B8; padding: 6px 10px; border-bottom: 1px solid #E2E8F0;
        }
        .bank-table td { padding: 7px 10px; border-bottom: 1px solid #E2E8F0; font-size: 10px; color: #1E293B; }

        .notes-box { border: 1px solid #E2E8F0; border-radius: 4px; padding: 10px 12px; font-size: 9.5px; color: #475569; margin-top: 6px; }

        .footer {
            position: fixed;
            bottom: -18px;
            left: 0;
            right: 0;
            border-top: 1px solid #E2E8F0;
            font-size: 8.5px;
            color: #94A3B8;
            text-align: center;
            padding-top: 7px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                @if ($companyLogo)
                    <table class="brand-table"><tr>
                        <td class="brand-logo-cell"><img src="{{ $companyLogo }}" alt="Logo"></td>
                        <td>
                            <p class="brand-name">{{ $invoice->user->company_name ?? $invoice->user->name ?? 'Emetteur' }}</p>
                            @if ($invoice->user->company_sigle)
                                <p class="brand-sigle">{{ $invoice->user->company_sigle }}</p>
                            @endif
                        </td>
                    </tr></table>
                @else
                    <p class="brand-name">{{ $invoice->user->company_name ?? $invoice->user->name ?? 'Emetteur' }}</p>
                    @if ($invoice->user->company_sigle)
                        <p class="brand-sigle">{{ $invoice->user->company_sigle }}</p>
                    @endif
                @endif

                @php
                    $issuerAddress = $invoice->user->full_geographic_address
                        ?: trim(collect([$invoice->user->address ?? null, $invoice->user->city ?? null])->filter()->implode(', '));
                    $issuerLines = collect([
                        $issuerAddress,
                        $invoice->user->phone ? 'Tel : '.$invoice->user->phone : null,
                        $invoice->user->email,
                        $invoice->user->rccm ? 'RCCM : '.$invoice->user->rccm : null,
                        $invoice->user->company_tax_id ? 'NIF : '.$invoice->user->company_tax_id : null,
                    ])->filter();
                @endphp
                @if ($issuerLines->isNotEmpty())
                    <p class="issuer-details">{{ $issuerLines->implode(' · ') }}</p>
                @endif
            </td>
            <td class="header-right">
                <p class="doc-title">FACTURE</p>
                <p class="doc-number">N&deg; <strong>{{ $invoice->invoice_number }}</strong></p>
                <p class="doc-number">Emise le <strong>{{ $invoice->issue_date->format('d/m/Y') }}</strong></p>
            </td>
        </tr>
    </table>
    <div class="header-rule"></div>

    <table class="status-band">
        <tr>
            <td>
                <span class="status-pill status-{{ $invoice->status }}">{{ strtoupper(str_replace('_', ' ', (string) $invoice->status)) }}</span>
            </td>
            <td class="text-right due-note">Echeance de paiement : <strong>{{ $invoice->due_date->format('d/m/Y') }}</strong></td>
        </tr>
    </table>

    <table class="meta-table">
        <tr>
            <td>
                <div class="meta-card">
                    <div class="meta-card-title">Facture a</div>
                    <div class="meta-row">
                        <div class="meta-value">{{ $invoice->client_name }}</div>
                    </div>
                    @if ($invoice->client_contact)
                        <div class="meta-row">
                            <div class="meta-label">Contact</div>
                            <div class="meta-value" style="font-size:9.5px; font-weight:400;">{{ $invoice->client_contact }}</div>
                        </div>
                    @endif
                    @if ($invoice->client_address)
                        <div class="meta-row">
                            <div class="meta-label">Adresse</div>
                            <div class="meta-value" style="font-size:9.5px; font-weight:400;">{{ $invoice->client_address }}</div>
                        </div>
                    @endif
                    @if ($invoice->client_tax_id)
                        <div class="meta-row">
                            <div class="meta-label">NIF</div>
                            <div class="meta-value" style="font-size:9.5px; font-weight:400;">{{ $invoice->client_tax_id }}</div>
                        </div>
                    @endif
                </div>
            </td>
            <td class="gap"></td>
            <td>
                <div class="meta-card">
                    <div class="meta-card-title">Recapitulatif</div>
                    <div class="meta-row">
                        <div class="meta-label">Devise</div>
                        <div class="meta-value">{{ $invoice->currency }}</div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Montant regle</div>
                        <div class="meta-value">{{ number_format((float) $invoice->amount_paid, 0, ',', ' ') }} {{ $invoice->currency }}</div>
                    </div>
                    <div class="meta-row">
                        <div class="meta-label">Solde du</div>
                        <div class="meta-value">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
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

    <table class="totals-wrap">
        <tr>
            <td style="width:55%;"></td>
            <td style="width:45%;">
                <table class="totals">
                    <tr><td class="label">Sous-total</td><td class="value">{{ number_format((float) $invoice->subtotal, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                    <tr><td class="label">TVA ({{ $invoice->tax_rate }}%)</td><td class="value">{{ number_format((float) $invoice->tax_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                    <tr class="total-ttc"><td class="label">Total TTC</td><td class="value">{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                    <tr><td colspan="2" style="height:6px; padding:0;"></td></tr>
                    <tr class="balance-row"><td class="label">Solde du</td><td class="value">{{ number_format($invoice->balanceDue(), 0, ',', ' ') }} {{ $invoice->currency }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @php
        $bankAccounts = collect($invoice->user->company_bank_accounts ?? [])
            ->filter(fn ($row) => !empty($row['bank']) || !empty($row['account_number']));
    @endphp
    @if ($bankAccounts->isNotEmpty())
        <p class="section-title">Coordonnees de paiement</p>
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
        <p class="section-title">Notes</p>
        <div class="notes-box">{{ $invoice->notes }}</div>
    @endif

    <div class="footer">
        Document genere via {{ config('app.name') }} — Facturation, le {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
