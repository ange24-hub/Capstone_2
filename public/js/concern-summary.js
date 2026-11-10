(() => {
    const panel = document.querySelector('[data-concern-table]');
    if (!panel) return;
    const rows = [...panel.querySelectorAll('[data-concern-row]')];
    const search = panel.querySelector('[data-concern-search]');
    const controls = panel.querySelector('[data-concern-pagination]');
    const previous = panel.querySelector('[data-concern-previous]');
    const next = panel.querySelector('[data-concern-next]');
    const status = panel.querySelector('[data-concern-table-status]');
    const empty = panel.querySelector('[data-concern-no-match]');
    const pageLabel = panel.querySelector('[data-concern-page]');
    const pageSize = 10;
    let page = 0;
    const render = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const matches = rows.filter(row => row.dataset.name.toLocaleLowerCase().includes(query));
        const pages = Math.max(1, Math.ceil(matches.length / pageSize));
        page = Math.min(page, pages - 1);
        const visible = new Set(matches.slice(page * pageSize, (page + 1) * pageSize));
        rows.forEach(row => row.hidden = !visible.has(row));
        empty.hidden = matches.length !== 0;
        previous.disabled = page === 0;
        next.disabled = page >= pages - 1;
        controls.hidden = matches.length <= pageSize;
        pageLabel.textContent = `${page + 1} / ${pages}`;
        status.textContent = matches.length
            ? `Showing ${page * pageSize + 1}–${Math.min((page + 1) * pageSize, matches.length)} of ${matches.length} ${matches.length === 1 ? 'barangay' : 'barangays'}`
            : 'No matching barangays';
    };
    const turnPage = direction => {
        page = Math.max(0, page + direction);
        render();
        // Keep the breakdown in view when paging near the bottom of a narrow screen.
        if (panel.getBoundingClientRect().top < 0) {
            panel.scrollIntoView({block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
        }
    };
    search.addEventListener('input', () => { page = 0; render(); });
    previous.addEventListener('click', () => turnPage(-1));
    next.addEventListener('click', () => turnPage(1));
    panel.querySelector('[data-concern-search-wrap]').hidden = false;
    render();
})();
