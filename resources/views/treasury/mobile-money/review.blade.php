@extends('layouts.app')

@section('title', 'Revue rapprochement Mobile Money | Sitiame Capital')
@section('page_title', 'Revue de l\'import Mobile Money')

@section('content')
<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h3 mb-1"><strong>Import</strong> {{ ucfirst(str_replace('_', ' ', $import->operator)) }} — {{ $import->original_filename }}</h2>
            <p class="text-muted small mb-0">
                {{ $import->rows_imported }} transaction(s) importée(s) · {{ $import->rows_matched }} rapprochée(s) automatiquement · {{ $import->rows_duplicate }} doublon(s) ignoré(s)
            </p>
        </div>
        <a href="{{ route('treasury.mobile-money.index') }}" class="btn btn-outline-secondary btn-sm">Retour aux imports</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="small text-muted">
                @if ($import->personal_data_purged_at)
                    <span class="badge bg-secondary">Données personnelles effacées</span>
                    le {{ $import->personal_data_purged_at->format('d/m/Y H:i') }}
                @elseif ($import->consent_given_at)
                    Consentement donné le {{ $import->consent_given_at->format('d/m/Y H:i') }} (IP {{ $import->consent_ip }})
                @endif
            </div>
            @if (! $import->personal_data_purged_at)
                <form action="{{ route('treasury.mobile-money.purge', $import) }}" method="POST"
                      onsubmit="return confirm('Effacer définitivement les noms, numéros et lignes brutes de ce relevé ? Les montants, dates et écritures déjà générées seront conservés.');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-danger">Effacer les données personnelles</button>
                </form>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sens</th>
                            <th class="text-end">Montant</th>
                            <th>Correspondant</th>
                            <th>Référence</th>
                            <th>Statut</th>
                            <th style="min-width: 320px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr>
                                <td>{{ $transaction->occurred_at->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge bg-{{ $transaction->direction === 'in' ? 'success' : 'danger' }}">
                                        {{ $transaction->direction === 'in' ? 'Encaissement' : 'Décaissement' }}
                                    </span>
                                </td>
                                <td class="text-end">{{ number_format((float) $transaction->amount, 0, ',', ' ') }} FCFA</td>
                                <td>{{ $transaction->counterparty_name ?? '—' }}{{ $transaction->counterparty_number ? ' ('.$transaction->counterparty_number.')' : '' }}</td>
                                <td>{{ $transaction->external_reference ?? '—' }}</td>
                                <td>
                                    @php
                                        $badge = ['matched' => 'success', 'created' => 'success', 'ignored' => 'secondary', 'pending' => 'warning'][$transaction->status] ?? 'secondary';
                                        $label = ['matched' => 'Rapprochée', 'created' => 'Créée en trésorerie', 'ignored' => 'Ignorée', 'pending' => 'En attente'][$transaction->status] ?? $transaction->status;
                                    @endphp
                                    <span class="badge bg-{{ $badge }}">{{ $label }}</span>
                                </td>
                                <td>
                                    @if ($transaction->status === 'pending')
                                        @if ($transaction->candidates && $transaction->candidates->count() > 0)
                                            <form action="{{ route('treasury.mobile-money.match', $transaction) }}" method="POST" class="d-flex gap-2">
                                                @csrf
                                                <select name="treasury_transaction_id" class="form-select form-select-sm" required>
                                                    <option value="">Choisir une correspondance…</option>
                                                    @foreach ($transaction->candidates as $candidate)
                                                        <option value="{{ $candidate->id }}">
                                                            {{ $candidate->transaction_date->format('d/m/Y') }} — {{ number_format((float) $candidate->amount, 0, ',', ' ') }} FCFA — {{ $candidate->description }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary text-nowrap">Lier</button>
                                            </form>
                                        @else
                                            <form action="{{ route('treasury.mobile-money.create', $transaction) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Créer en trésorerie</button>
                                            </form>
                                        @endif
                                        <form action="{{ route('treasury.mobile-money.ignore', $transaction) }}" method="POST" class="d-inline mt-1">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">Ignorer</button>
                                        </form>
                                    @elseif ($transaction->treasuryTransaction)
                                        <a href="{{ route('treasury.edit', $transaction->treasuryTransaction) }}" class="btn btn-sm btn-outline-primary">Voir la transaction</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Aucune transaction dans cet import.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
