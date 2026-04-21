@extends('layouts.app')

@section('title', 'Modifier Écriture | Sitiame Capitale')
@section('page_title', 'Modifier l’écriture comptable')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Modifier l’écriture</h5>
                    <p class="text-muted">Ajustez les détails de l’écriture avant de sauvegarder.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8">
            <div class="card">
                <div class="card-body">
                    <div class="alert alert-info" role="alert">
                        <i data-feather="info" class="me-2"></i>
                        Toute modification de la date, du type, de la référence, de la description, du montant ou du justificatif réinitialise le contrôle OCR.
                    </div>

                    <form id="editEntryForm" action="{{ route('accounting.entries.update', $entry) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" value="{{ old('date', $entry->date->toDateString()) }}" class="form-control @error('date') is-invalid @enderror" required>
                            @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type de document</label>
                            <select id="documentTypeEdit" name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                                @foreach(['Vente', 'Achat', 'Reçu', 'Justificatif'] as $type)
                                    <option value="{{ $type }}" {{ old('document_type', $entry->document_type) === $type ? 'selected' : '' }}>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Référence</label>
                            <input type="text" name="document_reference" value="{{ old('document_reference', $entry->document_reference) }}" class="form-control @error('document_reference') is-invalid @enderror" placeholder="Ex: FAC-2026-001">
                            @error('document_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" value="{{ old('description', $entry->description) }}" class="form-control @error('description') is-invalid @enderror" placeholder="Libellé de l'écriture" required>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Compte débit</label>
                                <input type="text" id="debitAccountEditAuto" value="{{ old('debit_account', $entry->debit_account) }}" class="form-control" readonly>
                                <small class="form-text text-muted d-block mt-1">Compte calculé automatiquement selon le type de document.</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Compte crédit</label>
                                <input type="text" id="creditAccountEditAuto" value="{{ old('credit_account', $entry->credit_account) }}" class="form-control" readonly>
                                <small class="form-text text-muted d-block mt-1">Compte calculé automatiquement selon le type de document.</small>
                            </div>
                        </div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Montant</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $entry->amount) }}" class="form-control @error('amount') is-invalid @enderror" placeholder="0.00" required>
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fichier justificatif</label>
                            <input type="file" id="attachmentInput" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx,.zip">
                            <div class="form-text">Formats : PDF, images, Excel, Word, ZIP. Taille max 20 Mo.</div>
                            @error('attachment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @if($entry->attachment_path)
                            <div class="mb-3">
                                <a href="{{ asset('storage/' . $entry->attachment_path) }}" target="_blank">Fichier actuel</a>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="remove_attachment" value="1" id="remove_attachment" {{ old('remove_attachment') ? 'checked' : '' }}>
                                <label class="form-check-label" for="remove_attachment">
                                    Supprimer le fichier actuel (si aucun nouveau fichier n'est uploadé)
                                </label>
                            </div>
                        @endif
                        <button type="submit" class="btn btn-success">Sauvegarder</button>
                        <a href="{{ route('accounting') }}" class="btn btn-outline-secondary ms-2">Annuler</a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Met à jour automatiquement les comptes affichés selon le type sélectionné.
        function updateEditAccountsByDocumentType() {
            const documentType = document.getElementById('documentTypeEdit').value;
            const debitInput = document.getElementById('debitAccountEditAuto');
            const creditInput = document.getElementById('creditAccountEditAuto');

            const accountMap = {
                'Achat': { debit: '607 Achats de marchandises', credit: '401 Fournisseurs' },
                'Vente': { debit: '411 Clients', credit: '701 Ventes de marchandises' },
                'Reçu': { debit: '512 Banque', credit: '411 Clients' },
                'Justificatif': { debit: '627 Services bancaires', credit: '512 Banque' },
            };

            const fallback = { debit: '471 Compte transitoire', credit: '472 Compte d\'attente' };
            const accounts = accountMap[documentType] || fallback;

            debitInput.value = accounts.debit;
            creditInput.value = accounts.credit;
        }

        document.addEventListener('DOMContentLoaded', function () {
            document.getElementById('documentTypeEdit').addEventListener('change', updateEditAccountsByDocumentType);
            updateEditAccountsByDocumentType();
            if (typeof feather !== 'undefined') {
                feather.replace();
            }

            const form = document.getElementById('editEntryForm');
            const fileInput = document.getElementById('attachmentInput');
            const removeCheckbox = document.getElementById('remove_attachment');

            // Popup d'information immédiate lorsqu'un nouveau fichier est sélectionné.
            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    alert('Nouveau justificatif détecté. Le fichier sera pris en compte à la validation et le contrôle OCR sera relancé.');
                }
            });

            form.addEventListener('submit', function (event) {
                const hasNewFile = fileInput.files && fileInput.files.length > 0;
                const wantsRemove = !!(removeCheckbox && removeCheckbox.checked);

                if (hasNewFile && wantsRemove) {
                    const ok = confirm('Vous avez sélectionné un nouveau fichier ET coché suppression du fichier actuel. Le nouveau fichier remplacera l\'ancien. Continuer ?');
                    if (!ok) {
                        event.preventDefault();
                        return;
                    }
                }

                if (wantsRemove && !hasNewFile) {
                    const ok = confirm('Vous allez supprimer le justificatif actuel sans en uploader un nouveau. Confirmer cette action ?');
                    if (!ok) {
                        event.preventDefault();
                        return;
                    }
                }
            });
        });
    </script>
@endsection
