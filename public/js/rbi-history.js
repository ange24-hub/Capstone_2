(() => {
    const workspace = document.querySelector('.rbi-history-workspace');
    if (!workspace) return;
    const navigation = workspace.querySelector('[data-rbi-history-navigation]');
    const links = [...(navigation?.querySelectorAll('a[href^="#"]') || [])];
    const panelFor = link => document.getElementById(link.hash.slice(1))?.closest('section');
    const panels = links.map(panelFor).filter(Boolean);
    const activate = (link, focus = false) => {
        const panel = panelFor(link);
        if (!panel) return;
        panels.forEach(item => item.hidden = item !== panel);
        const tools = workspace.querySelector('.rbi-history-tools');
        if (tools) tools.hidden = panel.id !== 'report-history';
        links.forEach(item => {
            if (item === link) item.setAttribute('aria-current','page');
            else item.removeAttribute('aria-current');
        });
        if (focus) {
            const heading = panel.querySelector('h2,h3');
            if (heading) { heading.tabIndex = -1; heading.focus({preventScroll:true}); }
        }
    };
    links.forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        activate(link, true);
        history.replaceState(null,'',link.hash);
    }));
    const fromHash = () => activate(links.find(link => link.hash === location.hash) || links[0]);
    if (links.length) {
        fromHash();
        addEventListener('hashchange', fromHash);
    }
    const menus = [...workspace.querySelectorAll('.rbi-history-more,.rbi-history-page-tools')];
    menus.forEach(menu => menu.addEventListener('toggle', () => {
        if (menu.open) menus.forEach(other => { if (other !== menu) other.open = false; });
    }));
    document.addEventListener('click', event => menus.forEach(menu => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    }));
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        const menu = menus.find(item => item.open);
        if (menu) { menu.open = false; menu.querySelector('summary').focus(); }
    });
})();
