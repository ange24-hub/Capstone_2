@php
    $documentFields = [
        ['A. REGION:', 'VIII'],
        ['B. PROVINCE:', 'SOUTHERN LEYTE'],
        ['C. CITY/MUNICIPALITY:', 'TOMAS OPPUS'],
        ['D. BARANGAY:', $documentBarangay ?? 'All barangays'],
        ['E. HOUSEHOLD NO.:', $documentHousehold ?? ''],
    ];
@endphp
<header class="report-document-header {{ ($documentPrintOnly ?? false) ? 'report-document-header--print' : '' }}">
    <h1 class="report-document-title">{{ $documentTitle }}</h1>
    <div class="report-document-fields" aria-label="Report location and household">
        @foreach($documentFields as $index => [$label, $value])
            <p class="report-document-field">
                @if($index === 4)
                    <span class="report-document-date">DATE ACCOMPLISHED: {{ $documentDate ?? '' }}</span>
                @endif
                <span class="report-document-label">{{ $label }}</span>
                <span class="report-document-value">{{ $value }}</span>
            </p>
        @endforeach
    </div>
</header>
