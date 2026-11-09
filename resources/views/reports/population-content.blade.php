@php($selectedSection = $selectedSection ?? 'summary')
@if($selectedSection === 'summary')
    @include('reports.population-monitoring')
@elseif(in_array($selectedSection, ['residents', 'families', 'households', 'seniors', 'pwd'], true))
    @include('reports.population-members')
@else
    @include('reports.population-tables')
@endif
