@extends('layouts.app')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/resident-registry-table.css') }}?v={{ filemtime(public_path('css/resident-registry-table.css')) }}" media="screen">
@endpush

@push('head')
    <link rel="stylesheet" href="{{ asset('css/registry-accents.css') }}?v={{ filemtime(public_path('css/registry-accents.css')) }}" media="screen">
@endpush

@section('content')
<section class="panel registry-workspace registry-color-workspace residence-list rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
    <div class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <x-workspace-heading icon="home" label="Resident registry"><h1>{{ App\Models\Inhabitant::residenceLabels()[$scope] }}</h1><p>Barangay {{ $barangay->name }}</p></x-workspace-heading>
        <div class="toolbar flex flex-wrap items-center gap-3">
            @php($reportScope = $scope === App\Models\Inhabitant::RESIDENCE_HERE ? 'registered' : $scope)
            <a class="button secondary-button" href="{{ route('barangay.registry.print', ['scope' => $reportScope]) }}" target="_blank" rel="noopener">Print this list</a>
            <a class="button" href="{{ route('barangay.registry.download', ['scope' => $reportScope]) }}">Download Excel</a>
        </div>
    </div>
    @if(session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</div>@endif
    <nav class="registry-scope-actions" aria-label="Registry lists">
        <a class="button secondary-button" href="{{ route('barangay.registry.active') }}">All registered</a>
        @foreach(App\Models\Inhabitant::residenceLabels() as $value=>$label)
            <a class="button {{ $scope === $value ? '' : 'secondary-button' }}" href="{{ route('barangay.residence.index', ['scope'=>$value]) }}" @if($scope === $value) aria-current="page" @endif>{{ $label }} ({{ number_format($counts[$value] ?? 0) }})</a>
        @endforeach
    </nav>
    <div class="workflow-card">
    <div class="workflow-head registry-records-heading">
        <div><h2>Residents</h2><p>{{ number_format($residents->total()) }} records</p></div>
        <form class="registry-records-filter" method="GET">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <div class="registry-search-field"><label for="residence-search">Search residents</label><input id="residence-search" type="search" name="search" value="{{ request('search') }}" placeholder="Resident name"></div>
            <button type="submit">Search</button>
        </form>
    </div>
    <p class="registry-scroll-hint" id="residence-scroll-hint">Scroll sideways to see more columns.</p>
    <div tabindex="0" role="region" aria-label="Residence records" aria-describedby="residence-scroll-hint" class="table-wrap registry-scroll-region registry-screen-table registry-document-table registry-document-list w-full overflow-x-auto"><table><thead><tr><th scope="col">Name</th><th scope="col" class="table-cell-end">Household</th><th scope="col" class="table-cell-end">Current residence</th><th scope="col">Remarks</th><th scope="col">Action</th></tr></thead><tbody>
    @forelse($residents as $resident)
        <tr class="{{ $resident->residence_status === 'living_elsewhere' ? 'residence-elsewhere' : '' }}">
            <td>{{ $resident->fullName() }}</td><td class="table-cell-end">{{ $resident->household->household_number }}</td>
            <td class="table-cell-end">{{ $resident->residenceLabel() }}</td>

            <td>{{ \App\Support\RegistryRemarks::display($resident->remarks) }}</td><td><a href="{{ route('registry.movement', $resident) }}">Edit in Resident Registry</a></td>
        </tr>
    @empty<tr><td colspan="5">No residents in this list.</td></tr>@endforelse
    </tbody></table></div>
    {{ $residents->links() }}
    </div>
</section>
@endsection

@push('scripts')<script src="{{ asset('js/registry-table.js') }}?v={{ filemtime(public_path('js/registry-table.js')) }}" defer></script>@endpush
