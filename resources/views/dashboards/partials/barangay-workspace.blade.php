<section class="brgy-workspace" aria-label="Barangay workspace">
    <div class="brgy-workspace-panels">
        <section class="brgy-work-card brgy-reporting-card" aria-labelledby="brgy-reporting-title">
            <header><h2 id="brgy-reporting-title">RBI Forms</h2><a class="brgy-text-link" href="{{ route('barangay.rbi-updates.index') }}">Open forms &rarr;</a></header>
            <div class="brgy-report-counts"><div><strong>{{ $submittedRbi }}</strong><span>Submitted</span></div><div><strong>{{ $draftRbi }}</strong><span>Drafts</span></div></div>
            <div class="brgy-latest-report">
                @if ($rbiUpdates->isNotEmpty())
                    @php($latestRbi = $rbiUpdates->first())
                    <span>Latest report</span><strong>{{ optional($latestRbi->reporting_month)->format('F Y') ?: 'Month not set' }}</strong><x-status-badge :status="$latestRbi->status">{{ $latestRbi->statusLabel() }}</x-status-badge>
                @else
                    <span>No monthly report yet</span><a class="brgy-text-link" href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}">Create RBI form &rarr;</a>
                @endif
            </div>
        </section>
        <section class="brgy-work-card brgy-pending-card" aria-labelledby="brgy-pending-title">
            <header><h2 id="brgy-pending-title">Pending tasks</h2><span class="brgy-status-pill {{ $residentApprovalRequests->count() + $pendingDocuments === 0 ? 'is-clear' : '' }}">{{ $residentApprovalRequests->count() + $pendingDocuments }} pending</span></header>
            <div class="brgy-task-list">
                <a href="{{ route('barangay.resident-approvals.index') }}"><span class="brgy-task-copy"><strong>Resident registrations</strong></span><span class="brgy-task-count">{{ $residentApprovalRequests->count() }}</span><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('barangay.document-requests.index') }}"><span class="brgy-task-copy"><strong>Document requests</strong></span><span class="brgy-task-count">{{ $pendingDocuments }}</span><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('barangay.document-requests.index') }}#document-requests"><span class="brgy-task-copy"><strong>Verify payments</strong><small>Review GCash proofs and in-person payments in each request</small></span><span class="brgy-link-arrow" aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    </div>
</section>
