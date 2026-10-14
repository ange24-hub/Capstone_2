<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Monthly Migration Report</title>
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
th { background: #fff; text-align: left; font-weight: bold; }
.number { text-align: right; }
.clean-table th:first-child { width: 19%; } .migration-report-table { font-size: 11pt; } .migration-report-table th:last-child { width: 27%; }
.total-row td { font-weight: bold; background: #fff; }
tr { page-break-inside: avoid; } thead { display: table-header-group; }
.population-report-notes { font-size: 11pt; line-height: 1.25; }
ol { margin: 4pt 0; padding-left: 15pt; } li { margin-bottom: 3pt; }
.report-caption { font-size: 11pt; margin: 4pt 0; color: #000; }
.population-area-page { page-break-before: always; }
.population-coverage-table { font-size: 11pt; }
.population-coverage-table th:first-child { width: 21%; }
.population-coverage-table th, .population-coverage-table td { padding: 5pt 3pt; }
footer { position: fixed; bottom: -22pt; left: 0; right: 0; border-top: .5pt solid #000; padding-top: 4pt; font-size: 11pt; color: #000; }
.page-number { float: right; } .page-number:after { content: counter(page); }
@include('reports._document-header-styles')
</style></head><body>
<footer>SB-ISPGM | Monthly Migration Report | {{ $report['generatedAt']->format('d M Y') }} <span class="page-number">Page </span></footer>
@include('reports._document-header', ['documentTitle' => 'MONTHLY MIGRATION REPORT — '.$report['year'], 'documentBarangay' => str_starts_with($report['scopeLabel'], 'Barangay ') ? substr($report['scopeLabel'], 9) : 'All barangays', 'documentDate' => $report['generatedAt']->format('m/d/Y')])
@include('reports.migration-tables', ['showReportNotes' => false])
</body></html>
