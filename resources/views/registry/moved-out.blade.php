@extends('layouts.app')
@push('head')
    <link rel="stylesheet" href="{{ asset('css/resident-registry-table.css') }}?v={{ filemtime(public_path('css/resident-registry-table.css')) }}" media="screen">
@endpush

@push('head')
    <link rel="stylesheet" href="{{ asset('css/moved-out.css') }}?v={{ filemtime(public_path('css/moved-out.css')) }}" media="screen">
    <link rel="stylesheet" href="{{ asset('css/registry-accents.css') }}?v={{ filemtime(public_path('css/registry-accents.css')) }}" media="screen">
@endpush

@section('content')
<section class="panel registry-workspace registry-color-workspace moved-out-workspace">
    <div class="page-head moved-out-page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
        <x-workspace-heading icon="users" label="Movement records"><h1>Moved Out</h1><p>Departure records for Barangay {{ $barangay->name }}.</p></x-workspace-heading>
        <a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Consolidated / All Registered</a>
    </div>
    @if(session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="workflow-card"><h2>Movement records from Resident Registry</h2><p>Record or correct person / household movements in Resident Registry. Moved out residents stay in Consolidated; transferred residents are archived here. Dated departures supply out-migration analytics.</p><a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Open Resident Registry</a></div>
    <div class="workflow-card moved-out-list-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
        <div class="workflow-head registry-records-heading">
            <div><h2>Moved-out residents</h2><p>{{ number_format($residents->total()) }} records</p></div>
            <form method="GET" class="registry-records-filter">
                <div class="registry-search-field"><label for="moved-out-search">Search moved-out residents</label><input id="moved-out-search" type="search" name="search" value="{{ request('search') }}" placeholder="Resident name"></div>
                <button type="submit">Search</button>
            </form>
        </div>
        <p class="registry-scroll-hint" id="moved-out-scroll-hint">Scroll sideways to see more columns.</p>
        <div tabindex="0" role="region" aria-label="Moved-out records" aria-describedby="moved-out-scroll-hint" class="table-wrap registry-scroll-region registry-screen-table registry-document-table registry-document-list w-full overflow-x-auto"><table><thead><tr><th scope="col" class="table-cell-end">Resident No.</th><th scope="col">Name</th><th scope="col" class="table-cell-end">Household</th><th scope="col" class="table-cell-end">Movement status</th><th scope="col">Date moved out</th><th scope="col">Destination</th><th scope="col">Reason / Source remarks</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($residents as $resident)
            @php($departure = $resident->migrationRecords->first())
            <tr><td class="table-cell-end">{{ $residents->firstItem() + $loop->index }}</td><td>{{ $resident->fullName() }}</td><td class="table-cell-end">{{ $resident->household->household_number }}</td><td class="table-cell-end">{{ $resident->movementLabel() }}</td><td>{{ $departure?->movement_date?->format('M d, Y') ?? 'Not recorded' }}</td><td>{{ $departure?->destination ?: $resident->sourceTransferDestination() ?: 'Not recorded' }}</td><td>{{ $departure?->reason ?: \App\Support\RegistryRemarks::display($resident->remarks) ?: '—' }}</td><td>
                <a href="{{ route('registry.movement', $resident) }}">Review in Resident Registry</a>
            </td></tr>
        @empty<tr><td colspan="8">No moved-out residents found.</td></tr>@endforelse
        </tbody></table></div>
        {{ $residents->links() }}
    </div>
</section>
@endsection

@push('scripts')
    <script src="{{ asset('js/registry-table.js') }}?v={{ filemtime(public_path('js/registry-table.js')) }}" defer></script>
    <script src="{{ asset('js/resident-picker.js') }}?v={{ filemtime(public_path('js/resident-picker.js')) }}" defer></script>
@endpush
