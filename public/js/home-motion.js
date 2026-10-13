(() => {
    const home = document.querySelector('.civic-home');
    const header = document.querySelector('.public-site-header');
    if (!home || !header) return;

    const syncHeader = () => {
        document.documentElement.style.setProperty('--home-header-height', `${header.getBoundingClientRect().height}px`);
    };
    syncHeader();
    if ('ResizeObserver' in window) new ResizeObserver(syncHeader).observe(header);
    else window.addEventListener('resize', syncHeader);
    // Honour direct links after the responsive header has been measured.
    if (location.hash === '#about') requestAnimationFrame(() => document.getElementById('about')?.scrollIntoView({ behavior: 'instant' }));

})();
