@if(count($report->rows ?? []) > 0)
    <form method="POST" action="{{ route('barangay.rbi-updates.add-to-registry', $report) }}">
        @csrf
        <button type="submit" class="link-button">Add to Consolidated RBI</button>
    </form>
    @if(collect($report->rows)->every(fn ($row) => filled($row['inhabitant_id'] ?? null)))
        <a href="{{ route('barangay.registry.active') }}">View Consolidated RBI</a>
    @endif
@endif
