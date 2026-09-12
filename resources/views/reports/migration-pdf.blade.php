<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Monthly Migration Report</title>
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
th { background: #e6ebef; text-align: left; font-weight: bold; }
.number { text-align: right; }
.clean-table th:first-child { width: 19%; } .migration-report-table { font-size: 8pt; } .migration-report-table th:last-child { width: 27%; }
.total-row td { font-weight: bold; background: #f0f2f4; }
tr { page-break-inside: avoid; } thead { display: table-header-group; }
.population-report-notes { font-size: 7pt; line-height: 1.25; }
ol { margin: 4pt 0; padding-left: 15pt; } li { margin-bottom: 3pt; }
.report-caption { font-size: 7.5pt; margin: 4pt 0; color: #374557; }
.population-area-page { page-break-before: always; }
.population-coverage-table { font-size: 7pt; }
.population-coverage-table th:first-child { width: 21%; }
.population-coverage-table th, .population-coverage-table td { padding: 5pt 3pt; }
footer { position: fixed; bottom: -22pt; left: 0; right: 0; border-top: .5pt solid #8793a0; padding-top: 4pt; font-size: 7pt; color: #465361; }
.page-number { float: right; } .page-number:after { content: counter(page); }
</style></head><body>
<footer>SB-ISPGM | Monthly Migration Report | {{ $report['generatedAt']->format('d M Y') }} <span class="page-number">Page </span></footer>
<header class="report-header"><p>Republic of the Philippines</p><p>Province of Southern Leyte</p><p class="office">Municipality of Tomas Oppus</p><h1>MONTHLY MIGRATION REPORT</h1><p class="office">{{ $report['scopeLabel'] }} | Reporting year {{ $report['year'] }}</p><p class="report-meta">As of {{ $report['generatedAt']->format('d F Y') }} &nbsp; | &nbsp; Generated {{ $report['generatedAt']->format('h:i A T') }}</p></header>
@include('reports.migration-tables')
</body></html>