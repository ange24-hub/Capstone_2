@props(['icon' => 'document', 'label' => null])

<div class="workspace-heading">
    <span class="workspace-heading-emblem" aria-hidden="true"><x-app-icon :name="$icon" /></span>
    <div class="workspace-heading-copy">
        @if($label)<span class="dashboard-eyebrow">{{ $label }}</span>@endif
        {{ $slot }}
    </div>
</div>
