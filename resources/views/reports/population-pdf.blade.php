<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>{{ $reportTitle ?? 'Population Summary' }}</title>
<style>
@page { margin: 30pt 34pt 34pt; }
body { font-family: DejaVu Serif, serif; color: #172536; font-size: 8.5pt; line-height: 1.2; }
.report-header { text-align: center; border-bottom: 1.5pt solid #172536; padding-bottom: 9pt; margin-bottom: 10pt; }
.report-header p { margin: 2pt 0; } .report-header .office { font-weight: bold; }
h1 { font-size: 15pt; letter-spacing: 1pt; margin: 10pt 0 6pt; }
h2 { font-size: 10pt; margin: 8pt 0 4pt; page-break-after: avoid; }
.report-meta { font-size: 8pt; margin: 4pt 0; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th, td { border: .5pt solid #8793a0; padding: 2.5pt 6pt; overflow-wrap: break-word; }
th { background: #e2e8f0; color: #172536; text-align: center; font-weight: bold; }
.number { text-align: right; }
th.number { text-align: center; }
.clean-table th:first-child { width: 70%; }
.total-row td { font-weight: bold; background: #f1f5f9; }
tr { page-break-inside: avoid; } thead { display: table-header-group; }
.population-report-notes { font-size: 7pt; line-height: 1.25; }
ol { margin: 4pt 0; padding-left: 15pt; } li { margin-bottom: 3pt; }
.report-caption { font-size: 7.5pt; margin: 4pt 0; color: #374557; }
.population-member-table { table-layout: fixed; font-size: 8pt; }
.population-member-table th, .population-member-table td { padding: 5pt 3pt; vertical-align: top; word-wrap: break-word; }
.population-member-table th, .population-member-table td { text-align: center; }
.population-member-table th:nth-child(1) { width: 55%; }
.population-member-table th:nth-child(2) { width: 15%; }
.population-member-table th:nth-child(3) { width: 30%; }
.population-member-table-grouped th:nth-child(1) { width: 40%; }
.population-member-table-grouped th:nth-child(2) { width: 12%; }
.population-member-table-grouped th:nth-child(3) { width: 23%; }
.population-member-table-grouped th:nth-child(4) { width: 25%; }
.population-detail-group h3 { font-size: 10pt; page-break-after: avoid; text-align: center; }
.population-barangay-row th { background: #e2e8f0; text-align: center; }
.population-subgroup-row th { background: #f1f5f9; text-align: center; }
.population-detail-group { margin-bottom: 14pt; }
.population-head-table th:nth-child(1) { width: 25%; }
.population-head-table th:nth-child(2) { width: 25%; }
.population-head-table th:nth-child(3) { width: 50%; }
.population-head-table th, .population-head-table td { padding: 6pt; vertical-align: top; }
.population-area-page { page-break-before: always; }
.population-coverage-table { font-size: 7pt; }
.population-coverage-table th:first-child { width: 21%; }
.population-coverage-table th, .population-coverage-table td { padding: 5pt 3pt; }
footer { position: fixed; bottom: -22pt; left: 0; right: 0; border-top: .5pt solid #8793a0; padding-top: 4pt; font-size: 7pt; color: #465361; }
.page-number { float: right; } .page-number:after { content: counter(page); }
</style></head><body>
<footer>RBIM | {{ $reportTitle ?? 'Population Summary' }} | {{ $report['generatedAt']->format('d M Y') }} <span class="page-number">Page </span></footer>
<header class="report-header"><p>Republic of the Philippines</p><h1>{{ strtoupper($reportTitle ?? 'Population Summary') }} REPORT</h1></header>
@include('reports._jurisdiction')
@include('reports.population-content', ['showReportNotes' => false])
</body></html>
