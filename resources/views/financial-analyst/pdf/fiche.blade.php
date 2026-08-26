<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Analyse financière — {{ $company->company_name ?: $company->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #222; line-height: 1.4; }
        h1 { font-size: 16pt; border-bottom: 2px solid #0f1b3a; padding-bottom: 6px; margin-top: 0; }
        h2 { font-size: 12pt; margin-top: 16px; color: #0f1b3a; }
        table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; font-size: 9pt; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f0f4f8; font-weight: bold; }
        .muted { color: #666; font-size: 9pt; }
        .header-meta { margin-bottom: 16px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8.5pt; font-weight: bold; background: #eee; }
    </style>
</head>
<body>
    <div class="header-meta">
        <h1>Analyse financière — {{ $company->company_name ?: $company->name }}</h1>
        <p class="muted">{{ $company->name }} · {{ $company->email }} · Secteur : {{ $company->sector ?: 'non renseigné' }} — Document généré le {{ now()->format('d/m/Y à H:i') }}</p>
        @if($company->accounting_quality_reviewed_at)
            <p><span class="badge">Données vérifiées le {{ $company->accounting_quality_reviewed_at->format('d/m/Y') }}</span></p>
        @else
            <p><span class="badge">Données non vérifiées — à interpréter avec prudence</span></p>
        @endif
    </div>

    <h2>Classement financier automatique</h2>
    <p>{{ $analysis['classement']['label'] ?? 'Non classé' }} — {{ $analysis['classement']['resume'] ?? '' }}</p>

    <h2>Scoring 360</h2>
    @if($scoring360)
        <table>
            <thead><tr><th>Vision</th><th>Score / 100</th><th>Décision</th></tr></thead>
            <tbody>
                <tr><td>Composite</td><td>{{ $scoring360['composite']['total'] ?? '—' }}</td><td>{{ $scoring360['composite']['decision']['label'] ?? '' }}</td></tr>
                <tr><td>Vision bancaire</td><td>{{ $scoring360['blocks']['bank']['total'] ?? '—' }}</td><td>{{ $scoring360['blocks']['bank']['decision']['label'] ?? '' }}</td></tr>
                <tr><td>Vision investisseur</td><td>{{ $scoring360['blocks']['investor']['total'] ?? '—' }}</td><td>{{ $scoring360['blocks']['investor']['decision']['label'] ?? '' }}</td></tr>
                <tr><td>Vision interne</td><td>{{ $scoring360['blocks']['internal']['total'] ?? '—' }}</td><td>{{ $scoring360['blocks']['internal']['decision']['label'] ?? '' }}</td></tr>
            </tbody>
        </table>
    @else
        <p class="muted">Score non calculable (pas assez de données comptables).</p>
    @endif

    <h2>Ratios financiers</h2>
    <table>
        <tbody>
            <tr><td>ROA (rentabilité économique)</td><td>{{ $analysis['ratios']['roa_pct'] ?? '—' }}{{ isset($analysis['ratios']['roa_pct']) ? ' %' : '' }}</td></tr>
            <tr><td>ROE (rentabilité des capitaux propres)</td><td>{{ $analysis['ratios']['roe_pct'] ?? '—' }}{{ isset($analysis['ratios']['roe_pct']) ? ' %' : '' }}</td></tr>
            <tr><td>Marge nette</td><td>{{ $analysis['ratios']['marge_nette_pct'] ?? '—' }}{{ isset($analysis['ratios']['marge_nette_pct']) ? ' %' : '' }}</td></tr>
            <tr><td>Endettement / actif</td><td>{{ $analysis['ratios']['endettement_sur_actif_pct'] ?? '—' }}{{ isset($analysis['ratios']['endettement_sur_actif_pct']) ? ' %' : '' }}</td></tr>
            <tr><td>Levier financier</td><td>{{ $analysis['ratios']['dettes_sur_capitaux_propres'] ?? '—' }}</td></tr>
            <tr><td>Liquidité générale</td><td>{{ $analysis['ratios']['liquidite_generale'] ?? '—' }}</td></tr>
            <tr><td>Trésorerie / passif</td><td>{{ $analysis['ratios']['couverture_tresorerie_passif'] ?? '—' }}</td></tr>
            <tr><td>Rotation de l'actif</td><td>{{ $analysis['ratios']['rotation_actif'] ?? '—' }}</td></tr>
            <tr><td>Délai créances (jours)</td><td>{{ $analysis['ratios']['delai_creances_jours'] ?? '—' }}</td></tr>
        </tbody>
    </table>

    @if($sectorComparison)
        <h2>Comparaison sectorielle — {{ $sectorComparison['sector'] }}</h2>
        <table>
            <thead><tr><th></th><th>Cette PME</th><th>Moyenne du secteur ({{ $sectorComparison['peers_count'] }} PME)</th></tr></thead>
            <tbody>
                <tr><td>ROA</td><td>{{ $analysis['ratios']['roa_pct'] ?? '—' }}</td><td>{{ $sectorComparison['avg_roa_pct'] ?? '—' }}</td></tr>
                <tr><td>Marge nette</td><td>{{ $analysis['ratios']['marge_nette_pct'] ?? '—' }}</td><td>{{ $sectorComparison['avg_marge_nette_pct'] ?? '—' }}</td></tr>
                <tr><td>Endettement / actif</td><td>{{ $analysis['ratios']['endettement_sur_actif_pct'] ?? '—' }}</td><td>{{ $sectorComparison['avg_endettement_sur_actif_pct'] ?? '—' }}</td></tr>
            </tbody>
        </table>
    @endif

    <h2>Dossier(s) de financement</h2>
    @forelse($investmentRequests as $ir)
        <p>
            <strong>{{ number_format((float) $ir->amount_requested, 0, ',', ' ') }} {{ $ir->currency }}</strong> — {{ $ir->purpose }}
            (horizon : {{ $ir->horizon }}, déposé le {{ $ir->created_at?->format('d/m/Y') }}) —
            statut : <span class="badge">{{ $ir->status }}</span>
            @if($ir->review_note)
                <br><span class="muted">Note : {{ $ir->review_note }}</span>
            @endif
        </p>
    @empty
        <p class="muted">Aucun dossier de financement déposé par cette entreprise.</p>
    @endforelse
</body>
</html>
