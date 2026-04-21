@extends('layouts.app')

@section('title', 'Résultat paiement | ' . config('app.name'))
@section('page_title', 'Résultat du paiement')

@push('styles')
<style>
    .crypto-shell { background: #f5f7fb; border-radius: 1rem; padding: 1rem; }
    .crypto-hero {
        border-radius: 1rem;
        color: #fff;
        padding: 1.1rem 1.2rem;
    }
    .crypto-hero-success { background: linear-gradient(120deg, #14532d 0%, #15803d 55%, #22c55e 100%); }
    .crypto-hero-failure { background: linear-gradient(120deg, #7f1d1d 0%, #b91c1c 55%, #ef4444 100%); }
    .crypto-hero-pending { background: linear-gradient(120deg, #1f2937 0%, #1d4ed8 60%, #2563eb 100%); }
    .crypto-status-pill {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .16);
        border: 1px solid rgba(255, 255, 255, .28);
        color: #fff;
        font-weight: 700;
        padding: .45rem .8rem;
    }
    .crypto-status-icon {
        width: 1.2rem;
        height: 1.2rem;
        border-radius: 999px;
        background: rgba(255, 255, 255, .24);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .78rem;
        line-height: 1;
    }
    .crypto-kpi {
        border: 1px solid #e5e7eb;
        border-radius: .9rem;
        background: #fff;
        box-shadow: 0 8px 22px rgba(15, 23, 42, .06);
        padding: 1rem;
        height: 100%;
    }
    .crypto-kpi-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #64748b; font-weight: 700; }
    .crypto-kpi-value { font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: .2rem; }
    .crypto-card {
        border: 1px solid #e5e7eb;
        border-radius: .95rem;
        background: #fff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .06);
        overflow: hidden;
    }
</style>
@endpush

@section('content')
<div class="container-fluid p-0 crypto-shell">
    @php
        $heroState = $isSuccess ? 'success' : ($isFailure ? 'failure' : 'pending');
        $heroTitle = $isSuccess ? 'Transaction approuvée' : ($isFailure ? 'Transaction échouée' : 'Transaction en attente');
        $heroSubtitle = $isSuccess
            ? 'Le paiement est validé et le compte est mis à jour.'
            : ($isFailure
                ? 'Le paiement a échoué. Réessayez depuis le formulaire.'
                : 'Le paiement est en cours de confirmation côté FedaPay.');
        $heroIcon = $isSuccess ? '✓' : ($isFailure ? '✕' : '…');
    @endphp
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="crypto-hero crypto-hero-{{ $heroState }} mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h4 class="mb-1 fw-bold">{{ $heroTitle }}</h4>
                        <p class="mb-0 small text-white-50">{{ $heroSubtitle }}</p>
                    </div>
                    <span class="crypto-status-pill">
                        <span class="crypto-status-icon">{{ $heroIcon }}</span>
                        {{ $isSuccess ? 'Succès' : ($isFailure ? 'Échec' : 'En attente') }}
                    </span>
                </div>
            </div>

            <div class="crypto-card p-4 p-md-4">
                    @if($isSuccess)
                    <div class="alert alert-success">
                        <strong>Paiement effectué avec succès.</strong>
                        Votre abonnement est maintenant en version Premium.
                    </div>
                @elseif($isFailure)
                    <div class="alert alert-danger">
                        <strong>Paiement échoué.</strong>
                        Votre abonnement reste en version gratuite, veuillez réessayer.
                    </div>
                    <div class="alert alert-warning py-2">
                        Retour automatique vers le formulaire de paiement dans
                        <strong><span id="retryCountdown">8</span> secondes</strong>.
                    </div>
                @else
                    <div class="alert alert-warning">
                        <strong>Paiement en attente.</strong>
                        Le statut est en cours de confirmation.
                    </div>
                @endif

                @if(session('result_error'))
                    <div class="alert alert-danger py-2 small">{{ session('result_error') }}</div>
                @endif
                @if(session('status'))
                    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
                @endif

                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Statut abonnement</div>
                            <div class="crypto-kpi-value {{ $isPremium ? 'text-success' : 'text-danger' }}">
                                {{ $isPremium ? 'Premium actif' : 'Gratuit (non premium)' }}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Référence transaction</div>
                            <div class="crypto-kpi-value">{{ $transaction?->provider_reference ?? 'Indisponible' }}</div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Montant</div>
                            <div class="crypto-kpi-value">{{ $transaction ? number_format((float) $transaction->amount, 0, ',', ' ').' '.$transaction->currency : '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Moyen de paiement</div>
                            <div class="crypto-kpi-value">{{ $transaction?->correspondent ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Numéro payé</div>
                            <div class="crypto-kpi-value">{{ $transaction?->payer_msisdn ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Date</div>
                            <div class="crypto-kpi-value">{{ $transaction?->updated_at?->format('d/m/Y H:i') ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="crypto-kpi">
                            <div class="crypto-kpi-label">Motif d’échec</div>
                            <div class="crypto-kpi-value text-danger">{{ $transaction?->failure_reason ?? session('result_error') ?? 'Non communiqué' }}</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    <a href="{{ route('payments.sandbox') }}" class="btn btn-primary">Retour à la page paiement</a>
                    <a href="{{ route('subscriptions.history') }}" class="btn btn-outline-primary">Historique des abonnements</a>
                    <a href="{{ route('profile') }}" class="btn btn-outline-secondary">Aller au profil</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@if($isFailure)
    @push('scripts')
    <script>
        (function () {
            const countdownEl = document.getElementById('retryCountdown');
            let seconds = 8;
            const timer = setInterval(function () {
                seconds -= 1;
                if (countdownEl) countdownEl.textContent = String(seconds);
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = @json(route('payments.sandbox'));
                }
            }, 1000);
        })();
    </script>
    @endpush
@endif

