<section class="population-report-section"><h2>1. Monthly movements in {{ $report['year'] }}</h2>
<div class="table-wrap"><table class="clean-table migration-report-table"><thead><tr><th>Month</th><th class="number">In-migration</th><th class="number">Out-migration</th><th class="number">Recorded net movement</th><th>Record status</th></tr></thead><tbody>
@foreach($report['months'] as $month)<tr><td>{{ $month['label'] }}</td><td class="number">{{ $month['in_known'] ? number_format($month['in']) : '-' }}</td><td class="number">{{ $month['out_known'] ? number_format($month['out']) : '-' }}</td><td class="number">{{ $month['net_known'] ? number_format($month['net']) : '-' }}</td><td>{{ $month['status'] }}</td></tr>@endforeach
<tr class="total-row"><td>Annual total</td><td class="number">{{ $report['in_known'] ? number_format($report['in']) : '-' }}</td><td class="number">{{ $report['out_known'] ? number_format($report['out']) : '-' }}</td><td class="number">{{ $report['in_known'] && $report['out_known'] ? number_format($report['net']) : '-' }}</td><td>{{ number_format($report['total']) }} events</td></tr>
</tbody></table></div></section>
@if($report['totalBarangays'] > 1)
<div class="population-area-page"><section class="population-report-section"><h2>2. Annual movements by barangay</h2><p class="report-caption">{{ $report['scopeLabel'] }} | Reporting year {{ $report['year'] }}</p>
<div class="table-wrap"><table class="clean-table migration-report-table"><thead><tr><th>Barangay</th><th class="number">In-migration</th><th class="number">Out-migration</th><th class="number">Recorded net movement</th><th>Record status</th></tr></thead><tbody>
@foreach($report['coverage'] as $area)<tr><td>{{ $area['name'] }}</td><td class="number">{{ $area['in_known'] ? number_format($area['in']) : '-' }}</td><td class="number">{{ $area['out_known'] ? number_format($area['out']) : '-' }}</td><td class="number">{{ $area['in_known'] && $area['out_known'] ? number_format($area['net']) : '-' }}</td><td>{{ $area['status'] }}</td></tr>@endforeach
<tr class="total-row"><td>Total recorded</td><td class="number">{{ $report['in_known'] ? number_format($report['in']) : '-' }}</td><td class="number">{{ $report['out_known'] ? number_format($report['out']) : '-' }}</td><td class="number">{{ $report['in_known'] && $report['out_known'] ? number_format($report['net']) : '-' }}</td><td>{{ number_format($report['total']) }} events</td></tr>
</tbody></table></div></section></div>
@endif
@if($showReportNotes ?? true)
<details class="population-report-notes migration-report-notes"><summary>Notes and counting basis</summary><ol>
<li>Source: recorded migration events and unlinked dated movement-ledger entries with an actual movement date in {{ $report['year'] }}. Generated {{ $report['generatedAt']->format('d F Y, h:i A T') }}. {{ $report['coveredBarangays'] }} of {{ $report['totalBarangays'] }} barangays in scope have recorded events for this year.</li>
<li>In-migration means a recorded move into a barangay; out-migration means a recorded move out. Recorded net movement = in-migration minus out-migration. The same resident, direction, barangay and movement day count once. Different movement days remain separate events. Duplicate originals are retained.</li>
<li>A dash means no confirmed count. A displayed zero requires a completed month whose verified source still matches the records. Future months are not forecasts. These records alone do not establish seasonal patterns, population growth or complete migration coverage.</li>
<li>Registration, new-inhabitant entries and changes in residence status are not automatically counted as migration events. A move between two barangays may be recorded once at the origin and once at the destination; municipal totals combine barangay events.</li>
</ol></details>
@endif
