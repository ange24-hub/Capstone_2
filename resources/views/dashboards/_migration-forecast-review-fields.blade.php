@csrf
<input type="hidden" name="barangay_id" value="{{ $selectedBarangayId }}">
<input type="hidden" name="year" value="{{ $selectedYear }}">
<input type="hidden" name="month" value="{{ $month['month'] }}">
<input type="hidden" name="movement_scope" value="all">
<input type="hidden" name="source_fingerprint" value="{{ $month['fingerprint'] }}">
<input type="hidden" name="in_source_fingerprint" value="{{ $arrival['fingerprint'] }}">
