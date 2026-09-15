@extends('layouts.app')
@section('content')
<section class="dashboard-page grid gap-6">
    <header class="dashboard-page-header flex flex-wrap items-start justify-between gap-4"><div><span class="dashboard-eyebrow">RBI Forms &middot; {{ $resident->barangay->name }}</span><h1>Edit registered resident</h1><p>{{ $resident->fullName() }} &middot; Household {{ $resident->household?->household_number }}</p></div><a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Back to Consolidated</a></header>
    <p class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-blue-950">Saving this RBI form updates this resident in Consolidated / All Registered automatically. Household assignment and residence status are managed separately.</p>
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-900"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('barangay.rbi-residents.update', $resident) }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        @csrf @method('PUT')
        <input type="hidden" name="record_version" value="{{ old('record_version', (string) $resident->updated_at) }}">
        <div class="form-grid grid grid-cols-1 gap-5 md:grid-cols-2">
            @foreach(App\Support\HouseholdRbi::fields() + ['family_number'=>'Family number', 'individual_number'=>'Individual number', 'ethnicity'=>'Ethnicity', 'contact_number'=>'Contact number'] as $rbiField => $label)
                @php($field = $rbiField === 'relationship' ? 'relationship_to_head' : $rbiField)
                @php($value = old($field, $field === 'birth_date' ? $resident->birth_date?->format('Y-m-d') : ($field === 'remarks' ? App\Support\RegistryRemarks::display($resident->remarks) : $resident->$field)))
                <div><label for="resident-{{ $field }}">{{ $label }}</label>
                    @if(in_array($field, ['civil_status','education_level','religion'], true))
                        <x-rbi-dropdown :field="$field" :name="$field" :id="'resident-'.$field" :value="$value" />
                    @elseif($field === 'sex')
                        <select name="sex" id="resident-sex"><option value="">Not specified</option>@foreach(['Male','Female'] as $sex)<option value="{{ $sex }}" @selected($value === $sex)>{{ $sex }}</option>@endforeach</select>
                    @elseif($field === 'remarks')
                        <textarea id="resident-remarks" name="remarks" rows="3">{{ $value }}</textarea>
                    @else
                        <input id="resident-{{ $field }}" name="{{ $field }}" type="{{ $field === 'birth_date' ? 'date' : ($field === 'recorded_age' ? 'number' : 'text') }}" value="{{ $value }}" @required(in_array($field, ['last_name','first_name'])) @if($field === 'recorded_age') min="0" max="150" @endif>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-6 flex flex-wrap gap-3"><button type="submit">Save RBI Changes</button><a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Cancel</a></div>
    </form>
</section>
@endsection
