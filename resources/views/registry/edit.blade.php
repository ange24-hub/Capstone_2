@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Central Registry</div>
        <div class="page-head">
            <div>
                <h1>Edit {{ $inhabitant->fullName() }}</h1>
                <p>{{ $inhabitant->barangay->name }} household {{ $inhabitant->household->household_number }}</p>
            </div>
            <a class="button secondary-button" href="{{ route('registry.index') }}">Back to Registry</a>
        </div>

        <div class="workflow-card">
            @include('registry._form')
        </div>

        <div class="workflow-card">
            <h2 class="section-title">Migration History</h2>

            @if ($inhabitant->migrationRecords->isEmpty())
                <p>No migration events recorded.</p>
            @else
                <div class="table-wrap">
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
                                    <td><span class="badge">{{ $record->typeLabel() }}</span></td>
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
@endsection
