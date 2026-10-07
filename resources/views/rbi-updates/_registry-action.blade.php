@if(count($report->rows ?? []) > 0)
    <a href="{{ route('barangay.rbi-updates.certification', ['rbiUpdate' => $report, 'action' => 'consolidate']) }}">Add to Consolidated RBI</a>
    @if(collect($report->rows)->every(fn ($row) => filled($row['inhabitant_id'] ?? null)))
        <a href="{{ route('barangay.registry.active') }}">View Consolidated RBI</a>
    @endif
@endif
