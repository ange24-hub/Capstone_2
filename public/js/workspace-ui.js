/* Enhance existing controls; form actions, values and validation remain authoritative. */
(() => {
    const scope = document.querySelector('#main-content');
    if (!scope) return;
    let fieldId = 0;
    const uniqueId = prefix => {
        let id;
        do { id = `rbim-${prefix}-${++fieldId}`; } while (document.getElementById(id));
        return id;
    };
    const tableObservers = new WeakSet();
    const resizeObserver = typeof ResizeObserver === 'function' ? new ResizeObserver(entries => {
        entries.forEach(entry => entry.target.dispatchEvent(new Event('portal:resize')));
    }) : null;
    const enhance = root => {
        root.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(field => {
            if (field.labels?.length || field.hasAttribute('aria-label') || field.hasAttribute('aria-labelledby')) return;
            const label = field.previousElementSibling;
            if (label?.tagName !== 'LABEL') return;
            if (!field.id) {
                field.id = uniqueId('field');
            }
            label.htmlFor = field.id;
        });
        root.querySelectorAll('.table-wrap,.clean-table-wrap,.payment-history-table-wrap').forEach(wrapper => {
            if (wrapper.closest('.rbi-document-preview,.rbi-word-page,.rbi-source-table')) return;
            if (!wrapper.hasAttribute('role')) wrapper.setAttribute('role', 'region');
            if (!wrapper.hasAttribute('aria-label') && !wrapper.hasAttribute('aria-labelledby')) {
                wrapper.setAttribute('aria-label', 'Scrollable table');
            }
            wrapper.querySelectorAll('thead th').forEach(cell => {
                if (!cell.hasAttribute('scope')) cell.scope = cell.colSpan > 1 ? 'colgroup' : 'col';
            });
            if (tableObservers.has(wrapper)) return;
            tableObservers.add(wrapper);
            const hint = document.createElement('p');
            hint.className = 'portal-table-hint';
            hint.id = uniqueId('table-help');
            hint.textContent = 'Scroll horizontally to view all columns. Use the arrow keys when the table is focused.';
            hint.hidden = true;
            wrapper.before(hint);
            const syncOverflow = () => {
                const overflowing = wrapper.scrollWidth > wrapper.clientWidth + 1;
                hint.hidden = !overflowing;
                if (overflowing) {
                    wrapper.tabIndex = 0;
                    const ids = new Set((wrapper.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                    ids.add(hint.id);
                    wrapper.setAttribute('aria-describedby', [...ids].join(' '));
                } else {
                    wrapper.removeAttribute('tabindex');
                    const ids = (wrapper.getAttribute('aria-describedby') || '').split(/\s+/).filter(id => id && id !== hint.id);
                    if (ids.length) wrapper.setAttribute('aria-describedby', ids.join(' '));
                    else wrapper.removeAttribute('aria-describedby');
                }
            };
            wrapper.addEventListener('portal:resize', syncOverflow);
            resizeObserver?.observe(wrapper);
            const table = wrapper.querySelector('table');
            if (table) resizeObserver?.observe(table);
            table?.addEventListener('portal:resize', syncOverflow);
            syncOverflow();
        });
        root.querySelectorAll('input[required],select[required],textarea[required]').forEach(field => {
            if (field.closest('.rbi-document-preview,.rbi-word-page,.leaflet-container')) return;
            const label = field.labels?.[0];
            if (!label || label.querySelector('.required-mark,.civic-required') || label.textContent.includes('*')) return;
            const mark = document.createElement('span');
            mark.className = 'civic-required';
            mark.textContent = '*';
            mark.setAttribute('aria-hidden', 'true');
            label.appendChild(mark);
        });
    };
    enhance(scope);
    scope.querySelectorAll('.field-group,.profile-field').forEach(group => {
        const field = group.querySelector('input,select,textarea');
        const help = group.querySelector('.field-help,small');
        if (!field || !help) return;
        if (!help.id) help.id = uniqueId('help');
        const ids = new Set((field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        ids.add(help.id);
        field.setAttribute('aria-describedby', [...ids].join(' '));
    });
    scope.querySelectorAll('[data-password-toggle]').forEach(button => {
        const field = document.getElementById(button.getAttribute('aria-controls'));
        if (!field) return;
        const label = button.dataset.passwordLabel || 'password';
        const text = button.querySelector('[data-password-text]');
        const showIcon = button.querySelector('[data-password-show-icon]');
        const hideIcon = button.querySelector('[data-password-hide-icon]');
        button.hidden = false;
        button.addEventListener('click', () => {
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            if (text) text.textContent = show ? 'Hide' : 'Show';
            if (showIcon) showIcon.hidden = show;
            if (hideIcon) hideIcon.hidden = !show;
            button.setAttribute('aria-label', `${show ? 'Hide' : 'Show'} ${label}`);
            button.setAttribute('aria-pressed', String(show));
        });
    });
    scope.querySelectorAll('.success').forEach(box => {
        if (!box.hasAttribute('role')) box.setAttribute('role', 'status');
    });
    scope.querySelectorAll('.errors').forEach(box => {
        if (!box.hasAttribute('role')) box.setAttribute('role', 'alert');
    });
    // RBI encoding creates member cards dynamically; apply the same label treatment.
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE && node.querySelector('input,select,textarea,table')) enhance(node);
        }));
    }).observe(scope, {childList: true, subtree: true});
})();
