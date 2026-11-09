@if(!$month['verified'] && !$month['duplicates'] && !$month['unsynced'])
<form method="POST" action="{{ route('prediction.verify') }}" class="statistical-verify">@csrf
    <input type="hidden" name="barangay_id" value="{{ $barangay->id }}"><input type="hidden" name="log_month" value="{{ $month['month'] }}">
    <input type="hidden" name="migration_type" value="{{ $type }}"><input type="hidden" name="source_hash" value="{{ $month['source_hash'] }}">
    <label><input type="checkbox" name="confirm_complete" value="1" required>Kompleto ang {{ strtoupper($type) }} report; na-review ang dates ug counts.</label>
    <button type="submit">Confirm complete</button>
</form>
@else
{{ $month['verified'] ? 'Verified' : ($month['unsynced'] ? 'Sync source records first' : 'Resolve duplicate source events first') }}
@endif
