@props(['counts', 'barangay'])
@php
    $total = max(0, (int) $counts['residents']);
    $tracked = $barangay->usesResidenceRegistry();
    $living = min($total, max(0, (int) $barangay->local_residents_count));
    $residentValue = $tracked ? $living : $total;
    $residentPercent = $total ? $residentValue / $total * 100 : 0;
    $sectors = [
        'seniors' => ['Senior citizens', 'var(--to-chart-blue, #4d6b89)'],
        'pwd' => ['Persons with disabilities', 'var(--to-chart-teal, #60817d)'],
        'indigent' => ['Indigent residents', 'var(--to-chart-gold, #9a8155)'],
    ];
@endphp
<div class="community-analytics population-overview">
    <div class="population-metrics" aria-label="Barangay totals">
        @foreach ([['Registered residents', $total, 'users', 'blue'], ['Households', $barangay->households_count, 'home', 'teal'], ['Migration events', $barangay->migration_records_count, 'trend', 'gold']] as [$label, $value, $icon, $accent])
            <article class="population-metric population-metric-{{ $accent }}">
                <div class="population-metric-label"><h3>{{ $label }}</h3><span aria-hidden="true"><x-app-icon :name="$icon" /></span></div>
                <strong>{{ number_format($value) }}</strong>
            </article>
        @endforeach
    </div>
    <div class="population-overview-grid" role="group" aria-label="Resident sectors">
        <section class="population-resident-summary" aria-labelledby="population-residents-title">
            <header><h3 id="population-residents-title">{{ $tracked ? 'Residence status' : 'Registered residents' }}</h3></header>
            <div class="population-resident-visual">
                <figure class="population-pie-figure">
                    <div class="population-pie{{ $total ? '' : ' population-pie-empty' }}" style="--living-percent: {{ $residentPercent }}%" role="img" aria-label="{{ number_format($total) }} registered residents. {{ $tracked ? number_format($living).' living in barangay; '.number_format($total - $living).' elsewhere or unconfirmed.' : 'All registered residents.' }}"></div>
                    <figcaption>{{ number_format($total) }} registered</figcaption>
                </figure>
                <dl class="population-resident-legend">
                    <div><dt><i class="population-key" aria-hidden="true"></i>{{ $tracked ? 'Living in barangay' : 'Registered residents' }}</dt><dd>{{ number_format($residentValue) }}<small>{{ number_format($residentPercent, 1) }}%</small></dd></div>
                    @if ($tracked)
                        <div><dt><i class="population-key population-key-other" aria-hidden="true"></i>Elsewhere / unconfirmed</dt><dd>{{ number_format($total - $living) }}<small>{{ number_format($total ? 100 - $residentPercent : 0, 1) }}%</small></dd></div>
                    @endif
                </dl>
            </div>
            @if (!$total)<p class="population-no-data">No resident records yet</p>@endif
        </section>
        <section class="population-sector-summary" aria-label="Resident groups">
            <header class="population-groups-heading"><h3>Resident groups</h3><span>Share of registered residents</span></header>
            @foreach ($sectors as $key => [$label, $color])
                @php
                    $value = min($total, max(0, (int) $counts[$key]));
                    $percent = $total ? $value / $total * 100 : 0;
                @endphp
                <div class="population-sector-row" style="--sector-color: {{ $color }}" role="group" aria-label="{{ $label }}: {{ number_format($value) }} of {{ number_format($total) }} registered residents, {{ number_format($percent, 1) }} percent.">
                    <div class="population-sector-copy"><h4>{{ $label }}</h4></div>
                    <div class="population-sector-count"><strong>{{ number_format($value) }}</strong><span>{{ number_format($percent, 1) }}%</span></div>
                    <div class="population-sector-track" aria-hidden="true"><span style="width: {{ $percent }}%"></span></div>
                </div>
            @endforeach
            <p class="population-sector-note">A resident may belong to more than one group.</p>
        </section>
    </div>
</div>
