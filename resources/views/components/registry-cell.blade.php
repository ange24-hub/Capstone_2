@props(['value' => '', 'readOnly' => false, 'type' => 'text'])
@if($readOnly)
    <span class="registry-cell-value">{{ $value }}</span>
@elseif($type === 'text')
    <textarea {{ $attributes->class(['registry-cell-editor']) }} rows="1">{{ $value }}</textarea>
@else
    <input {{ $attributes }} type="{{ $type }}" value="{{ $value }}">
@endif
