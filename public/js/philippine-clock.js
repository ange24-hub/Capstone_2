(() => {
    const options = { timeZone: 'Asia/Manila' };
    const weekday = new Intl.DateTimeFormat('en-PH', { ...options, weekday: 'long' });
    const date = new Intl.DateTimeFormat('en-GB', { ...options, day: '2-digit', month: 'short', year: 'numeric' });
    const time = new Intl.DateTimeFormat('en-PH', { ...options, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    document.querySelectorAll('[data-ph-clock]').forEach(clock => {
        const serverTime = Date.parse(clock.dataset.serverTime);
        const started = performance.now();
        const update = () => {
            const now = new Date(serverTime + performance.now() - started);
            clock.querySelector('[data-ph-weekday]').textContent = weekday.format(now);
            clock.querySelector('[data-ph-date]').textContent = date.format(now);
            const output = clock.querySelector('[data-ph-time]');
            output.textContent = time.format(now);
            output.dateTime = now.toISOString();
        };
        update();
        setInterval(update, 1000);
    });
})();
