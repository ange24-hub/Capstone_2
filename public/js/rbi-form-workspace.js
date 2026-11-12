(() => {
    const form = document.getElementById('rbi-monthly-form');
    if (!form) return;
    const steps = ['residents', 'deceased', 'certification'];
    const panels = [...form.querySelectorAll('[data-rbi-step-panel]')];
    if (panels.length !== 3) return;
    const links = [...form.querySelectorAll('[data-rbi-step-link]')];
    const previous = form.querySelector('[data-rbi-previous]');
    const next = form.querySelector('[data-rbi-next]');
    const save = form.querySelector('[data-rbi-save]');
    const status = form.querySelector('[data-rbi-step-status]');
    let active = 0;
    const show = (index, focus = false) => {
        active = Math.max(0, Math.min(index, 2));
        panels.forEach(panel => panel.hidden = panel.dataset.rbiStepPanel !== steps[active]);
        links.forEach(link => {
            if (link.dataset.rbiStepLink === steps[active]) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        });
        previous.hidden = active === 0;
        next.hidden = active === 2;
        save.hidden = active !== 2;
        next.textContent = active === 0 ? 'Continue to deceased records' : 'Continue to certification';
        status.textContent = `Step ${active + 1} of ${steps.length}`;
        if (focus) {
            const heading = panels[active].querySelector('h3');
            heading.tabIndex = -1;
            heading.focus({preventScroll:true});
            heading.scrollIntoView({block:'start', behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
        }
    };
    const reveal = input => {
        const panel = input.closest('[data-rbi-step-panel]');
        if (panel) show(steps.indexOf(panel.dataset.rbiStepPanel));
        for (let node = input.parentElement; node && node !== form; node = node.parentElement) {
            if (node.tagName === 'DETAILS') node.open = true;
        }
    };
    next.addEventListener('click', () => {
        const controls = [...form.querySelectorAll('[data-rbi-common-fields] input, [data-rbi-common-fields] select'),
            ...panels[active].querySelectorAll('input,select,textarea')];
        const invalid = controls.find(input => input.willValidate && !input.checkValidity());
        if (invalid) { reveal(invalid); invalid.reportValidity(); return; }
        show(active + 1, true);
    });
    previous.addEventListener('click', () => show(active - 1, true));
    links.forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        show(steps.indexOf(link.dataset.rbiStepLink), true);
    }));
    // Native validation must reveal a hidden step before the browser focuses it.
    let revealing = false;
    form.addEventListener('invalid', event => {
        if (revealing) return;
        revealing = true;
        reveal(event.target);
        queueMicrotask(() => revealing = false);
    }, true);
    show(location.hash === '#deceased-section' ? 1 : location.hash === '#certification-section' ? 2 : 0);
})();
