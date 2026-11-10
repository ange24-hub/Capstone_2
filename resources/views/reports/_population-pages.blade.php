@php
    $populationScope = $selectedBarangayId ?? $areaId ?? $submission->barangay_id ?? null;
    $historyPage = request()->routeIs('reports.population.submissions.index', 'reports.population.submissions.show');
@endphp
<nav class="population-page-tabs" aria-label="Population report pages">
    <a href="{{ route('reports.population', array_filter(['barangay_id'=>$populationScope])) }}" @unless($historyPage) aria-current="page" @endunless>Population Summary</a>
    <a href="{{ route('reports.population.submissions.index', array_filter(['barangay_id'=>$populationScope])) }}" @if($historyPage) aria-current="page" @endif>Submission History</a>
</nav>
