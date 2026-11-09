(() => {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const colors = { actual: '#0d9488', predicted: '#7c3aed' };
    const format = value => new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(value);
    const hasValue = value => typeof value === 'number' && Number.isFinite(value);
    const node = (tag, attributes = {}, text = '') => {
        const element = document.createElementNS(NS, tag);
        for (const [name, value] of Object.entries(attributes)) element.setAttribute(name, value);
        if (text) element.textContent = text;
        return element;
    };

    document.querySelectorAll('[data-forecast-chart]').forEach(figure => {
        const rows = JSON.parse(figure.querySelector('[data-chart-rows]').textContent);
        const plot = figure.querySelector('[data-chart-plot]');
        const output = figure.querySelector('[data-chart-value]');
        const note = output.textContent;
        const title = figure.querySelector('[id]').textContent;

        const render = () => {
            const width = Math.max(220, plot.clientWidth);
            const height = 192;
            const bounds = { left: 44, right: width - 34, top: 14, bottom: height - 34 };
            const values = rows.flatMap(row => [row.actual, row.predicted]).filter(hasValue);
            plot.replaceChildren();
            if (!values.length) {
                const empty = document.createElement('p');
                empty.className = 'statistical-chart-empty';
                empty.textContent = 'No data available';
                plot.append(empty);
                return;
            }

            const maximum = Math.max(...values, 1);
            const rawStep = maximum / 3;
            const magnitude = 10 ** Math.floor(Math.log10(rawStep));
            const step = [1, 2, 2.5, 5, 10].map(n => n * magnitude).find(n => n >= rawStep);
            const top = Math.ceil(maximum / step) * step;
            const x = index => bounds.left + index / Math.max(1, rows.length - 1) * (bounds.right - bounds.left);
            const y = value => bounds.bottom - value / top * (bounds.bottom - bounds.top);
            const svg = node('svg', { viewBox: `0 0 ${width} ${height}`, role: 'group', 'aria-label': title });
            svg.append(node('title', {}, title));
            svg.append(node('desc', {}, 'Solid teal circles: actual. Dashed violet squares: predicted. Missing periods are gaps. Focus or tap a point for values.'));

            for (let tick = 0; tick <= top + step / 100; tick += step) {
                svg.append(node('line', { x1: bounds.left, x2: bounds.right, y1: y(tick), y2: y(tick), class: 'statistical-chart-grid' }));
                svg.append(node('text', { x: bounds.left - 9, y: y(tick) + 4, 'text-anchor': 'end', class: 'statistical-chart-axis' }, format(tick)));
            }

            // Keep labels readable at phone widths; all periods remain available on tap.
            const labelSlots = Math.max(2, Math.floor((bounds.right - bounds.left) / 80));
            const labels = new Set(Array.from({ length: Math.min(rows.length, labelSlots) }, (_, index) =>
                Math.round(index * (rows.length - 1) / (Math.min(rows.length, labelSlots) - 1 || 1))));
            rows.forEach((row, index) => {
                if (labels.has(index)) svg.append(node('text', {
                    x: x(index), y: height - 12,
                    'text-anchor': 'middle',
                    class: 'statistical-chart-axis',
                }, row.label));
            });

            for (const key of ['actual', 'predicted']) {
                let path = '';
                let previous = null;
                rows.forEach((row, index) => {
                    if (!hasValue(row[key])) { previous = null; return; }
                    // Join the first projection to its immediately preceding actual only.
                    if (key === 'predicted' && previous === null && index > 0 && hasValue(rows[index - 1].actual)) {
                        path += `M${x(index - 1)},${y(rows[index - 1].actual)} `;
                        previous = index - 1;
                    }
                    path += `${previous === index - 1 ? 'L' : 'M'}${x(index)},${y(row[key])} `;
                    previous = index;
                });
                svg.append(node('path', {
                    d: path, fill: 'none', stroke: colors[key], 'stroke-width': 2.5,
                    ...(key === 'predicted' ? { 'stroke-dasharray': '6 5' } : {}),
                }));
            }

            rows.forEach((row, index) => {
                for (const key of ['actual', 'predicted']) {
                    if (!hasValue(row[key])) continue;
                    const label = `${row.label} · ${key === 'actual' ? 'Actual' : 'Predicted'} ${format(row[key])}${key === 'predicted' && row.assumption ? ' (assumption)' : ''}`;
                    const point = node('g', { tabindex: 0, role: 'img', 'aria-label': label, class: 'statistical-chart-point' });
                    point.append(node('title', {}, label));
                    point.append(node('circle', { cx: x(index), cy: y(row[key]), r: 14, fill: 'transparent' }));
                    point.append(key === 'actual'
                        ? node('circle', { cx: x(index), cy: y(row[key]), r: 4.5, fill: colors[key], stroke: '#fff', 'stroke-width': 2 })
                        : node('rect', { x: x(index) - 4.5, y: y(row[key]) - 4.5, width: 9, height: 9, rx: 1, fill: '#fff', stroke: colors[key], 'stroke-width': 2 }));
                    const show = () => { output.textContent = label; };
                    point.addEventListener('pointerenter', show);
                    point.addEventListener('focus', show);
                    point.addEventListener('click', show);
                    point.addEventListener('keydown', event => {
                        if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); show(); }
                    });
                    point.addEventListener('pointerleave', () => {
                        if (!point.contains(document.activeElement)) output.textContent = note;
                    });
                    point.addEventListener('blur', () => { output.textContent = note; });
                    svg.append(point);
                }
            });
            plot.append(svg);
        };

        render();
        if ('ResizeObserver' in window) new ResizeObserver(render).observe(plot);
        else window.addEventListener('resize', render);
    });
})();
