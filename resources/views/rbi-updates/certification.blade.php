@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-workspace.css') }}?v={{ filemtime(public_path('css/rbi-workspace.css')) }}">
    <style>.rbi-forms-workspace [data-upload-preview][hidden] { display: none; }</style>
@endpush

@push('scripts')
    <script src="{{ asset('js/rbi-signatures.js') }}?v={{ filemtime(public_path('js/rbi-signatures.js')) }}" defer></script>
@endpush

@section('content')
    <section class="panel rbi-forms-workspace">
        <header class="workflow-head">
            <div>
                <span class="step-pill">{{ $editingReport->reporting_month->format('F Y') }} &middot; {{ $barangay->name }}</span>
                <h1>{{ $certificationAction === 'submit' ? 'Sign and submit report' : 'Sign and add to Consolidated RBI' }}</h1>
                <p>{{ count($editingReport->rows ?? []) }} residents &middot; {{ count($editingReport->deceased_rows ?? []) }} deceased records</p>
            </div>
            <a class="button secondary-button" href="{{ route('barangay.rbi-updates.history') }}">Back to Report history</a>
        </header>
        @if($errors->any())
            <div role="alert" class="errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Please select uploaded files again before retrying.</p></div>
        @endif
        <p><a href="{{ route('rbi-updates.show', $editingReport) }}" target="_blank" rel="noopener">Review saved report</a></p>
        <form method="POST" enctype="multipart/form-data" action="{{ route($certificationAction === 'submit' ? 'barangay.rbi-updates.submit' : 'barangay.rbi-updates.add-to-registry', $editingReport) }}">
            @csrf
            @include('rbi-updates.forms._certification', ['collectSignatures' => true])
            <p class="field-help">BHW / Encoder and Punong Barangay signatures are required. The Barangay Secretary signature is optional.</p>
            <div class="form-actions">
                <button type="submit">{{ $certificationAction === 'submit' ? 'Submit to Municipal' : 'Add to Consolidated RBI' }}</button>
            </div>
        </form>
    </section>
@endsection
