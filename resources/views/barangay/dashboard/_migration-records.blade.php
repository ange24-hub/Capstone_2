<section id="migration-records" aria-labelledby="migration-records-title" class="statistical-records">
    <div class="statistical-section-heading"><div><h2 id="migration-records-title">Dated movement records &amp; monthly coverage</h2></div></div>
    <details class="statistical-card statistical-operations" @if($reviewError) open @endif>
        <summary>Monthly history &amp; coverage</summary>
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
    <details class="statistical-card statistical-operations" @if($movementError) open @endif>
        <summary>Record a verified movement</summary>
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
</section>
