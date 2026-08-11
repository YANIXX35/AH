@extends('layouts.app')

@section('title', 'Importateur & Espace de Stockage de Fichiers | SITIAME CAPITAL')
@section('page_title', 'Importation, Analyse & Enregistrement de Fichiers')

@push('styles')
<style>
    .soft-dashboard-body {
        background: linear-gradient(135deg, #f0f4ff 0%, #eef2f6 45%, #f0f9ff 100%);
        min-height: 100vh;
        font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        color: #1e293b;
        padding: 24px;
    }

    .soft-dashboard-container {
        background: #f8fafc;
        border-radius: 32px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.06);
        padding: 24px;
    }

    .mockup-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 24px;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.03);
    }

    /* En-tête */
    .import-header {
        background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 55%, #6366f1 100%);
        border-radius: 24px;
        padding: 28px 32px;
        color: #fff;
        box-shadow: 0 15px 35px -10px rgba(37, 99, 235, 0.35);
        position: relative;
        overflow: hidden;
    }
    .import-header::before {
        content: '';
        position: absolute;
        top: -60px; right: -60px;
        width: 220px; height: 220px;
        background: radial-gradient(circle, rgba(255,255,255,0.16) 0%, transparent 70%);
        border-radius: 50%;
    }
    .import-header .icon-bubble {
        width: 52px; height: 52px;
        background: rgba(255,255,255,0.18);
        border: 1px solid rgba(255,255,255,0.3);
        border-radius: 16px;
        display: flex; align-items: center; justify-content: center;
        backdrop-filter: blur(4px);
    }
    .import-header .btn-back {
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.35);
        color: #fff;
        transition: background .2s ease;
    }
    .import-header .btn-back:hover { background: rgba(255,255,255,0.26); color: #fff; }

    /* Zone de dépôt */
    .dropzone {
        border: 2px dashed #93c5fd;
        border-radius: 20px;
        background: linear-gradient(180deg, #f8fbff 0%, #f1f6fd 100%);
        padding: 56px 24px;
        text-align: center;
        cursor: pointer;
        transition: border-color .2s ease, background .2s ease, transform .15s ease;
    }
    .dropzone:hover {
        border-color: #3b82f6;
        background: linear-gradient(180deg, #eff6ff 0%, #e7f0fe 100%);
    }
    .dropzone.dropzone-active {
        border-color: #2563eb;
        background: linear-gradient(180deg, #dbeafe 0%, #e0e7ff 100%);
        transform: scale(1.005);
    }
    .dropzone .upload-icon-bubble {
        width: 88px; height: 88px;
        border-radius: 50%;
        background: linear-gradient(135deg, #dbeafe, #bfdbfe);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 18px;
        box-shadow: 0 8px 20px rgba(59, 130, 246, 0.18);
    }
    .format-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #eef2ff;
        color: #4338ca;
        border: 1px solid #e0e7ff;
        border-radius: 999px;
        padding: 4px 12px;
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .02em;
    }

    /* Sécurité anti-débordement : peu importe le texte, le badge de type ne peut pas dépasser sa carte */
    .file-type-badge {
        display: inline-block;
        max-width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }

    .doc-row-icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        background: linear-gradient(135deg, #eff6ff, #dbeafe);
        color: #2563eb;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
    }

    .section-eyebrow {
        text-transform: uppercase;
        letter-spacing: .08em;
        font-size: .72rem;
        font-weight: 700;
        color: #64748b;
    }

    @media (max-width: 767.98px) {
        .soft-dashboard-body { min-height: auto; padding: 10px 8px; }
        .soft-dashboard-container { padding: 10px; border-radius: 20px; }
        .import-header { padding: 20px; border-radius: 20px; }
        .dropzone { padding: 36px 16px; }
    }
</style>
@endpush

@php
    $fileTypeLabel = function (?string $mime, string $filename): string {
        $map = [
            'application/pdf' => 'PDF',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word',
            'application/msword' => 'Word',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel',
            'application/vnd.ms-excel' => 'Excel',
            'text/csv' => 'CSV',
            'application/json' => 'JSON',
            'text/plain' => 'Texte',
            'application/xml' => 'XML',
            'text/xml' => 'XML',
            'image/png' => 'Image PNG',
            'image/jpeg' => 'Image JPEG',
        ];
        if ($mime && isset($map[$mime])) {
            return $map[$mime];
        }
        $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
        return $ext ?: 'Document';
    };
@endphp

@section('content')
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">

        <!-- HEADER BANNER -->
        <div class="import-header d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div class="d-flex align-items-center gap-3 position-relative">
                <div class="icon-bubble">
                    <i data-feather="upload-cloud" style="width:24px; height:24px;"></i>
                </div>
                <div>
                    <h1 class="h4 fw-bold mb-1">Importateur &amp; Lecteur Universel de Fichiers</h1>
                    <p class="mb-0" style="color: rgba(255,255,255,0.85); font-size: .92rem;">
                        Analysez, lisez, extrayez et enregistrez vos documents en base de données, en toute sécurité.
                    </p>
                </div>
            </div>
            <a href="{{ route('commercial.dashboard') }}" class="btn btn-back rounded-pill px-4 fw-semibold text-sm position-relative">
                &larr; Retour au Dashboard
            </a>
        </div>

        <!-- NOTIFICATIONS / ALERTS -->
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="check-circle" class="me-2 text-success"></i>
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-4 mb-4 border-0 shadow-sm" role="alert">
                <i data-feather="alert-circle" class="me-2 text-danger"></i>
                <strong>Attention :</strong> Veuillez corriger les erreurs ci-dessous.
                <ul class="mb-0 mt-1 small">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <!-- MAIN FILE UPLOAD & ANALYZER CARD WITH ENREGISTRER / ANNULER FORM -->
        <div class="mockup-card p-4 mb-5">
            <div class="section-eyebrow mb-2">Étape 1</div>
            <form action="{{ route('commercial.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf

                <!-- Zone de dépôt / Glisser-Déposer -->
                <div class="dropzone mb-4" id="dropzone" onclick="document.getElementById('realFileInput').click();">
                    <input type="file" name="document_file" id="realFileInput" class="d-none" onchange="handleDedicatedFileRead(this.files[0])" required>
                    <div class="upload-icon-bubble">
                        <i data-feather="upload-cloud" class="text-primary" style="width:36px; height:36px;"></i>
                    </div>
                    <h4 class="fw-bold text-dark mb-2">Glissez-déposez votre fichier ici</h4>
                    <p class="text-muted mb-3">ou cliquez pour parcourir les fichiers de votre appareil</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                        <span class="format-pill">CSV</span>
                        <span class="format-pill">XLSX</span>
                        <span class="format-pill">PDF</span>
                        <span class="format-pill">DOCX</span>
                        <span class="format-pill">JSON</span>
                        <span class="format-pill">PNG / JPG</span>
                        <span class="format-pill">XML · TXT · LOG</span>
                    </div>
                    <button type="button" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                        <i data-feather="folder" class="me-2" style="width:16px; height:16px;"></i>Choisir un fichier sur mon appareil
                    </button>
                    <p class="text-muted small mt-3 mb-0">Taille maximale : 20 Mo</p>
                </div>

                <!-- Zone d'affichage des données lues avec BOUTONS ENREGISTRER / ANNULER -->
                <div id="fileReadOutput" style="display: none;">
                    <div class="section-eyebrow mb-2 mt-1">Étape 2</div>
                    <div class="card border rounded-4 p-4 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-primary text-white rounded-pill px-3 py-2 fs-6 file-type-badge" style="max-width: 160px;" id="fileReadTypeBadge">Fichier</span>
                                <div>
                                    <h3 class="h5 fw-bold text-dark mb-0" id="fileReadName">—</h3>
                                    <span class="text-muted small" id="fileReadSize">(0 KB)</span>
                                </div>
                            </div>
                            <!-- Actions principales : Enregistrer ou Annuler -->
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-semibold" onclick="resetFileRead()">
                                    <i data-feather="x-circle" class="me-1" style="width:16px; height:16px;"></i> Annuler
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 fw-bold shadow-sm">
                                    <i data-feather="save" class="me-1" style="width:16px; height:16px;"></i> Enregistrer dans mon espace & BDD
                                </button>
                            </div>
                        </div>

                        <!-- Métadonnées lues -->
                        <div class="row g-3 mb-4" id="fileMetaDataContainer"></div>

                        <!-- Champ Notes facultatif -->
                        <div class="mb-4">
                            <label class="form-label small fw-semibold text-dark">Notes ou description (facultatif) :</label>
                            <input type="text" name="notes" class="form-control rounded-3" placeholder="Ajoutez un commentaire ou un contexte à ce document...">
                        </div>

                        <!-- Contenu / Aperçu des informations lues -->
                        <h5 class="fw-bold text-dark mb-2">Aperçu & Extraction des Données :</h5>
                        <div class="bg-dark text-light p-4 rounded-3 font-monospace" style="max-height: 450px; overflow-y: auto; font-size: 0.9rem;" id="fileContentPreview">
                            <em>Aucun contenu extrait.</em>
                        </div>

                        <!-- Boutons d'actions répétés en bas de carte -->
                        <div class="d-flex justify-content-end align-items-center gap-2 mt-4 pt-3 border-top">
                            <button type="button" class="btn btn-outline-danger rounded-pill px-4 py-2 fw-semibold" onclick="resetFileRead()">
                                Annuler
                            </button>
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2 fw-bold shadow-sm fs-6">
                                <i data-feather="check-circle" class="me-1"></i> Confirmé & Enregistrer
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- HISTORIQUE DES DOCUMENTS ENREGISTRÉS EN BDD ET DANS L'ESPACE -->
        <div class="mockup-card p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom">
                <div>
                    <div class="section-eyebrow mb-1">Espace de stockage</div>
                    <h3 class="h5 fw-bold text-dark mb-1">Fichiers &amp; Documents Enregistrés</h3>
                    <p class="text-muted small mb-0">Tous les fichiers enregistrés par votre compte commercial sont archivés en toute sécurité.</p>
                </div>
                <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
                    {{ $savedDocuments->count() }} Fichier(s) enregistré(s)
                </span>
            </div>

            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="py-3">Nom du Document</th>
                            <th class="py-3">Format / Type</th>
                            <th class="py-3">Taille</th>
                            <th class="py-3">Date d'Enregistrement</th>
                            <th class="py-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($savedDocuments as $doc)
                            <tr>
                                <td class="py-3">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="doc-row-icon">
                                            <i data-feather="file-text" style="width:20px; height:20px;"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark mb-0">{{ $doc->original_name }}</div>
                                            @if($doc->notes)
                                                <div class="text-muted small" style="font-size:0.78rem;">{{ \Illuminate\Support\Str::limit($doc->notes, 50) }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary text-white rounded-pill px-3 py-1 small file-type-badge" title="{{ $doc->mime_type }}">
                                        {{ $fileTypeLabel($doc->mime_type, $doc->original_name) }}
                                    </span>
                                </td>
                                <td class="fw-semibold text-muted">{{ $doc->formatted_size }}</td>
                                <td class="text-muted small">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <a href="{{ route('commercial.import.download', $doc->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
                                            <i data-feather="download" style="width:14px; height:14px;" class="me-1"></i> Télécharger
                                        </a>
                                        <form action="{{ route('commercial.import.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer ce document de votre espace et de la base de données ?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                                                <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i data-feather="folder-minus" class="mb-2 text-muted" style="width:42px; height:42px; opacity:0.4;"></i>
                                    <div class="fw-semibold">Aucun document enregistré pour le moment.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards Layout -->
            <div class="d-block d-md-none">
                @forelse($savedDocuments as $doc)
                    <div class="card p-3 mb-3">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="doc-row-icon" style="width:36px; height:36px;">
                                <i data-feather="file-text" style="width:18px; height:18px;"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-bold text-dark text-truncate small" style="font-size: 0.9rem;">{{ $doc->original_name }}</div>
                                <span class="badge bg-secondary text-white rounded-pill px-2 py-0.5 mt-1 file-type-badge" style="font-size: 0.65rem; max-width: 140px;" title="{{ $doc->mime_type }}">
                                    {{ $fileTypeLabel($doc->mime_type, $doc->original_name) }}
                                </span>
                            </div>
                        </div>

                        @if($doc->notes)
                            <div class="p-2 bg-light rounded-3 my-2 text-muted small" style="font-size: 0.75rem;">
                                {{ $doc->notes }}
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <div class="text-muted small" style="font-size: 0.7rem;">
                                <div>Taille : <strong>{{ $doc->formatted_size }}</strong></div>
                                <div>Importé le {{ $doc->created_at->format('d/m/Y') }}</div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="{{ route('commercial.import.download', $doc->id) }}" class="btn btn-sm btn-outline-primary rounded-pill px-2.5">
                                    <i data-feather="download" style="width:14px; height:14px;"></i>
                                </a>
                                <form action="{{ route('commercial.import.destroy', $doc->id) }}" method="POST" onsubmit="return confirm('Voulez-vous vraiment supprimer ce document ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2">
                                        <i data-feather="trash-2" style="width:14px; height:14px;"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4 bg-light rounded-3 border">
                        <i data-feather="folder-minus" class="mb-2" style="width:40px; height:40px; opacity:0.3;"></i>
                        <div>Aucun document enregistré pour le moment.</div>
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
const FILE_TYPE_LABELS = {
    'application/pdf': 'PDF',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'Word',
    'application/msword': 'Word',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'Excel',
    'application/vnd.ms-excel': 'Excel',
    'text/csv': 'CSV',
    'application/json': 'JSON',
    'text/plain': 'Texte',
    'application/xml': 'XML',
    'text/xml': 'XML',
    'image/png': 'Image PNG',
    'image/jpeg': 'Image JPEG',
};

function shortFileTypeLabel(file) {
    if (file.type && FILE_TYPE_LABELS[file.type]) {
        return FILE_TYPE_LABELS[file.type];
    }
    const ext = file.name.split('.').pop();
    return ext ? ext.toUpperCase() : 'Document';
}

function handleDedicatedFileRead(file) {
    if (!file) return;

    const outputDiv = document.getElementById('fileReadOutput');
    const nameEl = document.getElementById('fileReadName');
    const sizeEl = document.getElementById('fileReadSize');
    const typeBadge = document.getElementById('fileReadTypeBadge');
    const metaContainer = document.getElementById('fileMetaDataContainer');
    const previewEl = document.getElementById('fileContentPreview');

    outputDiv.style.display = 'block';
    nameEl.innerText = file.name;
    sizeEl.innerText = '(' + (file.size / 1024).toFixed(2) + ' KB)';
    typeBadge.innerText = shortFileTypeLabel(file);
    typeBadge.title = file.type || '';

    outputDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const lastMod = new Date(file.lastModified).toLocaleString('fr-FR');
    metaContainer.innerHTML = `
        <div class="col-md-4"><div class="p-3 bg-light rounded-3 border"><strong>Format Mime :</strong> ${file.type || 'Fichier binaire / document'}</div></div>
        <div class="col-md-4"><div class="p-3 bg-light rounded-3 border"><strong>Taille Fichier :</strong> ${(file.size / 1024).toFixed(2)} KB (${file.size} octets)</div></div>
        <div class="col-md-4"><div class="p-3 bg-light rounded-3 border"><strong>Dernière modif. :</strong> ${lastMod}</div></div>
    `;

    const reader = new FileReader();

    if (file.type.startsWith('image/')) {
        reader.onload = function(e) {
            previewEl.innerHTML = `<div class="text-center p-3"><img src="${e.target.result}" class="img-fluid rounded-3" style="max-height:400px;"></div>`;
        };
        reader.readAsDataURL(file);
    } else {
        reader.onload = function(e) {
            const content = e.target.result;
            if (file.name.endsWith('.csv') || file.name.endsWith('.txt') || file.name.endsWith('.json') || file.name.endsWith('.xml') || file.name.endsWith('.log')) {
                previewEl.innerText = content.substring(0, 25000) + (content.length > 25000 ? '\n\n[... Contenu tronqué pour la lecture]' : '');
            } else {
                previewEl.innerText = "📄 [Lecteur Universel - Document " + (file.name.split('.').pop().toUpperCase()) + "]\n\nNom du fichier : " + file.name + "\nType : " + (file.type || 'Fichier binaire') + "\nTaille : " + file.size + " octets\n\nStatut : Fichier prêt à être enregistré dans votre espace et en base de données.";
            }
        };
        reader.readAsText(file);
    }
}

function resetFileRead() {
    document.getElementById('fileReadOutput').style.display = 'none';
    document.getElementById('realFileInput').value = '';
}

// Glisser-déposer réel sur la zone de dépôt.
(function setupDropzone() {
    const dropzone = document.getElementById('dropzone');
    const input = document.getElementById('realFileInput');
    if (!dropzone || !input) return;

    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dropzone-active');
        });
    });

    ['dragleave', 'dragend'].forEach(evt => {
        dropzone.addEventListener(evt, function (e) {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dropzone-active');
        });
    });

    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        dropzone.classList.remove('dropzone-active');

        const files = e.dataTransfer?.files;
        if (files && files.length > 0) {
            input.files = files;
            handleDedicatedFileRead(files[0]);
        }
    });
})();
</script>
@endpush
@endsection
