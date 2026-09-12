<section class="population-report-section">
    <h2>1. Population, families and households</h2>
    <div class="table-wrap"><table class="clean-table"><thead><tr><th>Indicator</th><th class="number">Count</th></tr></thead><tbody>
        <tr class="total-row"><td>Total registered residents</td><td class="number">{{ number_format($report['totalRecords']) }}</td></tr>
        <tr><td>Families</td><td class="number">{{ $report['totalFamilies'] ? number_format($report['totalFamilies']) : 'Not yet identified' }}</td></tr>
        <tr><td>Households</td><td class="number">{{ $report['totalHouseholds'] ? number_format($report['totalHouseholds']) : 'Not yet encoded' }}</td></tr>
        <tr><td>Senior citizens (birth date or SC remarks)</td><td class="number">{{ number_format($report['totalSeniors']) }}</td></tr>
        <tr><td>Persons with disability (PWD in remarks)</td><td class="number">{{ number_format($report['totalPwd']) }}</td></tr>
    </tbody></table></div>
    <p class="report-caption">Counts cover available registry records, including active, migrated-out and inactive records.</p>
</section>
<section class="population-report-section"><h2>2. Population by sex</h2><div class="table-wrap"><table class="clean-table"><thead><tr><th>Category</th><th class="number">Residents</th></tr></thead><tbody>
@foreach($report['sex'] as $label => $count)<tr><td>{{ $label }}</td><td class="number">{{ number_format($count) }}</td></tr>@endforeach
<tr class="total-row"><td>Total</td><td class="number">{{ number_format($report['totalRecords']) }}</td></tr>
</tbody></table></div></section>
<section class="population-report-section"><h2>3. Population by age</h2><div class="table-wrap"><table class="clean-table"><thead><tr><th>Age group (years)</th><th class="number">Residents</th></tr></thead><tbody>
@foreach($report['ages'] as $label => $count)<tr><td>{{ $label }}</td><td class="number">{{ number_format($count) }}</td></tr>@endforeach
<tr class="total-row"><td>Total</td><td class="number">{{ number_format($report['totalRecords']) }}</td></tr>
</tbody></table></div></section>
<section class="population-report-notes"><h2>Notes and counting basis</h2>
    <ol>
        <li>Source: inhabitant and household records as of {{ $report['generatedAt']->format('d F Y') }}. {{ $report['coveredBarangays'] }} of {{ $report['totalBarangays'] }} barangays in scope have resident records. Coverage is not yet verified as complete; this is a registry summary, not an official census.</li>
        <li>Ages use birth dates as of the report date. Senior citizens are counted once if aged 60+ OR marked SC / senior citizen in remarks. {{ number_format($report['seniorRemarksOnly']) }} senior records qualify through remarks only and may need birth-date verification.</li>
        <li>PWD counts use explicit PWD / person with disability markers in remarks. No marker does not confirm non-PWD status. Senior and PWD counts overlap other categories; do not add them to the population total.</li>
        <li>Each full family number identifies a family within its barangay. Numbers 64, 64.1 and 64.2 mean three families in one household. A household with one recorded resident also counts as one family, even without a family number ({{ number_format($report['singlePersonFamiliesAdded']) }} added). {{ number_format($report['recordsWithoutFamily']) }} resident records have no family number.</li>
        <li>No resident records means “Not yet encoded”, not zero population. Unknown ages and unspecified sex remain shown separately.</li>
    </ol>
</section>
@if($report['totalBarangays'] > 1)
<div class="population-area-page">
<section class="population-report-section"><h2>4. Summary by barangay</h2>
<p class="report-caption">{{ $report['scopeLabel'] }} | As of {{ $report['generatedAt']->format('d F Y') }}</p>
<div class="table-wrap"><table class="clean-table population-coverage-table"><thead><tr><th>Barangay</th><th class="number">Residents</th><th class="number">Male</th><th class="number">Female</th><th class="number">Families</th><th class="number">Households</th><th class="number">SC</th><th class="number">PWD</th></tr></thead><tbody>
    @foreach($report['coverage'] as $area)<tr><td>{{ $area['name'] }}</td>
    @if($area['records'] === 0)<td colspan="7">Not yet encoded</td>
    @else
    <td class="number">{{ number_format($area['records']) }}</td><td class="number">{{ number_format($area['sex']['Male']) }}</td><td class="number">{{ number_format($area['sex']['Female']) }}</td><td class="number">{{ $area['families'] ? number_format($area['families']) : 'Pending' }}</td><td class="number">{{ $area['households'] ? number_format($area['households']) : 'Pending' }}</td><td class="number">{{ number_format($area['seniors']) }}</td><td class="number">{{ number_format($area['pwd']) }}</td>
    @endif</tr>@endforeach
    <tr class="total-row"><td>Total recorded</td><td class="number">{{ number_format($report['totalRecords']) }}</td><td class="number">{{ number_format($report['sex']['Male']) }}</td><td class="number">{{ number_format($report['sex']['Female']) }}</td><td class="number">{{ number_format($report['totalFamilies']) }}</td><td class="number">{{ number_format($report['totalHouseholds']) }}</td><td class="number">{{ number_format($report['totalSeniors']) }}</td><td class="number">{{ number_format($report['totalPwd']) }}</td></tr>
</tbody></table></div>
<p class="report-caption">SC = senior citizens identified by birth date or remarks. PWD = marked in remarks. Pending = family / household information not yet identified. Male + female may differ from the total where sex is unspecified.</p>
</section></div>
<div class="population-area-page">
<section class="population-report-section"><h2>5. Age groups by barangay</h2>
<p class="report-caption">{{ $report['scopeLabel'] }} | As of {{ $report['generatedAt']->format('d F Y') }}</p>
<div class="table-wrap"><table class="clean-table population-coverage-table"><thead><tr><th>Barangay</th>@foreach(array_keys($report['ages']) as $label)<th class="number">{{ $loop->last ? 'Age unknown' : $label }}</th>@endforeach<th class="number">Sex unspecified</th></tr></thead><tbody>
    @foreach($report['coverage'] as $area)<tr><td>{{ $area['name'] }}</td>
    @if($area['records'] === 0)<td colspan="6">Not yet encoded</td>
    @else
    @foreach($area['ages'] as $count)<td class="number">{{ number_format($count) }}</td>@endforeach
    <td class="number">{{ number_format($area['sex']['Not specified / other']) }}</td>
    @endif</tr>@endforeach
    <tr class="total-row"><td>Total recorded</td>@foreach($report['ages'] as $count)<td class="number">{{ number_format($count) }}</td>@endforeach<td class="number">{{ number_format($report['sex']['Not specified / other']) }}</td></tr>
</tbody></table></div>
<p class="report-caption">The 60+ column uses birth dates only. The SC total may be higher because it also includes senior citizen markers in remarks.</p>
</section></div>
@endif
