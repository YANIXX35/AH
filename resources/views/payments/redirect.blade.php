@extends('layouts.app')

@section('title', 'Redirection paiement | ' . config('app.name'))
@section('page_title', 'Redirection vers FedaPay')

@section('content')
<div class="container-fluid p-0">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5 text-center">
                    <div class="mb-3">
                        <span class="badge bg-primary-subtle text-primary">FedaPay Sandbox</span>
                    </div>
                    <h3 class="mb-2">Vous allez etre redirige vers la page de paiement</h3>
                    <p class="text-muted mb-4">
                        La redirection automatique se lance dans <strong><span id="countdown">5</span> secondes</strong>.
                    </p>

                    <div class="alert alert-info text-start mb-4">
                        <div class="small text-muted mb-2">Moyens de paiement mobile affichés sur la page FedaPay</div>
                        <div class="d-flex flex-wrap gap-2">
                            @forelse($mobileMethods as $method)
                                <span class="badge bg-secondary">{{ $method }}</span>
                            @empty
                                <span class="badge bg-secondary">WAVE</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mb-4">
                        <a id="payNowBtn" href="{{ $paymentUrl }}" class="btn btn-primary btn-lg" target="_blank" rel="noopener">
                            Ouvrir la page de paiement maintenant
                        </a>
                        <a href="{{ route('pricing') }}" class="btn btn-outline-secondary btn-lg">
                            Retour aux tarifs
                        </a>
                    </div>

                    <div class="alert alert-light border text-start mb-0">
                        <div class="small text-muted mb-1">Lien configure</div>
                        <div class="small text-break">{{ $paymentUrl }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const paymentUrl = @json($paymentUrl);
        const countdownEl = document.getElementById('countdown');
        let seconds = 5;

        const timer = setInterval(function () {
            seconds -= 1;
            if (countdownEl) countdownEl.textContent = String(seconds);
            if (seconds <= 0) {
                clearInterval(timer);
                window.location.href = paymentUrl;
            }
        }, 1000);
    })();
</script>
@endpush

