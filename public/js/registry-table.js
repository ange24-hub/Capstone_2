(() => {
    const fields = document.querySelectorAll('.registry-cell-editor');
    const fit = field => { field.style.height = 'auto'; field.style.height = `${field.scrollHeight}px`; };
    fields.forEach(field => { fit(field); field.addEventListener('input', () => fit(field)); });
    window.addEventListener('resize', () => fields.forEach(fit));
    document.fonts.ready.then(() => fields.forEach(fit));

    document.querySelectorAll('.registry-scroll-region[aria-describedby]').forEach(region => {
        const hint = document.getElementById(region.getAttribute('aria-describedby'));
        const table = region.querySelector('table');
        if (!hint || !table) return;

        const observer = new ResizeObserver(() => {
            hint.hidden = region.scrollWidth <= region.clientWidth + 1;
        });
        observer.observe(region);
        observer.observe(table);
    });
})();
