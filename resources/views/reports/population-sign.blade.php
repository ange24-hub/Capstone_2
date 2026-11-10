@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">@endpush
@section('content')
<section class="population-history population-signing" aria-labelledby="population-signing-title">
    <header class="population-signing-header">
        <div class="population-signing-title">
            <span class="population-signing-icon"><x-app-icon name="edit" /></span>
            <div><span class="population-signing-eyebrow">Population Summary</span><h1 id="population-signing-title">Review signatures &amp; submit</h1></div>
        </div>
        <a class="population-signing-back" href="{{ route('reports.population') }}">&larr; Back to summary</a>
    </header>
    <div class="population-signing-meta" aria-label="Report details">
        <span><x-app-icon name="location" />{{ $report['monitoring']['barangay'] }}</span>
        <span><x-app-icon name="calendar" />{{ $report['monitoring']['semester']===1?'1st':'2nd' }} Semester {{ $report['monitoring']['year'] }}</span>
        <span><x-app-icon name="users" />{{ number_format($report['totalRecords']) }} inhabitants</span>
    </div>
    <p class="population-signing-intro">Confirm the two signatories below. Their signatures appear on your saved report and PDF.</p>
    @if($errors->any())
        <div class="population-signing-errors" role="alert"><strong>Please review your signatures.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p>Saved signatures are still available. Please reselect any new upload or draw it again.</p></div>
    @endif
    <form method="POST" action="{{ route('reports.population.submissions.store') }}" enctype="multipart/form-data" data-population-signing-form>
        @csrf
        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
        <input type="hidden" name="signature_source_id" value="{{ $savedSignatures['prepared']['source_id'] ?? $savedSignatures['submitted']['source_id'] ?? '' }}">
        <div class="population-signature-fields">
        @foreach(['prepared'=>['Prepared by','Barangay Secretary','preparedBy'], 'submitted'=>['Submitted by','Punong Barangay','submittedBy']] as $key=>[$label,$role,$nameKey])
            @php
                $saved = $savedSignatures[$key] ?? null;
                $reuse = $saved && (string) old($key.'_signature_reuse', '1') === '1';
                $defaultName = $saved['name'] ?? ($report['monitoring'][$nameKey] ?: ($key==='prepared' ? auth()->user()->name : ''));
            @endphp
            <fieldset class="population-signatory" data-population-signature data-initial-mode="{{ $reuse ? 'saved' : 'draw' }}">
                <legend><span class="population-signatory-number">{{ $loop->iteration===1 ? '01' : '02' }}</span>{{ $label }}</legend>
                <div class="population-signatory-heading"><h2>{{ $role }}</h2>@if($saved)<span class="population-signature-badge"><x-app-icon name="check" />Saved</span>@endif</div>
                <label for="{{ $key }}-name">Printed name <span aria-hidden="true">*</span></label>
                <input id="{{ $key }}-name" name="{{ $key }}_name" value="{{ old($key.'_name', $defaultName) }}" maxlength="255" required autocomplete="off">
                <input type="hidden" name="{{ $key }}_signature_reuse" value="{{ $reuse ? '1' : '0' }}" data-signature-reuse>
                <input type="hidden" name="{{ $key }}_signature_data" value="" data-signature-data>
                <div class="population-signature-methods" role="group" aria-label="{{ $role }} signature method">
                    @if($saved)<button type="button" data-signature-mode="saved" aria-pressed="{{ $reuse ? 'true' : 'false' }}" aria-controls="{{ $key }}-saved"><x-app-icon name="check" />Saved</button>@endif
                    <button type="button" data-signature-mode="draw" aria-pressed="{{ $reuse ? 'false' : 'true' }}" aria-controls="{{ $key }}-draw"><x-app-icon name="edit" />Draw</button>
                    <button type="button" data-signature-mode="upload" aria-pressed="false" aria-controls="{{ $key }}-upload-panel"><x-app-icon name="document" />Upload</button>
                </div>
                @if($saved)
                    <div id="{{ $key }}-saved" class="population-signature-panel population-saved-signature" data-signature-panel="saved" @if(!$reuse) hidden @endif>
                        <div class="population-signature-paper"><img src="{{ $saved['image'] }}" alt="Saved {{ $role }} signature"></div>
                        <p><x-app-icon name="check" />Ready to use. Choose Draw or Upload image to replace it.</p>
                    </div>
                @endif
                <div id="{{ $key }}-draw" class="population-signature-panel" data-signature-panel="draw" @if($reuse) hidden @endif>
                    <div class="population-signature-paper population-drawing-paper"><canvas aria-label="Draw {{ $role }} signature"></canvas><span class="population-signature-guide" aria-hidden="true">Sign here</span></div>
                    <div class="population-signature-panel-footer"><span>Use your mouse, finger, or stylus.</span><button type="button" data-clear-signature>Clear</button></div>
                </div>
                <div id="{{ $key }}-upload-panel" class="population-signature-panel" data-signature-panel="upload" hidden>
                    <label class="population-signature-upload" for="{{ $key }}-upload"><x-app-icon name="document" /><strong>Choose a signature image</strong><span>PNG or JPG · up to 2 MB</span></label>
                    <input id="{{ $key }}-upload" type="file" name="{{ $key }}_signature_upload" accept="image/png,image/jpeg" data-signature-upload disabled aria-describedby="{{ $key }}-upload-status">
                    <p id="{{ $key }}-upload-status" data-upload-status aria-live="polite">No image selected.</p>
                    <img data-upload-preview hidden alt="Selected {{ $role }} signature">
                </div>
                <p class="population-signature-error" data-signature-error role="alert" hidden></p>
            </fieldset>
        @endforeach
        </div>
        <footer class="population-signing-footer">
            <div class="population-signing-remember"><span><x-app-icon name="shield" /></span><p><strong>{{ count($savedSignatures ?? [])===2 ? 'Your signatures are ready for next time.' : 'Sign once. Reuse next time.' }}</strong><small>Names and signatures are remembered after a successful submission. You can update them anytime.</small></p></div>
            <button type="submit" class="button population-signing-submit"><x-app-icon name="check" />Confirm signatures and submit</button>
        </footer>
        <p class="population-signing-destination">Sends a signed copy to Municipal DILG/Admin and saves it in Submission History.</p>
    </form>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/population-signatures.js') }}?v={{ filemtime(public_path('js/population-signatures.js')) }}" defer></script>@endpush
