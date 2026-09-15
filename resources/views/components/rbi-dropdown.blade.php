@props(['field', 'value' => '', 'name' => null, 'rowField' => false])
@php
    $choices = App\Support\RbiFieldOptions::all()[$field];
    $current = (string) ($value ?? '');
    $label = App\Support\HouseholdRbi::fields()[$field];
@endphp
<div data-rbi-dropdown>
    <select {{ $attributes }} @if($name) name="{{ $name }}" @endif @if($rowField) data-row-field="{{ $field }}" @endif aria-label="{{ $label }}" data-rbi-select>
        <option value="" @selected($current === '')>Select {{ strtolower($label) }}</option>
        @if($current !== '' && !in_array($current, $choices, true))<option value="{{ $current }}" selected>{{ $current }}</option>@endif
        @foreach($choices as $choice)<option value="{{ $choice }}" @selected($current === $choice)>{{ $choice }}</option>@endforeach
        <option value="" data-rbi-other>Other (specify)</option>
    </select>
    <input type="text" data-rbi-custom hidden disabled maxlength="{{ $field === 'civil_status' ? 100 : 255 }}" aria-label="Specify {{ strtolower($label) }}" placeholder="Please specify">
</div>
