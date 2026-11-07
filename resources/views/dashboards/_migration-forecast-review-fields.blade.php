@csrf
<input type="hidden" name="barangay_id" value="{{ $selectedBarangayId }}">
<input type="hidden" name="year" value="{{ $selectedYear }}">
<input type="hidden" name="month" value="{{ $month['month'] }}">
<input type="hidden" name="movement_scope" value="out">
<input type="hidden" name="source_fingerprint" value="{{ $month['fingerprint'] }}">
