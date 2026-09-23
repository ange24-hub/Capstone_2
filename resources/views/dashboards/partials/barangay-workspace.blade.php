<section class="brgy-workspace" aria-label="Barangay workspace">
    <div class="brgy-workspace-panels">
        <section class="brgy-work-card brgy-pending-card" aria-labelledby="brgy-pending-title">
            <header><h2 id="brgy-pending-title">Pending tasks</h2><span class="brgy-status-pill">{{ $residentApprovalRequests->count() + $pendingDocuments }} pending</span></header>
            <div class="brgy-task-list">
                <a href="{{ route('barangay.resident-approvals.index') }}"><span class="brgy-tool-icon"><x-app-icon name="users" /></span><span class="brgy-task-copy"><strong>Resident registrations</strong><small>Review and verify accounts</small></span><span class="brgy-task-count">{{ $residentApprovalRequests->count() }}</span><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('barangay.document-requests.index') }}"><span class="brgy-tool-icon"><x-app-icon name="document" /></span><span class="brgy-task-copy"><strong>Document requests</strong><small>Process pending requests</small></span><span class="brgy-task-count">{{ $pendingDocuments }}</span><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
        <section class="brgy-work-card brgy-reporting-card" aria-labelledby="brgy-reporting-title">
            <header><h2 id="brgy-reporting-title">Monthly RBI forms</h2><a class="brgy-text-link" href="{{ route('barangay.rbi-updates.index') }}">Manage forms &rarr;</a></header>
            <div class="brgy-report-counts"><div><strong>{{ $submittedRbi }}</strong><span>Submitted</span></div><div><strong>{{ $draftRbi }}</strong><span>Drafts</span></div></div>
            <div class="brgy-latest-report">
                @if ($rbiUpdates->isNotEmpty())
                    @php($latestRbi = $rbiUpdates->first())
                    <span>Latest report</span><strong>{{ optional($latestRbi->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><span class="brgy-status-pill">{{ $latestRbi->statusLabel() }}</span>
                @else
                    <span>No monthly report yet</span><a class="brgy-text-link" href="{{ $newInhabitantsUrl }}">Create report &rarr;</a>
                @endif
            </div>
        </section>
    </div>
    <nav class="brgy-quick-links" aria-label="Quick access">
        @foreach ([['concerns.index', 'activity', 'Resident concerns'], ['barangay.rbi-updates.index', 'form', 'RBI forms'], ['spatial.index', 'map', 'Household map']] as [$routeName, $icon, $title])
            <a href="{{ route($routeName) }}"><span class="brgy-tool-icon"><x-app-icon :name="$icon" /></span><strong>{{ $title }}</strong><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
        @endforeach
    </nav>
    <div class="brgy-office-summary" aria-label="Assigned office">
        <div><span>Assigned office</span><strong>Barangay {{ $barangay->name }}</strong></div>
        <div><span>Secretary</span><strong>{{ $barangay->secretary_name ?: auth()->user()->name }}</strong></div>
        <div><span>Punong Barangay</span><strong>{{ $barangay->punong_barangay_name ?: 'Not yet configured' }}</strong></div>
    </div>
</section>
