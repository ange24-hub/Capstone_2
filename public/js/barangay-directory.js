(() => {
    const search = document.getElementById('barangay-directory-search');
    if (!search) return;

    const cards = [...document.querySelectorAll('[data-directory-card]')];
    const filters = [...document.querySelectorAll('[data-directory-filter]')];
    const count = document.getElementById('barangay-directory-count');
    const empty = document.getElementById('empty-directory');
    const pagination = document.getElementById('barangay-directory-pagination');
    const numbers = document.getElementById('barangay-directory-page-numbers');
    const pageStatus = document.getElementById('barangay-directory-page-status');
    const previous = pagination.querySelector('[data-directory-previous]');
    const next = pagination.querySelector('[data-directory-next]');
    const pageSize = 4;
    let activeFilter = 'all';
    let currentPage = 1;
    let totalPages = 1;

    const refresh = () => {
        const query = search.value.trim().toLowerCase();
        const matching = cards.filter(card => card.dataset.name.includes(query)
            && (activeFilter === 'all' || card.dataset.status === activeFilter));
        totalPages = Math.max(1, Math.ceil(matching.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        const pageCards = new Set(matching.slice(start, start + pageSize));
        cards.forEach(card => { card.hidden = !pageCards.has(card); });

        const noun = matching.length === 1 ? 'barangay' : 'barangays';
        count.textContent = matching.length
            ? `Showing ${start + 1}–${Math.min(start + pageSize, matching.length)} of ${matching.length} ${noun}`
            : 'Showing 0 barangays';
        empty.hidden = matching.length !== 0;
        pagination.hidden = totalPages <= 1;
        previous.disabled = currentPage === 1;
        next.disabled = currentPage === totalPages;
        pageStatus.textContent = `Page ${currentPage} of ${totalPages}`;

        const fragment = document.createDocumentFragment();
        for (let page = 1; page <= totalPages; page++) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = String(page);
            button.dataset.directoryPage = String(page);
            button.setAttribute('aria-label', `Page ${page}`);
            button.setAttribute('aria-controls', 'municipal-directory-grid');
            if (page === currentPage) button.setAttribute('aria-current', 'page');
            fragment.append(button);
        }
        numbers.replaceChildren(fragment);
    };

    const goToPage = (page, focusNumber = false) => {
        if (page < 1 || page > totalPages || page === currentPage) return;
        currentPage = page;
        refresh();
        if (focusNumber) numbers.querySelector('[aria-current="page"]').focus({preventScroll: true});
        search.closest('.municipal-directory-toolbar').scrollIntoView({
            block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth',
        });
    };

    previous.addEventListener('click', () => goToPage(currentPage - 1));
    next.addEventListener('click', () => goToPage(currentPage + 1));
    numbers.addEventListener('click', event => {
        const button = event.target.closest('[data-directory-page]');
        if (button) goToPage(Number(button.dataset.directoryPage), true);
    });
    search.addEventListener('input', () => { currentPage = 1; refresh(); });
    filters.forEach(button => button.addEventListener('click', () => {
        activeFilter = button.dataset.directoryFilter;
        currentPage = 1;
        filters.forEach(item => {
            item.classList.toggle('is-active', item === button);
            item.setAttribute('aria-pressed', String(item === button));
        });
        refresh();
    }));
    refresh();
})();
