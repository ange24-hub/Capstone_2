@extends('layouts.app')

@push('head')
<link rel="stylesheet" href="{{ asset('css/migration-history-experiment.css') }}?v={{ filemtime(public_path('css/migration-history-experiment.css')) }}">
@endpush

@section('content')
<section id="history-experiment" aria-labelledby="history-experiment-title">
    <header class="history-heading">
        <div><span class="history-eyebrow">Prediction analysis · Barangay {{ $barangay->name }}</span><h1 id="history-experiment-title">Short-history prediction experiment</h1><p>Classified RBI in-migration counts · seven-month historical window</p></div>
        <div class="history-actions"><a class="button secondary-button" href="{{ route('migration.history-experiment', ['barangay_id'=>$barangay->id,'mode'=>'holdout','method'=>'mean_rate','horizon'=>6]) }}">3–6 month forecast</a><a class="button secondary-button" href="{{ route('migration.dashboard', ['section'=>'forecast','barangay_id'=>$barangay->id]) }}">Live forecast</a><a class="button secondary-button" href="{{ route('rbi.arrivals.index', ['barangay_id'=>$barangay->id]) }}">Review source records</a></div>
    </header>
    <div class="history-notice" role="note"><strong>PRELIMINARY · LIMITED HISTORY</strong><span>Uses saved entry classifications, including drafts. This experiment does not submit reports or establish official verification. A few test months cannot establish reliable future accuracy.</span></div>
    <div class="history-actions"><a class="button secondary-button" href="{{ route('migration.history-experiment',['barangay_id'=>$barangay->id,'mode'=>'rolling','method'=>'poisson_jeffreys','training_months'=>6,'horizon'=>6]) }}">Rolling backtesting</a></div>
    <form method="GET" action="{{ route('migration.history-experiment') }}" class="history-filters">
        <label>Training and test design<select name="mode"><option value="short" selected>7 months: short-history pilot</option><option value="holdout">12 training months + next-month test</option></select></label>
        @unless(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<label>Barangay<select name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id===$barangay->id)>{{ $area->name }}</option>@endforeach</select></label>@endunless
        <label>History ending month<input type="month" name="end_month" value="{{ request('end_month') ?: ($history['rows'] ? end($history['rows'])['month'] : '') }}" max="{{ now()->startOfMonth()->subMonth()->format('Y-m') }}"></label>
        <button type="submit">Show experiment</button>
    </form>
    @if($evaluation['ready'])
        @php
            $rows = collect($history['rows']);
            $testRows = collect($evaluation['backtest']);
            $firstTest = $testRows->first();
            $target = \Carbon\Carbon::createFromFormat('!Y-m', $firstTest['month'])->format('F Y');
            $initialRows = $rows->take($evaluation['initial_training_months'] + 1);
            $maxValue = max(1, (int) ceil(max($initialRows->max('count'), $firstTest['predicted'])));
            $chartY = fn($value) => 228 - $value / $maxValue * 168;
            $chartX = fn($index) => 56 + $index * (602 / $evaluation['initial_training_months']);
            $points = $initialRows->map(fn($row,$i) => $chartX($i).','.round($chartY($row['count']),3))->implode(' ');
            $testMax = max(1, (int) ceil($testRows->max(fn($row) => max($row['actual'],$row['predicted'],$row['baseline']))));
            $testY = fn($value) => 228 - $value / $testMax * 168;
            $testX = fn($index) => 80 + $index * (420 / ($evaluation['test_months'] - 1));
            $testPoints = fn($key) => $testRows->map(fn($row,$i) => $testX($i).','.round($testY($row[$key]),3))->implode(' ');
        @endphp
        <div class="history-source-heading"><h2>Barangay {{ $barangay->name }} · {{ $rows->first()['label'] }}–{{ $rows->last()['label'] }}</h2><span>{{ $rows->sum('count') }} classified in-migration entries · {{ $history['draft_months'] }} draft months</span></div>
        <p><strong>Selected method: {{ $evaluation['method'] }}.</strong> The earlier {{ $evaluation['validation_label'] }} comparison chooses the method before the {{ $testRows->first()['label'] }}–{{ $testRows->last()['label'] }} test results. One validation month gives limited evidence.</p>
        <div class="history-metrics">
            <article><span>{{ $target }} test prediction</span><strong>{{ number_format($firstTest['predicted'],2) }}</strong><small>Fit uses {{ $rows[0]['label'] }}–{{ $rows[$evaluation['initial_training_months']-1]['label'] }} only</small></article>
            <article><span>{{ $target }} recorded count</span><strong>{{ $firstTest['actual'] }}</strong><small>Kept out of its own forecast</small></article>
            <article><span>{{ $target }} absolute error</span><strong>{{ number_format($firstTest['absolute_error'],2) }}</strong><small>Distance between predicted and recorded count</small></article>
        </div>
        <section class="history-detail"><h2>Rolling accuracy check · {{ $testRows->first()['label'] }}–{{ $testRows->last()['label'] }}</h2><p><strong>Model MAE {{ number_format($evaluation['mae'],2) }}</strong> · Moving-average baseline MAE {{ number_format($evaluation['baseline_mae'],2) }} · {{ $evaluation['test_months'] }} one-month-ahead tests. {{ match($evaluation['baseline_comparison']) { 'better'=>'The selected method had lower test error.', 'equal'=>'Both methods had equal test error.', default=>'The selected method had higher test error. The earlier validation choice did not outperform the baseline on these test months.' } }} Lower MAE is better. These results do not establish 80–90% accuracy.</p></section>
        <p class="history-context">Each target is predicted before its recorded count is used. A recorded zero can be compared using absolute error; percentage error is undefined for that zero. Fractional forecasts describe an expected count, not an exact number of people.</p>
        <div class="history-charts">
            <article class="history-chart-card">
                <header><h2>Train on three months → predict {{ $target }}</h2><p>Training: {{ $rows[0]['label'] }}–{{ $rows[$evaluation['initial_training_months']-1]['label'] }} · Test: {{ $target }}</p></header>
                <div class="history-legend"><span><i></i>Recorded in-migration</span><span><i class="estimate"></i>{{ $target }} prediction</span><span>○ Zero-entry gap</span></div>
                <div class="history-chart-scroll"><svg viewBox="0 0 720 285" role="img" aria-labelledby="history-series-title history-series-description">
                    <title id="history-series-title">{{ $barangay->name }}: three training months and {{ $target }} test prediction</title><desc id="history-series-description">The first three months fit the forecast for the fourth month. The recorded target is shown separately and was not used to predict itself. Empty months between recorded reports are zero.</desc>
                    @for($i=0;$i<=3;$i++)<line x1="56" y1="{{ 228-$i*56 }}" x2="658" y2="{{ 228-$i*56 }}" stroke="#dde5ed"/><text x="44" y="{{ 233-$i*56 }}" text-anchor="end">{{ number_format($maxValue*$i/3,1) }}</text>@endfor
                    <text x="56" y="28">In-migration count</text>
                    <polyline points="{{ $points }}" fill="none" stroke="#345b82" stroke-width="3"/>
                    <line x1="{{ $chartX(2) }}" y1="{{ $chartY($rows[2]['count']) }}" x2="{{ $chartX(3) }}" y2="{{ $chartY($firstTest['predicted']) }}" stroke="#a76a22" stroke-width="3" stroke-dasharray="7 5"/>
                    @foreach($initialRows as $row)<circle cx="{{ $chartX($loop->index) }}" cy="{{ $chartY($row['count']) }}" r="5" stroke="#345b82" stroke-width="2" fill="{{ $row['gap_zero'] ? '#fff' : '#345b82' }}"><title>{{ $row['label'] }}: {{ $row['count'] }} · {{ $row['status'] }}</title></circle><text x="{{ $chartX($loop->index) }}" y="{{ $chartY($row['count'])-12 }}" text-anchor="middle">{{ $row['count'] }}</text><text x="{{ $chartX($loop->index) }}" y="255" text-anchor="middle">{{ $row['label'] }}</text>@endforeach
                    <rect x="{{ $chartX(3)-5 }}" y="{{ $chartY($firstTest['predicted'])-5 }}" width="10" height="10" fill="#a76a22"/><text x="{{ $chartX(3) }}" y="{{ $chartY($firstTest['predicted'])-12 }}" text-anchor="middle">{{ number_format($firstTest['predicted'],2) }}</text><text x="{{ $chartX(3) }}" y="274" text-anchor="middle">Test target</text>
                </svg></div>
            </article>
            <article class="history-chart-card">
                <header><h2>Recorded versus predicted · test months</h2><p>{{ $testRows->first()['label'] }}–{{ $testRows->last()['label'] }} · One month ahead at each test origin</p></header>
                <div class="history-legend"><span><i></i>Recorded</span><span><i class="estimate"></i>Smoothing prediction</span><span><i class="baseline"></i>3-month average</span></div>
                <svg viewBox="0 0 580 285" role="img" aria-labelledby="history-test-title history-test-description">
                    <title id="history-test-title">Recorded versus predicted in-migration in {{ $evaluation['test_months'] }} test months</title><desc id="history-test-description">The method is selected using the earlier validation month only. Each prediction uses only earlier months. Test MAE is {{ number_format($evaluation['mae'],3) }} arrivals.</desc>
                    @for($i=0;$i<=3;$i++)<line x1="80" y1="{{ 228-$i*56 }}" x2="500" y2="{{ 228-$i*56 }}" stroke="#dde5ed"/><text x="67" y="{{ 233-$i*56 }}" text-anchor="end">{{ number_format($testMax*$i/3,1) }}</text>@endfor
                    <text x="80" y="28">In-migration count</text>
                    <polyline points="{{ $testPoints('baseline') }}" fill="none" stroke="#64748b" stroke-width="2" stroke-dasharray="6 5"/>
                    <polyline points="{{ $testPoints('actual') }}" fill="none" stroke="#345b82" stroke-width="3"/>
                    <polyline points="{{ $testPoints('predicted') }}" fill="none" stroke="#a76a22" stroke-width="3"/>
                    @foreach($testRows as $row)<circle cx="{{ $testX($loop->index) }}" cy="{{ $testY($row['actual']) }}" r="5" fill="#345b82"/><rect x="{{ $testX($loop->index)-5 }}" y="{{ $testY($row['predicted'])-5 }}" width="10" height="10" fill="#a76a22"/><text x="{{ $testX($loop->index) }}" y="255" text-anchor="middle">{{ $row['label'] }}</text>@endforeach
                </svg>
            </article>
        </div>
        <section class="history-detail"><h2>Five-model comparison</h2><p>Earlier validation: fit {{ $rows[0]['label'] }}–{{ $rows[1]['label'] }}, predict {{ $evaluation['validation_label'] }} (recorded {{ $evaluation['validation_actual'] }}). The lowest earlier validation error selects the method. Test scores below measure later performance; they are not used to choose a new winner.</p><div class="table-wrap"><table><thead><tr><th>Method</th><th>Earlier prediction</th><th>Validation error</th><th>Later test MAE</th><th>Selection</th></tr></thead><tbody>@foreach($evaluation['comparison'] as $candidate)<tr><th scope="row">{{ $candidate['method'] }}</th><td>{{ number_format($candidate['validation_prediction'],3) }}</td><td>{{ number_format($candidate['validation_error'],3) }}</td><td>{{ number_format($candidate['test_mae'],3) }}</td><td>{{ $candidate['selected'] ? 'Selected by earlier validation' : 'Comparison candidate' }}</td></tr>@endforeach</tbody></table></div></section>
        <section class="history-detail"><h2>Exact test results</h2><p>{{ $evaluation['method'] }}. The method is frozen after earlier validation. The later test actuals do not choose its settings.</p><div class="table-wrap"><table><thead><tr><th>Test month</th><th>Training through</th><th>Recorded</th><th>Predicted</th><th>3-month average</th><th>Absolute error</th></tr></thead><tbody>@foreach($evaluation['backtest'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ \Carbon\Carbon::createFromFormat('!Y-m',$row['training_through'])->format('M Y') }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'],3) }}</td><td>{{ number_format($row['baseline'],3) }}</td><td>{{ number_format($row['absolute_error'],3) }}</td></tr>@endforeach</tbody></table></div></section>
    @else
        <section class="history-detail" role="status"><h2>Complete seven classified months to run this experiment</h2><p>{{ $evaluation['available_months'] }} of 7 months have usable classifications. Resolve the source issues below. An unknown month is not automatically zero.</p></section>
    @endif
    <details class="history-detail"><summary>Source monthly counts and experiment notes</summary>
        <p>Birth / Newborn and Late registration entries are excluded. {{ $history['undated_entries'] }} undated history entries are excluded. {{ $history['unknown_origins'] ?? 0 }} classified arrivals have an origin marked Not recorded.</p>
        <div class="table-wrap"><table><thead><tr><th>Month</th><th>In-migration</th><th>Birth / late / demo excluded</th><th>Needs review</th><th>Source</th></tr></thead><tbody>@foreach($history['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['count'] ?? 'Pending' }}</td><td>{{ $row['excluded'] }}</td><td>{{ $row['unclassified'] }}</td><td>{{ $row['status'] }} @if($row['report_id'])<a href="{{ route('rbi-updates.show',$row['report_id']) }}">View form</a>@endif</td></tr>@endforeach</tbody></table></div>
        <p>Three initial training months are followed by four rolling one-step tests. Earlier test actuals become available for later test origins; a target's actual never enters its own forecast. MAE measures error in counts, not an accuracy percentage or a confidence interval. No seasonal or long-term forecasting claim is made.</p>
        @if($evaluation['ready'])<p>After these tests, fitting all seven months gives a separate {{ \Carbon\Carbon::createFromFormat('!Y-m',$evaluation['target_month'])->format('F Y') }} estimate of {{ number_format($evaluation['prediction'],3) }} arrivals. That future target has no recorded actual in this source window, so it has no test error yet.</p>@endif
        <p>The live forecast keeps its existing training and verification requirements. This page reads classifications without changing report statuses or arrival-period reviews. Method reference: <a href="https://itl.nist.gov/div898/handbook/pmc/section4/pmc432.htm" target="_blank" rel="noopener noreferrer">NIST · Single Exponential Smoothing</a>.</p>
    </details>
</section>
@endsection
