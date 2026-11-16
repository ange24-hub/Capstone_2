@extends('layouts.app')

@section('content')
<section class="panel dashboard-page" aria-labelledby="registry-submissions-title">
    <x-workspace-heading icon="users">
        <span class="dashboard-eyebrow">Municipal report inbox</span>
        <h1 id="registry-submissions-title">Registry submissions</h1>
        <p>Read-only snapshots submitted by barangays. Live registry changes do not alter received copies.</p>
    </x-workspace-heading>

    <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
        <table>
            <thead><tr><th>Reference</th><th>Barangay</th><th>Submitted file scope</th><th>Records</th><th>Submitted by</th><th>Submitted at</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($submissions as $submission)
                <tr>
                    <td>{{ $submission->reference() }}</td>
                    <td>{{ $submission->barangay_name }}</td>
                    <td>{{ $submission->scopeLabel() }}</td>
                    <td>{{ number_format($submission->record_count) }}</td>
                    <td>{{ $submission->submitter?->name ?? 'Account unavailable' }}</td>
                    <td>{{ $submission->submitted_at->timezone('Asia/Manila')->format('M d, Y · h:i A') }}</td>
                    <td><a href="{{ route('municipal.registry-submissions.show', $submission) }}">Review snapshot</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No registry snapshots have been submitted.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $submissions->links() }}
</section>
@endsection
