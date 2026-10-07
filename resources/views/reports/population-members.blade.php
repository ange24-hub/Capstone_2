@php
    $detailGroups = $detailGroups ?? $report['detailGroups'];
    $countKey = ['residents' => 'totalRecords', 'families' => 'totalFamilies', 'households' => 'totalHouseholds', 'seniors' => 'totalSeniors', 'pwd' => 'totalPwd'][$selectedSection];
    $grouped = in_array($selectedSection, ['families', 'households'], true);
@endphp
<section class="population-report-section">
    <h2>{{ $reportTitle }} — {{ number_format($report[$countKey]) }} {{ $grouped ? strtolower($reportTitle) : 'residents' }}</h2>
    <p class="report-caption">{{ $report['scopeLabel'] }} · As of {{ $report['generatedAt']->format('d F Y') }}.</p>
    @if($showReportNotes ?? true)
    <p class="report-caption report-note">Available registry records across all statuses; completeness has not been verified.</p>
    @if($selectedSection === 'families')
        <p class="report-caption report-note">Residents are listed under their identified family. Decimal family numbers remain separate. A household with one resident and no family number is treated as a single-person family.</p>
        @php($unassigned = $report['totalRecords'] - $report['detailGroups']->sum(fn ($group) => $group['members']->count()))
        @if($unassigned > 0)<p class="report-caption report-note">{{ number_format($unassigned) }} residents cannot yet be assigned to an identified family and are excluded from these family groups.</p>@endif
    @elseif($selectedSection === 'households')
        <p class="report-caption report-note">Residents are listed under their household number. Decimal household numbers share their base household. Households without an encoded resident remain listed.</p>
    @elseif($selectedSection === 'seniors')
        <p class="report-caption report-note">Includes residents aged 60 or above by birth date, or marked SC / senior citizen in remarks. Each resident is listed once.</p>
    @elseif($selectedSection === 'pwd')
        <p class="report-caption report-note">Includes residents with recognized PWD / person with disability markers in remarks.</p>
    @endif
    @endif

    @if($grouped)
        <div class="table-wrap">
            <table class="population-member-table population-member-table-grouped">
                <thead><tr><th>Name</th><th>Age</th><th>Household Number</th><th>Birthdate</th></tr></thead>
                <tbody>
                    @php($lastBarangay = null)
                    @forelse($detailGroups as $group)
                        @if($report['totalBarangays'] > 1 && $lastBarangay !== $group['barangay'])
                            <tr class="population-barangay-row"><th colspan="4">Barangay {{ $group['barangay'] }}</th></tr>
                            @php($lastBarangay = $group['barangay'])
                        @endif
                        <tr class="population-subgroup-row"><th colspan="4">{{ $group['title'] }} · {{ $group['members']->count() }} {{ $group['members']->count() === 1 ? 'member' : 'members' }}</th></tr>
                        @forelse($group['members'] as $member)
                            <tr>
                                <td>{{ $member['name'] ?: 'Name not encoded' }}</td>
                                <td>{{ $member['age'] ?? 'Unknown' }}</td>
                                <td>{{ $member['household'] !== null && $member['household'] !== '' ? $member['household'] : 'Not encoded' }}</td>
                                <td>{{ $member['birth_date'] ?? 'Not encoded' }}</td>
                            </tr>
                        @empty
                            <tr><td>Name not encoded</td><td>Unknown</td><td>{{ $group['household_number'] ?? 'Not encoded' }}</td><td>Not encoded</td></tr>
                        @endforelse
                    @empty
                        <tr><td colspan="4">No entries available for this category in the selected scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
    @forelse($detailGroups as $group)
        <section class="population-detail-group">
            @if($report['totalBarangays'] > 1)<h3 class="population-barangay-heading">Barangay {{ $group['barangay'] }}</h3>@endif
            <div class="table-wrap">
                <table class="population-member-table">
                    <thead><tr><th>Name</th><th>Age</th><th>Birthdate</th></tr></thead>
                    <tbody>
                        @forelse($group['members'] as $member)
                            <tr><td>{{ $member['name'] ?: 'Name not encoded' }}</td><td class="number">{{ $member['age'] ?? 'Unknown' }}</td><td>{{ $member['birth_date'] ?? 'Not encoded' }}</td></tr>
                        @empty
                            <tr><td colspan="3">No residents available for this category.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <p class="dashboard-empty-state">No entries available for this category in the selected scope.</p>
    @endforelse
    @endif
</section>
