@extends('layouts.app')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/registry-accents.css') }}?v={{ filemtime(public_path('css/registry-accents.css')) }}" media="screen">
@endpush
@section('content')
<section class="panel registry-color-workspace">
    <header class="page-head">
        <x-workspace-heading icon="users" label="Resident Registry"><h1>Edit person / household movement</h1><p>{{ $resident->fullName() }} · Household {{ $resident->household?->household_number }}</p></x-workspace-heading>
        <a href="{{ route('barangay.registry.active') }}" class="button secondary-button">Back to Consolidated / All Registered</a>
    </header>
    @if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('registry.movement.update',$resident) }}" class="workflow-card grid gap-5" id="registry-movement-form">
        @csrf @method('PUT')
        <div class="form-grid grid grid-cols-1 gap-5 md:grid-cols-2">
            <div><label for="movement-scope">Apply to</label><select id="movement-scope" name="scope"><option value="person">This person only</option>@if($members->contains('id',$resident->id))<option value="household">All {{ $members->count() }} registered members of household {{ $resident->household?->household_number }}</option>@endif</select></div>
            <div><label for="movement-action">Residence / movement</label><select id="movement-action" name="action" required>
                @foreach(['moved_out'=>'Moved out — keep in Consolidated','transferred'=>'Transferred — remove from Consolidated','living_here'=>'Living in barangay','unconfirmed'=>'Residence needs confirmation'] as $value=>$label)<option value="{{ $value }}" @selected(old('action',$resident->status === 'migrated_out' ? 'transferred' : ($resident->residence_status === 'living_elsewhere' ? 'moved_out' : $resident->residence_status)) === $value)>{{ $label }}</option>@endforeach
            </select></div>
            <div><label for="movement-date">Actual date moved out</label><input id="movement-date" type="date" name="movement_date" max="{{ now()->format('Y-m-d') }}" value="{{ old('movement_date',$departure?->movement_date?->format('Y-m-d')) }}"><small>Required for Moved out / Transferred. Analytics uses this date.</small></div>
            <div><label for="movement-destination">Destination</label><input id="movement-destination" name="destination" maxlength="255" value="{{ old('destination',$departure?->destination) }}"><small>Required when transferring registration.</small></div>
        </div>
        <label for="movement-reason">Reason / notes</label><textarea id="movement-reason" name="reason" maxlength="1000">{{ old('reason',$departure?->reason) }}</textarea>
        <p>Moved out: removed from Living in Barangay, retained in Consolidated and shown in Moved Out. Transferred: removed from Living in Barangay and Consolidated, retained in Moved Out.</p>
        <details><summary>Household members</summary><ul>@foreach($members as $member)<li>{{ $member->fullName() }}</li>@endforeach</ul></details>
        <div id="movement-versions" data-person-id="{{ $resident->id }}" data-person-version="{{ $movements->version($resident) }}">@foreach($members as $member)<input type="hidden" data-member-version data-member-id="{{ $member->id }}" value="{{ $movements->version($member) }}">@endforeach</div>
        <label><input type="checkbox" name="confirmed" value="1" required> I verified the movement date and the person / household members affected.</label>
        <button type="submit">Save registry movement</button>
    </form>
</section>
@endsection
@push('scripts')<script>
(() => {
    const form=document.getElementById('registry-movement-form'), scope=document.getElementById('movement-scope'), box=document.getElementById('movement-versions');
    function sync() {
        form.querySelectorAll('[data-version-submit]').forEach(e=>e.remove());
        const values=scope.value==='household'?[...box.querySelectorAll('[data-member-version]')].map(e=>[e.dataset.memberId,e.value]):[[box.dataset.personId,box.dataset.personVersion]];
        values.forEach(([id,value])=>{const e=document.createElement('input');e.type='hidden';e.name=`versions[${id}]`;e.value=value;e.dataset.versionSubmit='';form.appendChild(e);});
    }
    scope.addEventListener('change',sync);sync();
})();
</script>@endpush
