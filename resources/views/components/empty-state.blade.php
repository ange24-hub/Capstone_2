@props(['title', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->class(['dashboard-empty-state', 'civic-empty-state']) }}>
    <span class="civic-empty-icon" aria-hidden="true"><x-app-icon :name="$icon" /></span>
    <strong>{{ $title }}</strong>
    @if($description)<p>{{ $description }}</p>@endif
    @if($slot->isNotEmpty())<div class="civic-empty-actions">{{ $slot }}</div>@endif
</div>
