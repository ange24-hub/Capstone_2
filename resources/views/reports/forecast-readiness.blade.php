@extends('layouts.app')
@section('content')
<section class="grid gap-6" aria-labelledby="forecast-title">
    <header class="dashboard-page-header">
        <span class="dashboard-eyebrow">Predictive analytics preparation</span>
        <h1 id="forecast-title">Forecast Readiness</h1>
        <p>{{ $report['scope'] }} &middot; {{ $report['start']->format('M Y') }}–{{ $report['end']->copy()->subMonth()->format('M Y') }} · 24 completed months</p>
    </header>
    @unless($report['secretary'])
    <form method="GET" action="{{ route('analysis.forecast-readiness') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-5">
        <div><label for="forecast-area">Barangay</label><select id="forecast-area" name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($report['barangayId'] === $area->id)>{{ $area->name }}</option>@endforeach</select></div>
        <button type="submit">Apply filter</button>
    </form>
    @endunless
    <aside class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-amber-950" role="note">
        <strong>Forecast unavailable — reporting completeness is not verified</strong>
        <p>This page checks available history. It does not predict population or migration. A month with no recorded events is not a confirmed zero-movement month. Submitted RBI forms do not establish complete migration reporting.</p>
    </aside>
    <div class="grid gap-4 md:grid-cols-3">
        @foreach([['Recorded migration events', number_format($report['eventCount']), 'Events, not unique residents.'], ['Months containing events', $report['months']->where('events', '>', 0)->count().' / 24', 'Presence of records, not verified coverage.'], ['Forecast status', 'Not enabled', 'No trained or validated forecasting model.']] as [$label, $value, $note])
        <article class="rounded-xl border border-slate-200 bg-white p-5"><h2>{{ $label }}</h2><p class="my-3 text-2xl font-bold text-slate-900">{{ $value }}</p><p class="text-sm text-slate-600">{{ $note }}</p></article>
        @endforeach
    </div>
    <article class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="p-5"><h2>History available for review</h2><p>Current registry totals are a snapshot, not a historical population series. Counts below cover the displayed 24-month window only.</p></div>
        <div class="table-wrap"><table><thead><tr><th scope="col">Barangay</th><th scope="col">Migration events</th><th scope="col">Months with events</th><th scope="col">First / latest event month</th><th scope="col">Months with submitted RBI forms</th></tr></thead><tbody>
        @foreach($report['coverage'] as $area)<tr><th scope="row">{{ $area['name'] }}</th><td>{{ number_format($area['events']) }}</td><td>{{ $area['months'] }} / 24</td><td>{{ $area['first'] ?? 'No recorded history' }} @if($area['last']) / {{ $area['last'] }} @endif</td><td>{{ $area['reportMonths'] }} / 24</td></tr>@endforeach
        </tbody></table></div>
    </article>
    <article class="rounded-xl border border-slate-200 bg-white p-5">
        <h2>What is needed before forecasting</h2>
        <ol class="mt-4 list-decimal space-y-3 pl-5 text-sm text-slate-700">
            <li>Review dated movement records and confirm monthly completeness, including months with no movements. A submitted form alone is insufficient.</li>
            <li>For population growth, collect comparable, dated population totals. Do not treat newly encoded residents as births or population growth.</li>
            <li>Check duplicates, missing dates, and changes in reporting definitions with barangay staff.</li>
            <li>Evaluate a local model on later, withheld months and compare its errors with a simple baseline. Report uncertainty before enabling forecasts.</li>
        </ol>
        <a class="button secondary-button mt-5" href="{{ route('reports.migration', $report['barangayId'] ? ['barangay_id' => $report['barangayId']] : []) }}">Review migration report</a>
    </article>
    <details class="rounded-xl border border-slate-200 bg-white p-5">
        <summary class="cursor-pointer font-semibold text-slate-900">Inspect monthly source records</summary>
        <p class="my-4 text-sm text-slate-600">The current partial month and future dates are excluded. A dash means no events were recorded in this scope; it is not a verified zero. Secretary RBI counts include forms owned by the signed-in secretary.</p>
        <div class="table-wrap"><table><thead><tr><th scope="col">Month</th><th scope="col">Recorded arrivals</th><th scope="col">Recorded departures</th><th scope="col">Submitted RBI forms</th><th scope="col">Completeness</th></tr></thead><tbody>
        @foreach($report['months'] as $month)<tr><th scope="row">{{ $month['period'] }}</th><td>{{ $month['events'] ? $month['in'] : '—' }}</td><td>{{ $month['events'] ? $month['out'] : '—' }}</td><td>{{ $month['forms'] }}</td><td>Unverified</td></tr>@endforeach
        </tbody></table></div>
    </details>
</section>
@endsection
