@if ($submittedAndAddedReports->isNotEmpty())
    <section class="workflow-card report-history" id="submitted-and-added" aria-labelledby="submitted-and-added-heading">
        <header class="rbi-history-header">
            <div><h2 id="submitted-and-added-heading">Submitted and Added</h2><p>Monthly reports already added to Consolidated RBI.</p></div>
            <span class="rbi-history-total">{{ $submittedAndAddedReports->count() }} {{ Str::plural('report', $submittedAndAddedReports->count()) }}</span>
        </header>
        <div class="rbi-history-list" aria-label="Submitted and added monthly reports">
            @foreach($submittedAndAddedReports as $report)
                @php($period = optional($report->reporting_month)->format('F Y') ?: 'Month not set')
                <article class="rbi-history-card">
                    <div class="rbi-history-card-info">
                        <span class="rbi-history-month-icon" aria-hidden="true"><small>{{ optional($report->reporting_month)->format('M') ?: 'RBI' }}</small><strong>{{ optional($report->reporting_month)->format('Y') ?: '—' }}</strong></span>
                        <div class="rbi-history-card-copy">
                            <div class="rbi-history-card-title"><h3>{{ $period }}</h3><x-status-badge :status="$report->status">Submitted and Added</x-status-badge></div>
                            <div class="rbi-history-card-meta"><span><strong>{{ count($report->rows ?? []) }}</strong> {{ Str::plural('inhabitant', count($report->rows ?? [])) }}</span></div>
                            <div class="rbi-history-card-dates"><span>Added {{ $report->consolidated_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</span></div>
                        </div>
                    </div>
                    <div class="rbi-history-card-actions">
                        <a class="rbi-history-view" href="{{ route('rbi-updates.show', $report) }}" aria-label="View {{ $period }} form"><x-app-icon name="document" />View form</a>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
@endif
