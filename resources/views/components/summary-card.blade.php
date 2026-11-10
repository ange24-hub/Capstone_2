@props(['label', 'value', 'detail' => null, 'icon' => 'activity', 'tone' => 'neutral'])

<article {{ $attributes->class(['metric-card', 'metric-'.$tone]) }}>
    <span class="metric-icon"><x-app-icon :name="$icon" /></span>
    <div><span>{{ $label }}</span><strong>{{ $value }}</strong>@if($detail)<small>{{ $detail }}</small>@endif</div>
</article>
