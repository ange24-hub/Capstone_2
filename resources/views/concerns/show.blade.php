@extends('layouts.app')
@section('content')
<section class="grid min-w-0 grid-cols-1 gap-6">
    <header class="dashboard-page-header flex flex-wrap items-start justify-between gap-4">
            <x-workspace-heading icon="inbox"><span class="dashboard-eyebrow">{{ $concern->barangay->name }} · {{ \App\Models\ResidentConcern::statuses()[$concern->status] }}</span><h1 class="break-words">{{ $concern->title }}</h1><p class="break-all">{{ $concern->reference }} · Submitted {{ $concern->created_at->format('M d, Y g:i A') }}</p></x-workspace-heading><a class="button secondary-button" href="{{ route('concerns.index') }}">Back to concerns</a></header>
    @include('concerns._feedback')
    <div class="grid min-w-0 grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <div class="grid min-w-0 grid-cols-1 gap-6">
            <article class="rounded-xl border border-slate-200 bg-white p-5">
                <x-content-heading icon="location">Concern details</x-content-heading><dl class="concern-details-grid my-4 grid gap-3 text-sm"><div><dt class="text-slate-500">Category</dt><dd>{{ \App\Models\ResidentConcern::categories()[$concern->category] }}</dd></div>@if($staff)<div><dt class="text-slate-500">Submitted by</dt><dd>{{ $concern->resident->name }}</dd></div>@endif<div><dt class="text-slate-500">Location</dt><dd>{{ $concern->location ?: 'Not provided' }}</dd></div></dl>
                <p class="whitespace-pre-wrap break-words">{{ $concern->description }}</p>
                @if($concern->attachment_path)<a class="button secondary-button mt-5" href="{{ route('concerns.attachment', $concern) }}">Download supporting file</a>@endif
            </article>
            <article class="rounded-xl border border-slate-200 bg-white p-5"><x-content-heading icon="clock">Activity &amp; updates</x-content-heading><p class="mt-2 text-sm text-slate-600">Newest updates appear first.</p>
                <ol class="concern-timeline mt-5 grid min-w-0 grid-cols-1 gap-5">@foreach($updates as $update)<li class="min-w-0 border-l-2 border-slate-200 pl-4">
                    <div class="flex flex-wrap justify-between gap-2 text-sm"><strong>{{ (int) $update->author_id === (int) $concern->resident_id ? 'Resident' : 'Barangay staff' }}@if($staff) · {{ $update->author->name }}@endif</strong><time class="text-xs text-slate-500" datetime="{{ $update->created_at->toIso8601String() }}">{{ $update->created_at->format('M d, Y g:i A') }}</time></div>
                    @if($update->status)<p class="mt-1 text-xs font-semibold text-blue-800">{{ \App\Models\ResidentConcern::statuses()[$update->status] }} · {{ \App\Models\ResidentConcern::categories()[$update->category] }}</p>@endif
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $update->message }}</p>
                    @if($staff && $update->internal_note)<div class="mt-3 rounded-lg bg-slate-100 p-3 text-sm"><strong>Staff-only note</strong><p class="whitespace-pre-wrap break-words">{{ $update->internal_note }}</p></div>@endif
                </li>@endforeach</ol><div class="mt-5">{{ $updates->links() }}</div>
            </article>
        </div>
        <div class="grid min-w-0 grid-cols-1 gap-6">
            @if($staff)
                <article class="concern-ai-panel rounded-xl border border-slate-200 bg-white p-5"><x-content-heading icon="activity">Local AI category suggestion</x-content-heading><p class="my-3 text-sm text-slate-600">An optional suggestion from the on-premise model. Review it against the concern; it does not change the category or status automatically.</p>
                    @if($concern->ai_category)<div class="mb-4 rounded-lg bg-slate-50 p-4"><strong>{{ \App\Models\ResidentConcern::categories()[$concern->ai_category] }}</strong><p class="mt-1 text-xs text-slate-500">{{ $concern->ai_model }} · {{ $concern->ai_suggested_at->format('M d, Y g:i A') }} · Suggestion only; the current category is shown in concern details.</p></div>@endif
                    <form method="POST" action="{{ route('concerns.suggest', $concern) }}">@csrf<button class="secondary-button" type="submit" data-concern-ai>Suggest category</button><span class="ml-2 text-xs text-slate-500" role="status" data-concern-ai-status></span></form>
                </article>
                <form method="POST" action="{{ route('concerns.update', $concern) }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5">
                    @csrf @method('PUT')<input name="version" type="hidden" value="{{ $concern->version }}">
                    <x-content-heading icon="check">Record barangay action</x-content-heading>
                    <div><label for="status">Status</label><select id="status" name="status" required>@foreach($concern->allowedStatuses() as $key => $label)<option value="{{ $key }}" @selected(old('status', $concern->status) === $key)>{{ $label }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-600">Closed or resolved concerns may be reopened by selecting Under review.</p></div>
                    <div><label for="category">Confirmed category</label><select id="category" name="category" required>@foreach(\App\Models\ResidentConcern::categories() as $key => $label)<option value="{{ $key }}" @selected(old('category', $concern->category) === $key)>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="message">Update visible to the resident</label><textarea id="message" name="message" rows="4" minlength="5" maxlength="2000" required>{{ old('message') }}</textarea><p class="mt-2 text-xs text-slate-600">Explain the action taken, next step, or reason for closing. Do not include staff-only information here.</p></div>
                    <div><label for="internal_note">Staff-only note (optional)</label><textarea id="internal_note" name="internal_note" rows="3" maxlength="2000">{{ old('internal_note') }}</textarea></div>
                    <button type="submit">Save action &amp; update resident</button>
                </form>
            @elseif($concern->status !== 'closed')
                <form method="POST" action="{{ route('concerns.reply', $concern) }}" class="grid gap-4 rounded-xl border border-slate-200 bg-white p-5">@csrf<x-content-heading icon="document">Add information</x-content-heading><label for="message">Message to your barangay</label><textarea id="message" name="message" rows="5" minlength="5" maxlength="2000" required>{{ old('message') }}</textarea><p class="text-sm text-slate-600">You can add details or explain if the issue remains unresolved. Staff review your message; it does not automatically reopen the concern.</p><button type="submit">Send update</button></form>
            @else
                <aside class="rounded-xl border border-slate-200 bg-white p-5"><h2>This concern is closed</h2><p>Contact your barangay if it needs to be reopened, or submit a new concern for a different issue.</p><a class="button secondary-button mt-4" href="{{ route('concerns.create') }}">Submit a new concern</a></aside>
            @endif
        </div>
    </div>
</section>
@endsection
@push('scripts')
<script>
document.querySelector('[data-concern-ai]')?.form.addEventListener('submit', function () {
    this.querySelector('button').disabled = true;
    this.querySelector('[data-concern-ai-status]').textContent = 'Checking local model…';
});
</script>
@endpush
