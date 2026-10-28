@include('reports._document-header', [
    'documentTitle' => 'RECORD OF BARANGAY INHABITANTS BY HOUSEHOLD',
    'documentBarangay' => $rbiUpdate->barangay_name,
    'documentHousehold' => $page['household_number'] ?? '',
    'documentDate' => $rbiUpdate->accomplishedDate()->format('m/d/Y'),
])
<p class="report-document-context">Household head: {{ $page['household_head'] }} &middot; For the month of {{ strtoupper(optional($rbiUpdate->reporting_month)->format('F Y') ?: 'NOT SET') }}
    @if($page['continued'])
        &middot; CONTINUED
    @endif
</p>
