export function arrivalDateBounds(month, birth, today) {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month)) return {min: birth || '', max: today};
    const [year, number] = month.split('-').map(Number);
    const end = new Date(0);
    end.setUTCFullYear(year, number, 0);
    const first = `${month}-01`, last = end.toISOString().slice(0, 10);
    return {min: birth && birth > first ? birth : first, max: last < today ? last : today};
}

if (typeof document !== 'undefined') {
    const form = document.getElementById('rbi-monthly-form');
    if (form) {
        function sync(card) {
            const type = card.querySelector('[data-entry-type]');
            if (!type) return;
            const incoming = type.value === 'in_migration';
            card.querySelectorAll('[data-arrival-fields]').forEach(box => { box.hidden = !incoming; });
            const originUnknown = card.querySelector('[data-entry-origin-unknown]');
            originUnknown.disabled = !incoming;
            const origin = card.querySelector('[data-entry-origin]');
            origin.disabled = !incoming || originUnknown.checked;
            origin.required = incoming && !originUnknown.checked;
            const month = card.querySelector('[data-entry-month]');
            month.disabled = !incoming;
            month.required = incoming;
            const bounds = arrivalDateBounds(form.querySelector('[name="reporting_month"]').value,
                card.querySelector('input[name$="[birth_date]"]')?.value || '', form.dataset.entryToday);
            const arrival = card.querySelector('[data-entry-arrival]');
            arrival.disabled = !incoming;
            arrival.required = false;
            arrival.min = bounds.min;
            arrival.max = bounds.max;
        }
        const syncAll = () => form.querySelectorAll('[data-member-card]').forEach(sync);
        form.addEventListener('change', syncAll);
        new MutationObserver(syncAll).observe(form, {childList: true, subtree: true});
        syncAll();
    }
}
