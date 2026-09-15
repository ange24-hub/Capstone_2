<thead>
    <tr class="rbi-title-row"><th colspan="19">CONSOLIDATED HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)</th></tr>
    <tr class="rbi-spacer-row"><th colspan="19"></th></tr>
    <tr class="rbi-meta-row"><th colspan="3"></th><th colspan="2">A. REGION:</th><th colspan="5">VIII</th><th colspan="2">D. BARANGAY:</th><th colspan="7">BIASONG</th></tr>
    <tr class="rbi-meta-row"><th colspan="3"></th><th colspan="2">B. PROVINCE:</th><th colspan="5">SOUTHERN LEYTE</th><th colspan="2">E. HOUSEHOLD NO.</th><th colspan="7">{{ number_format($registryHouseholdCount) }} HOUSEHOLDS</th></tr>
    <tr class="rbi-meta-row"><th colspan="3"></th><th colspan="2">C. MUNICIPALITY:</th><th colspan="14">TOMAS OPPUS</th></tr>
    <tr class="rbi-spacer-row"><th colspan="19"></th></tr>
    <tr class="rbi-meta-row"><th colspan="19">Residents: {{ number_format($inhabitants->total()) }}</th></tr>
    <tr class="rbi-group-row">
        <th rowspan="2">HH<br>No.</th><th rowspan="2">No. of<br>Families</th><th rowspan="2">No. of<br>Individuals</th><th colspan="3">NAME</th><th>RELATIONSHIP</th><th rowspan="2">PUROK / SITIO</th><th rowspan="2">PLACE OF<br>BIRTH</th><th>DATE OF</th><th rowspan="2">AGE</th><th rowspan="2">SEX<br>(M/F)</th><th rowspan="2">CIVIL<br>STATUS</th><th>School</th><th rowspan="2">RELIGION</th><th rowspan="2">OCCUPATION</th><th>REMARKS</th><th rowspan="2" scope="col">Resident No.</th>@if($editThroughRbi)<th rowspan="2" scope="col">Action</th>@endif
    </tr>
    <tr><th>LAST NAME</th><th>FIRST NAME</th><th>MIDDLE NAME</th><th>TO HOUSEHOLD<br>HEAD</th><th>BIRTH<br>(mm-dd-yy)</th><th>Grade/Year/<br>Level Completed</th><th>(OTHER INFO)</th></tr>
</thead>
<tbody>
@foreach ($inhabitants as $inhabitant)
    @php($rowForm = 'rbi-row-'.$inhabitant->id)
    <tr class="{{ filled($inhabitant->registry_sequence) ? 'rbi-household-start' : '' }}">
        <td><input value="{{ $inhabitant->household->household_number === 'Not recorded' ? '' : $inhabitant->household->household_number }}" readonly aria-label="Household number"><input type="hidden" form="{{ $rowForm }}" name="registry_sequence" value="{{ $inhabitant->registry_sequence }}"></td>
        <td><input form="{{ $rowForm }}" name="family_number" value="{{ $inhabitant->family_number }}" aria-label="Family number"></td>
        <td><input form="{{ $rowForm }}" name="individual_number" value="{{ $inhabitant->individual_number }}" aria-label="Individual number"></td>
        <td><input form="{{ $rowForm }}" name="last_name" value="{{ $inhabitant->last_name }}" required aria-label="Last name"></td>
        <td><input form="{{ $rowForm }}" name="first_name" value="{{ $inhabitant->first_name }}" required aria-label="First name"></td>
        <td><input form="{{ $rowForm }}" name="middle_name" value="{{ $inhabitant->middle_name }}" aria-label="Middle name"></td>
        <td><input form="{{ $rowForm }}" name="relationship_to_head" value="{{ $inhabitant->relationship_to_head }}" aria-label="Relationship"></td>
        <td><input form="{{ $rowForm }}" name="purok" value="{{ $inhabitant->household->purok }}" aria-label="Purok"></td>
        <td><input form="{{ $rowForm }}" name="birth_place" value="{{ $inhabitant->birth_place }}" aria-label="Place of birth"></td>
        <td><input form="{{ $rowForm }}" name="birth_date" type="date" value="{{ optional($inhabitant->birth_date)->format('Y-m-d') }}" aria-label="Date of birth"></td>
        <td><input form="{{ $rowForm }}" name="recorded_age" type="number" min="0" max="150" value="{{ $inhabitant->recorded_age }}" aria-label="Age"></td>
        <td><select form="{{ $rowForm }}" name="sex" required><option value="Male" @selected($inhabitant->sex === 'Male')>M</option><option value="Female" @selected($inhabitant->sex === 'Female')>F</option></select></td>
        <td><input form="{{ $rowForm }}" name="civil_status" value="{{ $inhabitant->civil_status }}" aria-label="Civil status"></td>
        <td><input form="{{ $rowForm }}" name="education_level" value="{{ $inhabitant->education_level }}" aria-label="Education"></td>
        <td><input form="{{ $rowForm }}" name="religion" value="{{ $inhabitant->religion }}" aria-label="Religion"></td>
        <td><input form="{{ $rowForm }}" name="occupation" value="{{ $inhabitant->occupation }}" aria-label="Occupation"></td>
        <td><input form="{{ $rowForm }}" name="remarks" value="{{ \App\Support\RegistryRemarks::display($inhabitant->remarks) }}" aria-label="Remarks"></td>
        <td class="resident-row-number">{{ $inhabitants->firstItem() + $loop->index }}</td>
        @if($editThroughRbi)<td><a href="{{ route('barangay.rbi-updates.index', ['edit_resident' => $inhabitant->id]) }}" class="font-semibold text-blue-800 underline" aria-label="Edit {{ $inhabitant->fullName() }} in RBI Forms">Edit</a></td>@endif
        <td class="rbi-row-save" hidden><form id="{{ $rowForm }}" method="POST" action="{{ route('registry.update', $inhabitant) }}">@csrf @method('PUT')<input type="hidden" name="source" value="BIASONG.xlsx"><input type="hidden" name="barangay_id" value="{{ $inhabitant->barangay_id }}"><input type="hidden" name="household_number" value="{{ $inhabitant->household->household_number }}"><input type="hidden" name="address" value="{{ $inhabitant->household->address }}"><input type="hidden" name="status" value="{{ $inhabitant->status }}"></form></td>
    </tr>
@endforeach
</tbody>
