@extends('layouts.app')

@section('content')
    <section class="panel stack">
        <div class="page-kicker">Edited RBI File</div>
        <div class="page-head">
            <div>
                <h1>{{ $rbiUpdate->barangay_name ?: 'Barangay RBI Update' }}</h1>
                <p>{{ optional($rbiUpdate->reporting_month)->format('F Y') ?: 'No reporting month' }} registry update prepared by {{ $rbiUpdate->barangayUser->name }}.</p>
            </div>
            <div class="toolbar">
                <a class="button secondary-button" href="{{ route('rbi-updates.export-edited', $rbiUpdate) }}">Download Edited File</a>
                @if ($rbiUpdate->source_file_path)
                    <a class="button secondary-button" href="{{ route('rbi-updates.download', $rbiUpdate) }}">Original Upload</a>
                @endif
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta">
                <strong>Status</strong>
                {{ $rbiUpdate->statusLabel() }}
            </div>
            <div class="meta">
                <strong>As of</strong>
                {{ optional($rbiUpdate->as_of_date)->format('M d, Y') ?: 'Not set' }}
            </div>
            <div class="meta">
                <strong>Entries</strong>
                {{ count($rbiUpdate->rows ?? []) }}
            </div>
            <div class="meta">
                <strong>Submitted</strong>
                {{ optional($rbiUpdate->submitted_at)->format('M d, Y h:i A') ?: 'Not submitted' }}
            </div>
        </div>

        <div>
            <h2 class="section-title">Updated Registry Entries</h2>

            @if (empty($rbiUpdate->rows))
                <p>No edited rows saved yet.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                @foreach ($rbiRowFields as $label)
                                    <th>{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rbiUpdate->rows as $row)
                                <tr>
                                    @foreach ($rbiRowFields as $field => $label)
                                        <td>{{ $row[$field] ?? '' }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div>
            <h2 class="section-title">Certification</h2>
            <div class="signature-grid">
                <div class="signature-box">
                    <span>Prepared by</span>
                    <strong>{{ $rbiUpdate->prepared_by ?: $rbiUpdate->barangayUser->name }}</strong>
                    <small>Barangay Personnel / RBI Encoder</small>
                </div>
                <div class="signature-box">
                    <span>Attested by</span>
                    <strong>{{ $rbiUpdate->attested_by ?: '' }}</strong>
                    <small>Punong Barangay / Authorized Official</small>
                </div>
            </div>
        </div>
    </section>
@endsection
