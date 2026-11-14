        <nav class="rbi-history-sections" data-rbi-history-navigation aria-label="Report history sections">
            <a href="#report-history" aria-current="page">Monthly forms <span>{{ $reportHistoryPage->total() }}</span></a>
            @if($submittedAndAddedReports->isNotEmpty())
                <a href="#submitted-and-added">Submitted and Added <span>{{ $submittedAndAddedReports->count() }}</span></a>
            @endif
            @if($savedHouseholdPage->total() > 0)
                <a href="#saved-inhabitant-records">Pending consolidation <span>{{ $savedHouseholdPage->total() }}</span></a>
            @endif
            @if($consolidatedHouseholdPage->total() > 0)
                <a href="#consolidated-inhabitant-history">Consolidation history</a>
            @endif
            <a href="#registry-activity-title">Registry activity</a>
        </nav>

        @include('rbi-updates._monthly-history')
        @include('rbi-updates._submitted-and-added')

        <details class="rbi-history-tools">
            <summary>Month with no new or deceased residents?</summary>
            <div>
                <form method="POST" action="{{ route('barangay.rbi-updates.zero-activity') }}">
                    @csrf
                    <label>Reporting month<input type="month" name="reporting_month" max="{{ now()->format('Y-m') }}" required></label>
                    <label><input type="checkbox" name="confirmed" value="1" required> No new or deceased resident entries were recorded this month.</label>
                    <button type="submit" class="secondary-button">Create zero-activity draft for signing</button>
                </form>
            </div>
        </details>
        @include('rbi-updates._saved-inhabitants')
        @include('rbi-updates._consolidated-inhabitants')
