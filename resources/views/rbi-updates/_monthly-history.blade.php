<section class="workflow-card report-history" id="report-history" aria-labelledby="report-history-heading">
    <header class="rbi-history-header">
        <h2 id="report-history-heading">Monthly RBI Form History</h2>
    </header>

    @if($rbiUpdates->isNotEmpty() || $reportHistorySearch !== '')
        <form class="report-table-search" method="GET" action="{{ route('barangay.rbi-updates.history') }}#report-history">
            <div>
                <label for="rbi-report-search">Search reports</label>
                <div class="report-search-input">
                    <x-app-icon name="search" aria-hidden="true" />
                    <input id="rbi-report-search" name="report_search" type="search" value="{{ $reportHistorySearch }}" placeholder="Reporting month or status" maxlength="64">
                </div>
            </div>
            <button type="submit" class="button secondary-button">Search</button>
            @if($reportHistorySearch !== '')
                <a href="{{ route('barangay.rbi-updates.history') }}#report-history" class="button secondary-button">Clear</a>
            @endif
        </form>
    @endif

    @if($reportHistoryPage->isEmpty())
        <div class="rbi-history-empty">
            @if($reportHistorySearch !== '')
                <strong>No reports match your search</strong>
                <p>Try a reporting month, Draft, or Submitted.</p>
            @else
                <strong>No monthly RBI forms created yet</strong>
                <a class="button" href="{{ route('barangay.rbi-updates.residents', ['new' => 1]) }}">Create monthly form</a>
            @endif
        </div>
    @else
        <p class="report-table-hint">Scroll across the table for counts and actions.</p>
        <div class="table-wrap report-table-wrap" tabindex="0" role="region" aria-label="Monthly RBI reports">
            <table class="municipal-data-table report-table">
                <thead>
                    <tr>
                        <th scope="col">Reporting month</th>
                        <th scope="col" class="table-cell-end">Families</th>
                        <th scope="col" class="table-cell-end">Inhabitants</th>
                        <th scope="col" class="table-cell-end">Deceased</th>
                        <th scope="col" class="table-cell-end">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reportHistoryPage as $report)
                        @php
                            $rows = $report->rows ?? [];
                            $familyCount = collect($rows)->map(fn ($row, $index) => \App\Support\RbiFamilyIdentity::key($row, $index))->unique()->count();
                            $period = $report->reporting_month?->format('F Y') ?: 'Month not set';
                        @endphp
                        <tr>
                            <th scope="row">
                                <a href="{{ route('rbi-updates.show', $report) }}" aria-label="View {{ $period }} form">{{ $period }}</a>
                                @if($report->submitted_at)
                                    <small>Submitted {{ $report->submitted_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</small>
                                @elseif($report->created_at)
                                    <small>Saved {{ $report->created_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</small>
                                @endif
                            </th>
                            <td class="table-cell-end">{{ number_format($familyCount) }}</td>
                            <td class="table-cell-end">{{ number_format(count($rows)) }}</td>
                            <td class="table-cell-end">{{ number_format(count($report->deceased_rows ?? [])) }}</td>
                            <td class="table-cell-end"><x-status-badge :status="$report->status">{{ $report->statusLabel() }}</x-status-badge></td>
                            <td class="report-table-action-cell">
                                <div class="report-table-actions">
                                    <a href="{{ route($rbiFormRoute, ['edit' => $report->id]) }}">{{ $report->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue draft' : 'Update form' }}</a>
                                    <details class="rbi-history-more">
                                        <summary aria-label="More actions for {{ $period }}">More actions <x-app-icon name="chevron-down" /></summary>
                                        <div class="rbi-history-action-menu" aria-label="Actions for {{ $period }}">
                                            @if($rows !== [] || $report->status !== App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)
                                                <div class="rbi-history-menu-group">
                                                    <p>Workflow</p>
                                                    <div class="rbi-history-workflow">
                                                        @include('rbi-updates._registry-action', ['report' => $report])
                                                        @include('rbi-updates._submit-action', ['report' => $report])
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="rbi-history-menu-group rbi-history-downloads">
                                                <a href="{{ route('rbi-updates.export-pdf', $report) }}"><x-app-icon name="download" />Download PDF</a>
                                                <a href="{{ route('rbi-updates.export-excel', $report) }}"><x-app-icon name="download" />Download Excel</a>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <nav class="rbi-history-pagination" aria-label="Monthly RBI form pages">
            <span>Showing <strong>{{ $reportHistoryPage->firstItem() }}–{{ $reportHistoryPage->lastItem() }}</strong> of {{ $reportHistoryPage->total() }} reports</span>
            @if($reportHistoryPage->hasPages())
                <div class="rbi-history-page-links">
                    @if($reportHistoryPage->onFirstPage())
                        <span aria-disabled="true">Previous</span>
                    @else
                        <a href="{{ $reportHistoryPage->previousPageUrl() }}" rel="prev">Previous</a>
                    @endif
                    <span>Page {{ $reportHistoryPage->currentPage() }} of {{ $reportHistoryPage->lastPage() }}</span>
                    @if($reportHistoryPage->hasMorePages())
                        <a href="{{ $reportHistoryPage->nextPageUrl() }}" rel="next">Next</a>
                    @else
                        <span aria-disabled="true">Next</span>
                    @endif
                </div>
            @endif
        </nav>
    @endif
</section>
