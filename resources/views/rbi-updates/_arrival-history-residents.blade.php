<details class="history-resident-review">
    <summary>{{ \Carbon\Carbon::createFromFormat('!Y-m', $month)->format('F Y') }} · {{ $entries->count() }} {{ Str::plural('resident', $entries->count()) }}</summary>
    <div class="table-wrap"><table>
        <caption class="sr-only">Residents recorded in {{ $month }} for individual classification review</caption>
        <thead><tr><th>History entry</th><th>Resident</th><th>Household</th><th>Birth date</th><th>Entry type</th><th>Review</th></tr></thead>
        <tbody>
        @foreach($entries as $entry)
            <tr>
                <th scope="row">#{{ $entry->id }}</th>
                <td>{{ trim(implode(' ', array_filter([$entry->first_name, $entry->middle_name, $entry->last_name, $entry->suffix]))) }}</td>
                <td>{{ $entry->household_number }}</td>
                <td>{{ $entry->birth_date?->format('M d, Y') ?: 'Not recorded' }}</td>
                <td><strong>{{ $historyClassifications[$entry->id]['label'] ?? 'Needs review' }}</strong>@if(isset($historyClassifications[$entry->id]))<small> · {{ $historyClassifications[$entry->id]['status'] }}</small>@endif</td>
                <td>
                    @if(auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                        <form method="POST" action="{{ route('registry.history.prepare-arrivals', $month) }}">
                            @csrf<input type="hidden" name="history_record_id" value="{{ $entry->id }}">
                            <button type="submit" class="secondary-button">Review this resident</button>
                        </form>
                    @else
                        Review entry #{{ $entry->id }} with the barangay preparer.
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</details>
