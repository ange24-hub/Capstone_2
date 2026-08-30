@extends('layouts.app')

@section('content')
    @php($familyCount = collect($rbiUpdate->rows ?? [])->pluck('household_head')->filter()->unique()->count())
    <section class="panel stack">
        <div class="page-kicker">RBI Monthly Form</div>
        <div class="page-head">
            <div>
                <h1>Updates of Barangay Registry of Barangay Inhabitants</h1>
                <p>For the month of {{ strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET') }} — Barangay {{ $rbiUpdate->barangay_name ?: 'not set' }}</p>
            </div>
            <div class="toolbar">
                <a class="button" href="{{ route('rbi-updates.export-pdf', $rbiUpdate) }}">Download Consolidated PDF</a>
                <a class="button secondary-button" href="{{ route('rbi-updates.export-word', $rbiUpdate) }}">Download Word Document</a>
                @if ($rbiUpdate->source_file_path)<a class="button secondary-button" href="{{ route('rbi-updates.download', $rbiUpdate) }}">Original Upload</a>@endif
            </div>
        </div>

        <div class="meta-grid">
            <div class="meta"><strong>Status</strong>{{ $rbiUpdate->statusLabel() }}</div>
            <div class="meta"><strong>Families</strong>{{ $familyCount }}</div>
            <div class="meta"><strong>New inhabitants</strong>{{ count($rbiUpdate->rows ?? []) }}</div>
            <div class="meta"><strong>Deceased</strong>{{ count($rbiUpdate->deceased_rows ?? []) }}</div>
            <div class="meta"><strong>Submitted</strong>{{ optional($rbiUpdate->submitted_at)->format('M d, Y h:i A') ?: 'Not submitted' }}</div>
        </div>

        <article class="workflow-card">
            <h2 class="section-title">A. Newly Registered Barangay Inhabitants</h2>
            @if (empty($rbiUpdate->rows))
                <p>No newly registered inhabitants entered.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr>@foreach ($rbiRowFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($rbiUpdate->rows as $row)
                                <tr>
                                    @foreach ($rbiRowFields as $field => $label)
                                        <td>{{ $field === 'birth_date' && ! empty($row[$field]) ? date('m/d/y', strtotime($row[$field])) : ($row[$field] ?? '') }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h2 class="section-title">B. Deceased Registered Barangay Inhabitants</h2>
            @if (empty($rbiUpdate->deceased_rows))
                <p>No deceased inhabitants reported.</p>
            @else
                <div class="table-wrap">
                    <table>
                        <thead><tr>@foreach ($rbiDeceasedRowFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($rbiUpdate->deceased_rows as $row)
                                <tr>
                                    @foreach ($rbiDeceasedRowFields as $field => $label)
                                        <td>{{ $field === 'death_date' && ! empty($row[$field]) ? date('m/d/y', strtotime($row[$field])) : ($row[$field] ?? '') }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <h2 class="section-title">Monthly Form Certification</h2>
            <div class="signature-grid">
                <div class="signature-box">
                    <span>Prepared by</span>
                    @if ($rbiUpdate->prepared_signature_path)<img class="digital-signature" src="{{ route('rbi-updates.signature', [$rbiUpdate, 'secretary']) }}" alt="Barangay Secretary digital signature">@endif
                    <strong>{{ $rbiUpdate->prepared_by ?: $rbiUpdate->barangayUser->name }}</strong>
                    <small>Barangay Secretary</small>
                </div>
                <div class="signature-box">
                    <span>Noted by</span>
                    @if ($rbiUpdate->attested_signature_path)<img class="digital-signature" src="{{ route('rbi-updates.signature', [$rbiUpdate, 'captain']) }}" alt="Punong Barangay digital signature">@endif
                    <strong>{{ $rbiUpdate->attested_by ?: '' }}</strong>
                    <small>Punong Barangay</small>
                </div>
            </div>
        </article>
    </section>
@endsection
