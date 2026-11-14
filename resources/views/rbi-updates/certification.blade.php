@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-workspace.css') }}?v={{ filemtime(public_path('css/rbi-workspace.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/rbi-certification.css') }}?v={{ filemtime(public_path('css/rbi-certification.css')) }}">
@endpush

@push('scripts')
    <script src="{{ asset('js/rbi-signatures.js') }}?v={{ filemtime(public_path('js/rbi-signatures.js')) }}" defer></script>
@endpush

@section('content')
    <section class="rbi-forms-workspace rbi-certification" aria-labelledby="rbi-certification-title">
        <header class="rbi-certification-header">
            <div>
                <span class="step-pill">{{ $editingReport->reporting_month->format('F Y') }} &middot; {{ $barangay->name }}</span>
                <h1 id="rbi-certification-title">{{ $certificationAction === 'submit' ? 'Sign and submit report' : 'Sign and add to Consolidated RBI' }}</h1>
                <p>{{ count($editingReport->rows ?? []) }} residents &middot; {{ count($editingReport->deceased_rows ?? []) }} deceased records</p>
            </div>
            <div class="rbi-certification-links">
                <a class="button secondary-button" href="{{ route('barangay.rbi-updates.history') }}">Report history</a>
                <a class="button secondary-button" href="{{ route('rbi-updates.show', $editingReport) }}" target="_blank" rel="noopener">Review saved report</a>
            </div>
        </header>
        @if($errors->any())
            <div role="alert" class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Please select uploaded files again before retrying.</p></div>
        @endif
        @if($certificationAction === 'consolidate' && $existingResidents !== [])
            <aside class="rbi-existing-residents" role="status" aria-live="polite">
                <h2>Existing residents in Consolidated RBI</h2>
                <p>These people already have registry records. Check their household numbers before adding this report.</p>
                <ul>
                    @foreach($existingResidents as $resident)
                        <li><strong>{{ $resident['name'] }}</strong><span>{{ collect($resident['households'])->map(fn ($number) => 'Household '.$number)->join(', ') }}</span></li>
                    @endforeach
                </ul>
            </aside>
        @endif
        <form class="rbi-certification-form" method="POST" enctype="multipart/form-data" action="{{ route($certificationAction === 'submit' ? 'barangay.rbi-updates.submit' : 'barangay.rbi-updates.add-to-registry', $editingReport) }}">
            @csrf
            @if($certificationAction === 'submit' && empty($editingReport->rows) && empty($editingReport->deceased_rows))<label class="rbi-no-activity"><input type="checkbox" name="confirm_no_activity" value="1" required @checked(old('confirm_no_activity'))> I confirm that no new residents or deceased inhabitants were recorded for this reporting month.</label>@endif
            @include('rbi-updates.forms._certification', ['collectSignatures' => true])
            <div class="rbi-certification-actions">
                <p>Prepared by and Punong Barangay signatures are required. The Barangay Secretary signature is optional.</p>
                <button type="submit">{{ $certificationAction === 'submit' ? 'Submit to Municipal' : 'Add to Consolidated RBI' }}</button>
            </div>
        </form>
    </section>
@endsection
