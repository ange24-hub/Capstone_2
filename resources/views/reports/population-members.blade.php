@php
    $detailGroups = $detailGroups ?? $report['detailGroups'];
    $countKey = ['residents' => 'totalRecords', 'families' => 'totalFamilies', 'households' => 'totalHouseholds', 'seniors' => 'totalSeniors', 'pwd' => 'totalPwd'][$selectedSection];
    $grouped = in_array($selectedSection, ['families', 'households'], true);
@endphp
<section class="population-report-section">
    <h2>{{ $reportTitle }} — {{ number_format($report[$countKey]) }} {{ $grouped ? strtolower($reportTitle) : 'residents' }}</h2>
    <p class="report-caption">{{ $report['scopeLabel'] }} · As of {{ $report['generatedAt']->format('d F Y') }}. Available registry records across all statuses; completeness has not been verified.</p>
    @if($selectedSection === 'families')
        <p class="report-caption">One row per family, showing the family head only. Decimal family numbers remain separate. A household with one resident and no family number is listed as a single-person family.</p>
        @php($unassigned = $report['totalRecords'] - $report['detailGroups']->sum(fn ($group) => $group['members']->count()))
        @if($unassigned > 0)<p class="report-caption">{{ number_format($unassigned) }} residents cannot yet be assigned to an identified family and are excluded from these family groups.</p>@endif
    @elseif($selectedSection === 'households')
        <p class="report-caption">One row per household, showing the household head only. Decimal household numbers share their base household. Households without an identified head remain listed.</p>
    @elseif($selectedSection === 'seniors')
        <p class="report-caption">Includes residents aged 60 or above by birth date, or marked SC / senior citizen in remarks. Each resident is listed once.</p>
    @elseif($selectedSection === 'pwd')
        <p class="report-caption">Includes residents with recognized PWD / person with disability markers in remarks.</p>
    @endif

    @if($grouped)
        <div class="table-wrap">
            <table class="population-head-table">
                <thead><tr><th>{{ $selectedSection === 'families' ? 'Family' : 'Household' }}</th><th>Barangay</th><th>{{ $selectedSection === 'families' ? 'Family head name' : 'Household head name' }}</th></tr></thead>
                <tbody>
                    @forelse($detailGroups as $group)
                        <tr><td>{{ $group['title'] }}</td><td>{{ $group['barangay'] }}</td><td>{{ $group['head_name'] }}</td></tr>
                    @empty
                        <tr><td colspan="3">No entries available for this category in the selected scope.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @else
    @forelse($detailGroups as $group)
        <section class="population-detail-group">
            @if($grouped)<h3>{{ $group['title'] }} · Barangay {{ $group['barangay'] }} <small>({{ $group['members']->count() }} {{ $group['members']->count() === 1 ? 'member' : 'members' }})</small></h3>@endif
            <div class="table-wrap">
                <table class="population-member-table">
                    <thead><tr><th>Resident name</th><th>Barangay</th><th>Family no.</th><th>Household no.</th><th>Relationship to head</th><th>Sex</th><th>Age</th></tr></thead>
                    <tbody>
                        @forelse($group['members'] as $member)
                            <tr><td>{{ $member['name'] ?: 'Name not encoded' }}</td><td>{{ $member['barangay'] }}</td><td>{{ $member['family'] !== '' ? $member['family'] : 'Not encoded' }}</td><td>{{ $member['household'] !== null && $member['household'] !== '' ? $member['household'] : 'Not encoded' }}</td><td>{{ $member['relationship'] ?: 'Not encoded' }}</td><td>{{ $member['sex'] }}</td><td class="number">{{ $member['age'] ?? 'Unknown' }}</td></tr>
                        @empty
                            <tr><td colspan="7">No resident records linked to this household.</td></tr>
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
