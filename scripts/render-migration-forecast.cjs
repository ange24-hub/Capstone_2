// Render forecasts without target actuals or accuracy scores.
// Usage: node this-file training-output-directory [sharp-module-path]
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');

(async () => {
    const [directory, sharpModule = 'sharp'] = process.argv.slice(2);
    if (!directory) throw new Error('Provide the saved training output directory.');
    const model = JSON.parse(await fs.readFile(path.join(directory, 'model-result.json'), 'utf8'));
    const input = await fs.readFile(path.join(directory, 'input.csv'));
    if (model.data_source !== 'USER_PROVIDED_AGGREGATE_EXPERIMENT' || model.mode !== 'train_only' || model.method_key !== 'seasonal_naive_12'
        || model.accuracy_ready !== false || model.forecasts.length !== 12 || model.forecasts.some(row => row.actual !== null)
        || crypto.createHash('sha256').update(input).digest('hex') !== model.input_sha256) throw new Error('A matching, unevaluated seasonal-naive training run is required.');
    const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
    const month = value => new Intl.DateTimeFormat('en', {month: 'short', year: 'numeric', timeZone: 'UTC'}).format(new Date(`${value}-01T00:00:00Z`));
    const number = value => Number(value).toLocaleString('en', {maximumFractionDigits: 2});
    const labels = {in_migrant: 'In-migration', out_migrant: 'Out-migration', net_migration: 'Net migration'};
    const year = model.forecasts[0].month.slice(0, 4);
    const training = `${month(model.source_start)}–${month(model.source_end)}`;
    let panels = '';
    for (const [index, key] of Object.keys(labels).entries()) {
        const values = model.forecasts.map(row => row[key]);
        if (values.some(value => !Number.isFinite(value))) throw new Error('A forecast value is invalid.');
        const top = 150 + index * 260;
        const step = Math.max(1, Math.ceil((Math.max(1, ...values) - Math.min(0, ...values)) / 20) * 5);
        const minimum = Math.floor(Math.min(0, ...values) / step) * step;
        const maximum = Math.ceil(Math.max(1, ...values) / step) * step;
        const x = i => 100 + i * 1000 / 11;
        const y = value => top + 205 - (value - minimum) / (maximum - minimum) * 150;
        let grid = '';
        for (let tick = minimum; tick <= maximum; tick += step) grid += `<line x1="100" y1="${y(tick)}" x2="1100" y2="${y(tick)}" stroke="${tick === 0 ? '#9bafbf' : '#e0e7ef'}"/><text x="83" y="${y(tick) + 5}" text-anchor="end">${tick}</text>`;
        panels += `<text x="45" y="${top + 15}" class="panel-title">${labels[key]}</text><text x="1140" y="${top + 15}" text-anchor="end">Predicted annual total: ${number(values.reduce((a, b) => a + b, 0))}</text>${grid}<polyline points="${values.map((value, i) => `${x(i)},${y(value)}`).join(' ')}" fill="none" stroke="#345b82" stroke-width="3" stroke-dasharray="8 5"/>`;
        model.forecasts.forEach((row, i) => {
            panels += `<circle cx="${x(i)}" cy="${y(row[key])}" r="4" fill="#345b82"/><text x="${x(i)}" y="${top + 234}" text-anchor="middle" style="font-size:13px">${escape(row.label)}</text>`;
        });
    }
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1800" height="1500" viewBox="0 0 1200 1000" role="img" aria-labelledby="title description"><title id="title">${year} migration forecasts</title><desc id="description">Predictions only. No matching target actuals have been linked to this training run.</desc><style>text{font-family:Arial,sans-serif;font-size:16px;fill:#43556a}.panel-title{font-size:22px;font-weight:700;fill:#294d70}</style><rect width="1200" height="1000" fill="white"/><text x="45" y="43" style="font-size:29px;font-weight:700;fill:#294d70">${year} migration · Forecast only</text><text x="45" y="76">Training: ${escape(training)} (all ${model.initial_training_months} months). Targets: ${escape(month(model.forecasts[0].month))}–${escape(month(model.forecasts[11].month))}.</text><text x="45" y="103">${escape(model.method)}. Matching actuals are required to measure error.</text><line x1="45" y1="129" x2="70" y2="129" stroke="#345b82" stroke-width="3" stroke-dasharray="8 5"/><text x="80" y="134">Model forecast; not observed actual</text>${panels}<text x="45" y="962" style="font-size:14px">Seasonal naive assumes each month repeats the same month from the latest training year.</text><text x="45" y="986" style="font-size:14px">User-provided training counts. No accuracy score or independent model ranking is available for this run.</text></svg>`;
    await fs.writeFile(path.join(directory, 'forecast.svg'), svg);
    await require(sharpModule)(Buffer.from(svg)).png().toFile(path.join(directory, 'forecast.png'));
    const totals = Object.keys(labels).map(key => `<tr><th>${labels[key]}</th><td>${number(model.forecasts.reduce((sum, row) => sum + row[key], 0))}</td></tr>`).join('');
    const rows = model.forecasts.map(row => `<tr><th>${escape(row.label)}</th><td>${number(row.in_migrant)}</td><td>${number(row.out_migrant)}</td><td>${number(row.net_migration)}</td></tr>`).join('');
    const html = `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${year} migration forecast</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f3f6f9;color:#293f54;margin:0}main{max-width:1100px;margin:auto;padding:28px}h1{font-size:29px}h2{font-size:22px}section{background:white;padding:22px;margin:20px 0;border:1px solid #d4dfe9;border-radius:12px}.notice{background:#fff9ed;border-color:#dbc697}.chart{padding:0;overflow:hidden}.chart svg{width:100%;height:auto;display:block}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #e0e7ef;white-space:nowrap}.table{overflow:auto}a{color:#345b82;font-weight:bold}code{overflow-wrap:anywhere}@media print{body{background:white}main{padding:0}section{break-inside:avoid}}</style><main><h1>${year} migration forecast</h1><p>Training: <strong>${escape(training)}</strong>, using all ${model.initial_training_months} supplied months. Model: <strong>${escape(model.method)}</strong>.</p><section class="notice"><strong>Prediction only · accuracy pending</strong><p>This is a separate training dataset. No earlier target actuals or earlier dataset scores have been linked automatically. These forecasts need the matching actuals before MAE, WAPE or model performance can be reported.</p><p>The selected fixed calendar model repeats the latest observed count from each corresponding month of the previous year. This is an assumption, not evidence that the pattern will recur. With one training year and no linked actuals, no model can be certified as the best on this dataset. <a href="https://otexts.com/fpp3/toolbox.html#seasonal-na%C3%AFve-method">Method reference</a>.</p></section><section><h2>Predicted annual totals</h2><table><thead><tr><th>Series</th><th>Predicted total</th></tr></thead><tbody>${totals}</tbody></table></section><section class="chart">${svg}</section><section><h2>Monthly predictions</h2><div class="table"><table><thead><tr><th>Target month</th><th>In-migration</th><th>Out-migration</th><th>Net migration</th></tr></thead><tbody>${rows}</tbody></table></div><p>Net = predicted in − predicted out. Negative net values are retained.</p></section><section><h2>Saved sources and model</h2><p>Training input SHA-256: <code>${escape(model.input_sha256)}</code></p><p><a href="input.csv">Frozen training CSV</a> · <a href="forecasts.csv">Monthly forecast CSV</a> · <a href="model-result.json">Fitted model and results</a> · <a href="forecast.png">Exported forecast graph</a></p><p>This local experiment does not update production records or the live admin analytics page.</p></section></main></html>`;
    await fs.writeFile(path.join(directory, 'report.html'), html);
    process.stdout.write(`Saved forecast-only report and graph in ${directory}\n`);
})().catch(error => {process.stderr.write(`${error.message}\n`); process.exitCode = 1;});
