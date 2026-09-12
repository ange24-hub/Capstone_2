<section class="population-report-section"><h2>1. Monthly movements in {{ $report['year'] }}</h2>
<div class="table-wrap"><table class="clean-table migration-report-table"><thead><tr><th>Month</th><th class="number">In-migration</th><th class="number">Out-migration</th><th class="number">Net change</th><th>Record status</th></tr></thead><tbody>
@foreach($report['months'] as $month)<tr><td>{{ $month['label'] }}</td><td class="number">{{ number_format($month['in']) }}</td><td class="number">{{ number_format($month['out']) }}</td><td class="number">{{ number_format($month['net']) }}</td><td>{{ $month['total'] ? 'Events recorded' : 'No recorded events' }}</td></tr>@endforeach
<tr class="total-row"><td>Annual total</td><td class="number">{{ number_format($report['in']) }}</td><td class="number">{{ number_format($report['out']) }}</td><td class="number">{{ number_format($report['net']) }}</td><td>{{ number_format($report['total']) }} events</td></tr>
</tbody></table></div></section>
<section class="population-report-notes"><h2>Notes and counting basis</h2><ol>
<li>Source: recorded migration events with a movement date in {{ $report['year'] }}. Generated {{ $report['generatedAt']->format('d F Y, h:i A T') }}. {{ $report['coveredBarangays'] }} of {{ $report['totalBarangays'] }} barangays in scope have recorded events for this year.</li>
<li>In-migration means a recorded move into a barangay; out-migration means a recorded move out. Net change = in-migration minus out-migration. Counts represent events, not unique residents or families.</li>
<li>No recorded events does not confirm zero movement. Future months are not forecasts. These records alone do not establish seasonal patterns, population growth or complete migration coverage.</li>
<li>Registration, new-inhabitant entries and changes in residence status are not automatically counted as migration events. A move between two barangays may be recorded once at the origin and once at the destination; municipal totals combine barangay events.</li>
</ol></section>
@if($report['totalBarangays'] > 1)
<div class="population-area-page"><section class="population-report-section"><h2>2. Annual movements by barangay</h2><p class="report-caption">{{ $report['scopeLabel'] }} | Reporting year {{ $report['year'] }}</p>
<div class="table-wrap"><table class="clean-table migration-report-table"><thead><tr><th>Barangay</th><th class="number">In-migration</th><th class="number">Out-migration</th><th class="number">Net change</th><th>Record status</th></tr></thead><tbody>
@foreach($report['coverage'] as $area)<tr><td>{{ $area['name'] }}</td><td class="number">{{ number_format($area['in']) }}</td><td class="number">{{ number_format($area['out']) }}</td><td class="number">{{ number_format($area['net']) }}</td><td>{{ $area['total'] ? 'Events recorded' : 'No recorded events' }}</td></tr>@endforeach
<tr class="total-row"><td>Total recorded</td><td class="number">{{ number_format($report['in']) }}</td><td class="number">{{ number_format($report['out']) }}</td><td class="number">{{ number_format($report['net']) }}</td><td>{{ number_format($report['total']) }} events</td></tr>
</tbody></table></div></section></div>
@endif
