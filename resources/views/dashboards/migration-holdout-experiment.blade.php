@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/migration-history-experiment.css') }}?v={{ filemtime(public_path('css/migration-history-experiment.css')) }}">
@endpush
@section('content')
@php
    $meanRate = $method === 'mean_rate';
    $fixedRate = $method !== 'selected';
    $bayesian = $method === 'poisson_jeffreys';
    $sourceRows = collect($history['rows']);
@endphp
<section id="history-experiment" aria-labelledby="history-experiment-title">
    <header class="history-heading"><div><span class="history-eyebrow">Prediction analysis · Barangay {{ $barangay->name }}</span><h1 id="history-experiment-title">{{ $fixedRate ? 'Historical in-migration forecast' : '12-month training and next-month test' }}</h1><p>Fit the selected training months, then compare the following forecast months with classified RBI counts.</p></div><div class="history-actions"><a class="button secondary-button" href="{{ route('rbi.arrivals.index',['barangay_id'=>$barangay->id]) }}">Review source records</a><a class="button secondary-button" href="{{ route('migration.dashboard',['section'=>'forecast','barangay_id'=>$barangay->id]) }}">Live forecast</a></div></header>
    <div class="history-notice" role="note"><strong>HISTORICAL EXPERIMENT · CLASSIFIED DATA</strong><span>Uses saved classifications, including drafts. Reports are not submitted or officially verified here. This retrospective experiment uses a requested method after reviewing historical records; its results do not establish future accuracy.</span></div>
    <div class="history-actions"><a class="button secondary-button" href="{{ route('migration.history-experiment',['barangay_id'=>$barangay->id,'mode'=>'rolling','method'=>'poisson_jeffreys','training_months'=>6,'horizon'=>6]) }}">Rolling backtesting</a></div>
    <form method="GET" action="{{ route('migration.history-experiment') }}" class="history-filters">
        @unless(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<label>Barangay<select name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id===$barangay->id)>{{ $area->name }}</option>@endforeach</select></label>@endunless
        <label>Experiment<select name="mode"><option value="short">7 months: short-history pilot</option><option value="holdout" selected>Training + separate forecast months</option></select></label>
        <label>Method<select name="method"><option value="mean_rate" @selected($meanRate)>Mean-rate baseline</option><option value="poisson_jeffreys" @selected($bayesian)>Bayesian Poisson (Jeffreys prior)</option><option value="selected" @selected(!$fixedRate)>Earlier validation selection (12 + 1)</option></select></label>
        <label>Training months<select name="training_months">@foreach([6,12] as $months)<option value="{{ $months }}" @selected($trainingMonths===$months)>{{ $months }} months</option>@endforeach</select></label>
        <label>Forecast period<select name="horizon">@foreach([1,3,6] as $months)<option value="{{ $months }}" @selected($horizon===$months)>{{ $months }} {{ $months===1 ? 'month' : 'months' }}</option>@endforeach</select></label>
        <label>First forecast month<input type="month" name="end_month" value="{{ request('end_month') ?: ($sourceRows[$trainingMonths]['month'] ?? '') }}" max="{{ now()->startOfMonth()->subMonth()->format('Y-m') }}"></label><button type="submit">Show forecast</button>
        @error('horizon')<p role="alert">{{ $message }}</p>@enderror
    </form>
    @if($sourceRows->isNotEmpty())
        <div class="history-source-heading"><h2>Training: {{ $sourceRows[0]['label'] }}–{{ $sourceRows[$trainingMonths-1]['label'] }}</h2><span>{{ $history['draft_months'] }} draft source months · Births and late registrations excluded</span></div>
    @endif
    @if($evaluation['ready'])
        @php
            $target=\Carbon\Carbon::createFromFormat('!Y-m',$evaluation['target_month'])->format('F Y');
            $lastForecast=\Carbon\Carbon::createFromFormat('!Y-m',$evaluation['forecast_end'])->format('F Y');
            $forecasts=collect($evaluation['forecasts']);
            $maximum=max(1,(int)ceil(max($sourceRows->max('count'),$evaluation['prediction'])));
            $x=fn($index)=>70+$index*60;
            $y=fn($value)=>230-$value/$maximum*170;
            $chartWidth=max(780,$x($trainingMonths+$horizon-1)+70);
            $forecastPoints=$forecasts->map(fn($row,$index)=>$x($trainingMonths+$index).','.round($y($row['predicted']),3))->implode(' ');
            // Split the recorded line at unknown counts; missing actuals never become zeros.
            $segments=[];
            $segment=[];
            foreach($history['rows'] as $index=>$row) {
                if($row['count']===null) { if($segment) $segments[]=implode(' ',$segment); $segment=[]; }
                else $segment[]=$x($index).','.round($y($row['count']),3);
            }
            if($segment) $segments[]=implode(' ',$segment);
        @endphp
        @if($meanRate)
            <p><strong>Mean-rate baseline: {{ $evaluation['training_total'] }} in-migrants ÷ {{ $trainingMonths }} months = {{ number_format($evaluation['prediction'],2) }} expected arrivals per month.</strong> All {{ $horizon }} predictions use this same fixed average, with no later actuals fed back into training. It does not model trends or seasonality.</p>
            @if($evaluation['training_total']===0)<div class="history-notice"><strong>ZERO ARRIVALS IN THE TRAINING WINDOW</strong><span>This baseline forecasts zero each month. It cannot anticipate the later arrivals from an all-zero history; the graph retains those errors.</span></div>@endif
        @elseif($bayesian)
            <p><strong>Bayesian Poisson: ({{ $evaluation['training_total'] }} observed arrivals + 0.5 prior adjustment) ÷ {{ $trainingMonths }} training months = {{ number_format($evaluation['prediction'],4) }} expected arrivals per month.</strong> All forecasts use the same training cutoff. The fixed Jeffreys prior is a mathematical assumption, not an added resident or observed arrival, and is not tuned to these test errors.</p>
            <p>This model assumes a stable arrival rate and independent monthly counts. It was added after reviewing historical results, so this comparison is exploratory. <a href="https://www2.stat.duke.edu/~st118/sta250/priors.pdf" target="_blank" rel="noopener noreferrer">Method reference: Duke Statistics, Jeffreys' Poisson prior (page 4)</a>.</p>
        @else
            <p><strong>Selected method: {{ $evaluation['method'] }}.</strong> Seven candidates were compared using earlier validation in {{ $evaluation['validation_start'] }}–{{ $evaluation['validation_end'] }}. The {{ $target }} actual is excluded from fitting and automatic selection.</p>
        @endif
        <div class="history-metrics">
            <article><span>{{ $horizon===1 ? $target.' prediction' : 'Expected arrivals per month' }}</span><strong>{{ number_format($evaluation['prediction'],2) }}</strong><small>{{ $horizon }}-month expected total: {{ number_format($evaluation['forecast_total'],2) }}</small></article>
            <article><span>{{ $horizon===1 ? $target.' recorded count' : 'Recorded arrivals in forecast period' }}</span><strong>{{ $fixedRate ? ($evaluation['actual_total'] ?? 'Incomplete') : ($evaluation['holdout_actual'] ?? 'Needs review') }}</strong><small>{{ $fixedRate ? $evaluation['observed_months'].' of '.$horizon.' months have classified actuals' : 'Separate actual, outside training' }}</small></article>
            <article><span>{{ $horizon===1 ? 'Target absolute error' : 'Mean absolute error (MAE)' }}</span><strong>{{ ($fixedRate ? $evaluation['observed_mae'] : $evaluation['holdout_error'])===null ? 'Pending' : number_format($fixedRate ? $evaluation['observed_mae'] : $evaluation['holdout_error'],2) }}</strong><small>Error in arrival counts · lower is better</small></article>
        </div>
        <article class="history-chart-card"><h2>{{ $barangay->name }} · {{ $target }}{{ $horizon>1 ? '–'.$lastForecast : '' }} prediction</h2><p>Training ends {{ $sourceRows[$trainingMonths-1]['label'] }}. The forecast starts at the dashed boundary.</p><div class="history-legend"><span><i></i>Recorded in-migration</span><span><i class="estimate"></i>{{ $evaluation['method'] }} forecast</span><span>○ Zero-entry gap</span></div><div class="history-chart-scroll"><svg viewBox="0 0 {{ $chartWidth }} 295" role="img" aria-labelledby="holdout-history-title holdout-history-description"><title id="holdout-history-title">{{ $barangay->name }} training history and {{ $target }} prediction through {{ $lastForecast }}</title><desc id="holdout-history-description">The first {{ $trainingMonths }} months train the baseline. The next {{ $horizon }} months compare fixed-origin predictions with available actuals. Unknown counts are not drawn as zero.</desc>
            @for($tick=0;$tick<=4;$tick++)<line x1="70" y1="{{ 230-$tick*42.5 }}" x2="{{ $x($trainingMonths+$horizon-1) }}" y2="{{ 230-$tick*42.5 }}" stroke="#dde5ed"/><text x="57" y="{{ 235-$tick*42.5 }}" text-anchor="end">{{ number_format($maximum*$tick/4,1) }}</text>@endfor
            <text x="70" y="28">In-migration count</text><text x="{{ $x($trainingMonths-1)+40 }}" y="28">Forecast →</text><line x1="{{ $x($trainingMonths-1)+30 }}" y1="45" x2="{{ $x($trainingMonths-1)+30 }}" y2="240" stroke="#94a3b8" stroke-dasharray="5 5"/>
            @foreach($segments as $points)<polyline points="{{ $points }}" fill="none" stroke="#345b82" stroke-width="3"/>@endforeach
            <line x1="{{ $x($trainingMonths-1) }}" y1="{{ $y($sourceRows[$trainingMonths-1]['count']) }}" x2="{{ $x($trainingMonths) }}" y2="{{ $y($evaluation['prediction']) }}" stroke="#a76a22" stroke-width="2" stroke-dasharray="5 5"/><polyline points="{{ $forecastPoints }}" fill="none" stroke="#a76a22" stroke-width="3" stroke-dasharray="7 5"/>
            @foreach($sourceRows as $row)@if($row['count']!==null)<circle cx="{{ $x($loop->index) }}" cy="{{ $y($row['count']) }}" r="5" stroke="#345b82" stroke-width="2" fill="{{ $row['gap_zero'] ? '#fff' : '#345b82' }}"><title>{{ $row['label'] }}: {{ $row['count'] }} · {{ $row['status'] }}</title></circle>@endif<text x="{{ $x($loop->index) }}" y="256" text-anchor="middle">{{ \Carbon\Carbon::createFromFormat('!Y-m',$row['month'])->format('M') }}</text><text x="{{ $x($loop->index) }}" y="275" text-anchor="middle">{{ substr($row['month'],0,4) }}</text>@endforeach
            @foreach($forecasts as $row)<rect x="{{ $x($trainingMonths+$loop->index)-3.5 }}" y="{{ $y($row['predicted'])-3.5 }}" width="7" height="7" fill="#a76a22"/><text x="{{ $x($trainingMonths+$loop->index) }}" y="{{ $y($row['predicted'])-12 }}" text-anchor="middle">{{ number_format($row['predicted'],2) }}</text>@endforeach
        </svg></div></article>
        <section class="history-detail"><h2>Monthly predictions and actual counts</h2><p>All predictions use training through {{ $sourceRows[$trainingMonths-1]['label'] }}. Actuals are used only to measure error. Unknown actuals remain pending. Fractional predictions represent expected counts; MAE is not an accuracy percentage.</p><div class="table-wrap"><table><thead><tr><th>Month</th><th>Predicted arrivals</th><th>Recorded arrivals</th><th>Absolute error</th></tr></thead><tbody>@foreach($forecasts as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ number_format($row['predicted'],2) }}</td><td>{{ $row['actual'] ?? 'Not available' }}</td><td>{{ $row['absolute_error']===null ? 'Pending actual' : number_format($row['absolute_error'],2) }}</td></tr>@endforeach</tbody></table></div></section>
        @if($fixedRate)
            <section class="history-detail"><h2>Comparison with the mean-rate baseline</h2><p>Both estimates use exactly the same training and forecast months. No method is automatically chosen using these later actuals. Small retrospective error differences do not establish future accuracy.</p><div class="table-wrap"><table><thead><tr><th>Method</th><th>Monthly prediction</th><th>Expected total</th><th>Observed MAE</th><th>Displayed model</th></tr></thead><tbody>@foreach($evaluation['rate_comparison'] as $candidate)<tr><th scope="row">{{ $candidate['method'] }}</th><td>{{ number_format($candidate['prediction'],4) }}</td><td>{{ number_format($candidate['forecast_total'],3) }}</td><td>{{ $candidate['mae']===null ? 'Pending' : number_format($candidate['mae'],4) }}</td><td>{{ $candidate['selected'] ? 'Selected option' : 'Comparison only' }}</td></tr>@endforeach</tbody></table></div></section>
        @else
            <details class="history-detail"><summary>Earlier model comparison</summary><p>Earlier validation selects the method. Internal one-month tests: {{ $evaluation['internal_test_start'] }}–{{ $evaluation['internal_test_end'] }}. Each prediction uses only earlier months.</p><div class="table-wrap"><table><thead><tr><th>Candidate</th><th>Validation MAE</th><th>Internal test MAE</th><th>Choice</th></tr></thead><tbody>@foreach($evaluation['comparison'] as $candidate)<tr><th scope="row">{{ $candidate['method'] }}</th><td>{{ number_format($candidate['validation_mae'],3) }}</td><td>{{ number_format($candidate['test_mae'],3) }}</td><td>{{ $candidate['selected'] ? 'Selected by earlier validation' : 'Comparison candidate' }}</td></tr>@endforeach</tbody></table></div></details>
        @endif
    @else
        <section class="history-detail" role="status"><h2>Complete the {{ $trainingMonths===12 ? 'twelve' : 'six' }} training months first</h2><p>{{ $evaluation['available_months'] }} of {{ $trainingMonths }} training months have usable classifications. Review missing months and unclassified entries below. Forecast targets stay outside training.</p></section>
    @endif
    <details class="history-detail"><summary>Source counts and classifications</summary><p>Birth / Newborn and Late registration entries are excluded. {{ $history['undated_entries'] }} undated entries are excluded. Months outside recorded coverage stay unknown until confirmed. Gap zeros follow the barangay's stated reporting convention.</p><div class="table-wrap"><table><thead><tr><th>Month</th><th>Use</th><th>In-migration</th><th>Excluded entries</th><th>Source</th></tr></thead><tbody>@foreach($history['rows'] as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $loop->index>=$trainingMonths ? 'Comparison only' : 'Training' }}</td><td>{{ $row['count'] ?? 'Pending' }}</td><td>{{ $row['excluded'] }}</td><td>{{ $row['status'] }} @if($row['report_id'])<a href="{{ route('rbi-updates.show',$row['report_id']) }}">View form</a>@endif</td></tr>@endforeach</tbody></table></div></details>
</section>
@endsection
