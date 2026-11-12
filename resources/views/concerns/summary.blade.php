@extends('layouts.app')
@push('head')
<link rel="stylesheet" href="{{ asset('css/concern-summary.css') }}?v={{ filemtime(public_path('css/concern-summary.css')) }}" media="screen">
@endpush
@section('content')
@php
    $total = (int) $rows->sum('total');
    $open = (int) $rows->whereIn('status', ['submitted','under_review','in_progress'])->sum('total');
    $resolved = (int) $rows->where('status','resolved')->sum('total');
    $closed = (int) $rows->where('status','closed')->sum('total');
    $selectedArea = $barangays->firstWhere('id', (int) request('barangay_id'));
    $categories = collect(\App\Models\ResidentConcern::categories())->map(fn ($label, $key) => [
        'key'=>$key, 'label'=>$label, 'count'=>(int) $rows->where('category',$key)->sum('total'),
    ])->sortByDesc('count');
    $groups = $rows->groupBy('barangay_id');
    $areas = $barangays->filter(fn ($area) => !$selectedArea || $area->id === $selectedArea->id)->map(function ($area) use ($groups) {
        $group = $groups->get($area->id, collect());
        return ['name'=>$area->name, 'total'=>(int) $group->sum('total'),
            'open'=>(int) $group->whereIn('status',['submitted','under_review','in_progress'])->sum('total'),
            'resolved'=>(int) $group->where('status','resolved')->sum('total'),
            'closed'=>(int) $group->where('status','closed')->sum('total')];
    })->sortByDesc('total');
