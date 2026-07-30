@extends('layouts.app')

@section('title', 'Importateur & Espace de Stockage de Fichiers | SITIAME CAPITAL')
@section('page_title', 'Importation, Analyse & Enregistrement de Fichiers')

@push('styles')
<style>
    .soft-dashboard-body {
        background-color: #eef2f6;
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
</style>
@endpush

@section('content')
<div class="soft-dashboard-body">
    <div class="soft-dashboard-container">

        <!-- HEADER BANNER -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 p-3 bg-white rounded-4 border">
            <div class="d-flex align-items-center gap-3">
                <a href="{{ route('commercial.dashboard') }}" class="btn btn-light rounded-circle p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                    <i data-feather="arrow-left" style="width:20px; height:20px;"></i>
                </a>
                <div>
                    <h1 class="h4 fw-bold text-dark mb-0">Importateur & Lecteur Universel de Fichiers</h1>
                    <p class="text-muted small mb-0">Espace dédié pour analyser, lire, extraire et enregistrer vos documents en base de données.</p>
                </div>
            </div>
            <a href="{{ route('commercial.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold text-sm">
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
            <form action="{{ route('commercial.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
                @csrf
                
                <!-- Zone de dépôt / Glisser-Déposer -->
                <div class="border-2 border-dashed border-success rounded-4 p-5 text-center bg-light cursor-pointer mb-4" onclick="document.getElementById('realFileInput').click();">
                    <input type="file" name="document_file" id="realFileInput" class="d-none" onchange="handleDedicatedFileRead(this.files[0])" required>
                    <i data-feather="upload-cloud" class="text-success mb-3" style="width:56px; height:56px;"></i>
                    <h4 class="fw-bold text-dark mb-2">Glissez-déposez ou cliquez ici pour importer un fichier</h4>
                    <p class="text-muted mb-0">Formats supportés : <code>.csv, .xlsx, .pdf, .txt, .docx, .json, .png, .jpg, .xml, .log</code> (Max 20 Mo)</p>
                    <button type="button" class="btn btn-success rounded-pill px-4 py-2 mt-3 fw-bold shadow-sm">
                        Choisir un fichier sur mon appareil
                    </button>
                </div>

                <!-- Zone d'affichage des données lues avec BOUTONS ENREGISTRER / ANNULER -->
                <div id="fileReadOutput" style="display: none;">
                    <div class="card border rounded-4 p-4 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom">
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-success text-white rounded-pill px-3 py-2 fs-6" id="fileReadTypeBadge">Fichier</span>
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
                                <button type="submit" class="btn btn-success rounded-pill px-4 py-2 fw-bold shadow-sm">
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
                            <button type="submit" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-sm fs-6">
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
                    <h3 class="h5 fw-bold text-dark mb-1">📚 Fichiers & Documents Enregistrés dans la Base de Données</h3>
                    <p class="text-muted small mb-0">Tous les fichiers enregistrés par votre compte commercial sont archivés en toute sécurité.</p>
                </div>
                <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">
                    {{ $savedDocuments->count() }} Fichier(s) enregistré(s)
                </span>
            </div>

            <div class="table-responsive">
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
                                        <div class="bg-light text-primary rounded-3 p-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
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
                                    <span class="badge bg-secondary text-white rounded-pill px-3 py-1 small">
                                        {{ $doc->mime_type ?? 'Document' }}
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
                                    <div class="small">Utilisez l'importateur ci-dessus pour importer et sauvegarder vos premiers fichiers.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
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
    typeBadge.innerText = file.type || file.name.split('.').pop().toUpperCase();

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
</script>
@endpush
@endsection
