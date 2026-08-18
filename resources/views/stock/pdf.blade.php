<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche stock {{ $product->name }}</title>
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

        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .kpi-table td { border: none; padding: 0; vertical-align: top; }
        .kpi-gap { width: 10px; }
        .kpi-card { border: 1px solid #E2E8F0; border-radius: 6px; padding: 10px 12px; }
        .kpi-label { color: #94A3B8; font-size: 8.5px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; margin-bottom: 4px; }
        .kpi-value { font-size: 14px; font-weight: 700; color: #0F2747; }
        .kpi-value.low { color: #B45309; }

        table.data { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.data th, table.data td { border: 1px solid #E2E8F0; padding: 8px; text-align: left; color: #1E293B; }
        table.data th { background: #0F2747; color: #ffffff; font-size: 10.5px; font-weight: 700; letter-spacing: 0.3px; }
        table.data tbody tr:nth-child(even) td { background: #F8FAFC; }
        .text-right { text-align: right; }
        .type-pill { font-weight: 700; }
        .type-entree { color: #15803D; }
        .type-sortie { color: #B45309; }
        .type-ajustement { color: #0F2747; }
    </style>
</head>
<body>
    <div class="header">
        <table class="row"><tr>
            <td class="col-left">
                @if ($companyLogo)
                    <table class="brand-table"><tr>
                        <td class="brand-logo-cell"><img src="{{ $companyLogo }}" alt="Logo" class="brand-logo"></td>
                        <td><p class="brand-name">{{ $product->user->company_name ?? $product->user->name ?? 'Emetteur' }}</p></td>
                    </tr></table>
                @else
                    <p class="brand-name">{{ $product->user->company_name ?? $product->user->name ?? 'Emetteur' }}</p>
                @endif
                @if ($product->user->company_sigle)
                    <p class="brand-sub">{{ $product->user->company_sigle }}</p>
                @endif
                @php
                    $issuerAddress = $product->user->full_geographic_address
                        ?: trim(collect([$product->user->address ?? null, $product->user->city ?? null])->filter()->implode(', '));
                @endphp
                @if ($issuerAddress)
                    <p class="brand-sub">{{ $issuerAddress }}</p>
                @endif
                @if ($product->user->phone)
                    <p class="brand-sub">Tel : {{ $product->user->phone }}</p>
                @endif
                <p class="brand-sub">{{ $product->user->email ?? '' }}</p>
            </td>
            <td class="col-right">
                <p class="brand-sub">Reference produit</p>
                <p style="margin:0; font-weight:700; color:#0F2747;">{{ $product->sku ?: '—' }}</p>
                <p class="brand-sub" style="margin-top:8px;">Genere le</p>
                <p style="margin:0; font-weight:700; color:#0F2747;">{{ now()->format('d/m/Y') }}</p>
            </td>
        </tr></table>
    </div>

    <div class="invoice-title">FICHE STOCK</div>
    <p class="muted" style="margin:0 0 12px;">{{ $product->name }} &middot; unite : {{ $product->unit }}</p>

    <table class="kpi-table">
        <tr>
            <td class="kpi-card">
                <div class="kpi-label">Quantite en stock</div>
                <div class="kpi-value {{ $product->isBelowThreshold() ? 'low' : '' }}">{{ number_format((float) $product->quantity_on_hand, 2, ',', ' ') }} {{ $product->unit }}</div>
            </td>
            <td class="kpi-gap"></td>
            <td class="kpi-card">
                <div class="kpi-label">CUMP</div>
                <div class="kpi-value">{{ number_format((float) $product->average_cost, 0, ',', ' ') }} FCFA</div>
            </td>
            <td class="kpi-gap"></td>
            <td class="kpi-card">
                <div class="kpi-label">Valeur du stock</div>
                <div class="kpi-value">{{ number_format($product->stockValue(), 0, ',', ' ') }} FCFA</div>
            </td>
            <td class="kpi-gap"></td>
            <td class="kpi-card">
                <div class="kpi-label">Prix de vente</div>
                <div class="kpi-value">{{ number_format((float) $product->sale_price, 0, ',', ' ') }} FCFA</div>
            </td>
        </tr>
    </table>

    @if ($product->reorder_threshold !== null)
        <div class="meta-card" style="margin-bottom:18px;">
            <div class="meta-label">Seuil de reapprovisionnement</div>
            <div class="meta-value" style="margin-bottom:0;">{{ number_format((float) $product->reorder_threshold, 2, ',', ' ') }} {{ $product->unit }}
                @if ($product->isBelowThreshold())
                    <span style="color:#B45309; font-weight:700;"> — sous le seuil</span>
                @endif
            </div>
        </div>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th class="text-right">Quantite</th>
                <th class="text-right">Cout unitaire</th>
                <th class="text-right">Solde apres</th>
                <th>Motif</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($product->movements as $movement)
                @php
                    $typeLabel = ['entree' => 'Entree', 'sortie' => 'Sortie', 'ajustement' => 'Ajustement'][$movement->type] ?? $movement->type;
                @endphp
                <tr>
                    <td>{{ optional($movement->movement_date)->format('d/m/Y') }}</td>
                    <td><span class="type-pill type-{{ $movement->type }}">{{ $typeLabel }}</span></td>
                    <td class="text-right">{{ number_format((float) $movement->quantity, 2, ',', ' ') }}</td>
                    <td class="text-right">{{ $movement->unit_cost !== null ? number_format((float) $movement->unit_cost, 0, ',', ' ').' FCFA' : '—' }}</td>
                    <td class="text-right">{{ number_format((float) $movement->quantity_after, 2, ',', ' ') }}</td>
                    <td>{{ $movement->reason ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Aucun mouvement enregistre.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Document genere via {{ config('app.name') }} — Gestion de stock, le {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
