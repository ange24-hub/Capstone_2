@if ($newInhabitantRecords->isNotEmpty())
<section class="saved-report-list" id="saved-inhabitant-records" aria-label="Saved new-inhabitant reports">
    @foreach ($newInhabitantRecords->groupBy(fn ($record) => optional($record->reporting_month)->format('F Y') ?: ($record->month_submitted ?: 'Month not set')) as $month => $monthlyRecords)
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
                    <div class="saved-report-toolbar"><span>Report actions</span><div>
                        <a class="button secondary-button" href="{{ route('registry.new-inhabitant-monthly-reports.pdf', $reportMonth) }}">Download PDF</a>
                        @if($monthlyRecords->every(fn($member) => filled($member->submitted_rbi_update_id)))
                            <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Submitted to Municipal DILG</span>
                        @else
                            <form method="POST" action="{{ route('registry.new-inhabitant-monthly-reports.submit', $reportMonth) }}" onsubmit="return confirm('Submit this saved report to Municipal DILG?')">@csrf<button type="submit">Submit to Municipal DILG</button></form>
                        @endif
                    </div></div>
                @endif
                @foreach ($families as $household => $members)
                    <section class="saved-household">
                        <div class="saved-household-heading"><h3>Household {{ $household }}</h3><span>{{ $members->count() }} {{ Str::plural('member', $members->count()) }}</span></div>
                        <div class="saved-members">
                            @foreach($members as $member)
                                <div class="saved-member">
                                    <div class="saved-member-name"><span class="member-initial" aria-hidden="true">{{ mb_substr($member->first_name, 0, 1) }}</span><strong>{{ $member->first_name }} {{ $member->last_name }}</strong></div>
                                    <div class="saved-member-actions">
                                        <a class="member-edit" href="{{ route('registry.new-inhabitants.edit', $member) }}">Edit</a>
                                        <form method="POST" action="{{ route('registry.new-inhabitants.destroy', $member) }}" onsubmit="return confirm('Delete this member?')">@csrf @method('DELETE')<button type="submit" class="member-delete">Delete</button></form>
                                        @if($member->active_inhabitant_id)
                                            <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Added to Consolidated</span>
                                        @else
                                            <form method="POST" action="{{ route('registry.new-inhabitants.add-to-active', $member) }}" onsubmit="return confirm('Add this member to their household in Consolidated / All Registered?')">@csrf<button type="submit">Add to Consolidated / All Registered</button></form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="saved-household-footer">
                            @if($members->every(fn($member) => filled($member->active_inhabitant_id)))
                                <span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Added to Active Household</span>
                                <form method="POST" action="{{ route('registry.new-inhabitant-families.remove-from-active') }}" onsubmit="return confirm('Remove this family from Active Household?')">@csrf @method('DELETE')<input type="hidden" name="household_number" value="{{ $household }}"><input type="hidden" name="reporting_month" value="{{ optional($members->first()->reporting_month)->format('Y-m') }}"><button type="submit" class="member-delete">Remove from Active</button></form>
                            @elseif($familyMonth = optional($members->first()->reporting_month)->format('Y-m'))
                                <span>Add every member of this household together.</span>
                                <form method="POST" action="{{ route('registry.new-inhabitant-families.add-to-active') }}" onsubmit="return confirm('Add this family to Active Household?')">@csrf<input type="hidden" name="household_number" value="{{ $household }}"><input type="hidden" name="reporting_month" value="{{ $familyMonth }}"><button type="submit">Add to Active Household</button></form>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </details>
    @endforeach
</section>
@endif
