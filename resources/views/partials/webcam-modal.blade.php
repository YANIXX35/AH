{{-- Modale légère : capture photo navigateur (ordinateur / tablette avec webcam) --}}
<div id="webcamCaptureRoot" class="webcam-capture-root" hidden aria-hidden="true" role="dialog" aria-labelledby="webcamCaptureTitle">
    <div class="webcam-capture-dialog card shadow-lg border-0">
        <div class="card-body p-3">
            <h6 id="webcamCaptureTitle" class="mb-2">Prise de photo — webcam</h6>
            <p class="small text-muted mb-2">Autorisez l’accès à la caméra si le navigateur le demande. Fonctionne sur le web (HTTPS ou localhost).</p>
            <video class="js-webcam-video rounded border bg-dark w-100" playsinline autoplay muted style="max-height: 55vh; object-fit: contain;"></video>
            <canvas class="js-webcam-canvas d-none" width="1" height="1" aria-hidden="true"></canvas>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-primary btn-sm js-webcam-shoot">Capturer la photo</button>
                <button type="button" class="btn btn-outline-secondary btn-sm js-webcam-cancel">Annuler</button>
            </div>
        </div>
    </div>
</div>
<style>
    .webcam-capture-root {
        position: fixed;
        inset: 0;
        z-index: 10550;
        background: rgba(0, 0, 0, 0.55);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .webcam-capture-root[hidden] {
        display: none !important;
    }
    .webcam-capture-dialog {
        max-width: 640px;
        width: 100%;
    }
</style>
