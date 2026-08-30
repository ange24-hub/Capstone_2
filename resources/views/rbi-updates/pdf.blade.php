<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>RBI {{ $barangayName }} {{ optional($rbiUpdate->reporting_month)->format('F Y') }}</title>
    <style>
        @page { margin: 6mm 8mm; size: A4 landscape; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; font-family: DejaVu Sans, Arial, sans-serif; font-size: 8.2pt; }
        .rbi-page { position: relative; page-break-after: always; }
        .rbi-page:last-child { page-break-after: auto; }
        .header { width: 78%; margin: 0 auto 2mm; border-collapse: collapse; }
        .header td { border: 0; vertical-align: middle; }
        .logo-cell { width: 19mm; text-align: center; }
        .logo { width: 16mm; height: 16mm; object-fit: contain; }
        .heading { text-align: center; line-height: 1.35; }
        .heading h1 { margin: 0; font-size: 12.5pt; font-weight: 700; }
        .heading p { margin: 0; font-size: 10pt; }
        .continuation { margin-top: 1mm !important; font-size: 8pt !important; font-weight: 700; }
        table.form { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.form th, table.form td { border: .6pt solid #000; padding: .7mm .8mm; vertical-align: middle; overflow-wrap: anywhere; }
        table.form th { height: 14mm; text-align: center; font-size: 7.2pt; line-height: 1.15; font-weight: 700; }
        table.form td { height: 7.5mm; font-size: 7.5pt; line-height: 1.1; }
        .head-name, .resident-name { text-transform: uppercase; font-weight: 700; }
        .center { text-align: center; }
        .section-b { margin-top: 1.5mm; }
        .section-b th { height: 6.5mm !important; font-size: 8pt !important; }
        .section-b td { height: 6mm !important; }
        .signatures { width: 82%; margin: 2mm auto 0; border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
        .signatures td { width: 50%; border: 0; padding: 0 14mm; vertical-align: top; }
        .signature-label { margin-bottom: 0; }
        .signature-block { height: 13mm; text-align: center; position: relative; }
        .signature-image { display: block; height: 8mm; max-width: 43mm; margin: 0 auto -1.5mm; object-fit: contain; }
        .official-name { display: block; padding-top: 1mm; border-bottom: .6pt solid #000; text-transform: uppercase; font-weight: 700; }
        .official-title { display: block; margin-top: 1mm; }
        .page-number { position: absolute; right: 0; bottom: 0; font-size: 6.5pt; color: #333; }
    </style>
</head>
<body>
@foreach ($pages as $page)
    @php
        $memberRows = collect($page['members'])->pad(7, [])->take(7);
        $deceasedRows = collect($page['deceased'])->pad(3, [])->take(3);
    @endphp
    <section class="rbi-page">
        <table class="header">
            <tr>
                <td class="logo-cell">
                    @if ($logoDataUri)<img class="logo" src="{{ $logoDataUri }}" alt="Barangay seal">@endif
                </td>
                <td class="heading">
                    <h1>Updates of Barangay Registry of Barangay Inhabitants</h1>
                    <p>For the month of <strong>{{ strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET') }}</strong></p>
                    <p>Barangay {{ $barangayName }}</p>
                    @if ($page['continued'])<p class="continuation">{{ strtoupper($page['household_head']) }} HOUSEHOLD — CONTINUED</p>@endif
                </td>
                <td class="logo-cell"></td>
            </tr>
        </table>

        <table class="form">
            <colgroup>
                <col style="width: 13%"><col style="width: 22%"><col style="width: 5%"><col style="width: 10%">
                <col style="width: 13%"><col style="width: 9%"><col style="width: 13%"><col style="width: 15%">
            </colgroup>
            <thead>
            <tr>
                @foreach (App\Models\BarangayRbiUpdate::rowFields() as $label)<th>{{ $label }}</th>@endforeach
            </tr>
            </thead>
            <tbody>
            @foreach ($memberRows as $member)
                <tr>
                    @if ($loop->first)
                        <td class="head-name center" rowspan="7">{{ $page['household_head'] }}</td>
                    @endif
                    <td class="resident-name">{{ $member['inhabitant_name'] ?? '' }}</td>
                    <td class="center">{{ $member['sex'] ?? '' }}</td>
                    <td class="center">{{ ! empty($member['birth_date']) ? date('m/d/y', strtotime($member['birth_date'])) : '' }}</td>
                    <td>{{ $member['birth_place'] ?? '' }}</td>
                    <td class="center">{{ $member['civil_status'] ?? '' }}</td>
                    <td>{{ $member['occupation'] ?? '' }}</td>
                    <td>{{ $member['relationship'] ?? '' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

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
                        @if ($preparedSignatureDataUri)<img class="signature-image" src="{{ $preparedSignatureDataUri }}" alt="Secretary signature">@endif
                        <span class="official-name">{{ $secretaryName }}</span>
                        <span class="official-title">Brgy. Secretary</span>
                    </div>
                </td>
                <td>
                    <p class="signature-label">Noted by:</p>
                    <div class="signature-block">
                        @if ($attestedSignatureDataUri)<img class="signature-image" src="{{ $attestedSignatureDataUri }}" alt="Punong Barangay signature">@endif
                        <span class="official-name">{{ $punongBarangayName }}</span>
                        <span class="official-title">Punong Barangay</span>
                    </div>
                </td>
            </tr>
        </table>
        <span class="page-number">RBI family form {{ $loop->iteration }} of {{ count($pages) }}</span>
    </section>
@endforeach
</body>
</html>
