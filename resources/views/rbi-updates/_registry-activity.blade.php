<section class="rbi-registry-activity mt-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm" aria-labelledby="registry-activity-title">
    <div class="rbi-activity-heading">
        <h2 id="registry-activity-title" class="text-lg font-semibold text-slate-900">Registry activity</h2>
        <p class="mt-1 text-sm text-slate-600">Recent changes recorded by barangay staff.</p>
    </div>
    <div class="rbi-activity-table mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead><tr><th class="p-3">Date</th><th class="p-3">Change</th><th class="p-3">Recorded by</th></tr></thead>
            <tbody>
                @forelse($registryActivities as $activity)
                    <tr class="border-t border-slate-200"><td class="p-3">{{ $activity->created_at->copy()->timezone('Asia/Manila')->format('M d, Y g:i A').' PhST' }}</td><td class="p-3">{{ $activity->description }}</td><td class="p-3">{{ $activity->user?->name ?: 'Former staff account' }}</td></tr>
                @empty
                    <tr><td class="p-3 text-slate-600" colspan="3">No registry changes recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($registryActivities instanceof \Illuminate\Pagination\LengthAwarePaginator)
        <div class="mt-4">{{ $registryActivities->fragment('registry-activity-title')->links() }}</div>
    @endif
</section>
