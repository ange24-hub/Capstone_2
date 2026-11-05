(() => {
    const normalize = value => value.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase().trim();

    document.querySelectorAll('[data-resident-picker]').forEach(picker => {
        const select = picker.querySelector('select');
        const residents = Array.from(select.options).filter(option => option.value).map(option => ({
            id: option.value,
            label: option.textContent.trim(),
            search: normalize(option.textContent),
        }));
        const input = document.createElement('input');
        input.id = `${select.id}-search`;
        input.type = 'text';
        input.placeholder = 'Type a resident name or household number';
        input.autocomplete = 'off';
        input.required = select.required;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        const results = document.createElement('div');
        results.id = `${select.id}-results`;
        results.className = 'resident-picker-results';
        results.setAttribute('role', 'listbox');
        results.setAttribute('aria-label', 'Matching active residents');
        results.hidden = true;
        input.setAttribute('aria-controls', results.id);

        const status = document.createElement('small');
        status.id = `${select.id}-status`;
        status.className = 'resident-picker-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        status.textContent = 'Search by name or household number, then select a resident.';
        input.setAttribute('aria-describedby', status.id);
        picker.append(input, results, status);
        picker.querySelector('label').htmlFor = input.id;
        select.hidden = true;
        select.required = false;

        let matches = [];
        let activeIndex = -1;
        const syncSelection = () => {
            input.value = residents.find(resident => resident.id === select.value)?.label || '';
            input.setCustomValidity('');
        };
        const close = () => {
            results.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
        };
        const highlight = index => {
            activeIndex = index;
            Array.from(results.children).forEach((option, position) => {
                option.setAttribute('aria-selected', String(position === index));
            });
            const option = results.children[index];
            if (option) {
                input.setAttribute('aria-activedescendant', option.id);
                option.scrollIntoView({ block: 'nearest' });
            }
        };
        const choose = resident => {
            select.value = resident.id;
            syncSelection();
            close();
            status.textContent = `Selected: ${resident.label}`;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const search = () => {
            const terms = normalize(input.value).split(/\s+/u).filter(Boolean);
            const allMatches = residents.filter(resident => terms.every(term => resident.search.includes(term)));
            matches = allMatches.slice(0, 50);
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            results.replaceChildren();
            matches.forEach((resident, index) => {
                const option = document.createElement('div');
                option.id = `${results.id}-${index}`;
                option.className = 'resident-picker-option';
                option.setAttribute('role', 'option');
                option.setAttribute('aria-selected', 'false');
                option.textContent = resident.label;
                option.addEventListener('mousedown', event => event.preventDefault());
                option.addEventListener('click', () => choose(resident));
                results.append(option);
            });
            results.hidden = matches.length === 0;
            input.setAttribute('aria-expanded', String(matches.length > 0));
            status.textContent = allMatches.length === 0
                ? 'No active residents match. Try another name or household number.'
                : allMatches.length > matches.length
                    ? `${allMatches.length} residents found. Showing the first 50; type more to narrow the results.`
                    : `${allMatches.length} resident${allMatches.length === 1 ? '' : 's'} found. Select a resident.`;
        };

        syncSelection();
        input.addEventListener('focus', search);
        input.addEventListener('click', () => { if (results.hidden) search(); });
        input.addEventListener('input', () => {
            select.value = '';
            input.setCustomValidity(input.value ? 'Select a resident from the matching results.' : '');
            search();
        });
        input.addEventListener('keydown', event => {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (results.hidden) search();
                if (matches.length) highlight(event.key === 'ArrowDown'
                    ? (activeIndex + 1) % matches.length
                    : (activeIndex < 0 ? matches.length - 1 : (activeIndex + matches.length - 1) % matches.length));
            } else if (event.key === 'Enter' && !results.hidden) {
                event.preventDefault();
                if (activeIndex >= 0) choose(matches[activeIndex]);
                else if (matches.length === 1) choose(matches[0]);
            } else if (event.key === 'Escape') {
                close();
            }
        });
        input.addEventListener('blur', close);
        select.form?.addEventListener('reset', () => {
            setTimeout(() => { syncSelection(); close(); }, 0);
        });
    });
})();
