@php
    $arrivalMonths = collect($forecastArrivalSource['rows'])->keyBy('month');
    $arrivalCoverage = collect($forecastArrivalSource['coverage'])->keyBy('id');
    $forecastStorageReady = $forecastSource['storage_ready'] && $forecastArrivalSource['storage_ready'];
    $excludedDemo = $forecastSource['excluded_demo_events'] + $forecastArrivalSource['excluded_demo_events'];
@endphp
<section class="dashboard-card migration-forecast-card forecast-overview" aria-labelledby="out-migration-forecast-title">
    <header class="dashboard-card-header">
        <div><h2 id="out-migration-forecast-title">Migration estimate · {{ $predictionMonthLabel }}</h2>
            <p>Banabana sa moabot, mobalhin gawas ug net nga kausaban sa migration.</p></div>
        <span class="section-icon"><x-app-icon name="trend" /></span>
    </header>
    @if(session('status'))<p class="success" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <p class="forecast-note"><strong>Model: Seasonal naive (same month last year).</strong> Each target uses the corresponding month from the latest 12 verified source months. Arrivals come from reviewed submitted RBI forms; departures come from dated Moved Out records.</p>
    @if($forecastNextRefreshLabel)
        <p class="forecast-note" data-forecast-schedule>Records refresh every 3 months (January, April, July and October). This cycle uses records through {{ $sourceMonthLabel }}. Next refresh: <b>{{ $forecastNextRefreshLabel }}</b>.</p>
    @endif
    @if(!$forecastStorageReady)
        <div class="forecast-unavailable" role="status"><strong>Forecast setup is not ready yet</strong><p>Ask the system administrator to complete the setup.</p></div>
    @elseif($selectedYear > now()->year)
        <div class="forecast-unavailable" role="status"><strong>Choose the current year or a past year</strong><p>The system needs completed records to prepare an estimate.</p></div>
    @elseif(!$selectedBarangayId && $predictedInMigration === null && $predictedOutMigration === null)
        <div class="forecast-start" role="status"><strong>Municipal forecast is not ready</strong><p>Every configured barangay needs complete reviewed monthly counts. Check the coverage below, or select a barangay to view its own records and forecast readiness.</p></div>
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
            <p class="forecast-note">Departures use dated Moved Out records. Arrivals use reviewed RBI forms and zero-entry gaps between recorded history months.</p>
        @endif
    @endif
    @if($selectedYear < now()->year)<p class="forecast-note">Historical estimate based on records through {{ $sourceMonthLabel }}.</p>@endif
</section>

@if($predictedInMigration !== null || $predictedOutMigration !== null)
@include('dashboards._migration-seasonal-future')
<details class="dashboard-card migration-forecast-card forecast-secondary" data-forecast-accuracy>
    <summary>How close were earlier estimates?</summary>
    <section class="forecast-details-section" aria-labelledby="forecast-evaluation-title">
        <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Historical evaluation</span><h2 id="forecast-evaluation-title">Actual versus predicted migration</h2>
            <p>The model is fixed in advance. When enough earlier history exists, the final three verified months are tested using forecasts made at the displayed horizon. Each target stays outside its own training window.</p></div></header>
        @foreach(['arrivals' => [$forecastArrivalEvaluation, $predictedInMigration], 'departures' => [$forecastEvaluation, $predictedOutMigration], 'net' => [$forecastNetEvaluation, $predictedNetMigration]] as $kind => [$evaluation, $estimate])
            @if($estimate !== null)
                @include('dashboards._migration-forecast-evaluation', ['evaluation' => $evaluation, 'kind' => $kind])
            @endif
        @endforeach
        <details class="forecast-method"><summary>How the forecast is evaluated</summary><p>Seasonal naive repeats the verified count from the same calendar month in the previous year. Twelve consecutive complete months support a forecast. At a {{ $forecastHorizon }}-month horizon, {{ $forecastEvaluation['evaluation_required_months'] }} consecutive months are needed to measure three separate historical test errors. No model settings are selected using those outcomes. For each test target, training ends {{ $forecastHorizon }} month(s) earlier. The comparison is against a three-month moving-average forecast using the same origin and horizon. Net migration is predicted arrivals minus predicted departures; negative results are retained. Net errors are measured after subtraction, not by adding component MAEs. An available forecast can have no measured error yet. @if($forecastNextRefreshLabel)The source window stays fixed within the quarter; the target advances each month. Corrections require another review immediately.@endif Demo data, unverified months and duplicate-affected months stay outside fitting. Reviewed zero-entry gaps in RBI history remain valid arrival counts.</p></details>
    </section>
</details>
@endif

<details id="forecast-records" class="dashboard-card migration-forecast-card forecast-secondary" @if(request()->boolean('review') || session('status') || $errors->any()) open @endif>
    <summary>Check the records used</summary>
    <section class="forecast-details-section" aria-labelledby="forecast-history-title">
    <header class="dashboard-card-header"><div><span class="dashboard-eyebrow">Data readiness</span><h2 id="forecast-history-title">Review monthly migration reporting</h2>
        <p>Review dated departures from Moved Out here. Arrival counts and empty months between recorded reports are reviewed separately in the 3-month RBI arrival form. Missing departure checks remain unverified.</p></div></header>
    @if($selectedBarangayId)<p class="forecast-note"><a href="{{ route('rbi.arrivals.index',['barangay_id'=>$selectedBarangayId]) }}">Open submitted RBI 3-month arrival review</a></p>@endif
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
        <p class="forecast-note">Changed source records automatically need another review. Possible duplicate events must be resolved before verification. Every confirmation and reopening is recorded in Registry Activity History.</p>
        <div class="table-wrap forecast-history-table"><table><thead><tr><th>Month</th><th>Arrivals</th><th>Departures</th><th>Status</th><th>Review reporting</th></tr></thead><tbody>
        @foreach(array_reverse($forecastSource['rows']) as $month)
            @php
                $arrival = $arrivalMonths[$month['month']];
                $complete = $month['verified'];
                $duplicates = $month['duplicates'] + $arrival['duplicates'];
            @endphp
            <tr><td data-label="Month">{{ $month['label'] }}</td>
                <td data-label="Arrivals">{{ $arrival['count'] }}@if($arrival['demo_count'])<small class="forecast-cell-note">{{ $arrival['demo_count'] }} synthetic events excluded</small>@endif</td>
                <td data-label="Departures">{{ $month['count'] }}@if($month['demo_count'])<small class="forecast-cell-note">{{ $month['demo_count'] }} synthetic events excluded</small>@endif</td>
                <td data-label="Status">
                    @foreach(['Arrivals' => $arrival, 'Departures' => $month] as $label => $sourceRow)
                        <div class="forecast-status-line"><span class="forecast-status {{ $sourceRow['verified'] ? 'is-complete' : '' }}">{{ $label }}: {{ $sourceRow['status'] }}</span>@if($sourceRow['verified_at'])<small class="forecast-cell-note">{{ $sourceRow['verified_by'] ?: 'Staff account' }} · {{ $sourceRow['verified_at'] }}</small>@endif</div>
                    @endforeach
                </td>
                <td><details class="forecast-review"><summary>{{ $complete ? 'Review / reopen' : 'Review month' }}</summary>
                    <p>Check all dated Moved Out departures for {{ $month['label'] }}. Arrival verification comes from the RBI arrival review form.</p>
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
