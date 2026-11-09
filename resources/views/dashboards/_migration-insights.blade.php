<section class="migration-insights dashboard-card" aria-labelledby="migration-insights-title">
    <header>
        <span class="dashboard-eyebrow">Understand the records</span>
        <h2 id="migration-insights-title">Trend interpretation &amp; review alerts</h2>
        <p class="migration-insights-mode">{{ $analyticsInsights['ai_used'] ? 'AI-assisted focus · verified calculations' : 'Automated statistical summary · rule-based checks' }}</p>
    </header>
    <p>Pure PHP statistics ug explicit review rules. Walay AI model o external API nga gigamit.</p>
    <ul class="migration-insights-observations">
        @foreach ($analyticsInsights['observations'] as $observation)<li>{{ $observation }}</li>@endforeach
    </ul>
    <details class="migration-insights-notes">
        <summary>Sources, assumptions and limitations</summary>
        <p>Arrival entries use the first-save month of classified RBI rows, not a verified physical arrival date. Departures use recorded movement dates. These measures have different date bases; their difference does not establish actual population change.</p>
        <p>Comparisons use the last two completed calendar months within the selected year. Current and future months are excluded from these comparisons. A zero means no counted records, not confirmed zero migration. Reporting coverage may differ between months.</p>
        <p>Household alerts use stored encoding dates in the selected year. The threshold of 5 profiles per household per day is a review rule, not a learned anomaly model or evidence of fraud. Current moved-out profiles without dates are checked across all years. No cause, job purpose or clearance-related movement is inferred.</p>
    </details>
    <h3>Household encoding review</h3>
    @if ($analyticsInsights['alert_count'] > 0)
        <p>{{ $analyticsInsights['alert_count'] }} household/day groups flagged; showing up to 10 largest groups. Verify against the RBI source and household list before making changes.</p>
        <div class="table-wrap clean-table-wrap"><table class="clean-table">
            <thead><tr><th>Barangay</th><th>Household number</th><th>Encoding date</th><th>Profiles encoded</th></tr></thead>
            <tbody>@foreach ($analyticsInsights['alerts'] as $alert)
                <tr><td>{{ $alert['barangay'] }}</td><td>{{ $alert['household'] }}</td><td>{{ $alert['day'] }}</td><td>{{ $alert['count'] }}</td></tr>
            @endforeach</tbody>
        </table></div>
    @else
        <p>No household/day groups meet the 5-profile review threshold in this reporting year. This does not establish that all records are error-free.</p>
    @endif
</section>
