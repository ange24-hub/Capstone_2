@if ($consolidatedHouseholdPage->total() > 0)
<section class="saved-report-list" id="consolidated-inhabitant-history" aria-labelledby="consolidated-history-heading">
    <div class="rbi-history-header">
        <div><h3 id="consolidated-history-heading">Consolidation history</h3><p>Members already added to Consolidated / All Registered, grouped by reporting month and household.</p></div>
        <a class="button secondary-button" href="{{ route('barangay.registry.active') }}">View Consolidated / All Registered</a>
    </div>
    @include('rbi-updates._household-pagination', ['savedHouseholdPage' => $consolidatedHouseholdPage])
    @foreach ($consolidatedHouseholdPage->getCollection()->groupBy('month') as $month => $pageFamilies)
        @php($monthlyRecords = $consolidatedReportMonths->get($month))
        @php($reportMonth = optional($monthlyRecords->first()->reporting_month)->format('Y-m'))
        <details class="saved-report" @if($loop->first) open @endif>
            <summary>
                <span class="report-month"><span class="report-month-icon" aria-hidden="true"><x-app-icon name="form" /></span><span><strong>{{ $month }}</strong><small>Consolidation history</small></span></span>
                <span class="report-counts">{{ $monthlyRecords->count() }} {{ Str::plural('member', $monthlyRecords->count()) }}</span>
                <span class="report-expand" aria-hidden="true">&#8964;</span>
            </summary>
            <div class="saved-report-content">
                @if($reportMonth)
                    @include('rbi-updates._saved-report-actions', ['monthlyRecords' => $completeReportMonths->get($month)])
                @endif
                @foreach ($pageFamilies as $family)
                    <section class="saved-household">
                        <div class="saved-household-heading"><h3><span class="rbi-household-icon" aria-hidden="true"><x-app-icon name="users" /></span>Household {{ $family['household'] }}</h3><span>{{ $family['members']->count() }} {{ Str::plural('member', $family['members']->count()) }}</span></div>
                        <div class="saved-members">
                            @foreach ($family['members'] as $member)
                                <div class="saved-member">
                                    <div class="saved-member-name"><span class="member-initial" aria-hidden="true">{{ mb_substr($member->first_name, 0, 1) }}</span><strong>{{ $member->first_name }} {{ $member->last_name }}</strong></div>
                                    <div class="saved-member-actions">
                                        <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Added to Consolidated</span>
                                        @if($member->added_to_active_at)<small>Added {{ $member->added_to_active_at->copy()->timezone('Asia/Manila')->format('M d, Y h:i A') }}</small>@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </details>
    @endforeach
    @if($consolidatedHouseholdPage->hasPages())
        @include('rbi-updates._household-pagination', ['savedHouseholdPage' => $consolidatedHouseholdPage])
    @endif
</section>
@endif