@endphp
<section class="concern-summary" aria-labelledby="concern-summary-title">
    <header class="concern-page-heading">
        <div class="concern-title-group"><span class="concern-heading-icon"><x-app-icon name="activity" /></span><div><span class="concern-eyebrow">Municipal overview</span><h1 id="concern-summary-title">Concern summary</h1><p>{{ $selectedArea ? 'Barangay '.$selectedArea->name : 'All barangays' }} <span aria-hidden="true">·</span> Submissions since {{ $start->format('M d, Y') }}</p></div></div>
        <span class="concern-privacy-tag"><x-app-icon name="shield" />Aggregate data only</span>
    </header>
    @include('concerns._feedback')
    <form method="GET" action="{{ route('concerns.summary') }}" class="concern-filters" aria-label="Filter concern summary">
        <div><label for="area">Barangay</label><select id="area" name="barangay_id"><option value="">All barangays</option>@foreach($barangays as $area)<option value="{{ $area->id }}" @selected((string) request('barangay_id') === (string) $area->id)>{{ $area->name }}</option>@endforeach</select></div>
        <div><label for="days">Submission window</label><select id="days" name="days">@foreach([30,90,365] as $period)<option value="{{ $period }}" @selected($days === $period)>Last {{ $period }} days</option>@endforeach</select></div>
        <button type="submit"><x-app-icon name="filter" />Apply filters</button><a href="{{ route('concerns.summary') }}">Reset</a>
    </form>
    <div class="concern-metrics" aria-label="Concern totals for the selected window">
        @foreach([['total','Total submitted',$total,'All concerns in this window','inbox'],['open','Still open',$open,'Awaiting action or ongoing','clock'],['resolved','Resolved',$resolved,'Currently marked resolved','check'],['closed','Closed',$closed,'Currently marked closed','directory']] as [$key,$label,$count,$detail,$icon])
            <article class="concern-metric concern-metric-{{ $key }}"><span class="concern-metric-icon"><x-app-icon :name="$icon" /></span><div><h2>{{ $label }}</h2><strong data-concern-total="{{ $key }}">{{ number_format($count) }}</strong><p>{{ $detail }}</p></div></article>
        @endforeach
    </div>
    @if($total === 0)<p class="concern-empty-window"><x-app-icon name="inbox" />No concerns were submitted in the selected window.</p>@endif
    <div class="concern-breakdowns">
        <section class="concern-panel" aria-labelledby="concern-categories-title">
            <header class="concern-panel-heading"><div><h2 id="concern-categories-title">By category</h2><p>Current recorded category</p></div><span>{{ $categories->count() }} categories</span></header>
            <ul class="concern-category-list">
                @foreach($categories as $category)
                    <li><div><span>{{ $category['label'] }}</span><strong>{{ number_format($category['count']) }}</strong></div><div class="concern-category-track" aria-hidden="true"><span style="width: {{ round(100 * $category['count'] / max(1,$total),1) }}%"></span></div></li>
                @endforeach
            </ul>
        </section>
        <section class="concern-panel" aria-labelledby="concern-status-title">
            <header class="concern-panel-heading"><div><h2 id="concern-status-title">Current status</h2><p>Where submitted concerns stand now</p></div><x-app-icon name="activity" /></header>
            <dl class="concern-status-list">
                @foreach(\App\Models\ResidentConcern::statuses() as $key => $label)<div><dt><i class="concern-status-dot concern-status-{{ $key }}" aria-hidden="true"></i>{{ $label }}</dt><dd>{{ number_format($rows->where('status',$key)->sum('total')) }}</dd></div>@endforeach
            </dl>
            <p class="concern-status-note">Still open = submitted, under review, or in progress. Closed is separate from resolved.</p>
        </section>
    </div>
    <section class="concern-panel concern-area-panel" aria-labelledby="concern-areas-title" data-concern-table>
        <header class="concern-panel-heading"><div><h2 id="concern-areas-title">Barangay breakdown</h2><p>Highest submission counts first</p></div><div class="concern-table-search" data-concern-search-wrap hidden><label for="concern-area-search" class="sr-only">Search the barangay breakdown</label><x-app-icon name="search" /><input id="concern-area-search" type="search" placeholder="Search barangay" autocomplete="off" data-concern-search></div></header>
        <div class="concern-table-scroll"><table><caption class="sr-only">Concern counts by barangay for the selected submission window</caption><thead><tr><th scope="col">Barangay</th><th scope="col" class="table-cell-end">Total submitted</th><th scope="col" class="table-cell-end">Still open</th><th scope="col" class="table-cell-end">Resolved</th><th scope="col" class="table-cell-end">Closed</th></tr></thead><tbody>
            @foreach($areas as $area)<tr data-concern-row data-name="{{ $area['name'] }}"><th scope="row">{{ $area['name'] }}</th><td class="table-cell-end">{{ number_format($area['total']) }}</td><td class="table-cell-end"><span class="concern-open-count {{ $area['open'] ? 'has-open' : '' }}">{{ number_format($area['open']) }}</span></td><td class="table-cell-end">{{ number_format($area['resolved']) }}</td><td class="table-cell-end">{{ number_format($area['closed']) }}</td></tr>@endforeach
            <tr data-concern-no-match hidden><td colspan="5">No barangays match your search.</td></tr>
        </tbody></table></div>
        <footer class="concern-table-footer"><p data-concern-table-status role="status" aria-live="polite">{{ $areas->count() }} {{ $areas->count() === 1 ? 'barangay' : 'barangays' }}</p><div data-concern-pagination hidden><button type="button" data-concern-previous>Previous</button><span data-concern-page></span><button type="button" data-concern-next>Next</button></div></footer>
    </section>
    <details class="concern-data-note"><summary><x-app-icon name="shield" />How to read these counts<x-app-icon name="chevron-down" /></summary><p>These are submitted concern counts, not verified incident rates or resident risk scores. Categories reflect the current recorded classification, including staff reviews where available. Low counts can reflect limited participation. Individual descriptions, identities, and attachments are restricted to the resident and assigned barangay.</p></details>
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/concern-summary.js') }}?v={{ filemtime(public_path('js/concern-summary.js')) }}" defer></script>
@endpush
