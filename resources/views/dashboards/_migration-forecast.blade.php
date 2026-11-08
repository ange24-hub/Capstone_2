@php
    $arrivalMonths = collect($forecastArrivalSource['rows'])->keyBy('month');
    $arrivalCoverage = collect($forecastArrivalSource['coverage'])->keyBy('id');
    $forecastStorageReady = $forecastSource['storage_ready'] && $forecastArrivalSource['storage_ready'];
    $excludedDemo = $forecastSource['excluded_demo_events'] + $forecastArrivalSource['excluded_demo_events'];
@endphp
@if(isset($experimentalArrivalForecast))
    @include('dashboards._barangay-svr-arrival')
@endif
<section class="dashboard-card migration-forecast-card forecast-overview" aria-labelledby="out-migration-forecast-title">
    <header class="dashboard-card-header">
        <div><span class="dashboard-eyebrow">Verified monthly-history forecast</span><h2 id="out-migration-forecast-title">Migration estimate · {{ $predictionMonthLabel }}</h2>
            <p>Banabana sa moabot, mobalhin gawas ug net nga kausaban sa migration.</p></div>
        <span class="section-icon"><x-app-icon name="trend" /></span>
    </header>
    @if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p class="forecast-note forecast-source-period">Records through <b>{{ $sourceMonthLabel }}</b>@if($forecastNextRefreshLabel) · Next refresh <b>{{ $forecastNextRefreshLabel }}</b>@endif</p>
    @if(!$forecastStorageReady)
        <div class="forecast-unavailable" role="status"><strong>Forecast setup is not ready yet</strong><p>Ask the system administrator to complete the setup.</p></div>
    @elseif($selectedYear > now()->year)
        <div class="forecast-unavailable" role="status"><strong>Choose the current year or a past year</strong><p>The system needs completed records to prepare an estimate.</p></div>
    @elseif(!$selectedBarangayId && $predictedInMigration === null && $predictedOutMigration === null)
        <div class="forecast-start" role="status"><strong>Kulang pa ang verified monthly records.</strong><p>I-classify ug kompletoha ang saved RBI entries ug i-review ang moved-out records aron makagenerate ug forecast.</p></div>
    @else
        <div class="forecast-summary-grid forecast-estimates" aria-label="Estimated movement events">
            @foreach([['Arrivals', $predictedInMigration, $selectedBarangayId ? 'Moves into the barangay' : 'Moves into reporting barangays'], ['Departures', $predictedOutMigration, $selectedBarangayId ? 'Moves out of the barangay' : 'Moves out of reporting barangays'], ['Net change', $predictedNetMigration, 'Arrivals minus departures']] as [$label, $value, $hint])
                <article><span>{{ $label }}</span><strong>{{ $value === null ? 'Not ready' : ($label === 'Net change' && $value > 0 ? '+' : '').number_format($value, 1) }}</strong><small>{{ $hint }}</small></article>
            @endforeach
        </div>
        @if($predictedNetMigration !== null)
            <p class="forecast-note">Positive net means more arrivals; negative net means more departures. These are movement events, not total population. Use the estimates for planning; actual counts may be different.</p>
        @else
            <div class="forecast-unavailable" role="status"><strong>Not enough checked records yet</strong>
                <p>Kinahanglan masusi ang arrivals ug departures aron makabanabana sa net migration.</p>
                <p>Consecutive complete months checked: <b>{{ count($forecastArrivalSource['consecutive']) }} arrivals</b> · <b>{{ count($forecastSource['consecutive']) }} departures</b>. At least <b>{{ $forecastEvaluation['required_months'] }}</b> are needed for each.</p>
            </div>
            @if($selectedBarangayId)<a class="button forecast-review-link" href="{{ route('migration.dashboard', ['section' => 'forecast', 'year' => $selectedYear, 'barangay_id' => $selectedBarangayId, 'review' => 1]) }}#forecast-records">Review monthly records</a>@endif
            <p class="forecast-note">Departures use dated Moved Out records. Arrivals use classified RBI entries grouped by the first date each entry was saved. A later submission does not add another arrival.</p>
        @endif
    @endif
    <details class="forecast-method forecast-info" data-forecast-details>
        <summary>Forecast details</summary>
        <p class="forecast-note"><a href="{{ route('municipal.prediction-forecast-evidence') }}">Download forecast evidence</a> · Reporting coverage, measured errors when available, and model version hashes.</p>
        <p class="forecast-note"><strong>Models are selected separately for arrivals and departures.</strong> Seasonal naive (same month last year) remains the fallback. With enough history, six earlier validation months compare moving averages, exponential smoothing and Holt trend models against it. A challenger must reduce validation MAE without increasing RMSE. The final three test months never select the model.</p>
        <p class="forecast-note">Arrivals measure classified RBI entries by first-save date, which can differ from the actual arrival month. Late historical imports can create recording spikes. Departures use dated Moved Out records. The arrival estimate describes newly recorded arrivals, not an independently verified forecast of the month people physically move.</p>
        @if($forecastNextRefreshLabel)
            <p class="forecast-note" data-forecast-schedule>Records refresh every 3 months (January, April, July and October). This cycle uses records through {{ $sourceMonthLabel }}. Next refresh: <b>{{ $forecastNextRefreshLabel }}</b>.</p>
        @endif
    </details>
    @if($selectedYear < now()->year)<p class="forecast-note">Historical estimate based on records through {{ $sourceMonthLabel }}.</p>@endif
