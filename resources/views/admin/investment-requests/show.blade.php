@extends('layouts.app')

@section('title', 'Demande #' . $req->id . ' | Administration | ' . config('app.name'))
@section('page_title', 'Administration')

@section('content')
<div class="mb-4">
    <nav aria-label="Fil d’Ariane admin" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Administration</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.investment-requests.index') }}">Demandes d’investissement</a></li>
            <li class="breadcrumb-item active" aria-current="page">#{{ $req->id }}</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
        <div>
            <h1 class="h3 mb-1">Demande <strong>#{{ $req->id }}</strong></h1>
            <p class="text-muted mb-0">{{ $req->user->company_name ?? $req->user->name }} — {{ $req->user->email }}</p>
        </div>
        @php
            $st = $req->status;
            $badge = match ($st) {
                'pending' => 'warning',
                'in_review' => 'info',
                'accepted' => 'success',
                'declined' => 'danger',
                default => 'secondary',
            };
            $label = match ($st) {
                'pending' => 'En attente',
                'in_review' => 'En analyse',
                'accepted' => 'Acceptée',
                'declined' => 'Refusée',
                default => $st,
            };
        @endphp
        <span class="badge bg-{{ $badge }} fs-6">{{ $label }}</span>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 small">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Contenu du dépôt</h2>
            </div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-sm-4 text-muted">Montant sollicité</dt>
                    <dd class="col-sm-8 fw-medium">{{ number_format((float) $req->amount_requested, 0, ',', ' ') }} {{ $req->currency }}</dd>
                    <dt class="col-sm-4 text-muted">Horizon</dt>
                    <dd class="col-sm-8">
                        @if($req->horizon === 'court')
                            &lt; 12 mois
                        @elseif($req->horizon === 'moyen')
                            12–36 mois
                        @elseif($req->horizon === 'long')
                            &gt; 36 mois
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4 text-muted">Représentant légal déclaré</dt>
                    <dd class="col-sm-8">{{ $req->legal_representative ?? '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Pièce d’identité</dt>
                    <dd class="col-sm-8">
                        @php
                            $idLabels = ['cni' => 'Carte nationale d’identité', 'passport' => 'Passeport', 'residence_permit' => 'Titre de séjour', 'other' => 'Autre'];
                            $idType = $req->identity_document_type;
                        @endphp
                        {{ $idType ? ($idLabels[$idType] ?? $idType) : '—' }}
                        @if($req->identity_document_number)
                            <br><span class="text-muted">N° {{ $req->identity_document_number }}</span>
                        @endif
                        @if($req->identity_document_expires_at)
                            <br><span class="text-muted">Expiration : {{ $req->identity_document_expires_at->format('d/m/Y') }}</span>
                        @endif
                    </dd>
                    <dt class="col-sm-4 text-muted">Clôture fiscale N-1</dt>
                    <dd class="col-sm-8">{{ $req->fiscal_closing_at ? $req->fiscal_closing_at->format('d/m/Y') : '—' }}</dd>
                    <dt class="col-sm-4 text-muted">CA N-1 (déclaré)</dt>
                    <dd class="col-sm-8">{{ $req->revenue_n1 !== null ? number_format((float) $req->revenue_n1, 0, ',', ' ').' FCFA' : '—' }}</dd>
                    <dt class="col-sm-4 text-muted">Capitaux propres N-1 (déclaré)</dt>
                    <dd class="col-sm-8">{{ $req->equity_n1 !== null ? number_format((float) $req->equity_n1, 0, ',', ' ').' FCFA' : '—' }}</dd>
                </dl>
                <hr>
                <h3 class="h6">Projet / usage des fonds</h3>
                <div class="small" style="white-space: pre-wrap;">{{ $req->purpose }}</div>
                <hr>
                <h3 class="h6">Engagement sur les pièces</h3>
                <div class="small text-muted" style="white-space: pre-wrap;">{{ $req->attachments_commitment ?? '—' }}</div>
                @if($req->certifies_accuracy)
                    <p class="small text-success mb-0 mt-2">✓ Attestation d’exactitude cochée à l’envoi.</p>
                @endif

                <hr>
                <h3 class="h6">Photo du représentant &amp; pièce d’identité</h3>
                @if($req->photo_path || $req->identity_document_front_path || $req->identity_document_back_path)
                    <div class="row g-3">
                        @if($req->photo_path)
                            <div class="col-md-4">
                                <p class="small text-muted mb-1">Photo</p>
                                @if($req->isPdfPath($req->photo_path))
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'photo']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Ouvrir le fichier</a>
                                @else
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'photo']) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('admin.investment-requests.document.stream', [$req, 'photo']) }}" alt="Photo représentant" class="img-fluid rounded border" style="max-height: 220px;">
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if($req->identity_document_front_path)
                            <div class="col-md-4">
                                <p class="small text-muted mb-1">Recto</p>
                                @if($req->isPdfPath($req->identity_document_front_path))
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'identity_front']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Ouvrir le PDF</a>
                                @else
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'identity_front']) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('admin.investment-requests.document.stream', [$req, 'identity_front']) }}" alt="Recto" class="img-fluid rounded border" style="max-height: 220px;">
                                    </a>
                                @endif
                            </div>
                        @endif
                        @if($req->identity_document_back_path)
                            <div class="col-md-4">
                                <p class="small text-muted mb-1">Verso</p>
                                @if($req->isPdfPath($req->identity_document_back_path))
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'identity_back']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Ouvrir le PDF</a>
                                @else
                                    <a href="{{ route('admin.investment-requests.document.stream', [$req, 'identity_back']) }}" target="_blank" rel="noopener">
                                        <img src="{{ route('admin.investment-requests.document.stream', [$req, 'identity_back']) }}" alt="Verso" class="img-fluid rounded border" style="max-height: 220px;">
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @else
                    <p class="small text-muted mb-0">Aucun fichier joint (dépôt antérieur à l’ajout de cette exigence).</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 mb-0">Traitement (équipe plateforme)</h2>
            </div>
            <div class="card-body">
                @if($req->reviewed_at)
                    <p class="small text-muted mb-1">Dernière mise à jour</p>
                    <p class="small mb-2">{{ $req->reviewed_at->format('d/m/Y H:i') }}</p>
                    @if($req->reviewer)
                        <p class="small mb-2">Par : {{ $req->reviewer->name ?? $req->reviewer->email }}</p>
                    @endif
                    @if($req->review_note)
                        <p class="small mb-3"><strong>Note :</strong> {{ $req->review_note }}</p>
                    @endif
                @else
                    <p class="small text-muted mb-3">Aucune décision enregistrée.</p>
                @endif

                @if(in_array($req->status, ['pending', 'in_review'], true))
                    <form method="post" action="{{ route('admin.investment-requests.workflow', $req) }}" class="d-flex flex-column gap-2">
                        @csrf
                        <label class="form-label small mb-0" for="next_status">Action</label>
                        <select name="next_status" id="next_status" class="form-select" required>
                            @if($req->status === 'pending')
                                <option value="in_review">Prendre en charge (passer en analyse)</option>
                                <option value="declined">Refuser</option>
                            @elseif($req->status === 'in_review')
                                <option value="accepted">Accepter</option>
                                <option value="declined">Refuser</option>
                            @endif
                        </select>
                        <label class="form-label small mb-0" for="review_note">Note d’analyse</label>
                        <textarea name="review_note" id="review_note" rows="4" class="form-control" maxlength="2000" placeholder="Obligatoire pour une décision finale (acceptation ou refus).">{{ old('review_note') }}</textarea>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">Décision close — aucune transition supplémentaire.</p>
                @endif
            </div>
        </div>
        <div class="card border-0 bg-light">
            <div class="card-body small text-muted">
                <strong>Rappel :</strong> les transitions suivent le même workflow que côté déposant : en attente → analyse → décision. Les notes sont visibles dans l’historique métier.
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('admin.investment-requests.index') }}" class="btn btn-outline-secondary">← Retour à la liste</a>
    <a href="{{ route('admin.users.edit', $req->user) }}" class="btn btn-outline-primary">Fiche utilisateur</a>
</div>
@endsection
