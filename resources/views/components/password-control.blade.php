@props(['fieldId', 'label' => 'password'])

<div {{ $attributes->class(['portal-password-control']) }}>
    {{ $slot }}
    <button class="password-toggle" type="button" aria-controls="{{ $fieldId }}" aria-label="Show {{ $label }}" aria-pressed="false" data-password-toggle data-password-label="{{ $label }}" hidden>
        <span data-password-show-icon aria-hidden="true"><x-app-icon name="eye" /></span>
        <span data-password-hide-icon aria-hidden="true" hidden><x-app-icon name="eye-off" /></span>
        <span data-password-text>Show</span>
    </button>
</div>