</section>

@if($predictedInMigration !== null || $predictedOutMigration !== null)
@include('dashboards._migration-seasonal-future')
<details class="dashboard-card migration-forecast-card forecast-secondary" data-forecast-accuracy>
    <summary>How close were earlier estimates?</summary>
    <section class="forecast-details-section" aria-labelledby="forecast-evaluation-title">
        <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Historical evaluation</span><h2 id="forecast-evaluation-title">Actual versus predicted migration</h2>
            <p>The model is chosen using earlier validation history, or the fixed seasonal fallback is used. The final three verified months test forecasts at the displayed horizon. Each target stays outside its own training window.</p></div></header>
        @foreach(['arrivals' => [$forecastArrivalEvaluation, $predictedInMigration], 'departures' => [$forecastEvaluation, $predictedOutMigration], 'net' => [$forecastNetEvaluation, $predictedNetMigration]] as $kind => [$evaluation, $estimate])
            @if($estimate !== null)
                @include('dashboards._migration-forecast-evaluation', ['evaluation' => $evaluation, 'kind' => $kind])
            @endif
        @endforeach
        <details class="forecast-method"><summary>How the forecast is evaluated</summary><p>Twelve consecutive complete months support the seasonal fallback. At this {{ $forecastHorizon }}-month horizon, {{ $forecastEvaluation['evaluation_required_months'] }} months allow three historical test errors; {{ $forecastEvaluation['selection_required_months'] }} months allow six earlier validation targets plus the separate tests at a fixed {{ $forecastEvaluation['selection_horizon'] }}-month selection horizon. Horizon guard gaps keep validation outcomes known at the first test origin. No model settings are selected using test outcomes. For each test target, training ends {{ $forecastHorizon }} month(s) earlier. Earlier test actuals may enter later origins once known: this is a rolling-origin evaluation. The moving-average comparison uses the same origins and horizon. Selected models stay fixed across the six future estimates; error scores apply to the first target's horizon only. Net errors are measured after subtraction. An available forecast can have no measured error yet. @if($forecastNextRefreshLabel)The source cutoff and selection horizon stay fixed within the quarter, keeping model choice stable unless source records change. Test errors use the displayed target horizon. Corrections update the history immediately.@endif Demo data, incomplete months and duplicate-affected months stay outside fitting. Empty months bounded by saved RBI reports count as zero entries under the reporting convention.</p></details>
    </section>
</details>
@endif

