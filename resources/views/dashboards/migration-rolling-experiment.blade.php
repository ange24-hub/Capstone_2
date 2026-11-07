@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/migration-history-experiment.css') }}?v={{ filemtime(public_path('css/migration-history-experiment.css')) }}">
@endpush
@section('content')
@php
    $sourceRows=collect($history['rows']);
    $bayesian=$method==='poisson_jeffreys';
@endphp
<section id="history-experiment" aria-labelledby="history-experiment-title">
    <header class="history-heading"><div><span class="history-eyebrow">Prediction analysis · Barangay {{ $barangay->name }}</span><h1 id="history-experiment-title">Rolling backtesting · Actual vs predicted</h1><p>One month ahead each time. The training window expands after each recorded month.</p></div><div class="history-actions"><a class="button secondary-button" href="{{ route('rbi.arrivals.index',['barangay_id'=>$barangay->id]) }}">Review source records</a><a class="button secondary-button" href="{{ route('migration.dashboard',['section'=>'forecast','barangay_id'=>$barangay->id]) }}">Migration trends</a></div></header>
    <div class="history-notice" role="note"><strong>HISTORICAL BACKTEST · CLASSIFIED DATA</strong><span>Uses saved classifications, including drafts. Each target stays outside its own training window. This retrospective experiment measures monthly historical errors; it does not establish six-month-ahead or future accuracy. No report is submitted or officially verified here.</span></div>
    <form method="GET" action="{{ route('migration.history-experiment') }}" class="history-filters">
        @unless(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<label>Barangay<select name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id===$barangay->id)>{{ $area->name }}</option>@endforeach</select></label>@endunless
        <label>Experiment<select name="mode"><option value="rolling" selected>Rolling one-month backtesting</option><option value="holdout">Fixed-origin forecast</option><option value="short">7-month pilot</option></select></label>
        <label>Fixed method<select name="method"><option value="poisson_jeffreys" @selected($bayesian)>Bayesian Poisson (Jeffreys prior)</option><option value="mean_rate" @selected(!$bayesian)>Mean-rate baseline</option></select></label>
        <label>Initial training<select name="training_months">@foreach([6,12] as $months)<option value="{{ $months }}" @selected($trainingMonths===$months)>{{ $months }} months</option>@endforeach</select></label>
        <label>Test months<select name="horizon">@foreach([1,3,6] as $months)<option value="{{ $months }}" @selected($horizon===$months)>{{ $months }} {{ $months===1 ? 'month' : 'months' }}</option>@endforeach</select></label>
        <label>First test month<input type="month" name="end_month" value="{{ $sourceRows[$trainingMonths]['month'] ?? request('end_month') }}" max="{{ now()->startOfMonth()->subMonth()->format('Y-m') }}"></label><button type="submit">Run backtest</button>
    </form>
    @if($evaluation['ready'])
        @php
            $tests=collect($evaluation['backtest']);
            $first=$tests->first();
            $last=$tests->last();
            $testRange=\Carbon\Carbon::createFromFormat('!Y-m',$first['month'])->format('F Y').'–'.\Carbon\Carbon::createFromFormat('!Y-m',$last['month'])->format('F Y');
            $maximum=max(1,(int)ceil($tests->max(fn($row)=>max($row['actual'],$row['predicted']))));
            $x=fn($index)=>70+$index*(660/max(1,$tests->count()-1));
            $y=fn($value)=>225-$value/$maximum*165;
            $actualPoints=$tests->map(fn($row,$index)=>$x($index).','.round($y($row['actual']),3))->implode(' ');
            $predictedPoints=$tests->map(fn($row,$index)=>$x($index).','.round($y($row['predicted']),3))->implode(' ');
        @endphp
        <div class="history-source-heading"><h2>Test period: {{ $testRange }}</h2><span>Initial training: {{ $sourceRows[0]['label'] }}–{{ $sourceRows[$trainingMonths-1]['label'] }}</span></div>
        <p><strong>{{ $evaluation['method'] }}.</strong> The method stays fixed. Each month's fit uses every earlier count from {{ $sourceRows[0]['label'] }} through the preceding month; earlier test actuals enter later fits only after their own predictions. {{ $bayesian ? 'The Jeffreys prior adjustment remains fixed at 0.5; it is a model assumption, not an added arrival.' : 'Each estimate is the average of all earlier classified monthly counts.' }}</p>
        <div class="history-metrics"><article><span>Rolling model MAE</span><strong>{{ number_format($evaluation['mae'],3) }}</strong><small>{{ $evaluation['test_months'] }} one-month-ahead tests · lower is better</small></article><article><span>Rolling mean-rate MAE</span><strong>{{ number_format($evaluation['baseline_mae'],3) }}</strong><small>Same training prefixes and test months</small></article><article><span>Recorded in-migration</span><strong>{{ $evaluation['actual_total'] }}</strong><small>{{ $testRange }}</small></article></div>
        <article class="history-chart-card"><h2>{{ $barangay->name }} · Actual vs predicted</h2><p>{{ $testRange }} · Training grows from {{ $first['training_months'] }} to {{ $last['training_months'] }} months.</p><div class="history-legend"><span><i></i>Recorded in-migration</span><span><i class="estimate"></i>One-month prediction</span></div><div class="history-chart-scroll"><svg viewBox="0 0 800 295" role="img" aria-labelledby="rolling-test-title rolling-test-description"><title id="rolling-test-title">{{ $barangay->name }} rolling backtest: {{ $testRange }}</title><desc id="rolling-test-description">Recorded counts versus one-month-ahead predictions. Each fit stops before its target. Model MAE is {{ number_format($evaluation['mae'],4) }} across {{ $evaluation['test_months'] }} historical test months.</desc>
            @for($tick=0;$tick<=4;$tick++)<line x1="70" y1="{{ 225-$tick*41.25 }}" x2="730" y2="{{ 225-$tick*41.25 }}" stroke="#dde5ed"/><text x="57" y="{{ 230-$tick*41.25 }}" text-anchor="end">{{ number_format($maximum*$tick/4,1) }}</text>@endfor
            <text x="70" y="28">In-migration count · one month ahead</text>
            <polyline points="{{ $actualPoints }}" fill="none" stroke="#345b82" stroke-width="3"/><polyline points="{{ $predictedPoints }}" fill="none" stroke="#a76a22" stroke-width="3" stroke-dasharray="7 5"/>
            @foreach($tests as $row)<circle cx="{{ $x($loop->index) }}" cy="{{ $y($row['actual']) }}" r="5" fill="#345b82"><title>{{ $row['label'] }} recorded: {{ $row['actual'] }}</title></circle><rect x="{{ $x($loop->index)-4 }}" y="{{ $y($row['predicted'])-4 }}" width="8" height="8" fill="#a76a22"><title>{{ $row['label'] }} prediction: {{ number_format($row['predicted'],4) }}; training through {{ $row['training_through'] }}</title></rect><text x="{{ $x($loop->index) }}" y="{{ $y($row['predicted'])-12 }}" text-anchor="middle">{{ number_format($row['predicted'],2) }}</text><text x="{{ $x($loop->index) }}" y="255" text-anchor="middle">{{ \Carbon\Carbon::createFromFormat('!Y-m',$row['month'])->format('M') }}</text><text x="{{ $x($loop->index) }}" y="275" text-anchor="middle">{{ substr($row['month'],0,4) }}</text>@endforeach
        </svg></div></article>
        <section class="history-detail"><h2>Exact monthly test results</h2><p>Predicted values are calculated before reading that target's actual. MAE averages the absolute errors below. Counts are classified historical entries, not formal report verification.</p><div class="table-wrap"><table><thead><tr><th>Test month</th><th>Training through</th><th>Training months</th><th>Actual</th><th>Predicted</th><th>Mean baseline</th><th>Absolute error</th></tr></thead><tbody>@foreach($tests as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ \Carbon\Carbon::createFromFormat('!Y-m',$row['training_through'])->format('M Y') }}</td><td>{{ $row['training_months'] }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'],4) }}</td><td>{{ number_format($row['baseline'],4) }}</td><td>{{ number_format($row['absolute_error'],4) }}</td></tr>@endforeach</tbody></table></div></section>
        <p>The displayed method was fixed for this exploratory historical comparison; later test scores do not choose its settings. A rolling test uses new actuals as they become available and is different from making all six forecasts at one cutoff.</p>
    @else
        <section class="history-detail" role="status"><h2>Complete the historical source window first</h2><p>{{ $evaluation['available_months'] }} of {{ $evaluation['required_months'] }} source months have usable counts. Rolling testing needs classified consecutive initial and test months; an unknown earlier count cannot be replaced with zero.</p></section>
    @endif
    <details class="history-detail"><summary>Source counts and classifications</summary><p>Birth / Newborn and Late registration are excluded. Gap zeros follow the barangay's reporting convention; missing months outside coverage remain unknown. {{ $history['draft_months'] }} source months are drafts.</p><div class="table-wrap"><table><thead><tr><th>Month</th><th>Use</th><th>In-migration</th><th>Excluded entries</th><th>Source</th></tr></thead><tbody>@foreach($sourceRows as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $loop->index<$trainingMonths ? 'Initial training' : 'Test; then available for later fits' }}</td><td>{{ $row['count'] ?? 'Pending' }}</td><td>{{ $row['excluded'] }}</td><td>{{ $row['status'] }}</td></tr>@endforeach</tbody></table></div></details>
</section>
@endsection
