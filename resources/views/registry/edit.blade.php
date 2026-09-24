@extends('layouts.app')

@section('content')
    <section class="panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
        <div class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <x-workspace-heading icon="users"><div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Central Registry</div>
                <h1>Edit {{ $inhabitant->fullName() }}</h1>
                <p>{{ $inhabitant->barangay->name }} household {{ $inhabitant->household->household_number }}</p>
            </x-workspace-heading>
            <a class="button secondary-button" href="{{ route('registry.index', request('source') ? ['source' => request('source')] : []) }}">Back to {{ request('source') ?: 'Registry' }}</a>
        </div>

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
            @include('registry._form')
        </div>

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
            <h2 class="section-title text-lg font-semibold text-slate-900">Migration History</h2>

            @if ($inhabitant->migrationRecords->isEmpty())
                <p>No migration events recorded.</p>
            @else
                <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Origin</th>
                                <th>Destination</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inhabitant->migrationRecords->sortByDesc('movement_date') as $record)
                                <tr>
                                    <td><span class="badge inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $record->typeLabel() }}</span></td>
                                    <td>{{ $record->movement_date->format('M d, Y') }}</td>
                                    <td>{{ $record->origin ?: 'Not set' }}</td>
                                    <td>{{ $record->destination ?: 'Not set' }}</td>
                                    <td>{{ $record->reason ?: 'None' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@push('scripts')<script src="{{ asset('js/registry-confirm.js') }}?v={{ filemtime(public_path('js/registry-confirm.js')) }}" defer></script>@endpush
@endsection
