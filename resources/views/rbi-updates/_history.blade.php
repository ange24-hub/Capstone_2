        <nav class="rbi-history-sections" aria-label="Report history sections">
            <a href="#report-history">Monthly forms <span>{{ $reportHistoryPage->total() }}</span></a>
            @if($savedHouseholdPage->total() > 0)
                <a href="#saved-inhabitant-records">Pending consolidation <span>{{ $savedHouseholdPage->total() }}</span></a>
            @endif
            @if($consolidatedHouseholdPage->total() > 0)
                <a href="#consolidated-inhabitant-history">Consolidation history</a>
            @endif
            <a href="#registry-activity-title">Registry activity</a>
        </nav>

        <section class="workflow-card report-history rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6" id="report-history" aria-labelledby="report-history-heading">
            <div class="rbi-history-header">
                <div>
                    <h2 id="report-history-heading" class="section-title text-lg font-semibold text-slate-900">Monthly RBI Form History</h2>
                    <p>Open, update, or submit each month's RBI form.</p>
                </div>
                @if($reportHistoryPage->total() > 0)
                    <span class="rbi-history-total">{{ number_format($reportHistoryPage->total()) }} {{ Str::plural('report', $reportHistoryPage->total()) }}</span>
                @endif
            </div>

            @if ($reportHistoryPage->total() === 0)
                <div class="rbi-history-empty"><span aria-hidden="true"><x-app-icon name="calendar" /></span><strong>No monthly RBI forms created yet</strong><p>Saved forms will appear here by reporting month.</p></div>
            @endif

            @if ($reportHistoryPage->total() > 0)
                <div class="rbi-history-list" aria-label="Monthly RBI forms">
                    @foreach ($reportHistoryPage as $report)
                        @php($familyCount = collect($report->rows ?? [])->pluck('household_head')->filter()->unique()->count())
                        <article class="rbi-history-card">
                            <div class="rbi-history-card-main">
                                <div class="rbi-history-month">
                                    <span class="rbi-history-month-icon" aria-hidden="true"><x-app-icon name="calendar" /></span>
                                    <div><h3>{{ optional($report->reporting_month)->format('F Y') ?: 'Month not set' }}</h3></div>
                                </div>
                                <x-status-badge :status="$report->status">{{ $report->statusLabel() }}</x-status-badge>
                            </div>
                            <div class="rbi-history-card-meta">
                                <span><strong>{{ $familyCount }}</strong> {{ Str::plural('family', $familyCount) }}</span>
                                <span><strong>{{ count($report->rows ?? []) }}</strong> {{ Str::plural('inhabitant', count($report->rows ?? [])) }}</span>
                                @if($report->submitted_at)
                                    <span class="rbi-history-submitted"><x-app-icon name="clock" />Submitted {{ $report->submitted_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</span>
                                @endif
                            </div>
                            <div class="rbi-history-card-actions">
                                <div class="rbi-history-main-actions">
                                    <a class="rbi-history-view" href="{{ route('rbi-updates.show', $report) }}">View form</a>
                                    <a class="rbi-history-edit" href="{{ route($rbiFormRoute, ['edit' => $report->id]) }}">{{ $report->status === App\Models\BarangayRbiUpdate::STATUS_DRAFT ? 'Continue draft' : 'Update form' }}</a>
                                    <div class="rbi-history-workflow">
                                        @include('rbi-updates._registry-action', ['report' => $report])
                                        @include('rbi-updates._submit-action', ['report' => $report])
                                    </div>
                                </div>
                                <details class="rbi-history-more">
                                    <summary>Downloads</summary>
                                    <div class="rbi-history-downloads" aria-label="Download {{ optional($report->reporting_month)->format('F Y') ?: 'monthly' }} form">
                                        <a href="{{ route('rbi-updates.export-pdf', $report) }}">Download PDF</a>
                                        <a href="{{ route('rbi-updates.export-word', $report) }}">Download Word</a>
                                    </div>
                                </details>
                            </div>
                        </article>
                    @endforeach
                </div>
                <nav class="rbi-history-pagination" aria-label="Monthly RBI form pages">
                    <span>Showing <strong>{{ $reportHistoryPage->firstItem() }}–{{ $reportHistoryPage->lastItem() }}</strong> of {{ $reportHistoryPage->total() }} reports</span>
                    @if($reportHistoryPage->hasPages())
                        <div class="rbi-history-page-links">
                            @if($reportHistoryPage->onFirstPage())<span aria-disabled="true">Previous</span>@else<a href="{{ $reportHistoryPage->previousPageUrl() }}" rel="prev">Previous</a>@endif
                            @php($historyPageNumbers = collect([1, ...range(max(1, $reportHistoryPage->currentPage() - 1), min($reportHistoryPage->lastPage(), $reportHistoryPage->currentPage() + 1)), $reportHistoryPage->lastPage()])->unique()->sort()->values())
                            @foreach($historyPageNumbers as $number)
                                @if($loop->index > 0 && $number > $historyPageNumbers[$loop->index - 1] + 1)<span class="rbi-history-ellipsis" aria-hidden="true">…</span>@endif
                                @if($number === $reportHistoryPage->currentPage())<span aria-current="page" aria-label="Page {{ $number }}">{{ $number }}</span>@else<a href="{{ $reportHistoryPage->url($number) }}" aria-label="Page {{ $number }}">{{ $number }}</a>@endif
                            @endforeach
                            @if($reportHistoryPage->hasMorePages())<a href="{{ $reportHistoryPage->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
                        </div>
                    @endif
                </nav>
            @endif
        </section>

        @include('rbi-updates._saved-inhabitants')
        @include('rbi-updates._consolidated-inhabitants')
