@extends('layouts.app')
@section('content')
<section class="grid min-w-0 grid-cols-1 gap-6">
    <header class="dashboard-page-header flex flex-wrap items-start justify-between gap-4">
            <x-workspace-heading icon="inbox"><span class="dashboard-eyebrow">Barangay {{ auth()->user()->barangay->name }}</span><h1>{{ $staff ? 'Resident Concerns' : 'My Concerns' }}</h1><p>{{ $staff ? 'Review service concerns and keep residents informed of the action taken.' : 'Submit a concern, add details, and follow updates from your barangay.' }}</p></x-workspace-heading>
        @unless($staff)<a class="button" href="{{ route('concerns.create') }}">Submit a concern</a>@endunless
    </header>
    @include('concerns._feedback')
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        @foreach(\App\Models\ResidentConcern::statuses() as $key => $label)<article data-status="{{ $key }}" class="concern-status-card rounded-xl border border-slate-200 bg-white p-4"><span class="concern-status-symbol" aria-hidden="true"><x-app-icon :name="match($key) { 'submitted' => 'inbox', 'under_review' => 'clock', 'in_progress' => 'activity', 'resolved' => 'check', default => 'shield' }" /></span><p class="text-sm text-slate-600">{{ $label }}</p><strong class="mt-2 block text-2xl text-slate-900">{{ number_format($counts[$key] ?? 0) }}</strong></article>@endforeach
    </div>
    <p class="text-xs text-slate-600">Status totals cover all {{ $staff ? 'concerns in your barangay' : 'your concerns' }}. Filters below apply to the list.</p>
    <form method="GET" action="{{ route('concerns.index') }}" class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
        <div class="min-w-0 flex-1"><label for="concern-search">Reference or title</label><input id="concern-search" name="search" value="{{ request('search') }}" maxlength="160" placeholder="Search concerns"></div>
        <div><label for="concern-status">Status</label><select id="concern-status" name="status"><option value="">All statuses</option>@foreach(\App\Models\ResidentConcern::statuses() as $key => $label)<option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="concern-category">Category</label><select id="concern-category" name="category"><option value="">All categories</option>@foreach(\App\Models\ResidentConcern::categories() as $key => $label)<option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>@endforeach</select></div>
        <button type="submit">Apply filters</button><a class="button secondary-button" href="{{ route('concerns.index') }}">Reset</a>
    </form>
    <div class="table-wrap"><table><thead><tr><th scope="col">Concern</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col">Submitted</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse($concerns as $concern)<tr><td><strong class="block text-slate-900">{{ $concern->title }}</strong><small class="break-all text-slate-500">{{ $concern->reference }}</small></td><td>{{ \App\Models\ResidentConcern::categories()[$concern->category] }}</td><td><span data-status="{{ $concern->status }}" class="concern-status inline-flex rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium">{{ \App\Models\ResidentConcern::statuses()[$concern->status] }}</span></td><td class="whitespace-nowrap">{{ $concern->created_at->format('M d, Y') }}</td><td><a href="{{ route('concerns.show', $concern) }}">View details</a></td></tr>
        @empty<tr><td colspan="5" class="text-center">No concerns match these filters.</td></tr>@endforelse
    </tbody></table></div>
    {{ $concerns->links() }}
</section>
@endsection
