@extends('layouts.app')
@section('content')
<section class="dashboard-page grid gap-6">
    <header class="page-head"><x-workspace-heading icon="trend" label="RBI Forms"><h1>RBI entry classification</h1><p>Review the resident entries recorded for Barangay {{ $barangay->name }}.</p></x-workspace-heading></header>
    <div class="toolbar"><a class="button secondary-button" href="{{ auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY) ? route('barangay.rbi-updates.history') : route('municipal.barangays.index') }}">Back to reports</a>@if(auth()->user()->hasRole(App\Models\User::ROLE_MUNICIPAL_LGU))<a class="button secondary-button" href="{{ route('municipal.prediction-forecast', ['barangay_id'=>$barangay->id]) }}">Prediction analytics</a>@endif</div>
    @if(session('status'))<div class="success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="errors" role="alert">{{ $errors->first() }}</div>@endif
    @if($historyMonths->isNotEmpty() || $undatedHistoryCount)
    <section class="workflow-card"><h2>Existing {{ $barangay->name }} Report History</h2><p>Open a monthly RBI form to classify each entry as in-migration, birth, or late registration.</p>
        <div class="table-wrap"><table><thead><tr><th>History month</th><th>Resident entries</th><th>Classification</th></tr></thead><tbody>
        @foreach($historyMonths as $month=>$count)<tr><th scope="row">{{ \Carbon\Carbon::createFromFormat('!Y-m',$month)->format('F Y') }}</th><td>{{ $count }}</td><td>@if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<form method="POST" action="{{ route('registry.history.prepare-arrivals',$month) }}">@csrf<button type="submit" class="secondary-button">Prepare / open classification form</button></form>@else Review this month with the barangay preparer. @endif</td></tr>@endforeach
        </tbody></table></div>
        <h3>Review each resident entry</h3><p>Check why each person was added with the household or barangay preparer. Birth dates are context only; being a baby or sharing a household does not automatically establish migration.</p>
        @foreach($historyEntries as $month => $entries)
            @include('rbi-updates._arrival-history-residents')
        @endforeach
        @if($undatedHistoryCount)<p>{{ $undatedHistoryCount }} entries have no reporting month and are excluded from monthly analytics until their source month is confirmed.</p>@endif
    </section>
    @else
    <p>No RBI report history is available for this barangay.</p>
    @endif

</section>
@endsection
