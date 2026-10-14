@props(['title', 'description' => null])

<fieldset {{ $attributes->class(['civic-form-section']) }}>
    <legend>{{ $title }}</legend>
    @if($description)<p class="field-help">{{ $description }}</p>@endif
    {{ $slot }}
</fieldset>
