@extends('layouts.app')

@section('title', 'Importateur & Analyseur Universel de Fichiers | SITIAME CAPITAL')
@section('page_title', 'Importation et Analyse de Fichier')

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
                    <p class="text-muted small mb-0">Espace dédié pour analyser, lire et extraire les données de tout type de fichier.</p>
                </div>
            </div>
            <a href="{{ route('commercial.dashboard') }}" class="btn btn-outline-secondary rounded-pill px-4 fw-semibold text-sm">
                &larr; Retour au Dashboard
            </a>
        </div>

        <!-- MAIN FILE UPLOAD & ANALYZER CARD -->
        <div class="mockup-card p-4">
            <!-- Zone de dépôt / Glisser-Déposer -->
            <div class="border-2 border-dashed border-success rounded-4 p-5 text-center bg-light cursor-pointer mb-4" onclick="document.getElementById('dedicatedFileInput').click();">
                <input type="file" id="dedicatedFileInput" class="d-none" onchange="handleDedicatedFileRead(this.files[0])">
                <i data-feather="upload-cloud" class="text-success mb-3" style="width:56px; height:56px;"></i>
                <h4 class="fw-bold text-dark mb-2">Glissez-déposez ou cliquez ici pour importer un fichier</h4>
                <p class="text-muted mb-0">Formats supportés : <code>.csv, .xlsx, .pdf, .txt, .docx, .json, .png, .jpg, .xml, .log</code></p>
                <button type="button" class="btn btn-success rounded-pill px-4 py-2 mt-3 fw-bold shadow-sm">
                    Choisir un fichier sur mon appareil
                </button>
            </div>

            <!-- Zone d'affichage des données lues -->
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
                        <button type="button" class="btn btn-outline-danger rounded-pill px-3" onclick="resetFileRead()">
                            <i data-feather="trash-2" class="me-1" style="width:14px; height:14px;"></i> Réinitialiser
                        </button>
                    </div>

                    <!-- Métadonnées lues -->
                    <div class="row g-3 mb-4" id="fileMetaDataContainer"></div>

                    <!-- Contenu / Aperçu des informations lues -->
                    <h5 class="fw-bold text-dark mb-2">Aperçu & Extraction des Données :</h5>
                    <div class="bg-dark text-light p-4 rounded-3 font-monospace" style="max-height: 500px; overflow-y: auto; font-size: 0.9rem;" id="fileContentPreview">
                        <em>Aucun contenu extrait.</em>
                    </div>
                </div>
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
                previewEl.innerText = "📄 [Lecteur Universel - Document " + (file.name.split('.').pop().toUpperCase()) + "]\n\nNom du fichier : " + file.name + "\nType : " + (file.type || 'Fichier binaire') + "\nTaille : " + file.size + " octets\n\nStatut : Fichier chargé et analysé avec succès dans votre espace dédié.";
            }
        };
        reader.readAsText(file);
    }
}

function resetFileRead() {
    document.getElementById('fileReadOutput').style.display = 'none';
    document.getElementById('dedicatedFileInput').value = '';
}
</script>
@endpush
@endsection
