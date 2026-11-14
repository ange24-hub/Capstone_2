(() => {
    const form = document.querySelector('[data-population-report-controls]');
    if (!form) return;
    const button = form.querySelector('button[type="submit"]');
    const label = form.querySelector('[data-report-generate-label]');
    const status = form.querySelector('[data-report-control-status]');
    const hint = status.textContent;
    const year = form.elements.report_year;
    const semester = form.elements.semester;
    const secondSemester = semester.querySelector('option[value="2"]');
    const updateSemesters = () => {
        secondSemester.disabled = Number(year.value) === Number(form.dataset.currentYear)
            && Number(form.dataset.currentSemester) === 1;
        if (secondSemester.disabled && semester.value === '2') semester.value = '1';
    };
    const reset = () => {
        updateSemesters();
        form.removeAttribute('aria-busy');
        button.disabled = false;
        label.textContent = 'Generate report';
        status.textContent = hint;
    };
    form.addEventListener('submit', event => {
        if (form.getAttribute('aria-busy') === 'true') {
            event.preventDefault();
            return;
        }
        form.setAttribute('aria-busy', 'true');
        button.disabled = true;
        label.textContent = 'Generating…';
        status.textContent = 'Loading the report for the selected period…';
    });
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => {
            updateSemesters();
            if (form.getAttribute('aria-busy') !== 'true' && form.reportValidity()) form.requestSubmit();
        });
    });
    updateSemesters();
    window.addEventListener('pageshow', reset);
})();
