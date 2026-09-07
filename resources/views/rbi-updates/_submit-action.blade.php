@if($report->status !== App\Models\BarangayRbiUpdate::STATUS_SUBMITTED)
    <form method="POST" action="{{ route('barangay.rbi-updates.submit', $report) }}" onsubmit="return confirm('Submit this saved monthly RBI form to Municipal LGU?')">
        @csrf
        <button type="submit" class="history-submit">Submit to Municipal</button>
    </form>
@endif
