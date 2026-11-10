<section class="statistical-population statistical-card" id="population-forecast" aria-labelledby="semester-population-title">
    <div class="statistical-section-heading">
        <div>
            <h2 id="semester-population-title">{{ $barangay->name }} population forecast</h2>
        </div>
        <span class="statistical-status {{ $populationProjection['estimate'] === null ? 'is-waiting' : '' }}">{{ $populationProjection['estimate'] === null ? 'Insufficient history' : 'Provisional estimate' }}</span>
    </div>

    @if($populationProjection['estimate'] !== null)
        <div class="statistical-population-result statistical-population-compact">
            <div class="statistical-result-main"><span class="statistical-result-label">Projected {{ $populationProjection['target'] }}</span>
                <div><strong class="statistical-estimate">{{ number_format($populationProjection['estimate'], 0) }}</strong><span class="statistical-unit">inhabitants</span></div>
            </div>
        </div>
        @if(count($populationProjection['steps']) > 1)
            <p class="statistical-explain">{{ count($populationProjection['steps']) - 1 }} gap periods use estimates.</p>
        @endif
    @else
        <p class="statistical-empty">{{ $populationProjection['reason'] }}</p>
    @endif

    @if($populationProjection['quality_issues'])
        <p class="statistical-caution"><strong>{{ $populationProjection['quality_issues'] }} source reports need review.</strong></p>
    @endif
    @if($populationChart)
        @include('barangay.dashboard._prediction-chart', [
            'chartId' => 'population-trend', 'chartTitle' => 'Population trend',
            'chartRows' => $populationChart, 'chartNote' => 'S1 / S2 = semester',
        ])
    @endif
    <details class="statistical-details"><summary>Calculation &amp; source reports</summary>
        @if(!empty($populationProjection['excluded_reports']))
            <p>{{ count($populationProjection['excluded_reports']) }} submitted report(s) excluded from forecast inputs:</p>
            <ul>@foreach($populationProjection['excluded_reports'] as $excluded)<li>{{ $excluded['label'] }}: {{ $excluded['reason'] }}</li>@endforeach</ul>
        @endif
        @if($populationProjection['estimate'] !== null)
            <p><strong>Weighted Moving Average</strong> · 3 completed reports · weights 1, 2, 3.</p>
            <p>Last actual source: {{ $populationProjection['data_through'] }}</p>
        @endif
        @if($populationProjection['inputs'])
            <div class="statistical-inputs" aria-label="Actual reports used for initial forecast">
                @foreach($populationProjection['inputs'] as $row)
                    <a class="statistical-input" href="{{ route('reports.population.submissions.show', $row['id']) }}">
                        <span>{{ $row['label'] }}</span><strong>{{ number_format($row['population']) }}</strong><small>View source report</small>
                    </a>
                @endforeach
            </div>
        @endif
        <p>Ang forecast naggamit sa headline population totals. I-reconcile ang age-count differences before official planning.</p>
        @if($populationProjection['steps'])
            <ol class="statistical-steps">@foreach($populationProjection['steps'] as $step)<li><span>{{ $step['label'] }}</span><strong>{{ number_format($step['estimate'], 2) }}</strong><small>{{ $step['is_target'] ? 'Target estimate' : 'Gap estimate; no actual report' }}</small></li>@endforeach</ol>
        @endif
        @if($populationProjection['missing_periods'])<p>Missing historical semesters: {{ implode('; ', $populationProjection['missing_periods']) }}. Unknown sila, dili zero.</p>@endif
        @if($populationProjection['history'])
            <div class="statistical-table"><table><thead><tr><th>Semester</th><th>Headline population</th><th>Age-bracket sum</th><th>Used as initial input</th><th>Original</th></tr></thead><tbody>
                @foreach($populationProjection['history'] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ $row['population'] }}</td><td>{{ $row['age_sum'] }}</td><td>{{ in_array($row['id'], array_column($populationProjection['inputs'], 'id')) ? 'Yes' : 'No' }}</td><td><a href="{{ route('reports.population.submissions.show', $row['id']) }}">{{ $row['source'] === 'archive' ? 'Archived report' : 'Submitted report' }}</a></td></tr>@endforeach
            </tbody></table></div>
        @endif
        @if($populationProjection['historical_check'])
            @php($check=$populationProjection['historical_check'])
            <div class="statistical-backtest"><strong>Historical one-step check · {{ $check['label'] }}</strong>
                <p>Gamit ang tulo ka naunang actual reports: WMA estimate <b>{{ number_format($check['estimate'], 2) }}</b>; reported actual <b>{{ number_format($check['actual']) }}</b>. Kalainan: <b>{{ number_format($check['absolute_error'], 2) }}</b> inhabitants{{ $check['percentage_error'] === null ? '' : ' ('.number_format($check['percentage_error'], 2).'%)' }}.</p>
                <p>Kung gisunod lang ang pinakabag-ong report, ang kalainan kay {{ number_format($check['last_report_error'], 2) }}. Usa ra kini ka historical example, ug adunay source reconciliation issues; dili pa ebidensya nga mas maayo o accurate ang future WMA.</p>
            </div>
        @endif
        <p><b>Accuracy / MAPE:</b> wala pa ma-validate. Kinahanglan una ma-save ang forecast sa dili pa ang target semester, dayon i-compare sa verified actual report. Kini nga preview dili accuracy result.</p>
        <p>Ang method mosunod sa past totals; dili niya mahibal-an ang kalit nga births, deaths, o migration.</p>
    </details>
    <a class="statistical-text-link" href="{{ route('reports.population.submissions.index', ['barangay_id'=>$barangay->id]) }}">Population report history →</a>
</section>
