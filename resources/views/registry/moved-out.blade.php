@extends('layouts.app')

@section('content')
<section class="panel stack registry-workspace">
    <div class="page-head"><div><h1>Moved Out</h1><p>Departure records for Barangay {{ $barangay->name }}.</p></div><a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Active Household</a></div>
    @if(session('status'))<div class="success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="workflow-card">
        <h2>Record a departure</h2>
        <p>The resident will move from Active Household to Moved Out. Their household and previous records will be kept.</p>
        <form method="POST" action="{{ route('barangay.registry.moved-out.store') }}" class="form-grid">
            @csrf
            <label>Resident<select name="inhabitant_id" required><option value="">Select an active resident</option>@foreach($activeResidents as $resident)<option value="{{ $resident->id }}" @selected((string) old('inhabitant_id') === (string) $resident->id)>{{ $resident->fullName() }} — Household {{ $resident->household->household_number }}</option>@endforeach</select></label>
            <label>Date moved out<input type="date" name="movement_date" value="{{ old('movement_date') }}" required></label>
            <label>Destination<input name="destination" maxlength="255" value="{{ old('destination') }}"></label>
            <label>Reason<input name="reason" maxlength="1000" value="{{ old('reason') }}"></label>
            <div><button type="submit">Save moved-out record</button></div>
        </form>
    </div>
    <div class="workflow-card">
        <div class="workflow-head"><div><h2>Moved-out residents</h2><p>{{ number_format($residents->total()) }} records found.</p></div><form method="GET" class="toolbar"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search resident name" aria-label="Search moved-out residents"><button type="submit">Search</button></form></div>
        <div class="table-wrap"><table><thead><tr><th>Resident No.</th><th>Name</th><th>Household</th><th>Date moved out</th><th>Destination</th><th>Reason / Source remarks</th><th>Action</th></tr></thead><tbody>
        @forelse($residents as $resident)
            @php($departure = $resident->migrationRecords->first())
            <tr><td>{{ $residents->firstItem() + $loop->index }}</td><td>{{ $resident->fullName() }}</td><td>{{ $resident->household->household_number }}</td><td>{{ $departure?->movement_date?->format('M d, Y') ?? 'Not recorded' }}</td><td>{{ $departure?->destination ?: $resident->sourceTransferDestination() ?: 'Not recorded' }}</td><td>{{ $departure?->reason ?: \App\Support\RegistryRemarks::display($resident->remarks) ?: '—' }}</td><td><a href="{{ route('registry.edit', $resident) }}">Edit resident</a></td></tr>
        @empty<tr><td colspan="7">No moved-out residents found.</td></tr>@endforelse
        </tbody></table></div>
        {{ $residents->links() }}
    </div>
</section>
@endsection
