<thead class="rbi-family-builder-heading"><tr><th colspan="16"><strong>MONTHLY NEW INHABITANTS — CONSOLIDATED FAMILY REPORT</strong></th></tr></thead>
<tbody><tr><td colspan="16">
<form id="monthly-new-report" method="POST" action="{{ route('registry.new-inhabitant-monthly-reports.store') }}">
    @csrf
    <div class="rbi-month-controls"><label>Reporting month <input id="family-reporting-month" name="reporting_month" type="month" value="{{ request('reporting_month', now()->format('Y-m')) }}" required></label><button id="add-family-form" type="button">+ Add another family form</button></div>
    <div id="new-family-forms"></div>
    <div class="monthly-report-submit"><strong>All family forms above will be saved together as one report for the selected month.</strong><button type="submit">Save Monthly Report</button></div>
</form>

<template id="new-family-template">
<section class="new-family-form">
    <div class="new-family-form-head"><strong>HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)</strong><div class="family-household-picker"><label>Active household head<select name="families[__INDEX__][existing_household_id]" data-household-select><option value="">New household — enter below</option>@foreach($activeHouseholdOptions as $option)<option value="{{ $option['id'] }}" data-number="{{ $option['number'] }}">{{ $option['head'] }} (HH {{ $option['number'] }})</option>@endforeach</select></label><label>Household No.<input name="families[__INDEX__][household_number]" data-household-number></label><button type="button" class="danger-button" data-remove-family>Remove form</button></div></div>
    <div class="new-family-location"><span>A. REGION: <b>VIII</b></span><span>D. BARANGAY: <b>{{ strtoupper($registryBarangay->name) }}</b></span><span>B. PROVINCE: <b>SOUTHERN LEYTE</b></span><span>C. CITY/MUNICIPALITY: <b>TOMAS OPPUS</b></span></div>
    <div class="table-wrap rbi-family-entry-table"><table>
        <thead><tr><th>LAST</th><th>FIRST</th><th>MIDDLE</th><th>Qualifier</th><th>RELATIONSHIP TO HH HEAD</th><th>COMPLETE ADDRESS</th><th>PLACE OF BIRTH</th><th>DATE OF BIRTH</th><th>AGE</th><th>SEX</th><th>CIVIL STATUS</th><th>School Level</th><th>RELIGION</th><th>OCCUPATION</th><th>REMARKS</th></tr></thead>
        <tbody>@for ($row = 0; $row < 10; $row++)<tr>
            <td><input name="families[__INDEX__][members][{{ $row }}][last_name]"></td><td><input name="families[__INDEX__][members][{{ $row }}][first_name]"></td><td><input name="families[__INDEX__][members][{{ $row }}][middle_name]"></td><td><input name="families[__INDEX__][members][{{ $row }}][suffix]"></td><td><input name="families[__INDEX__][members][{{ $row }}][relationship_to_head]"></td><td><input name="families[__INDEX__][members][{{ $row }}][complete_address]"></td><td><input name="families[__INDEX__][members][{{ $row }}][birth_place]"></td><td><input name="families[__INDEX__][members][{{ $row }}][birth_date]" type="date"></td><td><input name="families[__INDEX__][members][{{ $row }}][recorded_age]" type="number" min="0" max="150"></td><td><select name="families[__INDEX__][members][{{ $row }}][sex]"><option value="">—</option><option value="Male">M</option><option value="Female">F</option></select></td><td><input name="families[__INDEX__][members][{{ $row }}][civil_status]"></td><td><input name="families[__INDEX__][members][{{ $row }}][education_level]"></td><td><input name="families[__INDEX__][members][{{ $row }}][religion]"></td><td><input name="families[__INDEX__][members][{{ $row }}][occupation]"></td><td><input name="families[__INDEX__][members][{{ $row }}][remarks]"></td>
        </tr>@endfor</tbody>
    </table></div>
    @if ($registryBarangay->name === 'Biasong')
        <div class="new-family-signatures"><span>Prepared by:<strong>NENA E. GONZAGA</strong><small>BHW</small></span><span>Certified Correct:<strong>MARY GRACE M. POLISTICO</strong><small>Barangay Secretary</small></span><span>Verified by:<strong>MARCOS L. MAQUILANG SR.</strong><small>Punong Barangay</small></span></div>
    @else
        <div class="new-family-signatures"><span>Prepared by:<strong>{{ $registryBarangay->secretary_name ?: auth()->user()->name }}</strong><small>BHW / Encoder</small></span><span>Certified Correct:<strong>{{ $registryBarangay->secretary_name ?: auth()->user()->name }}</strong><small>Barangay Secretary</small></span><span>Verified by:<strong>{{ $registryBarangay->punong_barangay_name ?: 'Punong Barangay' }}</strong><small>Punong Barangay</small></span></div>
    @endif
