<!-- Actions rapides -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">⚡ Actions rapides</h5>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12 col-sm-6 col-md-3">
                <button class="btn btn-success w-100" onclick="quickTransaction('encaissement')">
                    <i class="fas fa-plus-circle me-2"></i>
                    Encaissement rapide
                </button>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <button class="btn btn-danger w-100" onclick="quickTransaction('decaissement')">
                    <i class="fas fa-minus-circle me-2"></i>
                    Décaissement rapide
                </button>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <button class="btn btn-primary w-100" onclick="exportData()">
                    <i class="fas fa-download me-2"></i>
                    Exporter CSV
                </button>
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <button class="btn btn-info w-100" onclick="generateReport()">
                    <i class="fas fa-file-pdf me-2"></i>
                    Rapport PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal pour transaction rapide -->
<div class="modal fade" id="quickTransactionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction rapide</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickTransactionForm">
                    <input type="hidden" id="quickType" name="type">
                    
                    <div class="mb-3">
                        <label class="form-label">Type de transaction</label>
                        <select class="form-select" name="transaction_type" required>
                            <option value="">Sélectionner...</option>
                            <optgroup label="Encaissements">
                                <option value="Paiement client">Paiement client</option>
                                <option value="Apport capital">Apport capital</option>
                                <option value="Emprunt">Emprunt</option>
                                <option value="Autre encaissement">Autre encaissement</option>
                            </optgroup>
                            <optgroup label="Décaissements">
                                <option value="Paiement fournisseur">Paiement fournisseur</option>
                                <option value="Salaires">Salaires</option>
                                <option value="Loyer">Loyer</option>
                                <option value="Frais généraux">Frais généraux</option>
                                <option value="Autre décaissement">Autre décaissement</option>
                            </optgroup>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Montant (FCFA)</label>
                        <input type="number" class="form-control" name="amount" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="transaction_date" value="{{ now()->format('Y-m-d') }}" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveQuickTransaction()">Enregistrer</button>
            </div>
        </div>
    </div>
</div>
