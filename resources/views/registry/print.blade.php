<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - Barangay {{ $barangay->name }}</title>
    <style>
        @page { size: 13in 8.5in; margin: 6mm 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; background: #fff; font-family: Arial, sans-serif; font-size: 8pt; }
        .print-toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; padding: 12px 20px; background: #f6f3ec; }
        .print-actions { display: flex; align-items: center; gap: 8px; }
        .print-toolbar a, .print-toolbar button { border: 1px solid #d6d0c5; border-radius: 5px; padding: 8px 12px; color: #203957; background: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .print-toolbar button { background: #203957; color: #fff; border-color: #203957; }
        main { padding: 8mm; }
        h1 { margin: 0 0 2mm; text-align: center; font-size: 13pt; color: #203957; }
        .subtitle { margin: 0 0 6mm; text-align: center; font-size: 8pt; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 2.5mm 8mm; margin: 0 0 5mm; padding: 3mm 0; border-top: .6pt solid #b9c7d2; border-bottom: .6pt solid #b9c7d2; }
        .meta span { white-space: nowrap; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        th, td { border: .6pt solid #a9b7c3; padding: 2.5pt 2pt; vertical-align: top; overflow-wrap: break-word; line-height: 1.3; }
        th { background: #fff; color: #203957; text-align: center; font-size: 6pt; font-weight: 700; }
        td { font-size: 6.5pt; }
        tbody tr:nth-child(even) { background: #fff; }
        .household-start td { border-top: 1.3pt solid #6d8599; }
        .empty { padding: 6mm; text-align: center; }
        @media print {
            .print-toolbar { display: none; }
            main { padding: 0; }
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
                <col style="width: 4%"><col style="width: 4%">
                <col style="width: 7%"><col style="width: 7%"><col style="width: 6%"><col style="width: 4%">
                <col style="width: 7%"><col style="width: 6%"><col style="width: 7%"><col style="width: 6%">
                <col style="width: 3%"><col style="width: 3%"><col style="width: 5%"><col style="width: 6%">
                <col style="width: 6%"><col style="width: 7%"><col style="width: 7%"><col style="width: 5%">
            </colgroup>
            <thead>
                <tr><th rowspan="2">HH<br>NO.</th><th rowspan="2">FAMILY<br>NO.</th><th colspan="4">NAME</th><th rowspan="2">RELATIONSHIP<br>TO HH HEAD</th><th rowspan="2">PUROK / SITIO</th><th rowspan="2">PLACE OF<br>BIRTH</th><th rowspan="2">DATE OF<br>BIRTH</th><th rowspan="2">AGE</th><th rowspan="2">SEX<br>(M/F)</th><th rowspan="2">CIVIL<br>STATUS</th><th rowspan="2">SCHOOL<br>LEVEL</th><th rowspan="2">RELIGION</th><th rowspan="2">OCCUPATION</th><th rowspan="2">REMARKS</th><th rowspan="2">ETHNICITY</th></tr>
                <tr><th>LAST NAME</th><th>FIRST NAME</th><th>MIDDLE NAME</th><th>QUALIFIER</th></tr>
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
