@extends('layouts.app')

@section('title', 'Nouveau Lot de Paie & Salaires | ' . config('app.name'))
@section('page_title', 'Créer un Lot de Paie')

@push('styles')
<style>
    .payroll-bg {
        background-color: #f1f5f9;
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        padding: 24px;
    }
    .payroll-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.03);
    }
</style>
@endpush

@section('content')
<div class="payroll-bg">
    <div class="container-fluid max-w-7xl mx-auto">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('payroll.index') }}" class="text-decoration-none small text-muted fw-semibold">&larr; Retour à la liste des salaires</a>
                <h1 class="h3 fw-bold text-dark mb-1 mt-1">Saisie d'un Nouveau Lot de Paie 💳</h1>
            </div>
        </div>

        <form action="{{ route('payroll.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- Informations Générales du Lot -->
            <div class="payroll-card p-4 mb-4">
                <h4 class="h5 fw-bold text-dark mb-3">1. Informations Générales & Mode de Règlement</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Intitulé du lot de paie <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control rounded-3" placeholder="Ex: Salaires du personnel — Juillet 2026" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Mois & Année <span class="text-danger">*</span></label>
                        <input type="text" name="period_month" class="form-control rounded-3" value="{{ now()->translatedFormat('F Y') }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold">Date de virement <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" class="form-control rounded-3" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Mode de Règlement <span class="text-danger">*</span></label>
                        <select name="payment_method" class="form-select rounded-3" required>
                            <option value="bank_transfer">Virement Bancaire</option>
                            <option value="wave">Wave Mobile Money</option>
                            <option value="orange_money">Orange Money</option>
                            <option value="mtn">MTN Mobile Money</option>
                            <option value="check">Chèque</option>
                            <option value="cash">Caisse / Espèces</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Compte de Trésorerie Source</label>
                        <input type="text" name="payment_account" class="form-control rounded-3" placeholder="Ex: NSIA Banque CI / Compte Caisse">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Fichier de Paie / Pièce Justificative (PDF/Excel)</label>
                        <input type="file" name="file" class="form-control rounded-3">
                    </div>
                </div>
            </div>

            <!-- Table des Salariés -->
            <div class="payroll-card p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="h5 fw-bold text-dark mb-0">2. Salariés & Éléments de Rémunération</h4>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="addEmployeeRow()">
                        + Ajouter un salarié
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle border-0 mb-0" id="employeesTable">
                        <thead>
                            <tr class="text-muted text-uppercase small" style="font-size:0.72rem;">
                                <th class="border-0" style="width:200px;">Salarié <span class="text-danger">*</span></th>
                                <th class="border-0" style="width:130px;">Matricule / Poste</th>
                                <th class="border-0" style="width:120px;">Salaire Base</th>
                                <th class="border-0" style="width:110px;">Primes</th>
                                <th class="border-0" style="width:110px;">CNPS Salariale</th>
                                <th class="border-0" style="width:110px;">CNPS Patronale</th>
                                <th class="border-0" style="width:110px;">Impôt ITS</th>
                                <th class="border-0" style="width:130px;">Net à Payer <span class="text-danger">*</span></th>
                                <th class="border-0 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="employeesTbody">
                            <tr>
                                <td>
                                    <input type="text" name="employees[0][name]" class="form-control form-control-sm rounded-2" placeholder="Nom Prénom" required>
                                </td>
                                <td>
                                    <input type="text" name="employees[0][matricule]" class="form-control form-control-sm rounded-2" placeholder="MAT-001">
                                </td>
                                <td>
                                    <input type="number" name="employees[0][base_salary]" class="form-control form-control-sm rounded-2" placeholder="300000" required>
                                </td>
                                <td>
                                    <input type="number" name="employees[0][bonuses]" class="form-control form-control-sm rounded-2" value="0">
                                </td>
                                <td>
                                    <input type="number" name="employees[0][cnps_employee]" class="form-control form-control-sm rounded-2" value="0">
                                </td>
                                <td>
                                    <input type="number" name="employees[0][cnps_employer]" class="form-control form-control-sm rounded-2" value="0">
                                </td>
                                <td>
                                    <input type="number" name="employees[0][its_tax]" class="form-control form-control-sm rounded-2" value="0">
                                </td>
                                <td>
                                    <input type="number" name="employees[0][net_payable]" class="form-control form-control-sm rounded-2 fw-bold text-success" placeholder="280000" required>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0" onclick="removeRow(this)">
                                        <i data-feather="trash-2" style="width:16px; height:16px;"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions Submit -->
            <div class="d-flex justify-content-end gap-3 mb-5">
                <a href="{{ route('payroll.index') }}" class="btn btn-light rounded-pill px-4">Annuler</a>
                <button type="submit" class="btn btn-primary rounded-pill px-5 fw-bold shadow-sm">
                    Enregistrer le Lot de Paie &check;
                </button>
            </div>
        </form>

    </div>
</div>

@push('scripts')
<script>
    let rowIndex = 1;
    function addEmployeeRow() {
        const tbody = document.getElementById('employeesTbody');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>
                <input type="text" name="employees[${rowIndex}][name]" class="form-control form-control-sm rounded-2" placeholder="Nom Prénom" required>
            </td>
            <td>
                <input type="text" name="employees[${rowIndex}][matricule]" class="form-control form-control-sm rounded-2" placeholder="MAT-00${rowIndex + 1}">
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][base_salary]" class="form-control form-control-sm rounded-2" placeholder="300000" required>
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][bonuses]" class="form-control form-control-sm rounded-2" value="0">
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][cnps_employee]" class="form-control form-control-sm rounded-2" value="0">
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][cnps_employer]" class="form-control form-control-sm rounded-2" value="0">
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][its_tax]" class="form-control form-control-sm rounded-2" value="0">
            </td>
            <td>
                <input type="number" name="employees[${rowIndex}][net_payable]" class="form-control form-control-sm rounded-2 fw-bold text-success" placeholder="280000" required>
            </td>
            <td class="text-end">
                <button type="button" class="btn btn-sm btn-link text-danger p-0 border-0" onclick="removeRow(this)">
                    <i data-feather="trash-2" style="width:16px; height:16px;"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        rowIndex++;
        if (window.feather) feather.replace();
    }

    function removeRow(btn) {
        const row = btn.closest('tr');
        if (document.querySelectorAll('#employeesTbody tr').length > 1) {
            row.remove();
        }
    }
</script>
@endpush
@endsection
