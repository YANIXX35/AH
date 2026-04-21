/**
 * Prise de photo via webcam sur navigateur web (getUserMedia).
 * Déclencheurs : boutons .js-webcam-open avec data-webcam-for="idInput" et data-webcam-facing="user|environment".
 */
(function () {
    'use strict';

    var root = document.getElementById('webcamCaptureRoot');
    if (!root) {
        return;
    }

    var video = root.querySelector('.js-webcam-video');
    var canvas = root.querySelector('.js-webcam-canvas');
    var btnShoot = root.querySelector('.js-webcam-shoot');
    var btnCancel = root.querySelector('.js-webcam-cancel');
    var stream = null;
    var targetInput = null;

    function stopStream() {
        if (stream) {
            stream.getTracks().forEach(function (t) {
                t.stop();
            });
            stream = null;
        }
        if (video) {
            video.srcObject = null;
        }
    }

    function closeModal() {
        stopStream();
        root.setAttribute('hidden', 'hidden');
        root.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        targetInput = null;
    }

    function openModal() {
        root.removeAttribute('hidden');
        root.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function getConstraints(facing) {
        if (facing === 'user') {
            return { video: { facingMode: 'user' }, audio: false };
        }
        return { video: { facingMode: { ideal: 'environment' } }, audio: false };
    }

    async function startCamera(facing) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            window.alert('La webcam n’est pas disponible dans ce navigateur ou le site n’est pas servi en HTTPS (sauf localhost).');
            return false;
        }
        stopStream();
        try {
            stream = await navigator.mediaDevices.getUserMedia(getConstraints(facing));
        } catch (e1) {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            } catch (e2) {
                window.alert('Impossible d’accéder à la caméra : ' + (e2.message || 'autorisation refusée ou aucune caméra.'));
                return false;
            }
        }
        video.srcObject = stream;
        return true;
    }

    document.querySelectorAll('.js-webcam-open').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            var inputId = btn.getAttribute('data-webcam-for');
            if (!inputId) {
                return;
            }
            var input = document.getElementById(inputId);
            if (!input || input.type !== 'file') {
                return;
            }
            targetInput = input;
            var facing = btn.getAttribute('data-webcam-facing') || 'environment';
            openModal();
            var ok = await startCamera(facing);
            if (!ok) {
                closeModal();
            }
        });
    });

    if (btnShoot) {
        btnShoot.addEventListener('click', function () {
            if (!targetInput || !video || !canvas) {
                closeModal();
                return;
            }
            var w = video.videoWidth;
            var h = video.videoHeight;
            if (!w || !h) {
                window.alert('La vidéo n’est pas prête. Réessayez.');
                return;
            }
            canvas.width = w;
            canvas.height = h;
            var ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, w, h);
            canvas.toBlob(
                function (blob) {
                    if (!blob) {
                        window.alert('Échec de la capture.');
                        closeModal();
                        return;
                    }
                    var name = 'photo-web-' + Date.now() + '.jpg';
                    var file = new File([blob], name, { type: 'image/jpeg' });
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    targetInput.files = dt.files;
                    targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                    closeModal();
                },
                'image/jpeg',
                0.88
            );
        });
    }

    if (btnCancel) {
        btnCancel.addEventListener('click', closeModal);
    }

    root.addEventListener('click', function (e) {
        if (e.target === root) {
            closeModal();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !root.hasAttribute('hidden')) {
            closeModal();
        }
    });
})();
