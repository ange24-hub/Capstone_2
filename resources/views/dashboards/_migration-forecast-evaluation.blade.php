<section class="forecast-series-evaluation" aria-labelledby="forecast-{{ $kind }}-title">
    <h3 id="forecast-{{ $kind }}-title">Actual versus predicted {{ $kind === 'net' ? 'net migration' : $kind }}</h3>
    <div class="forecast-summary-grid">
        <article><span>{{ $kind === 'net' ? 'Net forecast MAE' : 'Seasonal model MAE' }}</span><strong>{{ $evaluation['mae'] === null ? 'Not measured' : number_format($evaluation['mae'], 2) }}</strong><small>Average absolute error in movement events · lower is better</small></article>
        <article><span>Moving-average baseline MAE</span><strong>{{ $evaluation['baseline_mae'] === null ? 'Not measured' : number_format($evaluation['baseline_mae'], 2) }}</strong><small>Same target months and forecast horizon</small></article>
        <article><span>Evaluation coverage</span><strong>{{ count($evaluation['backtest']) }} months</strong><small>{{ $kind === 'net' ? 'Arrival estimate minus departure estimate' : 'Fixed seasonal method; no tuning on test actuals' }}</small></article>
    </div>
    @if($kind !== 'net')<p class="forecast-note">Method: {{ $evaluation['method'] }}. Current fit: {{ $evaluation['fit_start'] }}–{{ $evaluation['source_end'] }}. Reference month for the next estimate: {{ $evaluation['reference_month'] }}.</p>@endif
    @if(!$evaluation['backtest'])
        <p class="forecast-note" role="status">Held-out error not measured yet. The forecast has enough training history, but separate test history at this horizon is incomplete. This does not mean zero error.</p>
    @else
    <p class="forecast-note">{{ match($evaluation['baseline_comparison']) { 'better' => 'The forecast had lower error than the moving-average benchmark on these held-out months.', 'equal' => 'The forecast matched the moving-average benchmark on these held-out months.', default => 'The forecast had higher error than the moving-average benchmark on these held-out months. Review the history before relying on the estimate.' } }} These few test months provide a limited accuracy check, not a guarantee of future performance.</p>
    @php
        $evaluationRows = collect($evaluation['backtest']);
        $chartMin = min(0, (float) $evaluationRows->min(fn ($row) => min($row['actual'], $row['predicted'], $row['baseline'])));
        $chartMax = max(0, (float) $evaluationRows->max(fn ($row) => max($row['actual'], $row['predicted'], $row['baseline'])));
        $chartRange = max(1, $chartMax - $chartMin);
        $chartY = fn ($value) => 182 - ($value - $chartMin) / $chartRange * 140;
        $forecastPoints = fn ($key) => $evaluationRows->map(fn ($row, $index) => (65 + $index * 210).','. $chartY($row[$key]))->implode(' ');
    @endphp
    <div class="forecast-chart-legend"><span>● Actual</span><span>■ Predicted</span><span>-- Moving-average baseline</span></div>
    <svg class="forecast-comparison-chart" viewBox="0 0 560 230" role="img" aria-labelledby="forecast-{{ $kind }}-chart-title forecast-{{ $kind }}-chart-description">
        <title id="forecast-{{ $kind }}-chart-title">Actual and predicted {{ $kind }} counts</title><desc id="forecast-{{ $kind }}-chart-description">Three held-out target months. Exact counts and absolute errors are in the table below.</desc>
        @for($tick = 0; $tick <= 4; $tick++)<line x1="65" y1="{{ 182 - $tick * 35 }}" x2="485" y2="{{ 182 - $tick * 35 }}" stroke="#dce4eb"/><text x="53" y="{{ 186 - $tick * 35 }}" text-anchor="end">{{ number_format($chartMin + $chartRange * $tick / 4, 1) }}</text>@endfor
        <polyline points="{{ $forecastPoints('actual') }}" fill="none" stroke="#2457a7" stroke-width="3"/>
        <polyline points="{{ $forecastPoints('predicted') }}" fill="none" stroke="#a35b13" stroke-width="3"/>
        <polyline points="{{ $forecastPoints('baseline') }}" fill="none" stroke="#64748b" stroke-width="2" stroke-dasharray="6 5"/>
        @foreach($evaluationRows as $row)<circle cx="{{ 65 + $loop->index * 210 }}" cy="{{ $chartY($row['actual']) }}" r="4" fill="#2457a7"/><rect x="{{ 61 + $loop->index * 210 }}" y="{{ $chartY($row['predicted']) - 4 }}" width="8" height="8" fill="#a35b13"/><text x="{{ 65 + $loop->index * 210 }}" y="215" text-anchor="middle">{{ $row['label'] }}</text>@endforeach
    </svg>
    <div class="table-wrap"><table><caption class="sr-only">Held-out {{ $kind }} forecast evaluation</caption><thead><tr><th>Target month</th><th>Actual</th><th>Predicted</th><th>Baseline</th><th>Absolute error</th></tr></thead><tbody>
        @foreach($evaluationRows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'], 2) }}</td><td>{{ number_format($row['baseline'], 2) }}</td><td>{{ number_format($row['absolute_error'], 2) }}</td></tr>@endforeach
    </tbody></table></div>
    @endif
</section>
