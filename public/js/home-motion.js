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
    const anchor = ['#about', '#resident-services'].includes(location.hash) ? document.querySelector(location.hash) : null;
    if (anchor) requestAnimationFrame(() => anchor.scrollIntoView({ behavior: 'instant' }));

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const toggle = home.querySelector('[data-home-motion-toggle]');
    const hero = home.querySelector('.landing-hero');
    let userPaused = false;
    let heroVisible = true;
    let revealObserver;
    let heroObserver;
    const revealTargets = [...home.querySelectorAll('.landing-copy > *, .landing-community, .landing-section-heading, .landing-service-card, .landing-about-intro, .landing-steps li, .landing-bottom')];
    const showAll = () => {
        revealObserver?.disconnect();
        revealTargets.forEach(element => element.dataset.homeReveal = 'visible');
    };
    const updateMotion = () => {
        const enabled = !reducedMotion.matches;
        home.classList.toggle('home-motion-enabled', enabled);
        home.dataset.motionState = !enabled || userPaused || !heroVisible || document.hidden ? 'paused' : 'running';
        home.dataset.motionPaused = String(!enabled || userPaused);
        if (toggle) {
            toggle.hidden = !enabled;
            toggle.setAttribute('aria-pressed', String(userPaused));
            toggle.querySelector('[data-motion-label]').textContent = userPaused ? 'Resume motion' : 'Pause motion';
            toggle.querySelector('[data-motion-symbol]').textContent = userPaused ? '▷' : 'Ⅱ';
        }
    };
    const startReveals = () => {
        revealObserver?.disconnect();
        if (reducedMotion.matches || userPaused || !('IntersectionObserver' in window)) {
            showAll();
            return;
        }
        revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                entry.target.dataset.homeReveal = 'visible';
                revealObserver.unobserve(entry.target);
            });
        }, { threshold: .08, rootMargin: '0px 0px -24px 0px' });
        revealTargets.forEach((element, index) => {
            if (element.dataset.homeReveal === 'visible') return;
            element.style.setProperty('--home-reveal-delay', `${index % 4 * 65}ms`);
            element.dataset.homeReveal = 'pending';
            // Focused links must remain visible, including keyboard navigation to offscreen sections.
            if (element.contains(document.activeElement)) element.dataset.homeReveal = 'visible';
            else revealObserver.observe(element);
        });
    };
    if ('IntersectionObserver' in window && hero) {
        heroObserver = new IntersectionObserver(entries => {
            heroVisible = entries[0].isIntersecting;
            updateMotion();
        });
        heroObserver.observe(hero);
    }
    home.addEventListener('focusin', event => {
        const target = event.target.closest('[data-home-reveal]');
        if (target) { target.dataset.homeReveal = 'visible'; revealObserver?.unobserve(target); }
    });
    toggle?.addEventListener('click', () => {
        userPaused = !userPaused;
        if (userPaused) showAll();
        updateMotion();
    });
    document.addEventListener('visibilitychange', updateMotion);
    const onPreferenceChange = () => { updateMotion(); startReveals(); };
    if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', onPreferenceChange);
    else reducedMotion.addListener(onPreferenceChange);
    updateMotion();
    startReveals();
})();
