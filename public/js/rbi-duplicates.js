(() => {
    const form = document.getElementById('rbi-monthly-form');
    if (!form) return;
    const notice = document.createElement('div');
    notice.className = 'rbi-duplicate-notice';
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.hidden = true;
    form.prepend(notice);
    const normalize = value => String(value || '').trim().replace(/\s+/gu, ' ').toLowerCase();
    function check() {
        const entries = new Map();
        form.querySelectorAll('[name]').forEach(input => {
            const match = input.name.match(/^(rows|deceased_rows)\[(\d+)\]\[([^\]]+)\]$/);
            if (!match) return;
            const id = `${match[1]}:${match[2]}`;
            if (!entries.has(id)) entries.set(id, { section: match[1], row: {}, element: input.closest('[data-member-card], tr') });
            entries.get(id).row[match[3]] = input.value;
        });
        form.querySelectorAll('.rbi-duplicate-row').forEach(row => row.classList.remove('rbi-duplicate-row'));
        const seen = new Map();
        const warnings = [];
        let residentNumber = 0, deceasedNumber = 0;
        entries.forEach(({ section, row, element }) => {
            const deceased = section === 'deceased_rows';
            const number = deceased ? ++deceasedNumber : ++residentNumber;
            const label = `${deceased ? 'Deceased' : 'Resident'} entry ${number}`;
            const keys = row.inhabitant_id ? [`${section}:id:${row.inhabitant_id}`] : [];
            const name = deceased ? normalize(row.deceased_name)
                : ['last_name', 'first_name', 'middle_name', 'suffix'].map(field => normalize(row[field])).join('|');
            if (name.replace(/\|/g, '').trim()) {
                const date = row[deceased ? 'death_date' : 'birth_date'] || '';
                const family = date ? '' : normalize(`${row.household_number || ''}|${row.household_head || ''}`);
                keys.push(`${section}:person:${name}|${date}|${family}`);
            }
            const previous = keys.map(key => seen.get(key)).find(Boolean);
            if (previous) {
                warnings.push(`${label} matches ${previous.label}. Review or remove the repeated entry before saving.`);
                element?.classList.add('rbi-duplicate-row');
                previous.element?.classList.add('rbi-duplicate-row');
            }
            keys.forEach(key => seen.set(key, { label, element }));
        });
        notice.replaceChildren();
        notice.hidden = warnings.length === 0;
        if (warnings.length) {
            const title = document.createElement('strong');
            title.textContent = 'Duplicate entry warning';
            const list = document.createElement('ul');
            warnings.forEach(message => {
                const item = document.createElement('li');
                item.textContent = message;
                list.append(item);
            });
            notice.append(title, list);
        }
    }
    let timer;
    const schedule = () => { clearTimeout(timer); timer = setTimeout(check, 150); };
    form.addEventListener('input', schedule);
    form.addEventListener('change', schedule);
    // Family/member add and remove controls change the form without an input event.
    form.addEventListener('click', schedule);
    check();
})();
