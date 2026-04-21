@extends('layouts.app')

@section('title', 'Documents Comptables | Sitiame Capitale')
@section('page_title', 'Documents Comptables')

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Importation des documents</h5>
                    <p class="text-muted">Chargez des factures, reçus ou justificatifs pour extraction OCR et validation.</p>
                    @include('partials.camera-upload-hint')
                    @if($errors->any())
                        <div class="alert alert-danger mt-3 mb-0">
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('accounting.documents.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Sélectionnez les fichiers (plusieurs possibles)</label>
                            <div class="border rounded p-4" id="drop-zone">
                                <p class="mb-2">Glissez-déposez vos fichiers ici ou cliquez pour sélectionner.</p>
                                <input type="file" name="documents[]" id="documents" class="form-control @error('documents') is-invalid @enderror" multiple accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx,.csv,image/*" style="cursor: pointer;" />
                                @error('documents')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                @error('documents.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Formats autorisés : PDF, JPG, PNG, XLS, XLSX, CSV · Taille max : 20 MB par fichier.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="documents_camera">Ou photographier un justificatif (un fichier)</label>
                            <div class="d-flex gap-2 flex-wrap align-items-stretch">
                                <input type="file" name="documents[]" id="documents_camera" class="form-control flex-grow-1" style="min-width: 200px;" accept="image/*" capture="environment">
                                <button type="button" class="btn btn-outline-secondary btn-sm js-webcam-open align-self-center text-nowrap px-3" data-webcam-for="documents_camera" data-webcam-facing="environment" title="Webcam (navigateur web)">
                                    <span aria-hidden="true">📷</span> Web
                                </button>
                            </div>
                            <div class="form-text">Sur mobile : appareil photo arrière. Sur ordinateur : bouton <strong>Web</strong> pour la webcam, ou choix d’un fichier. Vous pouvez envoyer seul ou en complément des fichiers ci-dessus.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">Importer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Documents importés</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Confiance</th>
                                    <th>Détection</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $document)
                                    <tr>
                                        <td>{{ $document->original_name }}</td>
                                        <td>{{ $document->document_type }}</td>
                                        <td>
                                            @if($document->status === 'pending_validation')
                                                <span class="badge bg-warning-subtle text-warning-emphasis">À valider</span>
                                            @elseif($document->status === 'validated')
                                                <span class="badge bg-success-subtle text-success-emphasis">Validé</span>
                                            @elseif($document->status === 'ocr_failed')
                                                <span class="badge bg-danger-subtle text-danger-emphasis">OCR échoué</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ number_format($document->confidence, 2, ',', ' ') }}%</td>
                                        <td>{{ $document->created_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ asset('storage/' . $document->stored_path) }}" target="_blank" class="btn btn-sm btn-icon btn-outline-secondary" title="Voir le document">
                                                <i data-feather="eye" class="icon-sm"></i>
                                            </a>
                                            <a href="{{ route('accounting.documents.validate', $document) }}" class="btn btn-sm btn-icon btn-primary ms-1" title="Valider le document">
                                                <i data-feather="check-circle" class="icon-sm"></i>
                                            </a>
                                            @if($document->status === 'ocr_failed')
                                                <form action="{{ route('accounting.documents.ocr.retry', $document) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-icon btn-outline-warning ms-1" title="Relancer OCR">
                                                        <i data-feather="refresh-cw" class="icon-sm"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if($document->status === 'ocr_failed' && !empty($document->extracted_data['ocr_error']))
                                        <tr>
                                            <td colspan="6" class="bg-danger-subtle">
                                                <small class="text-danger-emphasis d-block mb-1"><strong>Détail OCR</strong></small>
                                                <pre class="mb-0 small">{{ $document->extracted_data['ocr_error'] }}</pre>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Aucun document importé.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('documents');

        dropZone.addEventListener('click', () => fileInput.click());
        dropZone.addEventListener('dragover', (event) => {
            event.preventDefault();
            dropZone.classList.add('border-primary');
        });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('border-primary'));
        dropZone.addEventListener('drop', (event) => {
            event.preventDefault();
            dropZone.classList.remove('border-primary');
            fileInput.files = event.dataTransfer.files;
        });
    </script>
@endsection
