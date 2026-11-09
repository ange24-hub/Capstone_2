    @if($reportHistoryEvaluation)
        @include('barangay.dashboard._report-history-evaluation', ['evaluation' => $reportHistoryEvaluation])
    @endif
    <section id="migration-forecast" aria-labelledby="migration-forecast-title"><div class="statistical-section-heading"><div>
        <h2 id="migration-forecast-title">Monthly migration</h2><p>Forecast for <strong>{{ $targetLabel }}</strong></p></div>
        <span class="statistical-status {{ $allSaved ? '' : 'is-waiting' }}">{{ $allSaved ? 'Forecasts saved' : 'Preview' }}</span></div>
    <div class="statistical-cards">
    @foreach(['in'=>'In-migration', 'out'=>'Out-migration'] as $type=>$label)
        @php($forecast=$forecasts[$type]) @php($accuracy=$accuracies[$type])
        <article class="statistical-card statistical-migration-card {{ $type==='in' ? 'is-in' : 'is-out' }}"><div class="statistical-migration-heading"><span class="statistical-direction {{ $type==='in' ? 'is-in' : 'is-out' }}">{{ $type==='in' ? '↘' : '↗' }}</span><h3>{{ $label }}</h3>
            <strong class="statistical-estimate statistical-heading-value" aria-label="Predicted {{ $label }}: {{ number_format($forecast['predicted_value'], 2) }}">{{ number_format($forecast['predicted_value'], 2) }}</strong></div>
            <p class="statistical-method-label">{{ $forecast['method']==='weighted_moving_average' ? 'Based on verified monthly history' : 'Population-based assumption' }}</p>
            @include('barangay.dashboard._prediction-chart', [
                'chartId' => 'migration-'.$type.'-trend', 'chartTitle' => $label.' trend',
                'chartRows' => $migrationCharts[$type],
                'chartNote' => collect($migrationCharts[$type])->contains(fn ($row) => $row['actual'] !== null)
                    ? 'Tap a point for values'
                    : 'Wala pay verified actual data',
            ])
            <details class="statistical-details"><summary>Details &amp; accuracy</summary>
            <div class="statistical-card-meta">
                <span><b>{{ count($forecast['history']) }}</b> verified months</span>
                <span>History cutoff <b>{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $forecast['data_through'])->format('M Y') }}</b></span>
            </div>
            @if($forecast['method']==='population_baseline')
                <p>{{ $forecast['population'] }} active residents × {{ number_format($forecast['baseline_rate']*100, 3) }}%. Population assumption kay kulang pa sa 3 verified months.</p>
                @if($forecast['population']===0)<p>Walay active residents. Ang zero estimate dili verified zero migration.</p>@endif
            @else
                <p>Weighted moving average · weights {{ implode(', ', $forecast['weights']) }}, oldest to newest.</p>
            @endif
            <p>{{ $forecast['saved_at'] ? 'Saved '.\Illuminate\Support\Carbon::parse($forecast['saved_at'])->timezone(config('migration_forecast.timezone'))->format('M d, Y · g:i A') : 'Preview only. Save before the target month for later evaluation.' }}</p>
            <p>Accuracy: {{ $accuracy['mape']===null ? 'not yet measurable' : number_format($accuracy['mape'],2).'% MAPE' }}.</p>
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
    @php($netEstimate = $forecasts['in']['predicted_value'] - $forecasts['out']['predicted_value'])
    <section class="statistical-card statistical-interpretation"><div><h2>Net estimate <strong>{{ $netEstimate > 0 ? '+' : '' }}{{ number_format($netEstimate, 2) }}</strong></h2>
        <p class="statistical-muted">In minus out · Estimate lamang.</p>
        </div><div class="statistical-save">
        @if($allSaved)
            <span class="statistical-status">Saved for {{ $targetLabel }}</span>
            <a href="{{ route($workspaceRoute, ['barangay_id' => $barangay->id, 'page' => 1]) }}#saved-forecasts">View saved forecasts →</a>
        @else
        <form method="POST" action="{{ route('prediction.store') }}">@csrf
            <input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><input type="hidden" name="target_month" value="{{ $forecasts['in']['log_month'] }}">
            <button type="submit">Save in/out forecasts</button>
        </form>
        <p>For {{ $targetLabel }}</p>
        @endif
        </div>
    </section>
    <section id="forecast-records" aria-labelledby="forecast-records-title" class="statistical-records">
    <div class="statistical-section-heading"><div><h2 id="forecast-records-title">Records &amp; verification</h2></div></div>
    <details class="statistical-card statistical-operations" @if($reviewError) open @endif><summary>Monthly history &amp; coverage</summary>
        <p>Ang count gikan sa actual movement dates. I-confirm lang kung kompleto na ang source report sa bulan, apil ang confirmed zero. Ang walay entries nga wala pa ma-confirm kay <b>unknown</b>.</p>
        <form method="POST" action="{{ route('prediction.sync') }}">@csrf<input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><button type="submit">Sync existing dated movement records</button></form>
        <p>Ang sync dili maghimo og dates gikan sa RBI save dates ug dili mo-confirm sa completeness.</p>
        <div class="statistical-table"><table><thead><tr><th>Month</th><th>Type</th><th>Recorded events</th><th>Coverage</th><th>Review action</th></tr></thead><tbody>
        @foreach($history as $type=>$months) @foreach($months as $month)
            <tr><td class="statistical-nowrap">{{ \Illuminate\Support\Carbon::createFromFormat('!Y-m', $month['month'])->format('M Y') }}</td><td><span class="statistical-type {{ $type==='in' ? 'is-in' : 'is-out' }}">{{ $type==='in' ? 'In-migration' : 'Out-migration' }}</span></td><td>{{ $month['count'] }}</td><td><span class="statistical-coverage {{ $month['verified'] ? 'is-complete' : '' }}">{{ $month['verified'] ? 'Confirmed complete' : ($month['unsynced'] ? 'Sync required' : ($month['duplicates'] ? 'Possible duplicates — review' : 'Unknown / needs confirmation')) }}</span></td>
            <td>@include('barangay.dashboard._confirm-statistical-month', ['month'=>$month,'type'=>$type])</td></tr>
        @endforeach @endforeach
        </tbody></table></div>
    </details>
    <details class="statistical-card statistical-operations" id="saved-forecasts" @if(request()->has('page') || $reviewError) open @endif><summary>Saved forecasts <span class="statistical-count">{{ $logs->total() }}</span></summary><p>Actual counts appear after month verification.</p>
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
    <details class="statistical-card statistical-operations" @if($movementError) open @endif><summary>Record a verified movement</summary>
        <p>Gamita ang actual movement date gikan sa source. Ayaw gamita ang encoding date. Kini event log lamang; ang residency status i-update sa Resident Registry kung gikinahanglan.</p>
        <form method="POST" action="{{ route('prediction.movements.store') }}" class="statistical-entry">@csrf<input type="hidden" name="barangay_id" value="{{ $barangay->id }}">
            <label>Resident<select name="resident_id" required><option value="">Choose resident</option>@foreach($residents as $resident)<option value="{{ $resident->id }}" @selected((string) old('resident_id') === (string) $resident->id)>{{ $resident->fullName() }}</option>@endforeach</select></label>
            <label>Movement<select name="migration_type"><option value="in" @selected(old('migration_type') === 'in')>In-migration</option><option value="out" @selected(old('migration_type') === 'out')>Out-migration</option></select></label>
            <label>Actual movement date<input name="transaction_date" type="date" min="1900-01-01" max="{{ now(config('migration_forecast.timezone'))->toDateString() }}" value="{{ old('transaction_date') }}" required></label>
            <label class="statistical-check"><input type="checkbox" name="confirm_date" value="1" required>Verified kini nga movement date, dili hinimo nga petsa.</label>
            <button type="submit">Save movement event</button>
        </form>
        <div class="statistical-table"><table><thead><tr><th>Latest recorded date</th><th>Resident</th><th>Type</th></tr></thead><tbody>
        @forelse($movements as $event)<tr><td>{{ $event->transaction_date->format('Y-m-d') }}</td><td>{{ $event->resident->fullName() }}</td><td>{{ strtoupper($event->migration_type) }}</td></tr>@empty<tr><td colspan="3">Wala pay dated movement logs sa scope.</td></tr>@endforelse
        </tbody></table></div>
    </details>
    <details class="statistical-card statistical-operations"><summary>Method &amp; formulas</summary>
        <p>{{ $summary }}</p>
        <p><b>WMA:</b> Σ(weight × monthly count) / Σ(weights). Example: counts 2, 4, 8 ug weights 1, 2, 3 → (2 + 8 + 24) / 6 = 5.6667 events.</p>
        <p><b>Cold start:</b> P × ((1 + r)<sup>1</sup> − 1), default r = 0.001. Kini one-period population-proportional assumption sa matag direction; dili validated migration rate ug dili net population growth forecast.</p>
        <p><b>MAPE:</b> mean(|actual − predicted| / actual) × 100, para lamang sa verified actual &gt; 0. Score = 100 − MAPE; mahimo kining negative ug dili kini probability. Ang zero-actual months makita sa MAE/RMSE.</p>
        <p>Ang future target mao ang sunod nga calendar month; ang newest usable data mao ang last completed month. Busa adunay usa ka incomplete-month gap. Ang weights dili gi-tune batok sa future test results. Dili mahibal-an ang accuracy hangtod adunay saved predictions ug subsequent verified actuals.</p>
    </details>
    </section>
