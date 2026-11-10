@php
    $periodYear = $reportFilters['report_year'] ?? $submission->report_year ?? now()->year;
    $periodSemester = $reportFilters['semester'] ?? $submission->semester ?? (now()->month<=6?1:2);
    $periodArea = $reportFilters['barangay_id'] ?? $submission->barangay_id ?? $areaId ?? null;
@endphp
<form class="population-controls population-builder-controls" method="GET" action="{{ route('reports.population') }}" aria-label="Choose population report period" data-population-report-controls>
    <input type="hidden" name="section" value="summary">
    @if(auth()->user()->hasRole(\App\Models\User::ROLE_MUNICIPAL_LGU))
        <div class="population-control population-control-wide"><label for="report-barangay">Barangay</label><select id="report-barangay" name="barangay_id"><option value="">Choose a barangay</option>@foreach($barangays ?? collect() as $area)<option value="{{ $area->id }}" @selected((int)$periodArea===$area->id)>{{ $area->name }}</option>@endforeach</select></div>
    @else
        <input type="hidden" name="barangay_id" value="{{ $periodArea }}">
    @endif
    <div class="population-control population-control-year"><label for="report-year">Year</label><select id="report-year" name="report_year" required>@foreach(range(\App\Services\PopulationReportPeriod::maximumYear(),1900) as $year)<option value="{{ $year }}" @selected((int)$periodYear===$year)>{{ $year }}</option>@endforeach</select></div>
    <div class="population-control"><label for="report-semester">Semester</label><select id="report-semester" name="semester"><option value="1" @selected((int)$periodSemester===1)>1st Semester · Jan–Jun</option><option value="2" @selected((int)$periodSemester===2)>2nd Semester · Jul–Dec</option></select></div>
    <button class="button" type="submit"><x-app-icon name="document" /><span data-report-generate-label>Generate report</span></button>
    <p class="population-builder-control-hint" data-report-control-status role="status">Through 1st Semester 2026: saved reports. Newer periods: latest Consolidated data.</p>
</form>
