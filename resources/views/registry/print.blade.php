<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - Barangay {{ $barangay->name }}</title>
    <style>
        /* Keep browser-generated titles and URLs off every registry printout. */
        @page { size: 13in 8.5in; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: "Times New Roman", Times, serif; font-size: 11pt; }
        .print-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 20px; background: #f6f3ec; }
        .print-actions { display: flex; align-items: center; gap: 8px; }
        .print-toolbar a, .print-toolbar button { border: 1px solid #d6d0c5; border-radius: 5px; padding: 8px 12px; color: #203957; background: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .print-toolbar button { background: #203957; color: #fff; border-color: #203957; }
        main { padding: 8mm; min-width: 1200px; }
        h1 { margin: 0 0 2mm; text-align: center; font-size: 11pt; color: #203957; }
        .subtitle { margin: 0 0 6mm; text-align: center; font-size: 11pt; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2.5mm 8mm; margin: 0 0 5mm; padding: 3mm 0; border-top: .6pt solid #b9c7d2; border-bottom: .6pt solid #b9c7d2; }
        .meta span { white-space: nowrap; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border: .6pt solid #000; padding: 5pt 4pt; vertical-align: top; overflow-wrap: break-word; line-height: 1.35; }
        th { background: #fff; color: #000; text-align: center; font-size: 8pt; font-weight: 700; padding: 5pt 2pt; }
        td { font-size: 9pt; }
        tbody tr:nth-child(even) { background: #fff; }
        .household-start td { border-top: 1.3pt solid #000; }
        .empty { padding: 6mm; text-align: center; }
        @media print {
            .print-toolbar { display: none; }
            main { min-width: 0; padding: 6mm 8mm; -webkit-box-decoration-break: clone; box-decoration-break: clone; }
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; }
        }
        @include('reports._document-header-styles')
    </style>
</head>
<body>
    <div class="print-toolbar"><a href="{{ route('barangay.registry.active') }}">Back to Resident Registry</a><div class="print-actions"><a href="{{ route('barangay.registry.download', ['scope' => $scope]) }}">Download Excel</a><button type="button" onclick="window.print()">Print report</button></div></div>
    <main>
        @include('reports._document-header', ['documentTitle' => $title, 'documentBarangay' => strtoupper($barangay->name), 'documentDate' => now()->format('m/d/Y')])
        <p class="report-document-context">HOUSEHOLDS: {{ $residents->pluck('household_id')->unique()->count() }} &middot; RESIDENTS: {{ $residents->count() }}</p>
        <table>
            <colgroup>
                <col style="width: 3%"><col style="width: 3.5%"><col style="width: 7%"><col style="width: 8%"><col style="width: 6.5%"><col style="width: 4.5%"><col style="width: 6%"><col style="width: 7%"><col style="width: 8%"><col style="width: 6%"><col style="width: 2.5%"><col style="width: 3%"><col style="width: 4.5%"><col style="width: 6.5%"><col style="width: 4.5%"><col style="width: 6%"><col style="width: 9%"><col style="width: 4.5%">
            </colgroup>
            <thead>
                <tr><th rowspan="2">HH<br>NO.</th><th rowspan="2">Family<br>No.</th><th colspan="4">NAME</th><th rowspan="2">Relation to<br>HH head</th><th rowspan="2">Purok / Sitio</th><th rowspan="2">Place of<br>birth</th><th rowspan="2">Date of<br>birth</th><th rowspan="2">Age</th><th rowspan="2">Sex<br>(M/F)</th><th rowspan="2">Civil<br>status</th><th rowspan="2">School<br>level</th><th rowspan="2">Religion</th><th rowspan="2">Occupation</th><th rowspan="2">Remarks</th><th rowspan="2">Ethnicity</th></tr>
                <tr><th>Last name</th><th>First name</th><th>Middle name</th><th>Qualifier</th></tr>
            </thead>
            <tbody>
                @php($previousHousehold = null)
                @forelse($residents as $resident)
                    <tr class="{{ $previousHousehold !== $resident->household_id ? 'household-start' : '' }}">
                        <td>{{ $resident->household?->household_number }}</td><td>{{ $resident->family_number }}</td><td>{{ $resident->last_name }}</td><td>{{ $resident->first_name }}</td><td>{{ $resident->middle_name }}</td><td>{{ $resident->suffix }}</td><td>{{ $resident->relationship_to_head }}</td><td>{{ $resident->household?->purok }}</td><td>{{ $resident->birth_place }}</td><td>{{ optional($resident->birth_date)->format('m-d-y') }}</td><td>{{ $resident->recorded_age }}</td><td>{{ ['Male' => 'M', 'Female' => 'F'][$resident->sex] ?? $resident->sex }}</td><td>{{ $resident->civil_status }}</td><td>{{ $resident->education_level }}</td><td>{{ $resident->religion }}</td><td>{{ $resident->occupation }}</td><td>{{ \App\Support\RegistryRemarks::display($resident->remarks) }}</td><td>{{ $resident->ethnicity }}</td>
                    </tr>
                    @php($previousHousehold = $resident->household_id)
                @empty
                    <tr><td class="empty" colspan="18">No matching active residents.</td></tr>
                @endforelse
            </tbody>
        </table>
    </main>
</body>
</html>
