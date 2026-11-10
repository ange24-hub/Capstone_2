(() => {
    document.querySelectorAll('[data-population-signature]').forEach(field => {
        const canvas = field.querySelector('canvas');
        const context = canvas.getContext('2d');
        const data = field.querySelector('[data-signature-data]');
        const reuse = field.querySelector('[data-signature-reuse]');
        const upload = field.querySelector('[data-signature-upload]');
        const preview = field.querySelector('[data-upload-preview]');
        const uploadStatus = field.querySelector('[data-upload-status]');
        const error = field.querySelector('[data-signature-error]');
        const guide = field.querySelector('.population-signature-guide');
        let mode = field.dataset.initialMode;
        let drawnData = '';
        let drawing = false;
        let previewUrl;
        let revision = 0;
        let ratio = Math.max(window.devicePixelRatio || 1, 1);

        const resizeCanvas = () => {
            if (mode !== 'draw') return;
            const bounds = canvas.getBoundingClientRect();
            if (!bounds.width || !bounds.height) return;
            ratio = Math.max(window.devicePixelRatio || 1, 1);
            const width = Math.round(bounds.width * ratio);
            const height = Math.round(bounds.height * ratio);
            if (canvas.width === width && canvas.height === height) return;
            canvas.width = width;
            canvas.height = height;
            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.strokeStyle = '#173d59';
            context.lineWidth = 2.25;
            context.lineCap = 'round';
            context.lineJoin = 'round';
            if (drawnData) {
                const version = revision;
                const image = new Image();
                image.onload = () => {
                    if (version === revision && !drawing) context.drawImage(image, 0, 0, width / ratio, height / ratio);
                };
                image.src = drawnData;
            }
        };
        const finish = () => {
            if (!drawing) return;
            drawing = false;
            drawnData = canvas.toDataURL('image/png');
            data.value = mode === 'draw' ? drawnData : '';
        };
        const selectMode = selected => {
            finish();
            mode = selected;
            reuse.value = mode === 'saved' ? '1' : '0';
            upload.disabled = mode !== 'upload';
            upload.required = mode === 'upload';
            data.value = mode === 'draw' ? drawnData : '';
            field.querySelectorAll('[data-signature-mode]').forEach(button => {
                button.setAttribute('aria-pressed', String(button.dataset.signatureMode === mode));
            });
            field.querySelectorAll('[data-signature-panel]').forEach(panel => {
                panel.hidden = panel.dataset.signaturePanel !== mode;
            });
            error.hidden = true;
            resizeCanvas();
        };
        field.querySelectorAll('[data-signature-mode]').forEach(button => {
            button.addEventListener('click', () => selectMode(button.dataset.signatureMode));
        });
        const point = event => {
            const bounds = canvas.getBoundingClientRect();
            return {x: event.clientX - bounds.left, y: event.clientY - bounds.top};
        };
        canvas.addEventListener('pointerdown', event => {
            if (mode !== 'draw' || (event.pointerType === 'mouse' && event.button !== 0)) return;
            event.preventDefault();
            revision++;
            drawing = true;
            guide.hidden = true;
            error.hidden = true;
            canvas.setPointerCapture(event.pointerId);
            const p = point(event);
            context.beginPath();
            context.moveTo(p.x, p.y);
            context.lineTo(p.x + .01, p.y + .01);
            context.stroke();
        });
        canvas.addEventListener('pointermove', event => {
            if (!drawing) return;
            event.preventDefault();
            const p = point(event);
            context.lineTo(p.x, p.y);
            context.stroke();
        });
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        canvas.addEventListener('lostpointercapture', finish);
        field.querySelector('[data-clear-signature]').addEventListener('click', () => {
            revision++;
            drawing = false;
            drawnData = '';
            data.value = '';
            context.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
            guide.hidden = false;
            error.hidden = true;
        });
        upload.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
            preview.removeAttribute('src');
            preview.hidden = true;
            upload.setCustomValidity('');
            const file = upload.files[0];
            uploadStatus.textContent = file ? file.name : 'No image selected.';
            if (!file) return;
            if (!['image/png', 'image/jpeg'].includes(file.type) || file.size > 2097152) {
                upload.setCustomValidity('Choose a PNG or JPG image up to 2 MB.');
                uploadStatus.textContent = 'Choose a PNG or JPG image up to 2 MB.';
                return;
            }
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
            error.hidden = true;
        });
        field.closest('form').addEventListener('submit', event => {
            finish();
            if (mode === 'draw' && !drawnData) {
                event.preventDefault();
                error.hidden = false;
                error.textContent = 'Draw a signature, upload an image, or choose your saved signature.';
            }
        });
        if (window.ResizeObserver) new ResizeObserver(resizeCanvas).observe(canvas);
        else window.addEventListener('resize', resizeCanvas);
        selectMode(mode);
    });
})();
