<!-- Filtres avancés -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">🔍 Filtres avancés</h5>
            <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
                <i class="fas fa-undo me-1"></i> Réinitialiser
            </button>
        </div>
    </div>
    <div class="card-body">
        <form id="filtersForm" class="row g-3">
            <div class="col-12 col-md-3">
                <label class="form-label">Type de transaction</label>
                <select class="form-select" name="type" onchange="applyFilters()">
                    <option value="">Tous les types</option>
                    <option value="encaissement">Encaissements</option>
                    <option value="decaissement">Décaissements</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label">Statut</label>
                <select class="form-select" name="status" onchange="applyFilters()">
                    <option value="">Tous les statuts</option>
                    <option value="effectue">Effectué</option>
                    <option value="planifie">Planifié</option>
                    <option value="annule">Annulé</option>
                </select>
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label">Date de début</label>
                <input type="date" class="form-control" name="date_from" onchange="applyFilters()">
            </div>
            
            <div class="col-12 col-md-3">
                <label class="form-label">Date de fin</label>
                <input type="date" class="form-control" name="date_to" onchange="applyFilters()">
            </div>
            
            <div class="col-12 col-md-4">
                <label class="form-label">Catégorie</label>
                <select class="form-select" name="transaction_type" onchange="applyFilters()">
                    <option value="">Toutes les catégories</option>
                    <option value="Paiement client">Paiement client</option>
                    <option value="Paiement fournisseur">Paiement fournisseur</option>
                    <option value="Salaires">Salaires</option>
                    <option value="Loyer">Loyer</option>
                    <option value="Frais généraux">Frais généraux</option>
                    <option value="Apport capital">Apport capital</option>
                    <option value="Autre">Autre</option>
                </select>
            </div>
            
            <div class="col-12 col-md-4">
                <label class="form-label">Montant minimum</label>
                <input type="number" class="form-control" name="amount_min" step="0.01" min="0" placeholder="0 FCFA" onchange="applyFilters()">
            </div>
            
            <div class="col-12 col-md-4">
                <label class="form-label">Montant maximum</label>
                <input type="number" class="form-control" name="amount_max" step="0.01" min="0" placeholder="∞ FCFA" onchange="applyFilters()">
            </div>
            
            <div class="col-12">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-search me-2"></i>Appliquer les filtres
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="saveFilters()">
                        <i class="fas fa-save me-2"></i>Sauvegarder les filtres
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Recherche rapide -->
<div class="row mb-3">
    <div class="col-12">
        <div class="input-group">
            <span class="input-group-text">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" class="form-control" id="quickSearch" placeholder="Recherche rapide par description, référence ou montant..." onkeyup="quickSearch()">
            <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
</div>
