@extends('layouts.app')
@section('content')
<section class="dashboard-page grid gap-6">
    <header class="page-head"><x-workspace-heading icon="trend" label="RBI Forms"><h1>Three-month arrival counts</h1><p>Barangay {{ $barangay->name }} · Consecutive 3-month reporting periods begin with the first recorded history or submitted report month.</p></x-workspace-heading></header>
    <div class="toolbar"><a class="button secondary-button" href="{{ auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY) ? route('barangay.rbi-updates.history') : route('municipal.barangays.index') }}">Back to reports</a>@if(auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))<a class="button secondary-button" href="{{ route('municipal.prediction-forecast', ['barangay_id'=>$barangay->id]) }}">Prediction analytics</a>@endif</div>
    @if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
    <p>Resident counts come from submitted monthly RBI forms. Only confirmed In-migration entries with an arrival month matching the reporting month are eligible. Record the previous residence when known, or explicitly mark it as not recorded. Birth / Newborn and Late registration entries are excluded. Unclassified or invalid legacy entries must be reviewed in the monthly form first. Confirm each month's arrival count, including genuine zeros.</p>
    <p>A month with no report between two recorded history months means zero new entries. For example, March and May reports with no April report give April a count of 0. Drafts and saved entries still awaiting submission are not zero months. Months after the latest recorded report remain pending.</p>
    @unless($storageReady)<p class="errors">Arrival review storage is not ready. Install the arrival-period migration locally.</p>@endunless
    @if($historyMonths->isNotEmpty() || $undatedHistoryCount)
    <section class="workflow-card"><h2>Existing {{ $barangay->name }} Report History</h2><p>Use each recorded reporting month as the source. Prepare a monthly form for classification, then submit it before the three-month arrival review. These are registered-entry counts until their entry types are reviewed.</p>
        <div class="table-wrap"><table><thead><tr><th>History month</th><th>Resident entries</th><th>Arrival analysis</th></tr></thead><tbody>
        @foreach($historyMonths as $month=>$count)<tr><th scope="row">{{ \Carbon\Carbon::createFromFormat('!Y-m',$month)->format('F Y') }}</th><td>{{ $count }}</td><td>@if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<form method="POST" action="{{ route('registry.history.prepare-arrivals',$month) }}">@csrf<button type="submit" class="secondary-button">Prepare / open classification form</button></form>@else Review this month with the barangay preparer. @endif</td></tr>@endforeach
        </tbody></table></div>
        <h3>Review each resident entry</h3><p>Check why each person was added with the household or barangay preparer. Birth dates are context only; being a baby or sharing a household does not automatically establish migration.</p>
        @foreach($historyEntries as $month => $entries)
            @include('rbi-updates._arrival-history-residents')
        @endforeach
        @if($undatedHistoryCount)<p>{{ $undatedHistoryCount }} entries have no reporting month and are excluded from monthly analytics until their source month is confirmed.</p>@endif
    </section>
    @endif
    @forelse(array_reverse($periods) as $period)
    <section class="workflow-card grid gap-4">
        <div class="workflow-head"><div><h2>{{ $period['label'] }}</h2><p>{{ $period['status'] }}</p></div><strong>{{ $period['verified'] ? $period['total'].' confirmed arrivals' : 'Awaiting review' }}</strong></div>
        <form method="POST" action="{{ route('rbi.arrivals.store') }}">
            @csrf
            <input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><input type="hidden" name="start_month" value="{{ $period['start'] }}"><input type="hidden" name="fingerprint" value="{{ $period['fingerprint'] }}">
            <div class="table-wrap"><table><thead><tr><th>Reporting month</th><th>Report source</th><th>Eligible new residents</th><th>Birth / late / repeated entries</th><th>Needs classification</th><th>Confirmed arrivals</th></tr></thead><tbody>
                @foreach($period['months'] as $month)<tr><th scope="row">{{ $month['label'] }}</th><td>{{ $month['source_status'] }} @foreach($month['report_ids'] as $reportId)<a href="{{ route('rbi-updates.show',$reportId) }}">View form</a>@endforeach</td><td>{{ $month['eligible'] }}</td><td>{{ $month['excluded'] }}</td><td>{{ $month['unclassified'] }}</td><td><input type="number" name="counts[{{ $month['month'] }}]" aria-label="Confirmed arrivals for {{ $month['label'] }}" min="0" max="{{ $month['eligible'] }}" value="{{ $month['inferred_zero'] ? 0 : ($period['review']?->monthly_counts[$month['month']] ?? '') }}" @readonly($month['inferred_zero']) required @disabled(!$period['complete'] || !$storageReady) style="max-width:150px"></td></tr>@endforeach
            </tbody></table></div>
            @if($period['complete'] && $storageReady)
                <label>Review notes<textarea name="notes" maxlength="1000">{{ $period['review']?->notes }}</textarea></label>
                <label><input type="checkbox" name="confirmed" value="1" required> I checked the submitted forms and any zero-entry gaps in this three-month history. These counts represent people who arrived to live here, with births and repeat / late registrations excluded.</label>
                <button type="submit">Save reviewed 3-month arrival counts</button>
            @else<p>Complete the entry classifications and submit the available monthly forms, then review after the reporting period has ended. Only empty months between recorded reports are counted as zero entries.</p>@endif
        </form>
        @if($period['review']?->reviewed_at)<small>Last review: {{ $period['review']->reviewed_at->format('M d, Y H:i') }} · {{ $period['review']->reviewer?->name }}</small><form method="POST" action="{{ route('rbi.arrivals.reopen') }}">@csrf<input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><input type="hidden" name="start_month" value="{{ $period['start'] }}"><button type="submit" class="secondary-button">Reopen arrival review</button></form>@endif
    </section>
    @empty<div class="workflow-card"><h2>No dated RBI report history yet</h2><p>The first recorded history or submitted reporting month will start the first 3-month arrival period.</p></div>@endforelse
</section>
@endsection
