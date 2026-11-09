@extends('layouts.app')
@push('head')<link rel="stylesheet" href="{{ asset('css/population-submissions.css') }}?v={{ filemtime(public_path('css/population-submissions.css')) }}">@endpush
@section('content')
<section class="population-history population-signing">
    <h1>Sign and submit Population Summary</h1>
    <p>{{ $report['monitoring']['barangay'] }} · {{ $report['monitoring']['semester']===1?'1st':'2nd' }} Semester {{ $report['monitoring']['year'] }} · {{ number_format($report['totalRecords']) }} inhabitants</p>
    <p>Upload a signature image or draw using your mouse, finger or stylus. The signatures will appear over the printed names on the saved report and PDF.</p>
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach<p>Please select or draw the signatures again.</p></div>@endif
    <form method="POST" action="{{ route('reports.population.submissions.store') }}" enctype="multipart/form-data">@csrf
        <input type="hidden" name="preview_token" value="{{ $previewToken }}">
        <div class="population-signature-fields">
        @foreach(['prepared'=>['Prepared by — Barangay Secretary','preparedBy'], 'submitted'=>['Submitted by — Punong Barangay','submittedBy']] as $key=>[$role,$nameKey])
            <fieldset data-signature-field><legend>{{ $role }}</legend>
                <label for="{{ $key }}-name">Printed name</label>
                <input id="{{ $key }}-name" name="{{ $key }}_name" value="{{ old($key.'_name', $report['monitoring'][$nameKey] ?: ($key==='prepared' ? auth()->user()->name : '')) }}" maxlength="255" required>
                <label for="{{ $key }}-upload">Upload PNG or JPG (up to 2 MB)</label>
                <input id="{{ $key }}-upload" type="file" name="{{ $key }}_signature_upload" accept="image/png,image/jpeg" data-signature-upload>
                <img data-upload-preview hidden alt="Selected {{ $role }} signature">
                <p>Or draw your signature below:</p>
                <canvas aria-label="Draw {{ $role }} signature"></canvas>
                <input type="hidden" name="{{ $key }}_signature_data" value="">
                <button type="button" class="button secondary-button" data-clear-signature>Clear signature</button>
            </fieldset>
        @endforeach
        </div>
        <p>Confirming sends this signed copy to Municipal DILG/Admin and saves it in Population Report History.</p>
        <button type="submit" class="button">Confirm signatures and submit</button>
        <a class="button secondary-button" href="{{ route('reports.population') }}">Back to summary</a>
    </form>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/rbi-signatures.js') }}?v={{ filemtime(public_path('js/rbi-signatures.js')) }}" defer></script>@endpush
