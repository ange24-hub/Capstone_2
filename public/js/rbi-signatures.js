(() => {
    document.querySelectorAll('[data-signature-field]').forEach(field => {
        const canvas = field.querySelector('canvas');
        const input = field.querySelector('input[type="hidden"]');
        const upload = field.querySelector('[data-signature-upload]');
        const preview = field.querySelector('[data-upload-preview]');
        const context = canvas.getContext('2d');
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const bounds = canvas.getBoundingClientRect();
        canvas.width = Math.max(Math.round(bounds.width * ratio), 1);
        canvas.height = Math.max(Math.round(bounds.height * ratio), 1);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.strokeStyle = '#073353';
        context.lineWidth = 2.25;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        let drawing = false;
        let previewUrl;
        const clearDrawing = () => {
            context.clearRect(0, 0, canvas.width / ratio, canvas.height / ratio);
            input.value = '';
            drawing = false;
        };
        const clearUpload = () => {
            upload.value = '';
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
            preview.removeAttribute('src');
            preview.hidden = true;
        };
        const point = event => {
            const rect = canvas.getBoundingClientRect();
            return { x: (event.clientX - rect.left) * (canvas.width / ratio / rect.width), y: (event.clientY - rect.top) * (canvas.height / ratio / rect.height) };
        };
        canvas.addEventListener('pointerdown', event => {
            event.preventDefault();
            clearUpload();
            drawing = true;
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
        const finish = () => {
            if (!drawing) return;
            drawing = false;
            input.value = canvas.toDataURL('image/png');
        };
        canvas.addEventListener('pointerup', finish);
        canvas.addEventListener('pointercancel', finish);
        field.querySelector('[data-clear-signature]').addEventListener('click', () => {
            clearDrawing();
            clearUpload();
        });
        upload.addEventListener('change', () => {
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = undefined;
            preview.hidden = true;
            preview.removeAttribute('src');
            const file = upload.files[0];
            if (!file) return;
            clearDrawing();
            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            preview.hidden = false;
        });
        if (input.value) {
            const restoredData = input.value;
            const saved = new Image();
            saved.onload = () => {
                if (input.value === restoredData && !drawing) context.drawImage(saved, 0, 0, canvas.width / ratio, canvas.height / ratio);
            };
            saved.src = restoredData;
        }
        field.closest('form').addEventListener('submit', finish);
    });
})();
