@extends('layouts.app')

@section('title', 'Lecteur de document | Sitiame Capital')
@section('page_title', 'Lecteur de document')

@push('styles')
    <style>
        .pdf-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .pdf-toolbar-group {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .pdf-viewer-shell {
            min-height: 82vh;
            background: #eef2f6;
        }

        .pdf-viewer-loading,
        .pdf-viewer-error {
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .pdf-viewer-pages {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.25rem;
            align-items: center;
        }

        .pdf-page-card {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.5rem rgba(34, 46, 60, 0.12);
            padding: 0.75rem;
            width: fit-content;
            max-width: 100%;
        }

        .pdf-page-label {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .pdf-page-canvas {
            display: block;
            max-width: 100%;
            height: auto;
        }
    </style>
@endpush

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                    <div>
                        <h5 class="card-title mb-1">{{ $documentName }}</h5>
                        <div class="text-muted small">
                            {{ $documentTypeLabel }} · {{ $fileExtension }} · {{ $mimeType }} · {{ $fileSizeLabel }}
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="{{ $backUrl }}" class="btn btn-outline-secondary">Retour</a>
                        @if($previewType === 'unsupported')
                            <a href="{{ $previewUrl }}" target="_blank" class="btn btn-outline-primary">Ouvrir la source</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    @if($previewType === 'pdf')
                        <div class="pdf-toolbar">
                            <div class="pdf-toolbar-group">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdfZoomOut">- Zoom</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdfZoomIn">+ Zoom</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="pdfReload">Recharger</button>
                            </div>
                            <div class="pdf-toolbar-group small text-muted">
                                <span id="pdfStatus">Préparation du document…</span>
                            </div>
                        </div>
                        <div class="pdf-viewer-shell">
                            <div id="pdfLoading" class="pdf-viewer-loading">
                                Chargement du document PDF…
                            </div>
                            <div id="pdfError" class="pdf-viewer-error d-none">
                                <div class="alert alert-danger mb-0">
                                    Impossible d’afficher ce PDF dans le lecteur intégré.
                                </div>
                            </div>
                            <div id="pdfPages" class="pdf-viewer-pages d-none"></div>
                        </div>
                    @elseif($previewType === 'image')
                        <div class="text-center bg-light p-4">
                            <img
                                src="{{ $previewUrl }}"
                                alt="{{ $documentName }}"
                                style="max-width: 100%; max-height: 82vh; object-fit: contain;"
                            >
                        </div>
                    @elseif($previewType === 'text')
                        <pre class="mb-0 p-4" style="min-height: 70vh; white-space: pre-wrap;">{{ $textPreview }}</pre>
                    @else
                        <div class="p-4">
                            <div class="alert alert-warning mb-0">
                                <div class="fw-semibold mb-2">Prévisualisation intégrée indisponible pour ce format.</div>
                                <div class="small text-muted">
                                    Les formats PDF, image et texte sont lisibles directement dans l’application.
                                    Ce fichier peut toutefois être ouvert via le bouton <strong>Ouvrir la source</strong>.
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@if($previewType === 'pdf')
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
        <script>
            (() => {
                const pdfBase64 = @json($pdfDataBase64);
                const pagesContainer = document.getElementById('pdfPages');
                const loadingEl = document.getElementById('pdfLoading');
                const errorEl = document.getElementById('pdfError');
                const statusEl = document.getElementById('pdfStatus');
                const zoomInButton = document.getElementById('pdfZoomIn');
                const zoomOutButton = document.getElementById('pdfZoomOut');
                const reloadButton = document.getElementById('pdfReload');

                if (!window.pdfjsLib) {
                    loadingEl.classList.add('d-none');
                    errorEl.classList.remove('d-none');
                    errorEl.querySelector('.alert').textContent = 'PDF.js n’a pas pu être chargé.';
                    return;
                }

                if (!pdfBase64) {
                    loadingEl.classList.add('d-none');
                    errorEl.classList.remove('d-none');
                    errorEl.querySelector('.alert').textContent = 'Le contenu PDF n’a pas pu être chargé depuis le serveur.';
                    return;
                }

                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                let currentScale = 1.2;

                function decodeBase64ToUint8Array(base64) {
                    const binary = window.atob(base64);
                    const length = binary.length;
                    const bytes = new Uint8Array(length);

                    for (let index = 0; index < length; index++) {
                        bytes[index] = binary.charCodeAt(index);
                    }

                    return bytes;
                }

                function setStatus(message) {
                    statusEl.textContent = message;
                }

                function showError(message) {
                    loadingEl.classList.add('d-none');
                    pagesContainer.classList.add('d-none');
                    errorEl.classList.remove('d-none');
                    errorEl.querySelector('.alert').textContent = message;
                    setStatus('Erreur de lecture PDF');
                }

                async function renderPdf() {
                    loadingEl.classList.remove('d-none');
                    errorEl.classList.add('d-none');
                    pagesContainer.classList.add('d-none');
                    pagesContainer.innerHTML = '';
                    setStatus('Chargement du PDF…');

                    try {
                        const pdf = await pdfjsLib.getDocument({
                            data: decodeBase64ToUint8Array(pdfBase64),
                        }).promise;

                        setStatus(`Rendu de ${pdf.numPages} page(s)…`);

                        for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                            const page = await pdf.getPage(pageNumber);
                            const viewport = page.getViewport({ scale: currentScale });
                            const canvas = document.createElement('canvas');
                            const context = canvas.getContext('2d');

                            canvas.className = 'pdf-page-canvas';
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;

                            const pageWrapper = document.createElement('div');
                            pageWrapper.className = 'pdf-page-card';

                            const label = document.createElement('div');
                            label.className = 'pdf-page-label';
                            label.textContent = `Page ${pageNumber} / ${pdf.numPages}`;

                            pageWrapper.appendChild(label);
                            pageWrapper.appendChild(canvas);
                            pagesContainer.appendChild(pageWrapper);

                            await page.render({
                                canvasContext: context,
                                viewport,
                            }).promise;
                        }

                        loadingEl.classList.add('d-none');
                        pagesContainer.classList.remove('d-none');
                        setStatus(`PDF chargé · ${pdf.numPages} page(s) · zoom ${Math.round(currentScale * 100)}%`);
                    } catch (error) {
                        console.error(error);
                        showError('Impossible d’afficher ce PDF dans le lecteur intégré. ' + (error?.message ?? 'Erreur inconnue.'));
                    }
                }

                zoomInButton.addEventListener('click', () => {
                    currentScale = Math.min(2.4, currentScale + 0.2);
                    renderPdf();
                });

                zoomOutButton.addEventListener('click', () => {
                    currentScale = Math.max(0.6, currentScale - 0.2);
                    renderPdf();
                });

                reloadButton.addEventListener('click', () => {
                    renderPdf();
                });

                renderPdf();
            })();
        </script>
    @endpush
@endif
