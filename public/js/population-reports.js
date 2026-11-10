(() => {
    const form = document.querySelector('[data-population-report-controls]');
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    const label = form.querySelector('[data-report-generate-label]');
    const status = form.querySelector('[data-report-control-status]');
    const reset = () => {
        form.removeAttribute('aria-busy');
        button.disabled = false;
        label.textContent = 'Generate report';
        status.textContent = 'Through 1st Semester 2026: saved reports. Newer periods: latest Consolidated data.';
    };
    form.addEventListener('submit', event => {
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        label.textContent = 'Generating…';
        const year = Number(form.elements.report_year.value);
        const semester = Number(form.elements.semester.value);
        status.textContent = year < 2026 || (year === 2026 && semester === 1)
            ? 'Opening the saved report for this period, or the nearest earlier copy…'
            : 'Preparing your document from the latest Consolidated registry…';
    });
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => {
            if (form.getAttribute('aria-busy') !== 'true' && form.reportValidity()) form.requestSubmit();
        });
    });
    window.addEventListener('pageshow', reset);
})();
