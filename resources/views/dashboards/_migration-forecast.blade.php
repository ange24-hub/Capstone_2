<section class="dashboard-card migration-forecast-card" aria-labelledby="out-migration-forecast-title">
    <header class="dashboard-card-header">
        <div><span class="dashboard-eyebrow">Predictive Analytics</span><h2 id="out-migration-forecast-title">Out-Migration Forecast</h2>
            <p>Estimate for {{ $predictionMonthLabel }} using verified completed months through {{ $sourceMonthLabel }}.</p></div>
        <span class="section-icon"><x-app-icon name="trend" /></span>
    </header>
    @if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="forecast-summary-grid">
        <article><span>Latest completed month</span><strong>{{ number_format($currentMonthOut) }}</strong><small>Recorded departures · {{ $sourceMonthLabel }}</small></article>
        <article><span>Consecutive complete history</span><strong>{{ count($forecastSource['consecutive']) }} / {{ $forecastEvaluation['required_months'] }}</strong><small>Verified months available / minimum required</small></article>
        <article><span>Excluded synthetic events</span><strong>{{ number_format($forecastSource['excluded_demo_events']) }}</strong><small>Across the 36-month review window</small></article>
    </div>
    @if($predictedOutMigration !== null)
        <div class="forecast-result" role="status"><div><span>Estimate for {{ $predictionMonthLabel }}</span>
            <strong>{{ number_format($predictedOutMigration, 1) }} <small>departure events</small></strong>
            <p>{{ $predictionMethod }} · {{ $forecastHorizon }}-month horizon from the last completed source month.</p></div></div>
        <p class="forecast-note">This is a planning estimate, not a confirmed future count or a prediction of which resident will move. The model uses monthly totals only.</p>
    @else
        <div class="forecast-unavailable" role="status"><strong>Insufficient data for a reliable forecast</strong><p>{{ $predictionError }}</p></div>
    @endif
    @if($forecastHorizon === 2)<p class="forecast-note">The current calendar month is still in progress and is excluded from training. The estimate targets the next calendar month.</p>@endif
    @if($selectedYear < now()->year)<p class="forecast-note">Historical forecast exercise: source records after {{ $sourceMonthLabel }} are excluded.</p>@endif
    @if($selectedBarangayId === null)<p class="forecast-note">A municipal forecast requires every configured barangay to confirm the same reporting months. Select one barangay to review its own history.</p>@endif
</section>

