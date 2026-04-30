@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Municipal Review Desk</div>
        <h1>Municipal LGU Dashboard</h1>
        <p>{{ auth()->user()->name }} is signed in as {{ auth()->user()->roleLabel() }}.</p>

        <div class="meta-grid">
            <div class="meta">
                <strong>Access tier</strong>
                Municipal LGU
            </div>
            <div class="meta">
                <strong>Permissions</strong>
                Municipal, barangay, and resident areas
            </div>
            <div class="meta">
                <strong>Session</strong>
                Authenticated
            </div>
        </div>

        <div>
            <h2 class="section-title">Submitted RBI Monthly Updates</h2>

            @if ($rbiUpdates->isEmpty())
                <p>No barangay RBI updates submitted yet.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Reporting Month</th>
                                <th>As Of</th>
                                <th>Barangay Personnel</th>
                                <th>Entries</th>
                                <th>Submitted</th>
                                <th>Files</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rbiUpdates as $update)
                                <tr>
                                    <td>{{ $update->barangay_name ?: 'Not set' }}</td>
                                    <td>{{ optional($update->reporting_month)->format('M Y') ?: 'Not set' }}</td>
                                    <td>{{ optional($update->as_of_date)->format('M d, Y') ?: 'Not set' }}</td>
                                    <td>{{ $update->barangayUser->name }}</td>
                                    <td>{{ count($update->rows ?? []) }}</td>
                                    <td>{{ optional($update->submitted_at)->format('M d, Y h:i A') }}</td>
                                    <td class="row-actions">
                                        <a href="{{ route('rbi-updates.show', $update) }}">View edited</a>
                                        <a href="{{ route('rbi-updates.export-edited', $update) }}">Download edited</a>
                                        @if ($update->source_file_path)
                                            <a href="{{ route('rbi-updates.download', $update) }}">Original</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection
