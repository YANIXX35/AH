@extends('layouts.app')

@section('title', 'Dossier de financement '.$dossier->reference)
@section('page_title', 'Dossier de financement')

@section('content')
    @php($stepIndex = array_search($step, array_column(\App\Models\FinancingDossier::STEPS, 'key'), true))
    @php($stepCount = count(\App\Models\FinancingDossier::STEPS))
    @php($progressPct = (int) round((($stepIndex + 1) / $stepCount) * 100))

    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="small text-muted">Dossiers de financement / {{ $dossier->reference }}</div>
                            <h5 class="mb-1">Demande de financement — {{ $dossier->company?->company_name ?: $dossier->company?->name }}</h5>
                        </div>
                        <a href="{{ route('analyst.pme.show', $dossier->company) }}" class="btn btn-outline-secondary btn-sm">← Retour à la fiche PME</a>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Avancement du dossier</span>
                            <strong>{{ $progressPct }} %</strong>
                        </div>
                        <div class="progress" style="height:6px;">
                            <div class="progress-bar" role="progressbar" style="width: {{ $progressPct }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <div class="list-group list-group-flush">
                    @foreach(\App\Models\FinancingDossier::STEPS as $i => $s)
                        @php($implemented = in_array($s['key'], \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                        <a href="{{ $implemented ? route('analyst.financement.step', [$dossier, $s['key']]) : '#' }}"
                           class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ $s['key'] === $step ? 'active' : '' }} {{ ! $implemented ? 'disabled text-muted' : '' }}">
                            <span class="badge {{ $s['key'] === $step ? 'bg-light text-dark' : 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill">{{ $i + 1 }}</span>
                            <span class="flex-grow-1">{{ $s['label'] }}</span>
                            @if(! $implemented)
                                <span class="small">à venir</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="card">
                <div class="card-body">
                    @if(! in_array($step, \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                        <div class="alert alert-info mb-0">Cette étape sera disponible dans une prochaine mise à jour.</div>
                    @else
                        <form action="{{ route('analyst.financement.step.update', [$dossier, $step]) }}" method="POST">
                            @csrf
                            @if($step === 'demande')
                                @include('financial-analyst.financing-dossier.step-demande')
                            @elseif($step === 'entreprise')
                                @include('financial-analyst.financing-dossier.step-entreprise')
                            @endif

                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                @php($prevIndex = $stepIndex - 1)
                                @if($prevIndex >= 0 && in_array(\App\Models\FinancingDossier::STEPS[$prevIndex]['key'], \App\Models\FinancingDossier::IMPLEMENTED_STEPS, true))
                                    <a href="{{ route('analyst.financement.step', [$dossier, \App\Models\FinancingDossier::STEPS[$prevIndex]['key']]) }}" class="btn btn-outline-secondary">← Précédent</a>
                                @else
                                    <span></span>
                                @endif
                                <button type="submit" class="btn btn-primary">Enregistrer et continuer →</button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
