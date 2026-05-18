@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Central Registry</div>
        <div class="page-head">
            <div>
                <h1>Multi-Barangay Registry</h1>
                <p>{{ auth()->user()->name }} is signed in as {{ auth()->user()->roleLabel() }}.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif

        <div class="workflow-card">
            <h2 class="section-title">Create Inhabitant or Migrant Record</h2>
            @include('registry._form')
        </div>

        <div class="workflow-card">
            <div class="workflow-head">
                <div>
                    <h2 class="section-title">Registry Records</h2>
                </div>
                <form class="toolbar compact-toolbar" method="GET" action="{{ route('registry.index') }}">
                    <select name="barangay_id" aria-label="Filter by barangay">
                        <option value="">All barangays</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                        @endforeach
                    </select>
                    <input name="search" type="search" value="{{ request('search') }}" placeholder="Search name or household" aria-label="Search registry">
                    <button type="submit" class="secondary-button">Filter</button>
                </form>
            </div>

            @if ($inhabitants->isEmpty())
                <p>No inhabitant records found.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Barangay</th>
                                <th>Household</th>
                                <th>Coordinates</th>
                                <th>Status</th>
                                <th>Migration Events</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inhabitants as $inhabitant)
                                <tr>
                                    <td>
                                        <strong>{{ $inhabitant->fullName() }}</strong><br>
                                        {{ $inhabitant->sex }} {{ optional($inhabitant->birth_date)->format('M d, Y') }}
                                    </td>
                                    <td>{{ $inhabitant->barangay->name }}</td>
                                    <td>
                                        {{ $inhabitant->household->household_number }}<br>
                                        {{ $inhabitant->household->address ?: 'No address' }}
                                    </td>
                                    <td>{{ $inhabitant->household->coordinate() }}</td>
                                    <td><span class="badge">{{ $inhabitant->statusLabel() }}</span></td>
                                    <td>{{ $inhabitant->migrationRecords->count() }}</td>
                                    <td class="row-actions">
                                        <a href="{{ route('registry.edit', $inhabitant) }}">Edit</a>
                                        <form method="POST" action="{{ route('registry.destroy', $inhabitant) }}" onsubmit="return confirm('Delete this inhabitant record?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="link-button">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $inhabitants->links() }}
            @endif
        </div>
    </section>
@endsection
