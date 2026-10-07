@if($report->status !== App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)
    <a class="history-submit" href="{{ route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => 'submit']) }}">Submit to Municipal</a>
@endif
