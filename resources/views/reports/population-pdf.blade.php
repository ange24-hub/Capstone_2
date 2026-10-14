<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $reportTitle ?? 'Population Summary' }}</title>
<style>
@page { margin: 30pt 34pt 34pt; }
body { font-family: "Times New Roman", Times, serif; color: #000; background: #fff; font-size: 11pt; line-height: 1.2; }
.report-header { text-align: center; border-bottom: 1.5pt solid #000; padding-bottom: 9pt; margin-bottom: 10pt; }
.report-header p { margin: 2pt 0; } .report-header .office { font-weight: bold; }
h1 { font-size: 11pt; letter-spacing: 1pt; margin: 10pt 0 6pt; }
h2 { font-size: 11pt; margin: 8pt 0 4pt; page-break-after: avoid; }
.report-meta { font-size: 11pt; margin: 4pt 0; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: .5pt solid #000; padding: 2.5pt 6pt; overflow-wrap: break-word; }
th { background: #fff; color: #000; text-align: center; font-weight: bold; }
.number { text-align: right; }
th.number { text-align: center; }
.clean-table th:first-child { width: 70%; }
.total-row td { font-weight: bold; background: #fff; }
tr { page-break-inside: avoid; } thead { display: table-header-group; }
.population-report-notes { font-size: 11pt; line-height: 1.25; }
ol { margin: 4pt 0; padding-left: 15pt; } li { margin-bottom: 3pt; }
.report-caption { font-size: 11pt; margin: 4pt 0; color: #000; }
.population-member-table { table-layout: fixed; font-size: 11pt; }
.population-member-table th, .population-member-table td { padding: 5pt 3pt; vertical-align: top; word-wrap: break-word; }
.population-member-table th, .population-member-table td { text-align: center; }
.population-member-table th:nth-child(1) { width: 55%; }
.population-member-table th:nth-child(2) { width: 15%; }
.population-member-table th:nth-child(3) { width: 30%; }
.population-member-table-grouped th:nth-child(1) { width: 40%; }
.population-member-table-grouped th:nth-child(2) { width: 12%; }
.population-member-table-grouped th:nth-child(3) { width: 23%; }
.population-member-table-grouped th:nth-child(4) { width: 25%; }
.population-detail-group h3 { font-size: 11pt; page-break-after: avoid; text-align: center; }
.population-barangay-row th { background: #fff; text-align: center; }
.population-subgroup-row th { background: #fff; text-align: center; }
.population-detail-group { margin-bottom: 14pt; }
.population-head-table th:nth-child(1) { width: 25%; }
.population-head-table th:nth-child(2) { width: 25%; }
.population-head-table th:nth-child(3) { width: 50%; }
.population-head-table th, .population-head-table td { padding: 6pt; vertical-align: top; }
.population-area-page { page-break-before: always; }
.population-coverage-table { font-size: 11pt; }
.population-coverage-table th:first-child { width: 21%; }
.population-coverage-table th, .population-coverage-table td { padding: 5pt 3pt; }
footer { position: fixed; bottom: -22pt; left: 0; right: 0; border-top: .5pt solid #000; padding-top: 4pt; font-size: 11pt; color: #000; }
.page-number { float: right; } .page-number:after { content: counter(page); }
@include('reports._document-header-styles')
</style></head><body>
<footer>RBIM | {{ $reportTitle ?? 'Population Summary' }} | {{ $report['generatedAt']->format('d M Y') }} <span class="page-number">Page </span></footer>
@include('reports._document-header', ['documentTitle' => strtoupper($reportTitle ?? 'Population Summary').' REPORT', 'documentBarangay' => str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays', 'documentDate' => $report['generatedAt']->format('m/d/Y')])
@include('reports.population-content', ['showReportNotes' => false])
</body></html>
