/* Enhance existing controls; form actions, values and validation remain authoritative. */
(() => {
    const scope = document.querySelector('#main-content');
    if (!scope) return;
    let fieldId = 0;
    const enhance = root => {
        root.querySelectorAll('input:not([type=hidden]),select,textarea').forEach(field => {
            if (field.labels?.length || field.hasAttribute('aria-label') || field.hasAttribute('aria-labelledby')) return;
            const label = field.previousElementSibling;
            if (label?.tagName !== 'LABEL') return;
            if (!field.id) {
                do { field.id = `rbim-field-${++fieldId}`; } while (document.getElementById(field.id) !== field);
            }
            label.htmlFor = field.id;
        });
        root.querySelectorAll('.table-wrap,.clean-table-wrap,.payment-history-table-wrap').forEach(wrapper => {
            if (wrapper.closest('.rbi-document-preview,.rbi-word-page,.rbi-source-table')) return;
            wrapper.tabIndex = 0;
            if (!wrapper.hasAttribute('role')) wrapper.setAttribute('role', 'region');
            if (!wrapper.hasAttribute('aria-label') && !wrapper.hasAttribute('aria-labelledby')) {
                wrapper.setAttribute('aria-label', 'Scrollable table');
            }
            wrapper.querySelectorAll('thead th').forEach(cell => {
                if (!cell.hasAttribute('scope')) cell.scope = cell.colSpan > 1 ? 'colgroup' : 'col';
            });
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