<details id="forecast-records" class="dashboard-card migration-forecast-card forecast-secondary" @if(request()->boolean('review') || session('status') || $errors->any()) open @endif>
    <summary>Check the records used</summary>
    <section class="forecast-details-section" aria-labelledby="forecast-history-title">
    <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Data readiness</span><h2 id="forecast-history-title">Review monthly migration reporting</h2>
        <p>Review dated departures from Moved Out here. Arrival counts come from classified RBI entries by first-save month. Missing departure checks remain unverified.</p></div></header>
    @if($selectedBarangayId)<p class="forecast-note"><a href="{{ route('municipal.rbi-report-history') }}">Open RBI report history</a> to inspect the underlying entries.</p>@endif
    @if($predictionError)<p class="forecast-note">Departures: {{ $predictionError }}</p>@endif
    @if($predictionArrivalError)<p class="forecast-note">Arrivals: {{ $predictionArrivalError }}</p>@endif
    @if($selectedBarangayId === null)<p class="forecast-note">A municipal forecast requires every configured barangay to confirm the same reporting months for both arrivals and departures. Select one barangay to review its own history.</p>@endif
    @if($excludedDemo)<p class="forecast-note">{{ number_format($excludedDemo) }} synthetic events are excluded from forecasting.</p>@endif
    @if(!$forecastStorageReady)<p class="forecast-unavailable">Reporting-period storage is not ready. Ask the administrator to complete the forecast installation.</p>
    @elseif(!$selectedBarangayId)
        <div class="table-wrap"><table><thead><tr><th>Barangay</th><th>Recorded arrivals / departures</th><th>Consecutive checked months (in / out)</th><th>Review</th></tr></thead><tbody>
            @foreach($forecastSource['coverage'] as $area)<tr><td>{{ $area['name'] }}</td><td>{{ $arrivalCoverage[$area['id']]['events'] }} / {{ $area['events'] }}</td><td>{{ $arrivalCoverage[$area['id']]['consecutive_months'] }} / {{ $area['consecutive_months'] }}</td><td><a href="{{ route('migration.dashboard', ['section' => 'forecast', 'year' => $selectedYear, 'barangay_id' => $area['id'], 'review' => 1]) }}#forecast-records">Review months</a></td></tr>@endforeach
        </tbody></table></div>
    @else
        <p class="forecast-note">Saved RBI classifications update arrival counts automatically. Possible duplicate entries must be resolved before that month can train a forecast. Departure confirmations and reopening are recorded in Registry Activity History.</p>
        <div class="table-wrap forecast-history-table"><table><thead><tr><th>Month (first saved)</th><th>Arrivals</th><th>Departures</th><th>Status</th><th>Review reporting</th></tr></thead><tbody>
        @foreach(array_reverse($forecastSource['rows']) as $month)
            @php
                $arrival = $arrivalMonths[$month['month']];
                $complete = $month['verified'];
                $duplicates = $month['duplicates'] + $arrival['duplicates'];
            @endphp
            <tr><td data-label="Month">{{ $month['label'] }}</td>
                <td data-label="Arrivals">{{ $arrival['count'] }}@if($arrival['demo_count'])<small class="forecast-cell-note">{{ $arrival['demo_count'] }} synthetic events excluded</small>@endif
                    @if(!empty($arrival['source_reports']))<details class="forecast-review"><summary>RBI save details</summary>
                        @foreach($arrival['source_reports'] as $sourceReport)
                            <p class="forecast-cell-note">Form #{{ $sourceReport['id'] }} · form first saved {{ $sourceReport['first_saved_at'] }} · {{ $sourceReport['rows_first_saved_this_month'] }} entries saved in this month, {{ $sourceReport['arrivals_this_month'] }} classified in-migration · {{ $sourceReport['submitted_at'] ? 'submitted '.$sourceReport['submitted_at'] : 'not submitted' }}</p>
                            @foreach($sourceReport['save_batches'] as $batch)<p class="forecast-cell-note">Saved {{ $batch['saved_at'] }}: {{ $batch['entries'] }} entries input, {{ $batch['arrivals'] }} classified in-migration</p>@endforeach
                        @endforeach
                    </details>@endif
                </td>
                <td data-label="Departures">{{ $month['count'] }}@if($month['demo_count'])<small class="forecast-cell-note">{{ $month['demo_count'] }} synthetic events excluded</small>@endif</td>
                <td data-label="Status">
                    @foreach(['Arrivals' => $arrival, 'Departures' => $month] as $label => $sourceRow)
                        <div class="forecast-status-line"><span class="forecast-status {{ $sourceRow['verified'] ? 'is-complete' : '' }}">{{ $label }}: {{ $sourceRow['status'] }}</span>@if($sourceRow['verified_at'])<small class="forecast-cell-note">{{ $sourceRow['verified_by'] ?: 'Staff account' }} · {{ $sourceRow['verified_at'] }}</small>@endif</div>
                    @endforeach
                </td>
                <td><details class="forecast-review"><summary>{{ $complete ? 'Review / reopen' : 'Review month' }}</summary>
                    <p>Check all dated Moved Out departures for {{ $month['label'] }}. Arrival counts refresh from the saved RBI entries and their classifications.</p>
                    @if(!$complete && !$duplicates)
                        <form method="POST" action="{{ route('migration.reporting-periods.store') }}">
                            @include('dashboards._migration-forecast-review-fields')
                            <input type="hidden" name="action" value="verify">
                            <label class="forecast-confirm"><input type="checkbox" name="confirm_complete" value="1" required><span>I checked all departure records. Dates are correct, duplicates are resolved, and a zero departure count is a verified zero.</span></label>
                            <label>Review note (optional)<input name="notes" maxlength="500" placeholder="Source log or review reference"></label><button type="submit">Confirm departure month</button>
                        </form>
                    @elseif($duplicates)<p class="forecast-unavailable">Review possible duplicate departure events in the authorized registry workflow before confirming this month.</p>
                    @endif
                    @if($month['verified_at'])<form method="POST" action="{{ route('migration.reporting-periods.store') }}">@include('dashboards._migration-forecast-review-fields')<input type="hidden" name="action" value="reopen"><button type="submit" class="secondary-button">Reopen for review</button></form>@endif
                </details></td></tr>
        @endforeach
        </tbody></table></div>
    @endif
    </section>
</details>
