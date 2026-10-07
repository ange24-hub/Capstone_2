@if ($savedHouseholdPage->total() > 0)
<section class="saved-report-list" id="saved-inhabitant-records" aria-label="Pending consolidation">
    <div class="rbi-history-header"><div><h3>Pending consolidation</h3><p>Add these members to Consolidated / All Registered. Completed members move to Consolidation history below.</p></div></div>
    @include('rbi-updates._household-pagination')
    @foreach ($savedHouseholdPage->getCollection()->groupBy('month') as $month => $pageFamilies)
        @php($monthlyRecords = $savedReportMonths->get($month))
        @php($reportMonth = optional($monthlyRecords->first()->reporting_month)->format('Y-m'))
        @php($families = $monthlyRecords->groupBy('household_number'))
        <details class="saved-report" @if($loop->first) open @endif>
            <summary>
                <span class="report-month"><span class="report-month-icon" aria-hidden="true"><x-app-icon name="form" /></span><span><strong>{{ $month }}</strong><small>Saved new-inhabitant report</small></span></span>
                <span class="report-counts">{{ $families->count() }} {{ Str::plural('family', $families->count()) }} <span>&middot;</span> {{ $monthlyRecords->count() }} {{ Str::plural('member', $monthlyRecords->count()) }}</span>
                <span class="report-expand" aria-hidden="true">&#8964;</span>
            </summary>
            <div class="saved-report-content">
                @if($reportMonth)
                    @include('rbi-updates._saved-report-actions', ['monthlyRecords' => $completeReportMonths->get($month)])
                @endif
                <p class="field-help">Showing {{ $pageFamilies->count() }} of {{ $families->count() }} households for {{ $month }}. Report actions apply to the complete month.</p>
                @foreach ($pageFamilies as $pageFamily)
                    @php($household = $pageFamily['household'])
                    @php($members = $pageFamily['members'])
                    <section class="saved-household">
                        <div class="saved-household-heading"><h3><span class="rbi-household-icon" aria-hidden="true"><x-app-icon name="users" /></span>Household {{ $household }}</h3><span>{{ $members->count() }} {{ Str::plural('member', $members->count()) }}</span></div>
                        <div class="saved-members">
                            @foreach($members as $member)
                                <div class="saved-member">
                                    <div class="saved-member-name"><span class="member-initial" aria-hidden="true">{{ mb_substr($member->first_name, 0, 1) }}</span><strong>{{ $member->first_name }} {{ $member->last_name }}</strong></div>
                                    <div class="saved-member-actions">
                                        <a class="member-edit" href="{{ route('registry.new-inhabitants.edit', $member) }}">Edit</a>
                                        <form method="POST" action="{{ route('registry.new-inhabitants.destroy', $member) }}" onsubmit="return confirm('Delete this member?')">@csrf @method('DELETE')<button type="submit" class="member-delete">Delete</button></form>
                                        <form method="POST" action="{{ route('registry.new-inhabitants.add-to-active', $member) }}" onsubmit="return confirm('Add this member to their household in Consolidated / All Registered?')">@csrf<button type="submit" class="rbi-add-member-action">Add to Consolidated / All Registered</button></form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="saved-household-footer">
                            @if($familyMonth = optional($members->first()->reporting_month)->format('Y-m'))
                                <span>Add every member of this household together.</span>
                                <form method="POST" action="{{ route('registry.new-inhabitant-families.add-to-active') }}" onsubmit="return confirm('Add this family to Active Household?')">@csrf<input type="hidden" name="household_number" value="{{ $household }}"><input type="hidden" name="reporting_month" value="{{ $familyMonth }}"><button type="submit" class="rbi-add-household-action">Add to Active Household</button></form>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </details>
    @endforeach
    @if($savedHouseholdPage->hasPages())
        @include('rbi-updates._household-pagination')
    @endif
</section>
@endif
