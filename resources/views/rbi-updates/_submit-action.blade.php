@if($report->status !== App\Models\BarangayRbiUpdate::STATUS_SUBMITTED && $report->status !== App\Models\BarangayRbiUpdate::STATUS_ADDED && $report->reporting_month->format('Y') > 2025)
    <a class="history-submit" href="{{ route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => 'submit']) }}">Submit to Municipal</a>
@endif
