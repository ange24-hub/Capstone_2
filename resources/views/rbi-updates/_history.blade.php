        <div class="workflow-card report-history rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="report-history">
            <div class="history-heading"><div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Saved reports</div><h2 class="section-title text-lg font-semibold text-slate-900">Monthly RBI Form History</h2></div>
            <p>Review saved reports, add members to Consolidated RBI, or submit a completed monthly form.</p>
            @if ($rbiSection === 'history')
                @include('rbi-updates._saved-inhabitants')
            @endif
            @if ($rbiUpdates->isEmpty() && $newInhabitantRecords->isEmpty())
                <p>No monthly RBI forms created yet.</p>
            @endif
            @if ($rbiUpdates->isNotEmpty())
                <div class="table-wrap official-report-history w-full overflow-x-auto rounded-xl border border-slate-200">
                    <table>
                        <thead><tr><th>Month</th><th>Families</th><th>Inhabitants</th><th>Status</th><th>Submitted</th><th>Actions</th></tr></thead>
                        <tbody>
                            @foreach ($rbiUpdates as $report)
                                @php($familyCount = collect($report->rows ?? [])->pluck('household_head')->filter()->unique()->count())
                                <tr>
                                    <td>{{ optional($report->reporting_month)->format('F Y') ?: 'Not set' }}</td>
                                    <td>{{ $familyCount }}</td>
                                    <td>{{ count($report->rows ?? []) }}</td>
                                    <td><x-status-badge :status="$report->status">{{ $report->statusLabel() }}</x-status-badge></td>
                                    <td>{{ $report->submitted_at?->copy()->timezone('Asia/Manila')->format('M d, Y h:i A') ?: 'Not submitted' }}</td>
                                    <td><div class="rbi-history-actions">
                                        <a href="{{ route('rbi-updates.show', $report) }}">View form</a>
                                        <a href="{{ route($rbiFormRoute, ['edit' => $report->id]) }}">{{ $report->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue draft' : 'Update form' }}</a>
                                        <a href="{{ route('rbi-updates.export-pdf', $report) }}">Download PDF</a>
                                        <a href="{{ route('rbi-updates.export-word', $report) }}">Download Word</a>
                                        @include('rbi-updates._registry-action', ['report' => $report])
                                        @include('rbi-updates._submit-action', ['report' => $report])
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