@if($predictedOutMigration !== null)
<section class="dashboard-card migration-forecast-card" aria-labelledby="forecast-evaluation-title">
    <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Historical evaluation</span><h2 id="forecast-evaluation-title">Actual versus predicted departures</h2>
        <p>The final three verified months were held out from model selection. Each prediction uses only records available before its target month.</p></div></header>
    <div class="forecast-summary-grid">
        <article><span>Selected model MAE</span><strong>{{ number_format($forecastEvaluation['mae'], 2) }}</strong><small>Average absolute error in departure events · lower is better</small></article>
        <article><span>Moving-average baseline MAE</span><strong>{{ number_format($forecastEvaluation['baseline_mae'], 2) }}</strong><small>Same target months and forecast horizon</small></article>
        <article><span>Evaluation coverage</span><strong>{{ count($forecastEvaluation['backtest']) }} months</strong><small>{{ $forecastEvaluation['validation_months'] }} earlier validation months used for model selection</small></article>
    </div>
    <p class="forecast-note">{{ match($forecastEvaluation['baseline_comparison']) { 'better' => 'The selected model had lower error than the moving-average benchmark on these held-out months.', 'equal' => 'The selected model matched the moving-average benchmark on these held-out months.', default => 'The selected model had higher error than the moving-average benchmark on these held-out months. Review the history before relying on the estimate.' } }} These few test months provide a limited accuracy check, not a guarantee of future performance.</p>
    @php
        $evaluationRows = collect($forecastEvaluation['backtest']);
        $forecastChartMax = max(1, (float) $evaluationRows->max(fn ($row) => max($row['actual'], $row['predicted'], $row['baseline'])));
        $forecastPoints = fn ($key) => $evaluationRows->map(fn ($row, $index) => (65 + $index * 210).','. (182 - $row[$key] / $forecastChartMax * 140))->implode(' ');
    @endphp
    <div class="forecast-chart-legend"><span>● Actual</span><span>■ Predicted</span><span>-- Moving-average baseline</span></div>
    <svg class="forecast-comparison-chart" viewBox="0 0 560 230" role="img" aria-labelledby="forecast-chart-title forecast-chart-description">
        <title id="forecast-chart-title">Actual and predicted departure counts</title><desc id="forecast-chart-description">Three held-out target months. Exact counts and absolute errors are in the table below.</desc>
        @for($tick = 0; $tick <= 4; $tick++)<line x1="65" y1="{{ 182 - $tick * 35 }}" x2="485" y2="{{ 182 - $tick * 35 }}" stroke="#dce4eb"/><text x="53" y="{{ 186 - $tick * 35 }}" text-anchor="end">{{ number_format($forecastChartMax * $tick / 4, 1) }}</text>@endfor
        <polyline points="{{ $forecastPoints('actual') }}" fill="none" stroke="#2457a7" stroke-width="3"/>
        <polyline points="{{ $forecastPoints('predicted') }}" fill="none" stroke="#a35b13" stroke-width="3"/>
        <polyline points="{{ $forecastPoints('baseline') }}" fill="none" stroke="#64748b" stroke-width="2" stroke-dasharray="6 5"/>
        @foreach($evaluationRows as $row)<circle cx="{{ 65 + $loop->index * 210 }}" cy="{{ 182 - $row['actual'] / $forecastChartMax * 140 }}" r="4" fill="#2457a7"/><rect x="{{ 61 + $loop->index * 210 }}" y="{{ 178 - $row['predicted'] / $forecastChartMax * 140 }}" width="8" height="8" fill="#a35b13"/><text x="{{ 65 + $loop->index * 210 }}" y="215" text-anchor="middle">{{ $row['label'] }}</text>@endforeach
    </svg>
    <div class="table-wrap"><table><caption class="sr-only">Held-out forecast evaluation</caption><thead><tr><th>Target month</th><th>Actual</th><th>Predicted</th><th>Baseline</th><th>Absolute error</th></tr></thead><tbody>
        @foreach($evaluationRows as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'], 2) }}</td><td>{{ number_format($row['baseline'], 2) }}</td><td>{{ number_format($row['absolute_error'], 2) }}</td></tr>@endforeach
    </tbody></table></div>
    <details class="forecast-method"><summary>How the forecast is evaluated</summary><p>Last-month, three-month moving-average, exponential-smoothing and Holt linear-trend models are compared on earlier rolling validation windows. The chosen method is fixed before the final three test months are evaluated. For each target month, training ends {{ $forecastHorizon }} month(s) earlier. The final estimate is fitted again using all available consecutive verified months. Demo data, unverified months and possible duplicate events are excluded from training. Missing months break the series instead of becoming zeros.</p></details>
</section>
@endif

<section class="dashboard-card migration-forecast-card" aria-labelledby="forecast-history-title">
    <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Data readiness</span><h2 id="forecast-history-title">Review monthly departure reporting</h2>
        <p>Confirm a month only after checking the barangay transfer log. A confirmed zero means staff verified that there were no departures.</p></div></header>
    @if(!$forecastSource['storage_ready'])<p class="forecast-unavailable">Reporting-period storage is not ready. Ask the administrator to complete the forecast installation.</p>
    @elseif(!$selectedBarangayId)
        <div class="table-wrap"><table><thead><tr><th>Barangay</th><th>Recorded departures</th><th>Verified months</th><th>Consecutive latest months</th><th>Review</th></tr></thead><tbody>
            @foreach($forecastSource['coverage'] as $area)<tr><td>{{ $area['name'] }}</td><td>{{ $area['events'] }}</td><td>{{ $area['verified_months'] }}</td><td>{{ $area['consecutive_months'] }}</td><td><a href="{{ route('migration.dashboard', ['section' => 'forecast', 'year' => $selectedYear, 'barangay_id' => $area['id']]) }}">Review months</a></td></tr>@endforeach
        </tbody></table></div>
    @else
        <p class="forecast-note">If a departure is added, removed, moved to another month or reassigned to a different resident after verification, that month automatically needs another review. Possible duplicate events must be resolved before verification. Every confirmation and reopening is recorded in Registry Activity History.</p>
        <div class="table-wrap forecast-history-table"><table><thead><tr><th>Month</th><th>Recorded departures</th><th>Status</th><th>Review reporting</th></tr></thead><tbody>
        @foreach(array_reverse($forecastSource['rows']) as $month)
            <tr><td data-label="Month">{{ $month['label'] }}</td><td data-label="Recorded departures">{{ $month['count'] }}@if($month['demo_count'])<small class="forecast-cell-note">{{ $month['demo_count'] }} synthetic events excluded</small>@endif</td>
                <td data-label="Status"><span class="forecast-status {{ $month['verified'] ? 'is-complete' : '' }}">{{ $month['status'] }}</span>@if($month['verified_at'])<small class="forecast-cell-note">{{ $month['verified_by'] ?: 'Staff account' }} · {{ $month['verified_at'] }}</small>@endif</td>
                <td><details class="forecast-review"><summary>{{ $month['verified'] ? 'Review / reopen' : 'Review month' }}</summary>
                    <p>Check that all genuine departure events for {{ $month['label'] }} are encoded, dates are correct, and repeated events have been reviewed. This confirmation does not add or delete migration events.</p>
                    @if(!$month['verified'] && !$month['duplicates'])
                    <form method="POST" action="{{ route('migration.reporting-periods.store') }}">
                        @csrf<input type="hidden" name="barangay_id" value="{{ $selectedBarangayId }}"><input type="hidden" name="year" value="{{ $selectedYear }}"><input type="hidden" name="month" value="{{ $month['month'] }}"><input type="hidden" name="source_fingerprint" value="{{ $month['fingerprint'] }}"><input type="hidden" name="action" value="verify">
                        <label class="forecast-confirm"><input type="checkbox" name="confirm_complete" value="1" required><span>I checked the source log. All departures are encoded; a zero count is a verified zero.</span></label>
                        <label>Review note (optional)<input name="notes" maxlength="500" placeholder="Source log or review reference"></label><button type="submit">Confirm complete month</button>
                    </form>
                    @elseif($month['duplicates'])<p class="forecast-unavailable">Review possible duplicate departure events in the authorized registry workflow before confirming this month.</p>
                    @endif
                    @if($month['verified_at'])<form method="POST" action="{{ route('migration.reporting-periods.store') }}">@csrf<input type="hidden" name="barangay_id" value="{{ $selectedBarangayId }}"><input type="hidden" name="year" value="{{ $selectedYear }}"><input type="hidden" name="month" value="{{ $month['month'] }}"><input type="hidden" name="source_fingerprint" value="{{ $month['fingerprint'] }}"><input type="hidden" name="action" value="reopen"><button type="submit" class="secondary-button">Reopen for review</button></form>@endif
                </details></td></tr>
        @endforeach
        </tbody></table></div>
    @endif
</section>
