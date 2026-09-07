<table class="{{ $tableClass ?? 'form' }}" aria-label="A. Newly Registered Barangay Inhabitants">
    <colgroup>@foreach(App\Support\HouseholdRbi::widths() as $width)<col style="width:{{ $width }}%">@endforeach</colgroup>
    <thead><tr><th colspan="4">NAME</th>@foreach(array_slice(App\Support\HouseholdRbi::fields(), 4, null, true) as $label)<th rowspan="2">{{ $label }}</th>@endforeach</tr><tr>@foreach(array_slice(App\Support\HouseholdRbi::fields(), 0, 4, true) as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
    <tbody>@foreach(collect($page['members'])->pad(7, []) as $member)<tr aria-label="{{ $member['inhabitant_name'] ?? '' }}">
        @foreach(App\Support\HouseholdRbi::fields() as $field => $label)
            <td>{{ $field === 'birth_date' && !empty($member[$field]) ? date('m-d-y', strtotime($member[$field])) : ($field === 'sex' ? (['Male'=>'M','Female'=>'F'][$member[$field] ?? ''] ?? '') : ($field === 'remarks' ? App\Support\RegistryRemarks::display($member[$field] ?? '') : ($member[$field] ?? ''))) }}</td>
        @endforeach
    </tr>@endforeach</tbody>
</table>
