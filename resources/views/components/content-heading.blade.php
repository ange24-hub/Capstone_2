@props(['icon' => 'document'])
<h2 {{ $attributes->class(['content-panel-heading']) }}><span><x-app-icon :name="$icon" /></span>{{ $slot }}</h2>
