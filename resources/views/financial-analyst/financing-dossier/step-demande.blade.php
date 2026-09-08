<h6 class="mb-3">Identification de la demande</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label class="form-label">Type de financement *</label>
        <select name="financing_type" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Crédit d’investissement','Crédit de trésorerie','Crédit-bail','Affacturage','Garantie bancaire','Prise de participation','Subvention'] as $opt)
                <option value="{{ $opt }}" {{ old('financing_type', $dossier->financing_type) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Objet principal *</label>
        <select name="financing_purpose" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Acquisition d’équipements','Besoin en fonds de roulement','Extension d’activité','Lancement de projet','Refinancement','Exécution d’un marché'] as $opt)
                <option value="{{ $opt }}" {{ old('financing_purpose', $dossier->financing_purpose) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Montant demandé (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="amount_requested" class="form-control" value="{{ old('amount_requested', $dossier->amount_requested) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Durée souhaitée (mois) *</label>
        <input type="number" min="1" name="desired_term_months" class="form-control" value="{{ old('desired_term_months', $dossier->desired_term_months) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">Différé souhaité (mois)</label>
        <input type="number" min="0" name="grace_period_months" class="form-control" value="{{ old('grace_period_months', $dossier->grace_period_months) }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Périodicité de remboursement *</label>
        <select name="repayment_frequency" class="form-select" required>
            <option value="">Sélectionner…</option>
            @foreach(['Mensuelle','Trimestrielle','Semestrielle','In fine'] as $opt)
                <option value="{{ $opt }}" {{ old('repayment_frequency', $dossier->repayment_frequency) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Apport de l'entreprise (FCFA) *</label>
        <input type="number" step="0.01" min="0" name="promoter_contribution" class="form-control" value="{{ old('promoter_contribution', $dossier->promoter_contribution) }}" required>
        <div class="form-text">Montant déjà disponible et justifiable.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Date souhaitée de décaissement</label>
        <input type="date" name="desired_disbursement_date" class="form-control" value="{{ old('desired_disbursement_date', optional($dossier->desired_disbursement_date)->toDateString()) }}">
    </div>
</div>

<h6 class="mb-3">Justification du besoin</h6>
<div class="row g-3">
    <div class="col-12">
        <label class="form-label">Résumé de la demande *</label>
        <textarea name="financing_summary" class="form-control" rows="3" required>{{ old('financing_summary', $dossier->financing_summary_data['financing_summary'] ?? '') }}</textarea>
        <div class="form-text">Présenter en quelques lignes le besoin, son urgence et les résultats attendus.</div>
    </div>
    <div class="col-12">
        <label class="form-label">Source principale de remboursement *</label>
        <textarea name="repayment_source" class="form-control" rows="3" required>{{ old('repayment_source', $dossier->financing_summary_data['repayment_source'] ?? '') }}</textarea>
        <div class="form-text">Préciser les flux qui serviront au paiement des échéances.</div>
    </div>
</div>
