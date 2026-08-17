@extends('layouts.app')

@section('title', 'Qualité comptable | Admin')
@section('page_title', 'Contrôle qualité comptable périodique')

@section('content')
<div class="container-fluid p-0">
    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <p class="mb-0 text-muted small">
                        Contrôle de complétude par écriture (référence, montant, identification du tiers) — distinct du contrôle qualité périodique par période (page « Investisseur », validation comptable trimestrielle). Cette page en est le détail probant : elle alimente la décision du comptable sans jamais bloquer le scoring elle-même. Automatique tous les mois pour les entreprises dues ; « Lancer maintenant » force une revue immédiate.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Entreprises avec écritures comptables</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Entreprise</th>
                            <th>Écritures</th>
                            <th>Non conformes</th>
                            <th>Jamais revues</th>
                            <th>Dernière revue</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($summaries as $u)
                        <tr>
                            <td>{{ $u->company_name ?: $u->name }}</td>
                            <td>{{ $u->entries_total }}</td>
                            <td>
                                @if($u->entries_non_compliant > 0)
                                    <span class="badge bg-danger">{{ $u->entries_non_compliant }}</span>
                                @else
                                    <span class="badge bg-success">0</span>
                                @endif
                            </td>
                            <td>{{ $u->entries_pending }}</td>
                            <td>{{ optional($u->accounting_quality_reviewed_at)->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('admin.compliance.accounting-quality.review-now', $u) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">Lancer maintenant</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">Aucune entreprise avec des écritures comptables.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $summaries->links() }}</div>
        </div>
    </div>
</div>
@endsection
