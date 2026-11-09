<section class="statistical-card" aria-labelledby="semester-population-title">
    <h2 id="semester-population-title">{{ $barangay->name }} · Semestral population projection</h2>
    <p>Target: <strong>{{ $populationProjection['target'] }}</strong> · Total inhabitants, dili in-/out-migration counts.</p>
    @if($populationProjection['estimate'] !== null)
        <strong class="statistical-estimate">{{ number_format($populationProjection['estimate'], 0) }}</strong><span>estimated inhabitants · provisional</span>
        <p>WMA sa latest 3 consecutive completed semester reports, weights 1, 2, 3. Data through {{ $populationProjection['data_through'] }}. Calculation before rounding: {{ number_format($populationProjection['estimate'], 4) }}.</p>
        <p><b>Assumption:</b> Gigamit ang reported headline population. Wala gi-correct ang source totals. Smooth continuation ra kini sa past counts; dili makapredict sa kalit nga births, deaths o migration.</p>
        @if(count($populationProjection['steps']) > 1)
            <p>Walay actual reports sa intervening semesters. Ang mosunod nga bridge estimates gigamit recursively aron maabot ang future target; <b>dili kini actual history</b>:</p>
        @endif
        <ul>@foreach($populationProjection['steps'] as $step)<li>{{ $step['label'] }}: {{ number_format($step['estimate'], 2) }} — {{ $step['is_target'] ? 'future target projection' : 'unobserved bridge estimate' }}</li>@endforeach</ul>
    @else
        <p>{{ $populationProjection['reason'] }}</p>
    @endif
    @if($populationProjection['quality_issues'])<p class="statistical-notice"><strong>{{ $populationProjection['quality_issues'] }} source reports need reconciliation:</strong> headline population differs from the age-bracket sum. This projection is provisional; review source figures before official planning.</p>@endif
    @if($populationProjection['missing_periods'])<p>Missing historical semesters: {{ implode('; ', $populationProjection['missing_periods']) }}. Wala kini gi-fill og zero.</p>@endif
    <p><strong>MAPE / accuracy: Not yet validated.</strong> Wala pay forecast saved before its target semester with a subsequent verified actual. Ang projection preview dili validation result, ug dili apil sa monthly migration metrics.</p>
    @if($populationProjection['history'])
        <details><summary>Source population history and WMA inputs</summary>
            <div class="statistical-table"><table><thead><tr><th>Semester</th><th>Reported population</th><th>Age-bracket sum</th><th>Initial WMA input</th><th>Source</th></tr></thead><tbody>
            @foreach($populationProjection['history'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['population'] }}</td><td>{{ $row['age_sum'] }}</td><td>{{ in_array($row['id'], array_column($populationProjection['inputs'], 'id')) ? 'Yes' : 'No' }}</td><td><a href="{{ route('reports.population.submissions.show', $row['id']) }}">View archived report</a></td></tr>@endforeach
            </tbody></table></div>
        </details>
    @endif
    <a href="{{ route('reports.population.submissions.index', ['barangay_id'=>$barangay->id]) }}">Population Report History</a>
</section>
