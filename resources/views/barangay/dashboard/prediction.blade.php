@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/statistical-forecast.css') }}?v={{ filemtime(public_path('css/statistical-forecast.css')) }}">
@endpush
@section('content')
<main class="statistical-forecast">
    <header class="statistical-hero"><div><p class="statistical-eyebrow">RBIM / Statistical Forecast</p><h1>Forecasts for {{ $barangay->name }}</h1>
        <p>Population kada semester gikan sa RBI Form C. In ug out migration kada bulan gikan sa verified movement records.</p></div>
        <div class="statistical-hero-links"><a href="#population-forecast">Population ↓</a><a href="#migration-forecast">Migration ↓</a></div></header>
    @if(session('status'))<p class="statistical-notice" role="status">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="statistical-errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="GET" action="{{ route('prediction.dashboard') }}" class="statistical-toolbar">
        <label>Barangay<select name="barangay_id">@foreach($barangays as $area)<option value="{{ $area->id }}" @selected($area->id === $barangay->id)>{{ $area->name }}</option>@endforeach</select></label>
        <button type="submit">View barangay</button><a href="{{ route('migration.dashboard', ['barangay_id'=>$barangay->id]) }}">View migration trends →</a>
    </form>
    @include('barangay.dashboard._population-projection')
    <section id="migration-forecast" aria-labelledby="migration-forecast-title"><div class="statistical-section-heading"><div><span class="statistical-kicker">02 / Migration</span>
        <h2 id="migration-forecast-title">Next-month movement forecast</h2><p>Target month: <strong>{{ $forecasts['in']['log_month'] }}</strong>. Ang Form C population totals dili makasulti pila ang in ug out; verified dated movements ang kinahanglan para niini.</p></div>
        <span class="statistical-status is-waiting">Monthly events</span></div>
    <div class="statistical-cards">
    @foreach(['in'=>'In-migration', 'out'=>'Out-migration'] as $type=>$label)
        @php($forecast=$forecasts[$type]) @php($accuracy=$accuracies[$type])
        <article class="statistical-card statistical-migration-card"><span class="statistical-direction {{ $type==='in' ? 'is-in' : 'is-out' }}">{{ $type==='in' ? '↘' : '↗' }}</span><h3>{{ $label }}</h3>
            <div class="statistical-migration-value"><strong class="statistical-estimate">{{ number_format($forecast['predicted_value'], 2) }}</strong><span>estimated people moving {{ $type==='in' ? 'in' : 'out' }}</span></div>
            <p class="statistical-method-label">{{ $forecast['method']==='weighted_moving_average' ? 'Based on verified monthly history' : 'Population-based assumption' }}</p>
            @if($forecast['method']==='population_baseline')
                <p>{{ $forecast['population'] }} active registered residents × {{ number_format($forecast['baseline_rate']*100, 3) }}%. Assumption ra kini kay kulang sa 3 consecutive verified months.</p>
                @if($forecast['population']===0)<p>Walay encoded active residents sa scope. Ang zero baseline dili ebidensya sa zero actual migration.</p>@endif
            @else
                <p>{{ count($forecast['history']) }} verified months; weights {{ implode(', ', $forecast['weights']) }} gikan sa karaan ngadto sa bag-o.</p>
            @endif
            <p class="statistical-muted">Last usable month: {{ $forecast['data_through'] }}. {{ $forecast['saved_at'] ? 'Forecast saved: '.$forecast['saved_at'] : 'Preview only; save it before the target month to evaluate later.' }}</p>
            <details class="statistical-details"><summary>Accuracy: {{ $accuracy['mape']===null ? 'not yet measurable' : number_format($accuracy['mape'],2).'% MAPE' }}</summary>
            <p>Kinahanglan ang na-save nga forecast ug confirmed actual count aron masukod ang accuracy.</p>
            <dl><dt>MAPE (error)</dt><dd>{{ $accuracy['mape']===null ? 'Awaiting verified results' : number_format($accuracy['mape'],2).'%' }}</dd>
                <dt>100 − MAPE score</dt><dd>{{ $accuracy['accuracy_rate']===null ? 'Not yet measurable' : number_format($accuracy['accuracy_rate'],2).'%' }}</dd>
                <dt>Eligible MAPE months</dt><dd>{{ $accuracy['mape_months'] }}</dd>
                <dt>Zero-actual months excluded from MAPE</dt><dd>{{ $accuracy['zero_actual_months'] }}</dd>
                <dt>MAE / RMSE (including confirmed zeros)</dt><dd>{{ $accuracy['mae']===null ? 'Not yet measurable' : number_format($accuracy['mae'],2).' / '.number_format($accuracy['rmse'],2) }}</dd>
            </dl><p>MAPE mao ang average percentage error sa na-save daan nga forecasts ug verified actual months nga labaw sa zero. Ang MAE/RMSE nagpakita sa average error sa headcount, apil ang confirmed zero months.</p></details>
        </article>
    @endforeach
    </div></section>
    <section class="statistical-card statistical-interpretation"><span class="statistical-kicker">What the numbers say</span><h2>Short explanation</h2><p>{{ $summary }}</p>
        <p class="statistical-muted">Rule-based summary kini sa counts. Dili niini mahibal-an ang hinungdan sa pagbalhin.</p>
        <form method="POST" action="{{ route('prediction.store') }}">@csrf
            <input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><input type="hidden" name="target_month" value="{{ $forecasts['in']['log_month'] }}">
            <button type="submit">Save first-issued in/out forecasts</button>
        </form>
    </section>
    <details class="statistical-card statistical-operations"><summary>Review monthly migration history and completeness</summary>
        <p>Ang count gikan sa actual movement dates. I-confirm lang kung kompleto na ang source report sa bulan, apil ang confirmed zero. Ang walay entries nga wala pa ma-confirm kay <b>unknown</b>.</p>
        <form method="POST" action="{{ route('prediction.sync') }}">@csrf<input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><button type="submit">Sync existing dated movement records</button></form>
        <p>Ang sync dili maghimo og dates gikan sa RBI save dates ug dili mo-confirm sa completeness.</p>
        <div class="statistical-table"><table><thead><tr><th>Month</th><th>Type</th><th>Recorded events</th><th>Coverage</th><th>Review action</th></tr></thead><tbody>
        @foreach($history as $type=>$months) @foreach($months as $month)
            <tr><td>{{ $month['month'] }}</td><td>{{ strtoupper($type) }}</td><td>{{ $month['count'] }}</td><td>{{ $month['verified'] ? 'Confirmed complete' : ($month['duplicates'] ? 'Possible duplicates — review' : 'Unknown / needs confirmation') }}</td>
            <td>@include('barangay.dashboard._confirm-statistical-month', ['month'=>$month,'type'=>$type])</td></tr>
        @endforeach @endforeach
        </tbody></table></div>
    </details>
    <details class="statistical-card statistical-operations"><summary>View saved migration forecast logs</summary><p>Ang predictions gihimo una sa target month. Actual counts magamit lamang human ma-confirm ang complete month. Kung mausab ang source, kinahanglan i-confirm pag-usab.</p>
        <div class="statistical-table"><table><thead><tr><th>Month</th><th>Type</th><th>Issued</th><th>Method</th><th>Predicted</th><th>Verified actual</th><th>Month score</th><th>Review</th></tr></thead><tbody>
        @forelse($logs as $log)
            @php($check=$logChecks[$log->id])
            <tr><td>{{ $log->log_month }}</td><td>{{ strtoupper($log->migration_type) }}</td><td>{{ $log->issued_at?->format('Y-m-d H:i') }}</td><td>{{ str_replace('_',' ',$log->method) }}</td>
                <td>{{ number_format($log->predicted_value,4) }}</td><td>{{ $check['verified'] ? $check['count'] : 'Pending / recheck' }}</td>
                <td>{{ $check['verified'] && $log->accuracy_rate!==null ? number_format($log->accuracy_rate,2).'%' : 'N/A' }}</td>
                <td>@if($log->log_month < now(config('migration_forecast.timezone'))->format('Y-m'))@include('barangay.dashboard._confirm-statistical-month',['month'=>$check,'type'=>$log->migration_type])@endif</td></tr>
        @empty<tr><td colspan="8">Wala pay saved forecasts. Walay fabricated historical predictions o accuracy.</td></tr>@endforelse
        </tbody></table></div>{{ $logs->appends(['barangay_id'=>$barangay->id])->links() }}
    </details>
    <details class="statistical-card"><summary>Record a verified movement</summary>
        <p>Gamita ang actual movement date gikan sa source. Ayaw gamita ang encoding date. Kini event log lamang; ang residency status i-update sa Resident Registry kung gikinahanglan.</p>
        <form method="POST" action="{{ route('prediction.movements.store') }}" class="statistical-entry">@csrf<input type="hidden" name="barangay_id" value="{{ $barangay->id }}">
            <label>Resident<select name="resident_id" required><option value="">Choose resident</option>@foreach($residents as $resident)<option value="{{ $resident->id }}">{{ $resident->fullName() }}</option>@endforeach</select></label>
            <label>Movement<select name="migration_type"><option value="in">In-migration</option><option value="out">Out-migration</option></select></label>
            <label>Actual movement date<input name="transaction_date" type="date" max="{{ now(config('migration_forecast.timezone'))->toDateString() }}" required></label>
            <label class="statistical-check"><input type="checkbox" name="confirm_date" value="1" required>Verified kini nga movement date, dili hinimo nga petsa.</label>
            <button type="submit">Save movement event</button>
        </form>
        <div class="statistical-table"><table><thead><tr><th>Latest recorded date</th><th>Resident</th><th>Type</th></tr></thead><tbody>
        @forelse($movements as $event)<tr><td>{{ $event->transaction_date->format('Y-m-d') }}</td><td>{{ $event->resident->fullName() }}</td><td>{{ strtoupper($event->migration_type) }}</td></tr>@empty<tr><td colspan="3">Wala pay dated movement logs sa scope.</td></tr>@endforelse
        </tbody></table></div>
    </details>
    <details class="statistical-card"><summary>Formulas ug explanation para sa defense</summary>
        <p><b>WMA:</b> Σ(weight × monthly count) / Σ(weights). Example: counts 2, 4, 8 ug weights 1, 2, 3 → (2 + 8 + 24) / 6 = 5.6667 events.</p>
        <p><b>Cold start:</b> P × ((1 + r)<sup>1</sup> − 1), default r = 0.001. Kini one-period population-proportional assumption sa matag direction; dili validated migration rate ug dili net population growth forecast.</p>
        <p><b>MAPE:</b> mean(|actual − predicted| / actual) × 100, para lamang sa verified actual &gt; 0. Score = 100 − MAPE; mahimo kining negative ug dili kini probability. Ang zero-actual months makita sa MAE/RMSE.</p>
        <p>Ang future target mao ang sunod nga calendar month; ang newest usable data mao ang last completed month. Busa adunay usa ka incomplete-month gap. Ang weights dili gi-tune batok sa future test results. Dili mahibal-an ang accuracy hangtod adunay saved predictions ug subsequent verified actuals.</p>
    </details>
</main>
@endsection
