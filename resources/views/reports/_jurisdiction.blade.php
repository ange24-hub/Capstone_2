@php
    $reportBarangay = str_starts_with($report['scopeLabel'], 'Barangay ')
        ? substr($report['scopeLabel'], 9) : 'All barangays';
@endphp
<table class="report-jurisdiction" aria-label="Report location" style="width:100%;border-collapse:collapse;margin:10px auto;table-layout:fixed;background:#fff;color:#172536;text-align:center">
    <tbody>
        <tr><td style="width:62%;border:0;padding:3px 8px;text-align:center;background:#fff">REGION: VIII</td><td rowspan="3" style="width:38%;border:0;padding:3px 8px;text-align:center;vertical-align:middle;background:#fff">BARANGAY: <strong>{{ $reportBarangay }}</strong></td></tr>
        <tr><td style="border:0;padding:3px 8px;text-align:center;background:#fff">PROVINCE: SOUTHERN LEYTE</td></tr>
        <tr><td style="border:0;padding:3px 8px;text-align:center;background:#fff">CITY/MUNICIPALITY: TOMAS OPPUS</td></tr>
    </tbody>
</table>
