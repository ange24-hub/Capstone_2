@php
    $form = $report['monitoring'];
    $historical = $report['historical'] ?? false;
    $number = static fn ($value) => $value === null ? '—' : number_format($value);
@endphp
<div class="population-monitoring-scroll">
<article class="population-monitoring-paper" aria-label="RBI Form C monitoring report">
    <p class="monitoring-form-code">{{ $historical ? 'RBI FORM C · HISTORICAL TRANSCRIPTION' : 'RBI FORM C (Revised 2024)' }}</p>
    <header class="monitoring-title"><h2>MONITORING REPORT</h2><p>for <strong>{{ $form['semester'] === 1 ? '1st' : '2nd' }} Semester of CY {{ $form['year'] }}</strong></p></header>
    <div class="monitoring-meta">
        <div>REGION: <strong>VIII</strong></div>
        <div>PROVINCE: <strong>SOUTHERN LEYTE</strong></div>
        <div>CITY/MUNICIPALITY: <strong>TOMAS OPPUS</strong></div>
        <div>BARANGAY: <strong>{{ strtoupper($form['barangay']) }}</strong></div>
        <div>Total No. of Barangay Inhabitants: <strong>{{ number_format($report['totalRecords']) }}</strong></div>
        <div>Total No. of Households: <strong>{{ number_format($report['totalHouseholds']) }}</strong></div>
        <div>Total No. of Families: <strong>{{ $historical || $report['totalFamilies'] ? number_format($report['totalFamilies']) : ($report['totalRecords'] ? 'Not yet identified' : 'Not yet encoded') }}</strong></div>
    </div>
    <table class="monitoring-grid">
        <colgroup><col style="width:54%"><col style="width:9%"><col style="width:9%"><col style="width:9%"><col style="width:19%"></colgroup>
        <thead><tr><th style="width:54%">INDICATORS</th><th style="width:9%">MALE</th><th style="width:9%">FEMALE</th><th style="width:9%">TOTAL</th><th style="width:19%">REMARKS</th></tr></thead>
        <tbody>
            <tr class="monitoring-section"><th colspan="5">Population by Age Bracket:</th></tr>
            @foreach($form['ages'] as $row)
                <tr class="monitoring-age"><td>{{ $row['label'] }}</td><td>{{ $number($row['male']) }}</td><td>{{ $number($row['female']) }}</td><td>{{ $number($row['total']) }}</td><td class="monitoring-remarks">{{ $row['remarks'] ?? ($row['unspecified'] ? 'Sex unspecified: '.$row['unspecified'] : '') }}</td></tr>
            @endforeach
            <tr class="monitoring-total"><td>{{ $historical ? 'Sum of age brackets as recorded' : 'Total registered residents' }}</td><td>{{ $number($report['sex']['Male']) }}</td><td>{{ $number($report['sex']['Female']) }}</td><td>{{ number_format($historical ? $report['ageTotalSum'] : $report['totalRecords']) }}</td><td class="monitoring-remarks">{{ $historical && $report['ageTotalSum']!==$report['totalRecords'] ? 'Differs from reported population' : ($report['sex']['Not specified / other'] ? 'Sex unspecified: '.$report['sex']['Not specified / other'] : '') }}</td></tr>
            <tr class="monitoring-section"><th colspan="5">Population by Sector:</th></tr>
            @foreach($form['sectors'] as $row)
                <tr><td>{{ $row['label'] }}</td><td>{{ $row['available'] ? $number($row['male']) : '—' }}</td><td>{{ $row['available'] ? $number($row['female']) : '—' }}</td><td>{{ $row['available'] ? $number($row['total']) : '—' }}</td><td class="monitoring-remarks">{{ $row['remarks'] }}{{ $row['unspecified'] ? '; sex unspecified: '.$row['unspecified'] : '' }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table class="monitoring-signatures"><tr>
        <td><p>Prepared by:</p><div class="monitoring-signature-space {{ !empty($report['signatures']['prepared']) ? 'monitoring-signed-space' : '' }}">@if(!empty($report['signatures']['prepared']['image']))<img src="{{ $report['signatures']['prepared']['image'] }}" alt="Barangay Secretary signature">@endif</div><strong class="monitoring-signatory">{{ $form['preparedBy'] ?: ' ' }}</strong><div>{{ $form['isBarangay'] ? 'Barangay Secretary' : 'Authorized Municipal Preparer' }}</div><em>(signature over printed name)</em></td>
        <td><p>Submitted by:</p><div class="monitoring-signature-space {{ !empty($report['signatures']['submitted']) ? 'monitoring-signed-space' : '' }}">@if(!empty($report['signatures']['submitted']['image']))<img src="{{ $report['signatures']['submitted']['image'] }}" alt="Punong Barangay signature">@endif</div><strong class="monitoring-signatory">{{ $form['submittedBy'] ?: ' ' }}</strong><div>{{ $form['isBarangay'] ? 'Punong Barangay' : 'Authorized Municipal Official' }}</div><em>(signature over printed name)</em></td>
    </tr></table>
    <p class="monitoring-date">Date Accomplished: <strong>{{ $historical ? 'Not recorded on original' : $report['generatedAt']->format('F d, Y') }}</strong></p>
    <div class="monitoring-footnote">
        <p>Note: This RBI Form C Semestral Monitoring Report is to be submitted to DILG C/MLGOO as a reference for encoding to BIS-BPS.</p>
        @if($historical)
            <p>Digitized from the preserved original report. Original counts and period are unchanged; blank or untranscribed cells remain “—”, not zero. Original signatures remain in the retained source.</p>
            @if($report['ageTotalSum']!==$report['totalRecords'])<p>Needs reconciliation: reported population {{ $report['totalRecords'] }}; age-bracket sum {{ $report['ageTotalSum'] }}. Neither figure has been corrected.</p>@endif
        @else
            @if($report['totalRecords'] === 0)<p>Not yet encoded: no resident records are available in this scope; this does not establish zero population.</p>@endif
            <p>Source: active registered residents as of {{ $report['generatedAt']->format('d M Y') }}, matching Consolidated. Semester/year is a report label; historical population is not reconstructed. Ages use birth dates; sectors use explicit tags. “—” means not recorded. Completeness unverified.</p>
        @endif
    </div>
</article>
</div>
