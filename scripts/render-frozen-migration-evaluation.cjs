// Draw only the saved comparison. No training, tuning or refitting takes place here.
// Usage: node this-file evaluation-directory [sharp-module-path] [previous-evaluation-directory]
const fs = require('node:fs/promises');
const path = require('node:path');
const crypto = require('node:crypto');

(async () => {
    const [directory, sharpModule = 'sharp', previousDirectory] = process.argv.slice(2);
    if (!directory) throw new Error('Provide the saved evaluation directory.');
    const result = JSON.parse(await fs.readFile(path.join(directory, 'model-result.json'), 'utf8'));
    const frozenBytes = await fs.readFile(path.join(directory, 'frozen-model.json'));
    const frozen = JSON.parse(frozenBytes.toString('utf8'));
    const actualBytes = await fs.readFile(path.join(directory, 'actual.csv'));
    const hash = bytes => crypto.createHash('sha256').update(bytes).digest('hex');
    const actualsSuppliedLater = result.evaluation_context?.actual_dataset_received_after_forecast === true;
    const exploratory = result.target_year_previously_inspected === true && !actualsSuppliedLater;
    if (result.protocol !== 'frozen_twelve_month_fixed_origin_evaluation_v1' || result.model_refitted !== false || result.test_months !== 12
        || hash(frozenBytes) !== result.frozen_model_sha256 || hash(actualBytes) !== result.actual_sha256) throw new Error('The frozen model or actual-data provenance does not match.');
    const labels = {in_migrant: 'In-migration', out_migrant: 'Out-migration', net_migration: 'Net migration'};
    const escape = value => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
    const number = (value, digits = 2) => value === null ? 'Undefined' : Number(value).toFixed(digits);
    const month = value => new Intl.DateTimeFormat('en', {month: 'short', year: 'numeric', timeZone: 'UTC'}).format(new Date(`${value}-01T00:00:00Z`));
    const year = result.test_start.slice(0, 4);
    const training = `${month(result.source_start)}–${month(result.source_end)}`;
    let panels = '';
    for (const [index, [key, evaluation]] of Object.entries(result.evaluations).entries()) {
        evaluation.backtest.forEach((row, i) => {
            if (row.month !== frozen.forecasts[i].month || row.predicted !== frozen.forecasts[i][key] || row.actual !== result.actual_records[i][key]) throw new Error('A plotted count differs from its saved source.');
        });
        const top = 150 + index * 260;
        const values = evaluation.backtest.flatMap(row => [row.actual, row.predicted]);
        const step = Math.max(1, Math.ceil((Math.max(1, ...values) - Math.min(0, ...values)) / 20) * 5);
        const minimum = Math.floor(Math.min(0, ...values) / step) * step;
        const maximum = Math.ceil(Math.max(1, ...values) / step) * step;
        const x = i => 100 + i * 1000 / 11;
        const y = value => top + 205 - (value - minimum) / (maximum - minimum) * 150;
        let grid = '';
        for (let tick = minimum; tick <= maximum; tick += step) grid += `<line x1="100" y1="${y(tick)}" x2="1100" y2="${y(tick)}" stroke="${tick === 0 ? '#9bafbf' : '#e0e7ef'}"/><text x="83" y="${y(tick) + 5}" text-anchor="end">${tick}</text>`;
        const points = field => evaluation.backtest.map((row, i) => `${x(i)},${y(row[field])}`).join(' ');
        panels += `<text x="45" y="${top + 15}" class="panel-title">${labels[key]}</text><text x="1140" y="${top + 15}" text-anchor="end">Monthly MAE ${number(evaluation.metrics.mae)} · WAPE ${number(evaluation.metrics.wape_percent)}% error</text>${grid}<polyline points="${points('actual')}" fill="none" stroke="#345b82" stroke-width="3"/><polyline points="${points('predicted')}" fill="none" stroke="#a76a22" stroke-width="3" stroke-dasharray="8 5"/>`;
        evaluation.backtest.forEach((row, i) => {
            panels += `<circle cx="${x(i)}" cy="${y(row.actual)}" r="4" fill="#345b82"/><rect x="${x(i) - 4}" y="${y(row.predicted) - 4}" width="8" height="8" fill="#a76a22"/><text x="${x(i)}" y="${top + 234}" text-anchor="middle" style="font-size:13px">${escape(row.label)}</text>`;
        });
    }
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1800" height="1500" viewBox="0 0 1200 1000" role="img" aria-labelledby="title description"><title id="title">${year} migration: frozen forecast versus actual</title><desc id="description">Twelve monthly actuals compared with forecasts from the complete previous training year, without target-year refitting.</desc><style>text{font-family:Arial,sans-serif;font-size:16px;fill:#43556a}.panel-title{font-size:22px;font-weight:700;fill:#294d70}</style><rect width="1200" height="1000" fill="white"/><text x="45" y="43" style="font-size:29px;font-weight:700;fill:#294d70">${year} migration · Frozen forecast vs actual</text><text x="45" y="76">Training: ${escape(training)} (all 12 months). Targets: ${escape(month(result.test_start))}–${escape(month(result.test_end))}.</text><text x="45" y="103">${escape(result.method)} · Fixed origin: ${escape(month(result.source_end))}. No target-year refits.</text><line x1="45" y1="129" x2="70" y2="129" stroke="#345b82" stroke-width="3"/><text x="80" y="134">Supplied actual</text><line x1="300" y1="129" x2="325" y2="129" stroke="#a76a22" stroke-width="3" stroke-dasharray="8 5"/><text x="335" y="134">Previously saved prediction</text>${panels}<text x="45" y="962" style="font-size:14px">Source: user-provided aggregates. Net = predicted in − predicted out; negative actual net is preserved.</text><text x="45" y="986" style="font-size:14px">MAE is monthly count error; WAPE is percentage error. Annual total errors are reported separately.</text></svg>`;
    const exportedSvg = exploratory ? svg
        .replace(`${year} migration · Frozen forecast vs actual`, `${year} migration · Retrospective model comparison`)
        .replace('Previously saved prediction', 'Training-only prediction')
        .replace('MAE is monthly count error; WAPE is percentage error. Annual total errors are reported separately.', 'Exploratory comparison: target-year actuals were inspected before this model revision.')
        : actualsSuppliedLater ? svg.replace('MAE is monthly count error; WAPE is percentage error. Annual total errors are reported separately.', 'Forecasts were saved before this separate actual dataset was supplied; no target-year refits.') : svg;
    await fs.writeFile(path.join(directory, 'actual-vs-predicted.svg'), exportedSvg);
    await require(sharpModule)(Buffer.from(exportedSvg)).png().toFile(path.join(directory, 'actual-vs-predicted.png'));
    const annual = Object.entries(result.evaluations).map(([key, e]) => `<tr><th>${labels[key]}</th><td>${number(e.annual.predicted, 1)}</td><td>${e.annual.actual}</td><td>${number(e.annual.absolute_error, 1)}</td><td>${number(e.annual.absolute_percentage_error)}%</td></tr>`).join('');
    const metrics = Object.entries(result.evaluations).map(([key, e]) => `<tr><th>${labels[key]}</th><td>${number(e.metrics.mae)}</td><td>${number(e.metrics.rmse)}</td><td>${number(e.metrics.wape_percent)}%</td><td>${number(e.metrics.baseline_mae)}</td></tr>`).join('');
    const tests = result.evaluations.in_migrant.backtest.map((row, i) => `<tr><th>${escape(row.label)}</th><td>${row.forecast_horizon}</td>${Object.values(result.evaluations).map(e => `<td>${e.backtest[i].actual}</td><td>${number(e.backtest[i].predicted)}</td>`).join('')}</tr>`).join('');
    const html = `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>${year} migration forecast evaluation</title><style>body{font:16px/1.6 Arial,sans-serif;background:#f3f6f9;color:#293f54;margin:0}main{max-width:1100px;margin:auto;padding:28px}h1{font-size:29px}h2{font-size:22px}section{background:white;padding:22px;margin:20px 0;border:1px solid #d4dfe9;border-radius:12px}.notice{background:#fff9ed;border-color:#dbc697}.chart{padding:0;overflow:hidden}.chart svg{width:100%;height:auto;display:block}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:10px;border-bottom:1px solid #e0e7ef;white-space:nowrap}.table{overflow:auto}a{color:#345b82;font-weight:bold}.tag{font-size:13px;font-weight:bold;letter-spacing:.06em;color:#345b82}code{overflow-wrap:anywhere}@media print{body{background:white}main{padding:0}section{break-inside:avoid}.chart{page-break-before:always}a{display:none}}</style><main><p class="tag">LOCAL FROZEN-MODEL EVALUATION · USER-PROVIDED DATA</p><h1>${year} migration forecast evaluation</h1><p>Full training period: <strong>${escape(training)}</strong>. Comparison targets: <strong>${escape(month(result.test_start))}–${escape(month(result.test_end))}</strong>.</p><section class="notice"><strong>Previously saved predictions, unchanged model</strong><p>The model was fitted on all 12 supplied training months. Every target prediction was saved before these actuals were provided. All forecasts use the ${escape(month(result.source_end))} origin; the target months have horizons 1–12, without target-year refits or selecting settings using test scores. This is a fixed-origin forecast evaluation, not rolling backtesting.</p><p>These are user-provided aggregate counts with unassessed source verification. This comparison does not update live records or the production forecasting model.</p></section><section><h2>Annual totals</h2><div class="table"><table><thead><tr><th>Series</th><th>Predicted</th><th>Actual</th><th>Absolute error</th><th>Annual percentage error</th></tr></thead><tbody>${annual}</tbody></table></div><p>Annual percentage error = |annual actual − annual predicted| / |annual actual| × 100. Positive and negative monthly errors can cancel in annual totals; a close annual total does not imply that every monthly prediction is close.</p></section><section><h2>Monthly forecast errors</h2><div class="table"><table><thead><tr><th>Series</th><th>MAE</th><th>RMSE</th><th>WAPE error</th><th>Fixed training mean MAE</th></tr></thead><tbody>${metrics}</tbody></table></div><p>MAE is average absolute count error over the 12 monthly targets. WAPE = sum of monthly absolute errors / sum of monthly absolute actuals × 100. Negative net actuals retain their signs in the graph and use absolute magnitudes in the WAPE denominator. Percentage errors and their complements are not classification accuracy rates. One test year does not guarantee future performance.</p></section><section class="chart">${svg}</section><p>The navy line shows supplied monthly actuals. The dashed gold line shows the fixed predictions fitted on the complete preceding year. The constant-rate model produces a flat forecast and misses monthly fluctuations.</p><section><h2>All monthly comparisons</h2><div class="table"><table><thead><tr><th>Target</th><th>Horizon (months)</th><th>In actual</th><th>In predicted</th><th>Out actual</th><th>Out predicted</th><th>Net actual</th><th>Net predicted</th></tr></thead><tbody>${tests}</tbody></table></div></section><section><h2>Reproducible source files</h2><p>Frozen model SHA-256: <code>${escape(result.frozen_model_sha256)}</code></p><p>Actual CSV SHA-256: <code>${escape(result.actual_sha256)}</code></p><p><a href="input.csv">Training CSV</a> · <a href="actual.csv">Actual CSV</a> · <a href="forecasts.csv">Original forecast CSV</a> · <a href="test-results.csv">Monthly results CSV</a> · <a href="frozen-model.json">Original saved model</a> · <a href="model-result.json">Evaluation JSON</a> · <a href="actual-vs-predicted.png">Exported graph</a></p></section></main></html>`;
    let comparisonSection = '';
    if (previousDirectory) {
        const previousBytes = await fs.readFile(path.join(previousDirectory, 'model-result.json'));
        const previous = JSON.parse(previousBytes.toString('utf8'));
        if (previous.actual_sha256 !== result.actual_sha256 || previous.input_sha256 !== result.input_sha256
            || previous.source_start !== result.source_start || previous.source_end !== result.source_end
            || previous.test_start !== result.test_start || previous.test_end !== result.test_end) throw new Error('Model comparisons require identical training data, actuals and target dates.');
        const rows = Object.entries(result.evaluations).map(([key, evaluation]) => {
            const before = previous.evaluations[key].metrics.mae;
            const after = evaluation.metrics.mae;
            return {series: key, previous_mae: before, revised_mae: after,
                mae_reduction_percent: before > 0 ? 100 * (1 - after / before) : null};
        });
        const comparison = {previous_method: previous.method, revised_method: result.method,
            previous_evaluation_sha256: hash(previousBytes), revised_model_sha256: result.frozen_model_sha256,
            actual_sha256: result.actual_sha256, source_start: result.source_start, source_end: result.source_end,
            test_start: result.test_start, test_end: result.test_end, retrospective: exploratory, rows};
        await fs.writeFile(path.join(directory, 'model-comparison.json'), JSON.stringify(comparison, null, 2) + '\n');
        comparisonSection = `<section><h2>Comparison with the previous model</h2><p>Previous: ${escape(previous.method)}. Revised: ${escape(result.method)}. Both use the same training inputs and target actuals.</p><div class="table"><table><thead><tr><th>Series</th><th>Previous monthly MAE</th><th>Revised monthly MAE</th><th>MAE reduction</th></tr></thead><tbody>${rows.map(row => `<tr><th>${labels[row.series]}</th><td>${number(row.previous_mae)}</td><td>${number(row.revised_mae)}</td><td>${number(row.mae_reduction_percent)}%</td></tr>`).join('')}</tbody></table></div><p>This comparison describes historical errors; no automatic method selection or parameter tuning uses these target-year scores.</p></section>`;
    }
    const skillSection = result.pooled_in_out ? `<section><h2>Skill relative to forecasting zero</h2><p>Skill = 100 × (1 − WAPE). This is improvement over predicting zero for every target, not the probability of a correct prediction or guaranteed future accuracy. A zero forecast is a weak benchmark for these nonzero counts; read the model-to-model MAE comparison too. <a href="https://robjhyndman.com/hyndsight/wape.html">Metric definition</a>.</p><div class="table"><table><thead><tr><th>Series</th><th>WAPE error</th><th>Skill versus zero</th></tr></thead><tbody>${Object.entries(result.evaluations).map(([key, e]) => `<tr><th>${labels[key]}</th><td>${number(e.metrics.wape_percent)}%</td><td>${number(e.metrics.skill_vs_zero_percent)}%</td></tr>`).join('')}<tr><th>Pooled in + out</th><td>${number(result.pooled_in_out.wape_percent)}%</td><td>${number(result.pooled_in_out.skill_vs_zero_percent)}%</td></tr></tbody></table></div><p>Pooled in + out uses their combined absolute errors and actual totals. Net is derived and is excluded from pooling to avoid double counting.</p></section>` : '';
    let outputHtml = html.replace(svg, exportedSvg).replace('<section class="chart">', comparisonSection + skillSection + '<section class="chart">')
        .replace('Skill = 100 × (1 − WAPE).', 'Skill (%) = 100 − WAPE (%).');
    if (exploratory) {
        const replacements = [
            ['LOCAL FROZEN-MODEL EVALUATION · USER-PROVIDED DATA', 'LOCAL RETROSPECTIVE COMPARISON · USER-PROVIDED DATA'],
            ['Previously saved predictions, unchanged model', 'Exploratory model revision · target year already examined'],
            ['Every target prediction was saved before these actuals were provided.', 'This model was introduced after these target-year actuals had already been examined. It fits only the preceding training data, without tuning parameters on the target actuals. This is a retrospective comparison and is not an independent final test.']
        ];
        for (const [before, after] of replacements) {
            if (!outputHtml.includes(before)) throw new Error('The exploratory disclosure could not be applied.');
            outputHtml = outputHtml.replace(before, after);
        }
    }
    if (frozen.method_key === 'seasonal_naive_12') {
        outputHtml = outputHtml.replace('The constant-rate model produces a flat forecast and misses monthly fluctuations.',
            'The seasonal-naive model repeats each matching month from the last training year. One training cycle does not establish that this pattern will repeat reliably. Additional future evaluations are needed.');
    }
    if (actualsSuppliedLater) {
        outputHtml = outputHtml.replace('Previously saved predictions, unchanged model', 'Saved forecasts · separate actual dataset supplied later')
            .replace('This comparison does not update live records or the production forecasting model.',
                'The user confirmed that these are separate new actuals for this training dataset and supplied them after the saved forecast. The method was developed during earlier experiments on other supplied data; this new comparison uses unchanged predictions. It does not update live records or the production forecasting model.');
    }
    await fs.writeFile(path.join(directory, 'report.html'), outputHtml);
    process.stdout.write(`Saved frozen-forecast report and graph in ${directory}\n`);
})().catch(error => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
