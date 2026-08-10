<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $payout->receipt_number }}</title>
    <style>
        @page {
            margin: 35px 40px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #222;
            font-size: 12px;
            margin: 0;
        }
        .header {
            border-bottom: 1px solid #d9dee7;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .footer {
            border-top: 1px solid #d9dee7;
            font-size: 10px;
            color: #6b7280;
            padding-top: 8px;
            margin-top: 30px;
        }
        .row { width: 100%; clear: both; }
        .col-left { float: left; width: 58%; }
        .col-right { float: right; width: 40%; text-align: right; }
        .brand-name { font-size: 18px; font-weight: 700; color: #0F2747; margin: 0; }
        .brand-sub { margin: 2px 0 0; color: #6b7280; font-size: 10px; }
        .receipt-title { font-size: 20px; font-weight: 700; margin: 14px 0 2px; color: #0F2747; }
        .muted { color: #666; }
        .meta-card { border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
        .meta-label { color: #6b7280; font-size: 10px; margin-bottom: 2px; }
        .meta-value { font-size: 12px; font-weight: 600; margin-bottom: 8px; }
        .spacer { height: 10px; clear: both; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f8fafc; color: #334155; font-size: 11px; }
        .amount-box {
            border: 2px solid #0F2747;
            border-radius: 8px;
            padding: 14px 18px;
            margin-top: 16px;
            text-align: center;
        }
        .amount-box .label { color: #6b7280; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; }
        .amount-box .value { font-size: 24px; font-weight: 700; color: #0F2747; margin-top: 4px; }
        .text-right { text-align: right; }
        .signature-row { margin-top: 40px; width: 100%; }
        .signature-col { float: left; width: 45%; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 4px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="row">
            <div class="col-left">
                <p class="brand-name">{{ config('app.name', 'Sitiame Capital') }}</p>
                <p class="brand-sub">Reçu de versement de commission commerciale</p>
            </div>
            <div class="col-right">
                <p class="brand-sub">Date d'émission</p>
                <p style="margin:0; font-weight:700;">{{ $payout->created_at->format('d/m/Y') }}</p>
                <p class="brand-sub" style="margin-top:8px;">Référence reçu</p>
                <p style="margin:0; font-weight:700;">{{ $payout->receipt_number }}</p>
            </div>
        </div>
    </div>

    <div class="receipt-title">REÇU DE PAIEMENT</div>
    <p class="muted" style="margin:0 0 12px;">Versement de commission au commercial référent.</p>

    <div class="row">
        <div class="col-left">
            <div class="meta-card">
                <div class="meta-label">Bénéficiaire</div>
                <div class="meta-value">{{ $commercial->name }}</div>

                <div class="meta-label">E-mail</div>
                <div class="meta-value">{{ $commercial->email }}</div>

                @if($commercial->phone)
                    <div class="meta-label">Téléphone</div>
                    <div class="meta-value">{{ $commercial->phone }}</div>
                @endif
            </div>
        </div>
        <div class="col-right">
            <div class="meta-card" style="text-align:left;">
                <div class="meta-label">Validé par</div>
                <div class="meta-value">{{ $accountant->name }}</div>

                <div class="meta-label">Solde au moment du versement</div>
                <div class="meta-value">{{ number_format($payout->balance_at_payment, 0, ',', ' ') }} FCFA</div>

                <div class="meta-label">Déjà versé avant ce paiement</div>
                <div class="meta-value">{{ number_format($payout->previously_paid_total, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>
    <div class="spacer"></div>

    <div class="amount-box">
        <div class="label">Montant versé</div>
        <div class="value">{{ number_format($payout->amount, 0, ',', ' ') }} FCFA</div>
    </div>

    @if($payout->note)
        <div class="spacer"></div>
        <p class="muted" style="font-weight:700; margin-bottom: 4px;">Note</p>
        <p class="muted">{{ $payout->note }}</p>
    @endif

    <div class="signature-row">
        <div class="signature-col">
            <div class="signature-line">Signature du cabinet comptable</div>
        </div>
        <div class="signature-col" style="float:right;">
            <div class="signature-line">Signature du commercial</div>
        </div>
    </div>

    <div class="footer">
        Document généré via {{ config('app.name') }} - Suivi commercial, le {{ now()->format('d/m/Y H:i') }}.
    </div>
</body>
</html>
