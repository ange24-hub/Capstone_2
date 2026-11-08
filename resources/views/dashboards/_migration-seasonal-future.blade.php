@php
    $futureRows = collect($forecastMonths);
    $futureValues = $futureRows->flatMap(fn ($row) => [$row['in'], $row['out'], $row['net']])->filter(fn ($value) => $value !== null);
    $futureMin = min(0, (float) $futureValues->min());
    $futureMax = max(1, (float) $futureValues->max());
    $futureRange = max(1, $futureMax - $futureMin);
    $futureY = fn ($value) => 185 - ($value - $futureMin) / $futureRange * 140;
@endphp
<section class="dashboard-card migration-forecast-card" aria-labelledby="seasonal-future-title">
    <header class="dashboard-card-header"><div><h2 id="seasonal-future-title">Next six monthly estimates</h2><p>Source cutoff {{ $sourceMonthLabel }} · forecasts only</p></div></header>
    <p class="forecast-note">Arrivals: {{ $predictedInMigration === null ? 'Not ready' : $forecastArrivalEvaluation['method'] }}. Departures: {{ $predictedOutMigration === null ? 'Not ready' : $forecastEvaluation['method'] }}. Counts come from classified RBI save months and checked departure months. Each component keeps its selected model across all six estimates. Historical error checks use the first target's {{ $forecastHorizon }}-month horizon; later targets have no separately displayed error score. Previous-year months in the table are comparison references, used directly only by Seasonal naive.</p>
    <div class="forecast-chart-legend"><span style="color:#345b82">● Arrivals</span><span style="color:#238576">● Departures</span><span style="color:#a76a22">● Net change</span></div>
    <div class="forecast-future-chart-scroll"><svg class="forecast-comparison-chart" viewBox="0 0 720 235" role="img" aria-labelledby="seasonal-future-chart-title seasonal-future-chart-description">
        <title id="seasonal-future-chart-title">Six monthly migration forecasts</title><desc id="seasonal-future-chart-description">Predictions only, using classified RBI entries grouped by first-save month and dated departure records. The table provides exact values and previous-year comparison months.</desc>
        @for($tick=0;$tick<=4;$tick++)<line x1="65" y1="{{ 185-$tick*35 }}" x2="615" y2="{{ 185-$tick*35 }}" stroke="#dce4eb"/><text x="53" y="{{ 189-$tick*35 }}" text-anchor="end">{{ number_format($futureMin+$futureRange*$tick/4,1) }}</text>@endfor
        @foreach(['in'=>'#345b82','out'=>'#238576','net'=>'#a76a22'] as $series=>$color)
            @if($futureRows->first()[$series] !== null)
                <polyline points="{{ $futureRows->map(fn ($row,$i)=>(65+$i*110).','.$futureY($row[$series]))->implode(' ') }}" fill="none" stroke="{{ $color }}" stroke-width="3" stroke-dasharray="7 4"/>
                @foreach($futureRows as $row)<circle cx="{{ 65+$loop->index*110 }}" cy="{{ $futureY($row[$series]) }}" r="4" fill="{{ $color }}"/>@endforeach
            @endif
        @endforeach
        @foreach($futureRows as $row)<text x="{{ 65+$loop->index*110 }}" y="218" text-anchor="middle">{{ $row['label'] }}</text>@endforeach
    </svg></div>
    <div class="table-wrap"><table><thead><tr><th>Target month</th><th>Arrivals</th><th>Departures</th><th>Net change</th><th>Reference month</th></tr></thead><tbody>
        @foreach($futureRows as $row)<tr><th scope="row">{{ $row['label'] }}</th>@foreach(['in','out','net'] as $series)<td>{{ $row[$series] === null ? 'Not ready' : ($series==='net' && $row[$series]>0 ? '+' : '').number_format($row[$series],1) }}</td>@endforeach<td>{{ $row['reference_month'] }}</td></tr>@endforeach
    </tbody></table></div>
</section>
