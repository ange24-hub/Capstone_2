(() => {
    // Match the user's device clock; do not force a different calendar day in Manila.
    const options = {};
    const weekday = new Intl.DateTimeFormat('en-PH', { ...options, weekday: 'long' });
    const date = new Intl.DateTimeFormat('en-GB', { ...options, day: '2-digit', month: 'short', year: 'numeric' });
    const time = new Intl.DateTimeFormat('en-PH', { ...options, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    const headerDate = new Intl.DateTimeFormat('en-US', { ...options, month: 'long', day: '2-digit', year: 'numeric' });
    const update = () => {
        // Read the current device time on every tick, including after sleep or tab restore.
        const now = new Date();
        document.querySelectorAll('[data-ph-clock]').forEach(clock => {
            clock.querySelector('[data-ph-weekday]').textContent = weekday.format(now);
            clock.querySelector('[data-ph-date]').textContent = date.format(now);
            clock.querySelector('[data-local-timezone]').textContent = `Device local time (${Intl.DateTimeFormat().resolvedOptions().timeZone})`;
            const output = clock.querySelector('[data-ph-time]');
            output.textContent = time.format(now);
            output.dateTime = now.toISOString();
        });
        document.querySelectorAll('[data-ph-header-date]').forEach(output => {
            output.textContent = `${headerDate.format(now)} · ${time.format(now)}`;
            output.title = `Device local time (${Intl.DateTimeFormat().resolvedOptions().timeZone})`;
            output.dateTime = now.toISOString();
        });
    };
    update();
    setInterval(update, 1000);
    document.addEventListener('visibilitychange', update);
    window.addEventListener('pageshow', update);
})();
