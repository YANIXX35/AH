{{-- Corps partagé de la liste complète du pipeline de prospects (filtrable).
     Attend $prospects (paginé), $commercials, $filters, $showRoute (nom de route pour la fiche commercial, optionnel). --}}
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Commercial</label>
                <select name="commercial_id" class="form-select">
                    <option value="0">Tous</option>
                    @foreach($commercials as $c)
                        <option value="{{ $c->id }}" @selected((int) $filters['commercial_id'] === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous</option>
                    <option value="nouveau" @selected($filters['status'] === 'nouveau')>Nouveau Lead</option>
                    <option value="contacte" @selected($filters['status'] === 'contacte')>Contacté</option>
                    <option value="qualifie" @selected($filters['status'] === 'qualifie')>Qualifié</option>
                    <option value="client" @selected($filters['status'] === 'client')>Converti en Client</option>
                    <option value="sans_suite" @selected($filters['status'] === 'sans_suite')>Sans suite</option>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">Filtrer</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Prospect</th>
                        <th>Entreprise</th>
                        <th>Commercial</th>
                        <th>Besoin</th>
                        <th>Statut</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($prospects as $prospect)
                        <tr>
                            <td class="small fw-semibold">{{ $prospect->name }}</td>
                            <td class="small text-muted">{{ $prospect->company_name ?: '—' }}</td>
                            <td class="small">
                                @if($showRoute ?? null)
                                    <a href="{{ route($showRoute, $prospect->commercial_user_id) }}">{{ $prospect->commercial?->name ?? '—' }}</a>
                                @else
                                    {{ $prospect->commercial?->name ?? '—' }}
                                @endif
                            </td>
                            <td class="small text-muted">{{ $prospect->need_label }}</td>
                            <td><span class="badge {{ $prospect->status_badge_class }}">{{ $prospect->status_label }}</span></td>
                            <td class="small text-muted">{{ $prospect->created_at->format('d/m/Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">Aucun prospect trouvé.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $prospects->links() }}</div>
    </div>
</div>
