(() => {
    const fields = document.querySelectorAll('.registry-cell-editor');
    const fit = field => { field.style.height = 'auto'; field.style.height = `${field.scrollHeight}px`; };
    fields.forEach(field => { fit(field); field.addEventListener('input', () => fit(field)); });
    window.addEventListener('resize', () => fields.forEach(fit));
    document.fonts.ready.then(() => fields.forEach(fit));
})();
