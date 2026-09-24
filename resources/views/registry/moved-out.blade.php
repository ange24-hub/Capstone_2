@extends('layouts.app')

@section('content')
<section class="panel  registry-workspace rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
    <div class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <x-workspace-heading icon="users" label="Movement records"><h1>Moved Out</h1><p>Departure records for Barangay {{ $barangay->name }}.</p></x-workspace-heading><a class="button secondary-button" href="{{ route('barangay.registry.active') }}">Active Household</a></div>
    @if(session('status'))<div class="success rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
        <h2>Record a departure</h2>
        <p>The resident will move from Active Household to Moved Out. Their household and previous records will be kept.</p>
        <form method="POST" action="{{ route('barangay.registry.moved-out.store') }}" class="form-grid grid grid-cols-1 gap-x-5 gap-y-4 md:grid-cols-2">
            @csrf
            <label>Resident<select name="inhabitant_id" required><option value="">Select an active resident</option>@foreach($activeResidents as $resident)<option value="{{ $resident->id }}" @selected((string) old('inhabitant_id') === (string) $resident->id)>{{ $resident->fullName() }} — Household {{ $resident->household->household_number }}</option>@endforeach</select></label>
            <label>Date moved out<input type="date" name="movement_date" value="{{ old('movement_date') }}" required></label>
            <label>Destination<input name="destination" maxlength="255" value="{{ old('destination') }}"></label>
            <label>Reason<input name="reason" maxlength="1000" value="{{ old('reason') }}"></label>
            <div><button type="submit">Save moved-out record</button></div>
        </form>
    </div>
    <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
        <div class="workflow-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-4"><div><h2>Moved-out residents</h2><p>{{ number_format($residents->total()) }} records found.</p></div><form method="GET" class="toolbar flex flex-wrap items-end gap-3"><input type="search" name="search" value="{{ request('search') }}" placeholder="Search resident name" aria-label="Search moved-out residents"><button type="submit">Search</button></form></div>
        <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200"><table><thead><tr><th>Resident No.</th><th>Name</th><th>Household</th><th>Date moved out</th><th>Destination</th><th>Reason / Source remarks</th><th>Action</th></tr></thead><tbody>
        @forelse($residents as $resident)
            @php($departure = $resident->migrationRecords->first())
            <tr><td>{{ $residents->firstItem() + $loop->index }}</td><td>{{ $resident->fullName() }}</td><td>{{ $resident->household->household_number }}</td><td>{{ $departure?->movement_date?->format('M d, Y') ?? 'Not recorded' }}</td><td>{{ $departure?->destination ?: $resident->sourceTransferDestination() ?: 'Not recorded' }}</td><td>{{ $departure?->reason ?: \App\Support\RegistryRemarks::display($resident->remarks) ?: '—' }}</td><td>
                <div class="moved-out-row-actions">
                    <a href="{{ route('registry.edit', $resident) }}">Edit resident</a>
                    <form method="POST" action="{{ route('registry.destroy', $resident) }}" data-confirm="Permanently delete {{ $resident->fullName() }} and all linked migration records? This cannot be undone. The household and other residents will remain." onsubmit="return window.confirm(this.dataset.confirm)">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="return_to" value="moved-out">
                        <button type="submit" class="danger-button" aria-label="Delete resident {{ $resident->fullName() }}">Delete resident</button>
                    </form>
                </div>
            </td></tr>
        @empty<tr><td colspan="7">No moved-out residents found.</td></tr>@endforelse
        </tbody></table></div>
        {{ $residents->links() }}
    </div>
</section>
@endsection
