<section class="workflow-card report-history" id="report-history" aria-labelledby="report-history-heading">
    <header class="rbi-history-header">
        <div><h2 id="report-history-heading">Monthly RBI Form History</h2><p>Open a form, or use More actions to submit and download.</p></div>
        <span class="rbi-history-total">{{ number_format($reportHistoryPage->total()) }} {{ Str::plural('report', $reportHistoryPage->total()) }}</span>
    </header>
    @if($reportHistoryPage->isEmpty())
        <div class="rbi-history-empty"><span aria-hidden="true"><x-app-icon name="calendar" /></span><strong>No monthly RBI forms created yet</strong><p>Start a monthly form. Your saved reports will appear here.</p><a class="button" href="{{ route('barangay.rbi-updates.residents',['new'=>1]) }}">Create monthly form</a></div>
    @else
    <div class="rbi-history-list" aria-label="Monthly RBI forms">
        @foreach($reportHistoryPage as $report)
            @php
                $familyCount = collect($report->rows ?? [])->map(fn ($row,$index) => \App\Support\RbiFamilyIdentity::key($row,$index))->unique()->count();
                $period = optional($report->reporting_month)->format('F Y') ?: 'Month not set';
            @endphp
            <article class="rbi-history-card">
                <div class="rbi-history-card-info">
                    <span class="rbi-history-month-icon" aria-hidden="true"><small>{{ optional($report->reporting_month)->format('M') ?: 'RBI' }}</small><strong>{{ optional($report->reporting_month)->format('Y') ?: '—' }}</strong></span>
                    <div class="rbi-history-card-copy">
                        <div class="rbi-history-card-title"><h3>{{ $period }}</h3><x-status-badge :status="$report->status">{{ $report->statusLabel() }}</x-status-badge></div>
                        <div class="rbi-history-card-meta"><span><strong>{{ $familyCount }}</strong> {{ Str::plural('family',$familyCount) }}</span><span><strong>{{ count($report->rows ?? []) }}</strong> {{ Str::plural('inhabitant',count($report->rows ?? [])) }}</span>@if(count($report->deceased_rows ?? [])>0)<span><strong>{{ count($report->deceased_rows) }}</strong> deceased</span>@endif</div>
                        <div class="rbi-history-card-dates">@if($report->submitted_at)<span>Submitted {{ $report->submitted_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</span>@elseif($report->created_at)<span>First saved {{ $report->created_at->copy()->timezone('Asia/Manila')->format('M d, Y') }}</span>@endif</div>
                    </div>
                </div>
                <div class="rbi-history-card-actions">
                    <a class="rbi-history-view" href="{{ route('rbi-updates.show',$report) }}" aria-label="View {{ $period }} form"><x-app-icon name="document" />View form</a>
                    <a class="rbi-history-edit" href="{{ route($rbiFormRoute,['edit'=>$report->id]) }}">{{ $report->status===App\Models\BarangayRbiUpdate::STATUS_DRAFT?'Continue draft':'Update form' }}</a>
                    <details class="rbi-history-more">
                        <summary aria-label="More actions for {{ $period }}">More actions<x-app-icon name="chevron-down" /></summary>
                        <div class="rbi-history-action-menu" aria-label="Actions for {{ $period }}">
                            @if(count($report->rows ?? [])>0 || $report->status!==App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)
                            <div class="rbi-history-menu-group"><p>Workflow</p><div class="rbi-history-workflow">@include('rbi-updates._registry-action',['report'=>$report])@include('rbi-updates._submit-action',['report'=>$report])</div></div>
                            @endif
                            <div class="rbi-history-menu-group rbi-history-downloads"><p>Download a copy</p><a href="{{ route('rbi-updates.export-pdf',$report) }}"><x-app-icon name="download" />Download PDF</a><a href="{{ route('rbi-updates.export-excel',$report) }}"><x-app-icon name="download" />Download Excel</a></div>
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
            @php($historyPageNumbers=collect([1,...range(max(1,$reportHistoryPage->currentPage()-1),min($reportHistoryPage->lastPage(),$reportHistoryPage->currentPage()+1)),$reportHistoryPage->lastPage()])->unique()->sort()->values())
            @foreach($historyPageNumbers as $number)
                @if($loop->index>0 && $number>$historyPageNumbers[$loop->index-1]+1)<span class="rbi-history-ellipsis" aria-hidden="true">…</span>@endif
                @if($number===$reportHistoryPage->currentPage())<span aria-current="page" aria-label="Page {{ $number }}">{{ $number }}</span>@else<a href="{{ $reportHistoryPage->url($number) }}" aria-label="Page {{ $number }}">{{ $number }}</a>@endif
            @endforeach
            @if($reportHistoryPage->hasMorePages())<a href="{{ $reportHistoryPage->nextPageUrl() }}" rel="next">Next</a>@else<span aria-disabled="true">Next</span>@endif
        </div>
        @endif
    </nav>
    @endif
</section>
