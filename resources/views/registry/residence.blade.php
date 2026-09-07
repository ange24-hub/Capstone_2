@extends('layouts.app')

@section('content')
<section class="panel stack registry-workspace residence-list">
    <div class="page-head"><div><h1>{{ App\Models\Inhabitant::residenceLabels()[$scope] }}</h1><p>Barangay {{ $barangay->name }} — current residence of registered residents.</p></div>
        <a class="button" href="{{ route('barangay.residence.download', ['scope'=>$scope]) }}">Download CSV</a>
    </div>
    @if(session('status'))<div class="success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
    <div class="toolbar">
        <a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Consolidated / All Registered</a>
        @foreach(App\Models\Inhabitant::residenceLabels() as $value=>$label)
            <a class="button {{ $scope === $value ? '' : 'secondary-button' }}" href="{{ route('barangay.residence.index', ['scope'=>$value]) }}">{{ $label }} ({{ number_format($counts[$value] ?? 0) }})</a>
        @endforeach
    </div>
    <p>Green means registered here but living elsewhere. Only residents marked “Living in barangay” appear in the local-residents file. Moved-out and deceased records remain separate.</p>
    <form class="toolbar" method="GET"><input type="hidden" name="scope" value="{{ $scope }}"><input name="search" value="{{ request('search') }}" placeholder="Search resident name"><button type="submit">Search</button></form>
    <div class="table-wrap"><table><thead><tr><th>Name</th><th>Household</th><th>Current residence</th><th>Remarks</th><th>Action</th></tr></thead><tbody>
    @forelse($residents as $resident)
        <tr class="{{ $resident->residence_status === 'living_elsewhere' ? 'residence-elsewhere' : '' }}">
            <td>{{ $resident->fullName() }}</td><td>{{ $resident->household->household_number }}</td>
            <td><form data-residence-update method="POST" action="{{ route('barangay.residence.update', $resident) }}">@csrf @method('PUT')<select name="residence_status" aria-label="Current residence for {{ $resident->fullName() }}">@foreach(App\Models\Inhabitant::residenceLabels() as $value=>$label)<option value="{{ $value }}" @selected($resident->residence_status === $value)>{{ $label }}</option>@endforeach<option value="transferred">Transferred to...</option></select></form></td>
            <td>{{ \App\Support\RegistryRemarks::display($resident->remarks) }}</td><td><a href="{{ route('registry.edit', $resident) }}">Edit resident</a></td>
        </tr>
    @empty<tr><td colspan="5">No residents in this list.</td></tr>@endforelse
    </tbody></table></div>
    {{ $residents->links() }}
</section>
@push('scripts')<script src="{{ asset('js/registry-confirm.js') }}?v={{ filemtime(public_path('js/registry-confirm.js')) }}" defer></script>@endpush
@endsection
