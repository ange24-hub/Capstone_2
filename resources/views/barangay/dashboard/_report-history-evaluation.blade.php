<section class="statistical-card statistical-history-evaluation" aria-labelledby="report-history-test-title">
    <div class="statistical-section-heading">
        <div><h2 id="report-history-test-title">Tinago in-migrant history test</h2><p>Train: Jun 2020–Apr 2021 · Test: May–Jul 2021</p></div>
        <span class="statistical-status is-waiting">Historical test</span>
    </div>
    <p class="statistical-evaluation-note">Report History labels include age-based suggestions. Results measure historical label error, not verified movement accuracy.</p>
    @if($evaluation['ready'])
        <div class="statistical-error-metrics" aria-label="Held-out report-history error">
            <div><span>MAE</span><strong>{{ number_format($evaluation['metrics']['mae'], 2) }}</strong></div>
            <div><span>RMSE</span><strong>{{ number_format($evaluation['metrics']['rmse'], 2) }}</strong></div>
            <div><span>MAPE</span><strong>{{ $evaluation['metrics']['mape'] === null ? 'N/A' : number_format($evaluation['metrics']['mape'], 2).'%' }}</strong></div>
        </div>
        @include('barangay.dashboard._prediction-chart', [
            'chartId' => 'tinago-history-test', 'chartTitle' => 'Tinago report-history labels and held-out WMA predictions',
            'chartRows' => $evaluation['chart'], 'chartActualLabel' => 'History labels', 'chartNote' => 'Lower error is better · Tap a point',
        ])
        <details class="statistical-details"><summary>Test months &amp; method</summary>
            <p>11 training months; last 6 use fixed weights 1–6. All 3 test forecasts are made from training data and recursive predictions only. Test counts never become model inputs.</p>
            <div class="statistical-table"><table><thead><tr><th>Test month</th><th>History label count</th><th>Predicted</th><th>Error</th></tr></thead><tbody>
                @foreach($evaluation['test_results'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'], 2) }}</td><td>{{ number_format($row['absolute_error'], 2) }}</td></tr>
                @endforeach
            </tbody></table></div>
            <p>Last-report baseline MAE: <strong>{{ number_format($evaluation['baseline_metrics']['mae'], 2) }}</strong>. {{ $evaluation['metrics']['mae'] < $evaluation['baseline_metrics']['mae'] ? 'WMA has lower error on these test months.' : 'WMA does not beat the baseline on these test months.' }}</p>
            <p>MAPE uses {{ $evaluation['metrics']['mape_months'] }} positive-count months; {{ $evaluation['metrics']['zero_actual_months'] }} zero-count month(s) remain included in MAE/RMSE.</p>
            <p>{{ $evaluation['estimated_labels'] }} in-migrant labels use age suggestions. Historical records have been inspected before; this is a retrospective check. Out-migration is not inferred from these arrival reports.</p>
            <p>October–November 2021 are excluded because August–September reports are missing. The monthly history is not extended to 2026.</p>
        </details>
    @else
        <p class="statistical-empty">{{ $evaluation['reason'] }}</p>
    @endif
</section>
