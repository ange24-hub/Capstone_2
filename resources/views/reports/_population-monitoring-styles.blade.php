.population-monitoring-scroll { overflow-x: auto; }
.population-monitoring-paper { box-sizing: border-box; max-width: 840px; min-width: 650px; margin: 0 auto; padding: 30px 34px; background: #fff; color: #111; font-family: Arial, sans-serif; font-size: 12px; line-height: 1.25; border: 1px solid #cbd5e1; box-shadow: 0 3px 14px #0f172a0d; }
.monitoring-form-code { margin: 0 0 12px; font-weight: 700; font-size: 11px; }
.monitoring-title { text-align: center; margin: 0 0 28px; }
.monitoring-title h2 { font-family: Arial, sans-serif; font-size: 19px; font-weight: 700; margin: 0 0 3px; color: #111; }
.monitoring-title p { margin: 0; }
.monitoring-meta { margin: 0 0 24px; line-height: 1.5; }
.monitoring-meta strong { text-decoration: underline; }
.monitoring-grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
.monitoring-grid th, .monitoring-grid td { border: 1px solid #555; padding: 3px 5px; vertical-align: middle; font-size: 11px; }
.monitoring-grid thead th { background: #1895ce; color: #111; text-align: center; font-weight: 700; }
.monitoring-grid thead th:first-child { text-align: left; }
.monitoring-grid tbody td { text-align: center; }
.monitoring-grid tbody td:first-child { text-align: left; }
.monitoring-grid .monitoring-section th { text-align: left; background: #e4eef3; font-weight: 700; }
.monitoring-grid .monitoring-age td:first-child { text-align: center; }
.monitoring-grid .monitoring-remarks { text-align: left !important; font-size: 9px; line-height: 1.1; }
.monitoring-total { font-weight: 700; }
.monitoring-signatures { width: 100%; border-collapse: collapse; margin-top: 10px; table-layout: fixed; }
.monitoring-signatures td { width: 50%; border: none; vertical-align: top; text-align: center; padding: 0 14px; }
.monitoring-signatures p { text-align: left; margin: 0; }
.monitoring-signature-space { height: 30px; }
.monitoring-signatures .monitoring-signed-space { height: 36px; }
.monitoring-signed-space img { max-width: 180px; height: 34px; }
.monitoring-signatory { display: block; min-height: 16px; border-bottom: 1px solid #111; margin: 0 auto 3px; max-width: 240px; text-transform: uppercase; }
.monitoring-signatures em { font-size: 10px; }
.monitoring-date { margin: 18px 0 12px; }
.monitoring-date strong { display: inline-block; border-bottom: 1px solid #111; padding: 0 10px 2px; }
.monitoring-footnote { border-top: 1px solid #777; padding-top: 5px; font-size: 9px; font-style: italic; line-height: 1.25; }
.monitoring-footnote p { margin: 2px 0; }
@media print {
    @page { size: A4 portrait; margin: 10mm 11mm; }
    .population-report-monitoring > .report-document-header, .population-report-monitoring > .population-report-header,
    .population-report-monitoring > .population-report-filter, .population-report-monitoring > .population-coverage-notice,
    .population-report-monitoring > .population-category-nav, .population-report-monitoring > .dashboard-empty-state { display: none !important; }
    .population-report-monitoring { display: block !important; padding: 0 !important; margin: 0 !important; }
    .population-monitoring-scroll { overflow: visible; }
    .population-monitoring-paper { min-width: 0; width: 100%; max-width: none; padding: 0; border: 0; box-shadow: none; font-size: 9pt; }
    .monitoring-title { margin-bottom: 10pt; }
    .monitoring-meta { margin-bottom: 8pt; line-height: 11pt; }
    .monitoring-grid th, .monitoring-grid td { font-size: 8.4pt; line-height: 9pt; padding: 1pt 4pt; }
    .monitoring-grid .monitoring-remarks { font-size: 7pt; }
    .monitoring-grid thead th { background: #1895ce !important; }
    .monitoring-grid .monitoring-section th { background: #e4eef3 !important; }
    .monitoring-grid { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    .monitoring-grid tr, .monitoring-signatures { break-inside: avoid; }
    .monitoring-signature-space { height: 12pt; }
    .monitoring-footnote { font-size: 7pt; }
}
