<!doctype html><html><head><meta charset="utf-8"><style>
@page{margin:10mm}body{font-family: "Times New Roman", Times, serif;font-size: 11pt;color:#000;background:#fff}h1{text-align:center;font-size: 11pt;margin:0 0 10px}.meta{display:flex;justify-content:space-between;margin-bottom:10px}.family{page-break-inside:avoid;margin-bottom:14px}.family h2{font-size: 11pt;margin:0;padding:5px;background:#fff;border:1px solid #000}table{width:100%;border-collapse:collapse}th,td{border:1px solid #000;padding:4px;text-align:center}th{background:#fff;font-size: 11pt}.footer{margin-top:16px;text-align:center;font-size: 11pt}
@include('reports._document-header-styles')
</style></head><body>
@foreach($families as $household => $members)<section class="family">
@include('reports._document-header', ['documentTitle' => 'RECORD OF BARANGAY INHABITANTS BY HOUSEHOLD', 'documentBarangay' => strtoupper($barangay->name), 'documentHousehold' => $household, 'documentDate' => now()->format('m/d/Y')])
<p class="report-document-context">Reporting Month: {{ strtoupper(date('F Y', strtotime($month.'-01'))) }}</p>
<table><thead><tr><th>LAST</th><th>FIRST</th><th>MIDDLE</th><th>Qualifier</th><th>RELATIONSHIP</th><th>ADDRESS</th><th>PLACE OF BIRTH</th><th>DATE OF BIRTH</th><th>AGE</th><th>SEX</th><th>CIVIL STATUS</th><th>SCHOOL LEVEL</th><th>RELIGION</th><th>OCCUPATION</th><th>REMARKS</th></tr></thead><tbody>
@foreach($members as $member)<tr><td>{{ $member->last_name }}</td><td>{{ $member->first_name }}</td><td>{{ $member->middle_name }}</td><td>{{ $member->suffix }}</td><td>{{ $member->relationship_to_head }}</td><td>{{ $member->complete_address }}</td><td>{{ $member->birth_place }}</td><td>{{ optional($member->birth_date)->format('m-d-Y') }}</td><td>{{ $member->recorded_age }}</td><td>{{ $member->sex === 'Male' ? 'M' : 'F' }}</td><td>{{ $member->civil_status }}</td><td>{{ $member->education_level }}</td><td>{{ $member->religion }}</td><td>{{ $member->occupation }}</td><td>{{ \App\Support\RegistryRemarks::display($member->remarks) }}</td></tr>@endforeach
</tbody></table></section>@endforeach
<div class="footer">Prepared by: <b>&nbsp; — BHW / Encoder</b> &nbsp;&nbsp;&nbsp; Certified Correct: <b>{{ $barangay->secretary_name }} — Barangay Secretary</b> &nbsp;&nbsp;&nbsp; Verified by: <b>{{ $barangay->punong_barangay_name }} — Punong Barangay</b></div>
</body></html>
