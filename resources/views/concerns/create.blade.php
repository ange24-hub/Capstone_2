@extends('layouts.app')
@section('content')
<section class="mx-auto grid min-w-0 max-w-4xl grid-cols-1 gap-6">
    <header class="dashboard-page-header"><span class="dashboard-eyebrow">Barangay {{ auth()->user()->barangay->name }}</span><h1>Submit a concern</h1><p>Describe the issue clearly so your barangay can review and respond.</p></header>
    @include('concerns._feedback')
    <aside class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">This inbox is not monitored for emergencies. For immediate danger or urgent assistance, contact the appropriate emergency service directly.</aside>
    <form method="POST" action="{{ route('concerns.store') }}" enctype="multipart/form-data" class="grid gap-5 rounded-xl border border-slate-200 bg-white p-5 sm:p-7">
        @csrf
        <div><label for="title">Concern title <span aria-hidden="true">*</span></label><input id="title" name="title" maxlength="160" value="{{ old('title') }}" placeholder="Example: Blocked drainage near the barangay hall" required></div>
        <div class="grid gap-5 md:grid-cols-2">
            <div><label for="category">Category <span aria-hidden="true">*</span></label><select id="category" name="category" required><option value="">Choose a category</option>@foreach(\App\Models\ResidentConcern::categories() as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div><label for="location">Location or landmark (optional)</label><input id="location" name="location" maxlength="255" value="{{ old('location') }}"></div>
        </div>
        <div><label for="description">What happened? <span aria-hidden="true">*</span></label><textarea id="description" name="description" rows="6" minlength="20" maxlength="4000" aria-describedby="description-help" required>{{ old('description') }}</textarea><p id="description-help" class="mt-2 text-sm text-slate-600">Explain the issue, when it happened, and the assistance needed. English or Cebuano is welcome. Avoid unnecessary personal details about other people. 20–4,000 characters.</p></div>
        <div><label for="attachment">Supporting photo or PDF (optional)</label><input id="attachment" name="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="attachment-help"><p id="attachment-help" class="mt-2 text-sm text-slate-600">One PDF, JPG, or PNG, up to 5 MB. Only you and authorized staff in your barangay can download it. If the form needs correction, select your file again.</p></div>
        <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Your barangay receives this concern under your account. Staff may use the on-premise AI to suggest a category; the AI does not decide the action or resolution. Your email address will not be used to send notifications for this feature yet.</p>
        <div class="flex flex-wrap gap-3 border-t border-slate-200 pt-5"><button type="submit">Submit concern</button><a class="button secondary-button" href="{{ route('concerns.index') }}">Cancel</a></div>
    </form>
</section>
@endsection
