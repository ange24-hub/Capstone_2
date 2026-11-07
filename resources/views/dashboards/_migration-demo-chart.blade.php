@php
    $rows = collect($evaluation['backtest']);
    $minimum = min(0, (float) $rows->min(fn($r) => min($r['actual'], $r['predicted'], $r['baseline'])));
    $maximum = max(1, (float) $rows->max(fn($r) => max($r['actual'], $r['predicted'], $r['baseline'])));
    $step = max(1, ceil(($maximum - $minimum) / 4));
    $bottom = floor($minimum / $step) * $step;
    $top = ceil($maximum / $step) * $step;
    $range = max(1, $top - $bottom);
    $y = fn($value) => 180 - ($value - $bottom) / $range * 144;
    $points = fn($key) => $rows->map(fn($row, $i) => (54 + $i * 128).','.round($y($row[$key]), 3))->implode(' ');
@endphp
<article class="demo-chart-card" aria-labelledby="demo-{{ $kind }}-title">
    <h3 id="demo-{{ $kind }}-title">{{ $label }}</h3>
    <p class="demo-model-name">{{ $evaluation['method'] ?? 'Arrival forecast minus departure forecast' }}</p>
    <svg viewBox="0 0 350 220" role="img" aria-labelledby="demo-{{ $kind }}-svg-title demo-{{ $kind }}-svg-desc">
        <title id="demo-{{ $kind }}-svg-title">{{ $label }}: synthetic actual, predicted and baseline counts</title>
        <desc id="demo-{{ $kind }}-svg-desc">July, August and September 2026 held-out test results. Exact values are listed in the table below.</desc>
        <text x="10" y="17" class="demo-axis-label">Movement events</text>
        @for($tick = 0; $tick <= 4; $tick++)
            <line x1="54" y1="{{ 180 - $tick * 36 }}" x2="310" y2="{{ 180 - $tick * 36 }}" stroke="#dce4ed"/>
            <text x="43" y="{{ 184 - $tick * 36 }}" text-anchor="end">{{ number_format($bottom + $range * $tick / 4, $range % 4 ? 1 : 0) }}</text>
        @endfor
        <polyline points="{{ $points('baseline') }}" fill="none" stroke="#64748b" stroke-width="2" stroke-dasharray="6 5"/>
        <polyline points="{{ $points('actual') }}" fill="none" stroke="#345b82" stroke-width="3"/>
        <polyline points="{{ $points('predicted') }}" fill="none" stroke="#b56819" stroke-width="3"/>
        @foreach($rows as $row)
            <circle cx="{{ 54 + $loop->index * 128 }}" cy="{{ $y($row['actual']) }}" r="4" fill="#345b82"/>
            <rect x="{{ 50 + $loop->index * 128 }}" y="{{ $y($row['predicted']) - 4 }}" width="8" height="8" fill="#b56819"/>
            <text x="{{ 54 + $loop->index * 128 }}" y="207" text-anchor="middle">{{ substr($row['label'], 0, 3) }}</text>
        @endforeach
    </svg>
    <div class="demo-errors"><div><span>Model MAE</span><strong>{{ number_format($evaluation['mae'], 3) }}</strong></div><div><span>Baseline MAE</span><strong>{{ number_format($evaluation['baseline_mae'], 3) }}</strong></div></div>
    <p class="demo-comparison" data-comparison="{{ $evaluation['baseline_comparison'] }}">{{ match($evaluation['baseline_comparison']) { 'better' => 'Lower error than baseline', 'equal' => 'Same error as baseline', default => 'Higher error than baseline' } }}</p>
</article>
