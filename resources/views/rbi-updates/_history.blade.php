        <nav class="rbi-history-sections" data-rbi-history-navigation aria-label="Report history sections">
            <a href="#report-history" aria-current="page">Monthly forms <span>{{ $reportHistoryPage->total() }}</span></a>
            @if($savedHouseholdPage->total() > 0)
                <a href="#saved-inhabitant-records">Pending consolidation <span>{{ $savedHouseholdPage->total() }}</span></a>
            @endif
            @if($consolidatedHouseholdPage->total() > 0)
                <a href="#consolidated-inhabitant-history">Consolidation history</a>
            @endif
            <a href="#registry-activity-title">Registry activity</a>
        </nav>

        @include('rbi-updates._monthly-history')

        <details class="rbi-history-tools"><summary><span>Other reporting tools</span><small>Arrival review &amp; zero-activity reports</small></summary><div><section class="workflow-card"><h2>Three-month arrival review</h2><p>Review the arrival months recorded on submitted RBI forms. Classify each entry as In-migration, Birth / Newborn or Late registration before completing the arrival review.</p><a class="button secondary-button" href="{{ route('rbi.arrivals.index') }}">Open 3-month arrival review</a><details><summary>Month with no new or deceased residents?</summary><form method="POST" action="{{ route('barangay.rbi-updates.zero-activity') }}">@csrf<label>Reporting month<input type="month" name="reporting_month" max="{{ now()->format('Y-m') }}" required></label><label><input type="checkbox" name="confirmed" value="1" required> No new or deceased resident entries were recorded this month.</label><button type="submit" class="secondary-button">Create zero-activity draft for signing</button></form></details></section></div></details>
        @include('rbi-updates._saved-inhabitants')
        @include('rbi-updates._consolidated-inhabitants')
