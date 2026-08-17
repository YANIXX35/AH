<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu de Paiement #REC-{{ date('Y') }}-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }} | Sitiame Capital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: #1e293b;
        }
        .receipt-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            max-width: 800px;
            margin: 40px auto;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .receipt-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .brand-title {
            color: #1e3a8a;
            font-weight: 800;
            font-size: 1.5rem;
            letter-spacing: -0.5px;
        }
        .stamp-badge {
            display: inline-block;
            border: 3px solid #10b981;
            color: #10b981;
            font-weight: 800;
            text-transform: uppercase;
            padding: 8px 20px;
            border-radius: 12px;
            transform: rotate(-5deg);
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        .table-receipt th {
            background-color: #f8fafc;
            color: #475569;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        .info-box {
            background: #f8fafc;
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid #e2e8f0;
        }
        @media print {
            body { background: #ffffff; }
            .receipt-card { box-shadow: none; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-semibold me-2">
            🖨️ Imprimer / Télécharger PDF
        </button>
        <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
            &larr; Retour à la gestion des paiements
        </a>
    </div>

    <div class="receipt-card">
        <!-- HEADER -->
        <div class="receipt-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="brand-title">SITIAME CAPITAL</div>
                <div class="text-muted small">Plateforme de Gestion Financière &amp; Comptable</div>
                <div class="text-muted small">Abidjan, Côte d'Ivoire · support@sitiame-capital.com</div>
            </div>
            <div class="text-end">
                <div class="stamp-badge">
                    ✓ PAYÉ / VALIDÉ
                </div>
                <div class="text-muted small mt-2">
                    N° Reçu : <strong>REC-{{ date('Y') }}-{{ str_pad($payment->id, 6, '0', STR_PAD_LEFT) }}</strong>
                </div>
            </div>
        </div>

        <h4 class="fw-bold text-center text-dark mb-4">REÇU DE PAIEMENT OFFICIEL</h4>

        <!-- HORODATAGE PRÉCIS & INFOS CLIENT -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="info-box h-100">
                    <h6 class="fw-bold text-primary mb-2">🏢 Informations Client / PME</h6>
                    <div class="fw-bold text-dark fs-6">{{ $user->company_name ?: ($user->name ?: 'Client PME') }}</div>
                    <div class="small text-muted"><strong>Représentant :</strong> {{ $user->name }}</div>
                    <div class="small text-muted"><strong>E-mail :</strong> {{ $user->email }}</div>
                    @if($user->company_tax_id)
                        <div class="small text-muted"><strong>NIF :</strong> {{ $user->company_tax_id }}</div>
                    @endif
                    @if($user->phone)
                        <div class="small text-muted"><strong>Téléphone :</strong> {{ $user->phone }}</div>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="info-box h-100">
                    <h6 class="fw-bold text-primary mb-2">⏰ Traçabilité &amp; Horodatage</h6>
                    <div class="small mb-1">
                        <strong>Date d'émission :</strong> {{ $payment->created_at?->format('d/m/Y') }}
                    </div>
                    <div class="small mb-1">
                        <strong>Heure exacte :</strong> <span class="badge bg-dark font-monospace fs-6">{{ $payment->created_at?->format('H:i:s') }}</span>
                    </div>
                    <div class="small mb-1">
                        <strong>Référence Unique :</strong> <code class="text-primary">{{ $payment->provider_reference ?: 'TX-'.$payment->id }}</code>
                    </div>
                    <div class="small mb-1">
                        <strong>Opérateur / Canal :</strong> {{ $payment->correspondent ?: $payment->provider }} ({{ $payment->country ?: 'CI' }})
                    </div>
                    <div class="small">
                        <strong>N° Payeur :</strong> {{ $payment->payer_msisdn ?: 'N/A' }}
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLEAU TRANSACTION -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle table-receipt">
                <thead>
                    <tr>
                        <th class="py-2 px-3">Description du Service</th>
                        <th class="py-2 px-3 text-center">Durée</th>
                        <th class="py-2 px-3 text-center">Statut</th>
                        <th class="py-2 px-3 text-end">Montant Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="py-3 px-3">
                            <div class="fw-bold text-dark">Abonnement Plateforme Premium Sitiame</div>
                            <div class="text-muted small">Accès complet à la comptabilité, trésorerie, paie et analyses IA</div>
                        </td>
                        <td class="text-center py-3 px-3">
                            <span class="badge bg-primary-subtle text-primary border">30 Jours</span>
                        </td>
                        <td class="text-center py-3 px-3">
                            <span class="badge bg-success-subtle text-success border">COMPLETED</span>
                        </td>
                        <td class="text-end py-3 px-3 fw-bold fs-5 text-dark">
                            {{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency ?: 'XOF' }}
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="3" class="text-end fw-bold py-3 px-3">Montant Total Réglé :</td>
                        <td class="text-end fw-bold fs-4 text-success py-3 px-3">
                            {{ number_format((float) $payment->amount, 0, ',', ' ') }} {{ $payment->currency ?: 'XOF' }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- FOOTER & SIGNATURE -->
        <div class="mt-5 pt-3 border-top d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div class="text-muted small" style="max-width: 450px;">
                Ce reçu certifie le règlement effectif de l'abonnement Premium Sitiame Capital.
                Document généré automatiquement et faisant foi de paiement électronique.
            </div>
            <div class="text-end">
                <div class="text-muted small mb-1">Pour la direction Sitiame Capital</div>
                <div class="fw-bold text-primary font-monospace" style="letter-spacing:1px;">[ TAMPON ET SIGNATURE NUMÉRIQUE ]</div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
