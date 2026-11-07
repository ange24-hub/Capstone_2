@props(['name' => 'document'])
@php
    $illustration = match ($name) {
        'home', 'building', 'users', 'location', 'map' => 'community',
        'trend', 'activity' => 'analytics',
        'document', 'form', 'directory', 'inbox', 'shield' => 'documents',
        default => null,
    };
@endphp
<span class="portal-sticker" aria-hidden="true">
    @if($illustration)
        <img src="{{ asset('images/stickers/'.$illustration.'.png') }}" alt="" width="1280" height="1280" decoding="async">
    @else
        <x-app-icon :name="$name" />
    @endif
</span>
