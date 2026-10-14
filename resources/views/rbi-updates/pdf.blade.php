<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RBI {{ $barangayName }} {{ optional($rbiUpdate->reporting_month)->format('F Y') }}</title>
    <style>
        @page { margin: 6mm 8mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: "Times New Roman", Times, serif; font-size: 11pt; }
        .rbi-page { position: relative; page-break-after: always; }
        .rbi-page:last-child { page-break-after: auto; }
        .header { width: 78%; margin: 0 auto 2mm; border-collapse: collapse; }
        .header td { border: 0; vertical-align: middle; }
        .logo-cell { width: 19mm; text-align: center; }
        .logo { width: 16mm; height: 16mm; object-fit: contain; }
        .heading { text-align: center; line-height: 1.35; }
        .heading h1 { margin: 0; font-size: 11pt; font-weight: 700; }
        .heading p { margin: 0; font-size: 11pt; }
        .continuation { margin-top: 1mm !important; font-size: 11pt !important; font-weight: 700; }
        table.form { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.form th, table.form td { border: .6pt solid #000; padding: .4mm .6mm; vertical-align: middle; overflow-wrap: anywhere; }
        table.form th { height: 14mm; text-align: center; font-size: 11pt; line-height: 1.15; font-weight: 700; }
        table.form thead tr:first-child th[colspan], table.form thead tr:last-child th:not([rowspan]) { height: 7mm; }
        table.form td { height: 6mm; font-size: 11pt; line-height: 1.1; }
        .head-name, .resident-name { text-transform: uppercase; font-weight: 700; }
        .center { text-align: center; }
        .section-b { margin-top: 1.5mm; }
        .section-b th { height: 6.5mm !important; font-size: 11pt !important; }
        .section-b td { height: 4mm !important; }
        .signatures { width: 82%; margin: 2mm auto 0; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
        .signatures td { width: 33.33%; border: 0; padding: 0 5mm; vertical-align: top; }
        .signature-label { margin: 0; text-align: center; }
        .signature-block { text-align: center; }
        .signature-space { height: 6mm; text-align: center; }
        .signature-image { display: block; height: 8mm; max-width: 43mm; margin: 0 auto; object-fit: contain; }
        .official-name { display: block; min-height: 4mm; padding-top: 1mm; border-bottom: .6pt solid #000; text-transform: uppercase; font-weight: 700; overflow-wrap: break-word; }
        .official-title { display: block; margin-top: 1mm; }
        .page-number { position: absolute; right: 0; bottom: 0; font-size: 11pt; color: #333; }
        @include('reports._document-header-styles')
        .report-document-header { margin-bottom: 6pt !important; }
        .report-document-header .report-document-title { margin-bottom: 8pt !important; }
        .report-document-header .report-document-fields, .report-document-header .report-document-field, .report-document-header span { line-height: 1.15 !important; }
    </style>
</head>
<body>
@foreach ($pages as $page)
    @php
        $memberRows = collect($page['members'])->pad(7, [])->take(7);
        $deceasedRows = collect($page['deceased'])->pad(3, [])->take(3);
    @endphp
    <section class="rbi-page">
        @include('rbi-updates._household-meta')
        @include('rbi-updates._household-table')

        <table class="form section-b">
            <colgroup><col style="width: 75%"><col style="width: 25%"></colgroup>
            <thead><tr><th>B. Name of Deceased Registered Brgy. Inhabitant</th><th>Date of Death</th></tr></thead>
            <tbody>
            @foreach ($deceasedRows as $deceased)
                <tr>
                    <td class="resident-name">{{ $deceased['deceased_name'] ?? '' }}</td>
                    <td class="center">{{ ! empty($deceased['death_date']) ? date('m/d/y', strtotime($deceased['death_date'])) : '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        <table class="signatures">
            <tr>
                <td>
                    <p class="signature-label">Prepared by:</p>
                    <div class="signature-block">
                        <div class="signature-space">@if ($preparedSignatureDataUri)<img class="signature-image" src="{{ $preparedSignatureDataUri }}" alt="BHW / Encoder signature">@endif</div>
                        <span class="official-name">{{ $rbiUpdate->prepared_by }}</span>
                        <span class="official-title">BHW / Encoder</span>
                    </div>
                </td>
                <td><p class="signature-label">Certified Correct:</p><div class="signature-block"><div class="signature-space">@if($certifiedSignatureDataUri)<img class="signature-image" src="{{ $certifiedSignatureDataUri }}" alt="Barangay Secretary signature">@endif</div><span class="official-name">{{ $rbiUpdate->certified_by }}</span><span class="official-title">Barangay Secretary</span></div></td>
                <td>
                    <p class="signature-label">Verified by:</p>
                    <div class="signature-block">
                        <div class="signature-space">@if ($attestedSignatureDataUri)<img class="signature-image" src="{{ $attestedSignatureDataUri }}" alt="Punong Barangay signature">@endif</div>
                        <span class="official-name">{{ $rbiUpdate->attested_by }}</span>
                        <span class="official-title">Barangay Captain / Punong Barangay</span>
                    </div>
                </td>
            </tr>
        </table>
        <span class="page-number">RBI family form {{ $loop->iteration }} of {{ count($pages) }}</span>
    </section>
@endforeach
</body>
</html>
