@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/migration-demo.css') }}?v={{ filemtime(public_path('css/migration-demo.css')) }}">
@endpush

@section('content')
<section id="demo-analysis" aria-labelledby="demo-analysis-title">
    <header class="demo-heading">
        <div><span class="demo-eyebrow">Prediction analysis · Simulation</span><h1 id="demo-analysis-title">Demo migration predictions</h1><p>Actual versus predicted movement across three held-out test months.</p></div>
        <a href="{{ route('migration.dashboard', ['section' => 'forecast']) }}" class="button secondary-button">Back to live forecast</a>
    </header>
    <div class="demo-notice" role="note"><strong>DEMO DATA ONLY</strong><span>Fictional areas and synthetic counts. These results demonstrate the model workflow, not actual barangay predictions.</span></div>
    <nav class="demo-scenarios" aria-label="Demo scenarios">
        @foreach($scenarios as $key => $item)<a href="{{ route('migration.demo', ['scenario' => $key]) }}" @if($selected === $key) aria-current="page" @endif>{{ $item['title'] }}</a>@endforeach
    </nav>
    <div class="demo-scenario-heading"><div><h2>{{ $scenario['key'] }}</h2><p>{{ $scenario['description'] }}</p></div><span>36 months per area · 108 synthetic records</span></div>

    <div class="demo-forecast-grid" aria-label="December 2026 demo estimates">
        @foreach(['arrivals' => 'Arrivals', 'departures' => 'Departures', 'net' => 'Net migration'] as $kind => $label)
        <article><span>{{ $label }} · Dec 2026</span><strong>{{ $kind === 'net' && $evaluations[$kind]['prediction'] > 0 ? '+' : '' }}{{ number_format($evaluations[$kind]['prediction'], 2) }}</strong><small>Demo estimate · movement events</small></article>
        @endforeach
    </div>

    <div class="demo-chart-heading"><h2>Held-out evaluation · July–September 2026</h2><p>Three-month forecast horizon. Lower mean absolute error (MAE) is better.</p></div>
    <div class="demo-legend" aria-label="Graph legend"><span><i class="demo-line-actual"></i>Actual (synthetic)</span><span><i class="demo-line-predicted"></i>Model prediction</span><span><i class="demo-line-baseline"></i>Moving-average baseline</span></div>
    <div class="demo-chart-grid">
        @foreach(['arrivals' => 'Arrivals', 'departures' => 'Departures', 'net' => 'Net migration'] as $kind => $label)
            @include('dashboards._migration-demo-chart', ['evaluation' => $evaluations[$kind]])
        @endforeach
    </div>

    <section class="demo-detail-card" aria-labelledby="demo-values-title">
        <h2 id="demo-values-title">Exact test results</h2>
        <p>Predictions were made using only the data available at each forecast origin. Values are rounded for display.</p>
        <div class="table-wrap"><table><caption class="sr-only">Synthetic held-out predictions and absolute errors</caption><thead><tr><th scope="col">Series</th><th scope="col">Test month</th><th scope="col">Training through</th><th scope="col">Actual</th><th scope="col">Predicted</th><th scope="col">Baseline</th><th scope="col">Absolute error</th></tr></thead><tbody>
        @foreach(['arrivals' => 'Arrivals', 'departures' => 'Departures', 'net' => 'Net migration'] as $kind => $label)
            @foreach($evaluations[$kind]['backtest'] as $row)<tr><th scope="row">{{ $label }}</th><td>{{ $row['label'] }}</td><td>{{ \Carbon\Carbon::createFromFormat('!Y-m', $row['month'])->subMonths(3)->format('M Y') }}</td><td>{{ $row['actual'] }}</td><td>{{ number_format($row['predicted'], 3) }}</td><td>{{ number_format($row['baseline'], 3) }}</td><td>{{ number_format($row['absolute_error'], 3) }}</td></tr>@endforeach
        @endforeach
        </tbody></table></div>
    </section>
    <section class="demo-method" aria-labelledby="demo-method-title"><h2 id="demo-method-title">How this experiment was evaluated</h2><p>Source history: October 2023–September 2026. Earlier rolling validation selects each model at a four-month horizon; the final three test targets do not select the model. Test forecasts use April, May and June 2026 training cutoffs. The December demo estimate is refitted using all 36 months.</p><p>Net migration = arrivals − departures. MAE is an error in movement counts, not an accuracy percentage. Three test months provide limited evidence; better performance on synthetic data does not establish real-world accuracy.</p><span>Reproducible experiment · Seed {{ $report['seed'] }} · No live resident data used</span></section>
</section>
@endsection
