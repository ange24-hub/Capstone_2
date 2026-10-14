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
    <table class="report-document-fields" aria-label="Report location and household">
        <colgroup><col style="width:18%"><col style="width:16%"><col style="width:8%"><col style="width:3%"><col style="width:19%"><col style="width:18%"><col style="width:18%"></colgroup>
        <tbody>
        @foreach($documentFields as $index => [$label, $value])
            <tr aria-label="{{ $label }} {{ $value }}">
                <td class="report-document-label" style="width:18%">{{ $label }}</td>
                <td class="report-document-value" style="width:16%">{{ $value }}</td>
                @if($index < 4)
                    <td class="report-document-code" style="width:8%">{{ $documentCodes[$index] ?? '' }}</td>
                    @if($index === 0)
                        <td class="report-document-code-legend" style="width:3%" rowspan="4">PSGC<br>C<br>O<br>D<br>E<br>S</td>
                        <td class="report-document-gap" style="width:19%"></td><td class="report-document-gap" style="width:18%"></td><td class="report-document-gap" style="width:18%"></td>
                    @else
                        <td class="report-document-gap" style="width:55%" colspan="3"></td>
                    @endif
                @else
                    <td class="report-document-gap" style="width:30%" colspan="3"></td>
                    <td class="report-document-date-label" style="width:18%">DATE ACCOMPLISHED:</td>
                    <td class="report-document-date-value" style="width:18%">{{ $documentDate ?? '' }}</td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</header>
