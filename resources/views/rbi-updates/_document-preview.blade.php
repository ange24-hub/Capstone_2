@push('head')
    <link rel="stylesheet" href="{{ asset('css/rbi-document.css') }}?v={{ filemtime(public_path('css/rbi-document.css')) }}">
@endpush

<p>Document preview — automatically formatted from your saved entries.</p>
<div class="rbi-document-preview" tabindex="0" role="region" aria-label="RBI document pages">
    @foreach($wordPages as $page)
        <article class="rbi-word-page" aria-label="Page {{ $loop->iteration }}">
            <header class="rbi-word-heading">
                @if($logoDataUri)<img src="{{ $logoDataUri }}" alt="Municipality seal">@endif
                <div>
                    <h2>HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)</h2>
                    <p><strong>For the month of {{ strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET') }}</strong></p>
                    <p>Barangay {{ $rbiUpdate->barangay_name }}</p>
                    @if($page['continued'])<p>{{ $page['household_head'] }} — continued</p>@endif
                </div>
            </header>

            @include('rbi-updates._household-meta')
            @include('rbi-updates._household-table', ['tableClass' => 'rbi-word-table'])

            <table class="rbi-word-table rbi-word-deceased" aria-label="B. Deceased Registered Barangay Inhabitants">
                <colgroup><col style="width:54%"><col style="width:46%"></colgroup>
                <thead><tr>@foreach($rbiDeceasedRowFields as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach(collect($page['deceased'])->pad(1, []) as $death)
                        <tr><td>{{ $death['deceased_name'] ?? '' }}</td><td>{{ !empty($death['death_date']) ? date('m/d/y', strtotime($death['death_date'])) : '' }}</td></tr>
                    @endforeach
                </tbody>
            </table>

            @if($page['show_signatures'])
                <div class="rbi-word-signatures" role="group" aria-label="Monthly Form Certification">
                    <div>
                        <p>Prepared by:</p>
                        <div class="rbi-word-signature-image">@if($rbiUpdate->prepared_signature_path)<img src="{{ route('rbi-updates.signature', [$rbiUpdate, 'secretary']) }}" alt="BHW / Encoder signature">@endif</div>
                        <strong>{{ $rbiUpdate->prepared_by }}</strong>
                        <span>BHW / Encoder</span>
                    </div>
                    <div><p>Certified Correct:</p><div class="rbi-word-signature-image">@if($rbiUpdate->certified_signature_path)<img src="{{ route('rbi-updates.signature', [$rbiUpdate, 'certified']) }}" alt="Barangay Secretary signature">@endif</div><strong>{{ $rbiUpdate->certified_by }}</strong><span>Barangay Secretary</span></div>
                    <div>
                        <p>Verified by:</p>
                        <div class="rbi-word-signature-image">@if($rbiUpdate->attested_signature_path)<img src="{{ route('rbi-updates.signature', [$rbiUpdate, 'captain']) }}" alt="Punong Barangay digital signature">@endif</div>
                        <strong>{{ $rbiUpdate->attested_by }}</strong>
                        <span>Barangay Captain / Punong Barangay</span>
                    </div>
                </div>
            @endif
            <footer class="rbi-word-page-number">Page {{ $loop->iteration }} of {{ count($wordPages) }}</footer>
        </article>
    @endforeach
</div>
