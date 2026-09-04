<thead>
    <tr class="rbi-deceased-title"><th>HH<br>NO.</th><th colspan="16">NAME OF DECEASED PERSONS</th><th>DATE OF DEATH</th><th>ACTION</th></tr>
    <tr><th>HH No.</th><th>Family No.</th><th>Individual No.</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Relationship</th><th>Purok / Sitio</th><th>Place of Birth</th><th>Date of Birth</th><th>Age</th><th>Sex</th><th>Civil Status</th><th>School Level</th><th>Religion</th><th>Occupation</th><th>Remarks</th><th>Date of Death</th><th>Action</th></tr>
</thead>
<tbody class="rbi-deceased-body">
@foreach ($deceasedRecords as $record)
    @php($rowForm = 'deceased-row-'.$record->id)
    <tr>
        <td><input form="{{ $rowForm }}" name="household_number" value="{{ $record->household_number }}" required></td><td><input form="{{ $rowForm }}" name="family_number" value="{{ $record->family_number }}"></td><td><input form="{{ $rowForm }}" name="individual_number" value="{{ $record->individual_number }}"></td>
        <td><input form="{{ $rowForm }}" name="last_name" value="{{ $record->last_name }}" required></td><td><input form="{{ $rowForm }}" name="first_name" value="{{ $record->first_name }}" required></td><td><input form="{{ $rowForm }}" name="middle_name" value="{{ $record->middle_name }}"></td>
        <td><input form="{{ $rowForm }}" name="relationship_to_head" value="{{ $record->relationship_to_head }}"></td><td><input form="{{ $rowForm }}" name="purok" value="{{ $record->purok }}"></td><td><input form="{{ $rowForm }}" name="birth_place" value="{{ $record->birth_place }}"></td>
        <td><input form="{{ $rowForm }}" name="birth_date" type="date" value="{{ optional($record->birth_date)->format('Y-m-d') }}"></td><td><input form="{{ $rowForm }}" name="recorded_age" type="number" value="{{ $record->recorded_age }}"></td><td><select form="{{ $rowForm }}" name="sex"><option value="Male" @selected($record->sex === 'Male')>M</option><option value="Female" @selected($record->sex === 'Female')>F</option></select></td>
        <td><input form="{{ $rowForm }}" name="civil_status" value="{{ $record->civil_status }}"></td><td><input form="{{ $rowForm }}" name="education_level" value="{{ $record->education_level }}"></td><td><input form="{{ $rowForm }}" name="religion" value="{{ $record->religion }}"></td><td><input form="{{ $rowForm }}" name="occupation" value="{{ $record->occupation }}"></td><td><input form="{{ $rowForm }}" name="remarks" value="{{ $record->remarks }}"></td><td><input form="{{ $rowForm }}" name="death_date" type="date" value="{{ optional($record->death_date)->format('Y-m-d') }}"></td>
        <td class="rbi-row-save"><form id="{{ $rowForm }}" method="POST" action="{{ route('registry.deceased.update', $record) }}">@csrf @method('PUT')<button type="submit">Save row</button></form></td>
    </tr>
@endforeach
</tbody>
