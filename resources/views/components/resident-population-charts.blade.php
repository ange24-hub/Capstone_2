@props(['counts', 'barangay', 'approvals' => 0])
@php
    $total = $counts['residents'];
    $living = min($total, (int) $barangay->local_residents_count);
    $tracked = $barangay->usesResidenceRegistry();
    $charts = [
        'residents' => ['Residents', 'Registered inhabitants', '#208b80', '#4bbba7', $tracked ? $living : $total],
        'seniors' => ['Senior citizens', 'Age 60+ or marked SC', '#7854ad', '#ae8ed7', $counts['seniors']],
        'pwd' => ['Persons with disabilities', 'PWD - Marked in registry', '#197f97', '#56b9cb', $counts['pwd']],
        'indigent' => ['Indigent residents', 'Marked in registry', '#a8731e', '#dfb45d', $counts['indigent']],
    ];
@endphp
<div class="community-analytics">
    <div class="population-chart-grid" role="group" aria-label="Resident sectors">
        @foreach ($charts as $key => [$label, $description, $color, $light, $value])
            @php($percent = $total ? $value / $total * 100 : 0)
            <article class="population-chart" style="--sector-color: {{ $color }}">
                <header class="population-chart-heading">
                    <h3>{{ $label }}</h3>
                    <p>{{ $description }}</p>
                </header>
                <svg viewBox="0 0 200 200" class="population-donut" role="img" aria-label="{{ $label }}: {{ number_format($counts[$key]) }} residents. {{ $key === 'residents' && $tracked ? number_format($living).' living in barangay' : number_format($percent, 1).' percent of registered inhabitants' }}">
                    <title>{{ $label }}: {{ number_format($counts[$key]) }} residents</title>
                    <defs><linearGradient id="population-{{ $key }}-gradient" x1="0" y1="0" x2="1" y2="1"><stop stop-color="{{ $light }}"/><stop offset="1" stop-color="{{ $color }}"/></linearGradient></defs>
                    <circle class="donut-track" cx="100" cy="100" r="79" />
                    <circle class="donut-value" cx="100" cy="100" r="79" pathLength="100" stroke="url(#population-{{ $key }}-gradient)" stroke-dasharray="{{ $percent }} 100" transform="rotate(-90 100 100)" />
                    <text x="100" y="102" class="donut-total">{{ number_format($counts[$key]) }}</text>
                    <text x="100" y="123" class="donut-caption">{{ $key === 'residents' ? 'TOTAL RESIDENTS' : 'RESIDENTS' }}</text>
                </svg>
                <div class="population-chart-summary">
                    @if ($key === 'residents' && $tracked)
                        <strong>{{ number_format($living) }}</strong>
                        <span>Living in barangay</span>
                    @else
                        <strong>{{ number_format($percent, 1) }}<small>%</small></strong>
                        <span>of registered inhabitants</span>
                    @endif
                </div>
                <div class="population-chart-footnote">
                    <i class="population-legend-dot" aria-hidden="true"></i>
                    <span>{{ number_format($total - $value) }} {{ $key === 'residents' && $tracked ? 'elsewhere / unconfirmed' : 'other / unclassified' }}</span>
                </div>
            </article>
        @endforeach
    </div>
    @if (!$total)<p class="population-empty">No resident records yet</p>@endif
    <div class="population-operations" aria-label="Other barangay records">
        <a href="{{ route('barangay.resident-approvals.index') }}"><span class="population-operation-icon amber"><x-app-icon name="users" /></span><span class="population-operation-copy"><small>Resident approvals</small><span>Awaiting review</span></span><strong>{{ number_format($approvals) }}</strong></a>
        <div><span class="population-operation-icon teal"><x-app-icon name="home" /></span><span class="population-operation-copy"><small>Households</small><span>Household profiles</span></span><strong>{{ number_format($barangay->households_count) }}</strong></div>
        <div><span class="population-operation-icon violet"><x-app-icon name="trend" /></span><span class="population-operation-copy"><small>Migration events</small><span>Arrivals &amp; departures</span></span><strong>{{ number_format($barangay->migration_records_count) }}</strong></div>
    </div>
    <footer class="population-footer"><span>Barangay {{ $barangay->name }} &middot; Resident registry</span><a href="{{ route('barangay.registry.active') }}">View resident registry <span aria-hidden="true">&rarr;</span></a></footer>
</div>