</section>
</template>
</td></tr></tbody>

@if ($newInhabitantRecords->isNotEmpty())
<tfoot class="rbi-monthly-consolidation"><tr><td colspan="16"><h3>Saved Monthly Reports</h3>
@foreach ($newInhabitantRecords->groupBy(fn ($record) => optional($record->reporting_month)->format('F Y') ?: ($record->month_submitted ?: 'Month not set')) as $month => $monthlyRecords)
@php($reportMonth = optional($monthlyRecords->first()->reporting_month)->format('Y-m'))
<section><div class="monthly-report-head"><strong>{{ $month }} — {{ $monthlyRecords->groupBy('household_number')->count() }} families, {{ $monthlyRecords->count() }} people</strong>@if($reportMonth)<div><a class="button secondary-button" href="{{ route('registry.new-inhabitant-monthly-reports.pdf', $reportMonth) }}">Download PDF</a>@if($monthlyRecords->every(fn($member) => filled($member->submitted_rbi_update_id)))<span class="badge">Submitted to Municipal DILG</span>@else<form method="POST" action="{{ route('registry.new-inhabitant-monthly-reports.submit', $reportMonth) }}">@csrf<button type="submit">Submit to Municipal DILG</button></form>@endif</div>@endif</div>
@foreach ($monthlyRecords->groupBy('household_number') as $household => $members)<div class="monthly-family-row"><div><strong>Family / Household {{ $household }}</strong>@foreach($members as $member)<span class="saved-member-row">{{ $member->first_name }} {{ $member->last_name }} <a href="{{ route('registry.new-inhabitants.edit', $member) }}">Edit</a><form method="POST" action="{{ route('registry.new-inhabitants.destroy', $member) }}" onsubmit="return confirm('Delete this member?')">@csrf @method('DELETE')<button type="submit" class="link-button">Delete</button></form></span>@endforeach</div>@if($members->every(fn($member) => filled($member->active_inhabitant_id)))<div class="active-family-actions"><span class="badge">Added to Active Household</span><form method="POST" action="{{ route('registry.new-inhabitant-families.remove-from-active') }}" onsubmit="return confirm('Remove this family from Active Household?')">@csrf @method('DELETE')<input type="hidden" name="household_number" value="{{ $household }}"><input type="hidden" name="reporting_month" value="{{ optional($members->first()->reporting_month)->format('Y-m') }}"><button type="submit" class="danger-button">Remove from Active</button></form></div>@elseif($familyMonth = optional($members->first()->reporting_month)->format('Y-m'))<form method="POST" action="{{ route('registry.new-inhabitant-families.add-to-active') }}">@csrf<input type="hidden" name="household_number" value="{{ $household }}"><input type="hidden" name="reporting_month" value="{{ $familyMonth }}"><button type="submit">Add to Active Household</button></form>@endif</div>@endforeach
</section>@endforeach
</td></tr></tfoot>
@endif

@push('scripts')<script>
(() => {
 const container=document.getElementById('new-family-forms'),template=document.getElementById('new-family-template'); let index=0;
 const addFamily=()=>{const wrapper=document.createElement('div');wrapper.innerHTML=template.innerHTML.replaceAll('__INDEX__',index++);const section=wrapper.firstElementChild,select=section.querySelector('[data-household-select]'),number=section.querySelector('[data-household-number]');select.addEventListener('change',()=>{const option=select.options[select.selectedIndex];number.value=option.dataset.number||'';number.readOnly=Boolean(select.value);if(!select.value)number.focus();});section.querySelector('[data-remove-family]').addEventListener('click',()=>{if(container.children.length>1)section.remove();});container.appendChild(section);};
 addFamily();document.getElementById('add-family-form')?.addEventListener('click',addFamily);
})();
</script>@endpush
