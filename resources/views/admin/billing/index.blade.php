@extends('layouts.app')

@section('title', 'Billing Pro | Admin')
@section('page_title', 'Facturation professionnelle')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Abonnements actifs</div><div class="h4 mb-0">{{ $stats['active_subscriptions'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">En retard</div><div class="h4 mb-0">{{ $stats['past_due_subscriptions'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Suspendus</div><div class="h4 mb-0">{{ $stats['suspended_subscriptions'] }}</div></div></div></div>
        <div class="col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Factures impayees</div><div class="h4 mb-0">{{ $stats['unpaid_invoices'] }}</div></div></div></div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="card-title mb-0">Forcer un plan utilisateur</h5></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.billing.index') }}" class="row g-2 mb-3">
                <div class="col-md-10">
                    <label class="form-label">Rechercher utilisateur (email ou nom)</label>
                    <input type="text" name="user_lookup" value="{{ $userLookup ?? '' }}" class="form-control" placeholder="ex: client@exemple.com">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100" type="submit">Rechercher</button>
                </div>
            </form>

            @if(isset($matchedUsers) && $matchedUsers->count() > 0)
                <div class="alert alert-light border mb-3">
                    <strong>Resultats:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach($matchedUsers as $u)
                            <li>#{{ $u->id }} - {{ $u->name }} ({{ $u->email }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.billing.subscriptions.switch') }}" class="row g-2">
                @csrf
                <div class="col-md-3">
                    <label class="form-label">Utilisateur (ID)</label>
                    <input type="number" name="user_id" class="form-control" min="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">...ou email/nom exact</label>
                    <input type="text" name="user_lookup" class="form-control" placeholder="client@exemple.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Plan</label>
                    <select name="billing_plan_id" class="form-select" required>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} - {{ number_format((float) $plan->price, 0, ',', ' ') }} {{ $plan->currency }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Appliquer</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h5 class="card-title mb-0">Abonnements</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-striped">
                    <thead><tr><th>ID</th><th>Client</th><th>Plan</th><th>Statut</th><th>Prochaine echeance</th><th>Dunning</th></tr></thead>
                    <tbody>
                    @foreach($subscriptions as $subscription)
                        <tr>
                            <td>{{ $subscription->id }}</td>
                            <td>{{ $subscription->user->email ?? 'N/A' }}</td>
                            <td>{{ $subscription->plan->name ?? 'N/A' }}</td>
                            <td>{{ strtoupper($subscription->status) }}</td>
                            <td>{{ optional($subscription->next_billing_at)->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ (int) $subscription->dunning_level }}/4</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $subscriptions->links() }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="card-title mb-0">Dernieres factures</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>#</th><th>Client ID</th><th>Statut</th><th>Total</th><th>Echeance</th></tr></thead>
                    <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td>{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->user_id }}</td>
                            <td>{{ strtoupper($invoice->status) }}</td>
                            <td>{{ number_format((float) $invoice->total_amount, 0, ',', ' ') }} {{ $invoice->currency }}</td>
                            <td>{{ optional($invoice->due_at)->format('d/m/Y') ?? '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
