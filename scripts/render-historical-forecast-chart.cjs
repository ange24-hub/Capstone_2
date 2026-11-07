// Export the application's own holdout SVG with aggregate result labels.
// Usage: node this-file result.json rendered-preview.html output-prefix [sharp-module-path]
const fs = require('node:fs/promises');
const path = require('node:path');

(async () => {
    const [resultPath, previewPath, prefix, sharpModule = 'sharp'] = process.argv.slice(2);
    if (!resultPath || !previewPath || !prefix) throw new Error('Provide a result JSON, rendered HTML and output prefix.');
    const result = JSON.parse(await fs.readFile(resultPath, 'utf8'));
    if (result.data_source !== 'CLASSIFIED_RBI_HISTORY_EXPERIMENT' || !['holdout', 'rolling'].includes(result.mode) || !result.evaluation.ready) {
        throw new Error('A completed historical holdout result is required.');
    }
    const html = await fs.readFile(previewPath, 'utf8');
    for (const [name, value] of [['source-fingerprint', result.history.fingerprint], ['model-protocol', result.evaluation.protocol], ['model-method', result.evaluation.method_key], ['model-window', `${result.evaluation.source_start}|${result.evaluation.source_end}|${result.evaluation.forecast_end}`]]) {
        if (!html.includes(`<meta name="forecast-${name}" content="${value}">`)) {
            throw new Error('The preview and result metadata do not match. Render a fresh preview first.');
        }
    }
    const rolling = result.mode === 'rolling';
    const graph = html.match(rolling
        ? /<svg\b[^>]*aria-labelledby="rolling-test-title rolling-test-description"[^>]*>([\s\S]*?)<\/svg>/
        : /<svg\b[^>]*aria-labelledby="holdout-history-title holdout-history-description"[^>]*>([\s\S]*?)<\/svg>/);
    if (!graph) throw new Error('The rendered holdout graph was not found.');
    const expectedTitle = `${result.barangay.name} ${rolling ? 'rolling backtest:' : 'training history and'}`;
    if (!graph[1].includes(expectedTitle)) throw new Error('The preview and result barangay do not match.');
    const evaluation = result.evaluation;
    const width = Number(graph[0].match(/viewBox="0 0 (\d+) 295"/)?.[1]);
    if (!Number.isFinite(width)) throw new Error('Unexpected chart dimensions.');
    const number = value => value === null || value === undefined ? 'Pending' : Number(value).toFixed(2);
    const month = value => new Intl.DateTimeFormat('en', {month:'long',year:'numeric',timeZone:'UTC'}).format(new Date(`${value}-01T00:00:00Z`));
    const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
    const fixedRate = ['mean_rate', 'poisson_jeffreys'].includes(evaluation.method_key);
    const bayesian = evaluation.method_key === 'poisson_jeffreys';
    const targetRange = (rolling ? evaluation.test_months : evaluation.forecast_months) > 1 ? `${month(evaluation.target_month)}–${month(evaluation.forecast_end)}` : month(evaluation.target_month);
    const metrics = rolling
        ? `Model MAE: ${number(evaluation.mae)}. Mean-rate MAE: ${number(evaluation.baseline_mae)}. Recorded total: ${number(evaluation.actual_total)}.`
        : fixedRate
        ? `Monthly estimate: ${number(evaluation.prediction)}. Expected total: ${number(evaluation.forecast_total)}. Recorded total: ${number(evaluation.actual_total)}. MAE: ${number(evaluation.observed_mae)}.`
        : `Prediction: ${number(evaluation.prediction)}. Recorded: ${number(evaluation.holdout_actual)}. Absolute error: ${number(evaluation.holdout_error)}.`;
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${width * 2}" height="888" viewBox="0 -100 ${width} 444">
<style>text{font-family:Arial,sans-serif;font-size:14px;fill:#43556a}</style>
<rect x="0" y="-100" width="${width}" height="444" fill="#fff"/>
<text x="35" y="-67" style="font-size:23px;font-weight:700;fill:#294d70">${escape(result.barangay.name)}: ${escape(targetRange)} ${rolling ? 'rolling backtest' : 'historical prediction'}</text>
<text x="35" y="-40">${rolling ? 'Initial training' : 'Training'}: ${escape(month(evaluation.source_start))} through ${escape(month(evaluation.source_end))}. ${rolling ? 'Refit before each monthly target.' : 'Later actuals are outside training.'}</text>
<text x="35" y="-13" style="font-weight:700">${escape(metrics)}</text>
<line x1="35" y1="11" x2="55" y2="11" stroke="#345b82" stroke-width="3"/><text x="62" y="16">Recorded count</text>
<line x1="230" y1="11" x2="250" y2="11" stroke="#a76a22" stroke-width="3" stroke-dasharray="6 4"/><text x="257" y="16">${escape(rolling ? (bayesian ? 'Rolling Bayesian Poisson' : 'Rolling mean rate') : (bayesian ? 'Bayesian Poisson' : evaluation.method))}</text>
${rolling ? '' : `<circle cx="${width - 180}" cy="11" r="4" stroke="#345b82" stroke-width="2" fill="#fff"/><text x="${width - 169}" y="16">Zero-entry gap</text>`}
<g transform="translate(0,24)">${graph[1]}</g>
<text x="35" y="319" style="font-size:13px;fill:#78581d">Source: classified RBI history, including drafts. Births and late registrations excluded.</text>
<text x="35" y="338" style="font-size:13px;fill:#78581d">${rolling ? 'One month ahead, refitted after each actual. Historical errors do not establish future accuracy.' : (bayesian ? 'Fixed Jeffreys prior: (observed arrivals + 0.5) / training months. Exploratory, not proven future accuracy.' : 'Retrospective comparison. Errors shown here do not establish future accuracy.')}</text>
</svg>`;
    const sharp = require(sharpModule);
    await fs.mkdir(path.dirname(path.resolve(prefix)), {recursive: true});
    await fs.writeFile(`${prefix}.svg`, svg);
    await sharp(Buffer.from(svg)).png().toFile(`${prefix}.png`);
    process.stdout.write(`Saved ${prefix}.svg and ${prefix}.png\n`);
})().catch(error => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
