@extends('layouts.app')

@section('content')
    @php($isBiasong = $registryBarangay?->name === 'Biasong')
    @php($usesFamilyNewInhabitants = in_array($registryBarangay?->name, ['Biasong', 'Cabascan'], true))
    @php($isDedicatedRegistryPage = request()->routeIs('barangay.registry.*'))
    @php($registryFilterRoute = request()->routeIs('barangay.registry.new-inhabitants') ? 'barangay.registry.new-inhabitants' : (request()->routeIs('barangay.registry.deceased') ? 'barangay.registry.deceased' : (request()->routeIs('barangay.registry.active') ? 'barangay.registry.active' : 'registry.index')))
    <section class="panel stack registry-workspace {{ $isDedicatedRegistryPage ? 'registry-workspace-dedicated' : '' }}">
        <div class="page-kicker">{{ request()->routeIs('barangay.registry.new-inhabitants') ? 'Monthly Reporting' : (request()->routeIs('barangay.registry.deceased') ? 'Historical Records' : (request()->routeIs('barangay.registry.active') ? 'Community Records' : (request('source') ?: 'Central Registry'))) }}</div>
        <div class="page-head">
            <div>
                <h1>{{ request()->routeIs('barangay.registry.new-inhabitants') ? 'New Inhabitants' : (request()->routeIs('barangay.registry.deceased') ? 'Deceased Records' : (request()->routeIs('barangay.registry.active') ? 'Resident Registry' : ($registryBarangay ? 'Barangay '.$registryBarangay->name.' RBI Data' : 'Multi-Barangay Registry'))) }}</h1>
                <p>
                    @if (request()->routeIs('barangay.registry.new-inhabitants'))
                        Create and manage monthly family reports for Barangay {{ $registryBarangay->name }}.
                    @elseif (request()->routeIs('barangay.registry.deceased'))
                        Maintain the official deceased resident records for Barangay {{ $registryBarangay->name }}.
                    @elseif (request()->routeIs('barangay.registry.active'))
                        View and update active household and resident records for Barangay {{ $registryBarangay->name }}.
                    @elseif (request('source'))
                        Editable records imported from {{ request('source') }}. The information is separated per person and household.
                    @else
                        {{ auth()->user()->name }} is signed in as {{ auth()->user()->roleLabel() }}.
                    @endif
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="success">{{ session('status') }}</div>
        @endif

        @unless(request('source'))
        <div class="workflow-card">
            <h2 class="section-title">Create Inhabitant or Migrant Record</h2>
            @include('registry._form')
        </div>
        @endunless

        <div class="workflow-card">
            <div class="workflow-head">
                <div>
                    <h2 class="section-title">{{ $isDedicatedRegistryPage ? match(request('sheet')) { 'deceased' => 'Deceased resident records', 'new-inhabitants' => 'Monthly new inhabitant report', default => 'Active household records' } : (request('source') ? request('source').' — '.match(request('sheet')) { 'deceased' => 'Deceased', 'new-inhabitants' => 'New Inhabitants', default => 'Active Household' } : 'Registry Records') }}</h2>
                    <p>@if ($usesFamilyNewInhabitants && request('sheet') === 'new-inhabitants') Add each family, then click <strong>Save Monthly Report</strong> to consolidate them for the selected month. @else {{ match(request('sheet')) { 'deceased' => $deceasedRecords->count(), 'new-inhabitants' => $newInhabitantRecords->count(), default => number_format($inhabitants->total()) } }} records found. Edit the spreadsheet cells, then click <strong>Save row</strong>. @endif</p>
                    @if (request('source') && ! $isDedicatedRegistryPage)
                        <div class="rbi-sheet-tabs">
                            <a class="button {{ request('sheet') !== 'deceased' ? '' : 'secondary-button' }}" href="{{ route('registry.index', ['source' => request('source')]) }}">Active Household</a>
                            <a class="button {{ request('sheet') === 'deceased' ? '' : 'secondary-button' }}" href="{{ route('registry.index', ['source' => request('source'), 'sheet' => 'deceased']) }}">Deceased</a>
                            <a class="button {{ request('sheet') === 'new-inhabitants' ? '' : 'secondary-button' }}" href="{{ route('registry.index', ['source' => request('source'), 'sheet' => 'new-inhabitants']) }}">New Inhabitants</a>
                        </div>
                    @endif
                </div>
                <form class="toolbar compact-toolbar" method="GET" action="{{ route($registryFilterRoute) }}">
                    @if (request('source') && ! $isDedicatedRegistryPage)<input type="hidden" name="source" value="{{ request('source') }}">@endif
                    @if (request('sheet') && ! $isDedicatedRegistryPage)<input type="hidden" name="sheet" value="{{ request('sheet') }}">@endif
                    @unless($isDedicatedRegistryPage)<select name="barangay_id" aria-label="Filter by barangay">
                        <option value="">All barangays</option>
                        @foreach ($barangays as $barangay)
                            <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                        @endforeach
                    </select>@endunless
                    <input name="search" type="search" value="{{ request('search') }}" placeholder="Search name or household" aria-label="Search registry">
                    <button type="submit" class="secondary-button">Filter</button>
                </form>
            </div>

            @if ((request('sheet') === 'deceased' && $deceasedRecords->isEmpty()) || (request('sheet') === 'new-inhabitants' && !$usesFamilyNewInhabitants && $newInhabitantRecords->isEmpty()) || (!in_array(request('sheet'), ['deceased', 'new-inhabitants'], true) && $inhabitants->isEmpty()))
                <p>No records found.</p>
            @else
                <div class="table-wrap {{ request('source') ? 'rbi-source-table' : '' }}">
                    <table>
                        @if (request('source'))
                        @if (request('sheet') === 'deceased')
                        @if ($isBiasong)
                            @include('registry._biasong_deceased')
                        @else
                        <thead>
                            <tr class="rbi-deceased-title"><th>HH<br>NO.</th><th colspan="15">NAME OF DECEASED PERSONS</th><th>DATE OF DEATH</th><th>ACTION</th></tr>
                            <tr>
                                <th>HH No.</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Qualifier</th><th>Relationship</th><th>Purok / Sitio</th><th>Place of Birth</th><th>Date of Birth</th><th>Age</th><th>Sex</th><th>Civil Status</th><th>School Level</th><th>Religion</th><th>Occupation</th><th>Remarks</th><th>Date of Death</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody class="rbi-deceased-body">
                            @foreach ($deceasedRecords as $record)
                                @php($rowForm = 'deceased-row-'.$record->id)
                                <tr>
                                    <td><input form="{{ $rowForm }}" name="household_number" value="{{ $record->household_number }}" required aria-label="Household number"></td>
                                    <td><input form="{{ $rowForm }}" name="last_name" value="{{ $record->last_name }}" required aria-label="Last name"></td>
                                    <td><input form="{{ $rowForm }}" name="first_name" value="{{ $record->first_name }}" required aria-label="First name"></td>
                                    <td><input form="{{ $rowForm }}" name="middle_name" value="{{ $record->middle_name }}" aria-label="Middle name"></td>
                                    <td><input form="{{ $rowForm }}" name="suffix" value="{{ $record->suffix }}" aria-label="Qualifier"></td>
                                    <td><input form="{{ $rowForm }}" name="relationship_to_head" value="{{ $record->relationship_to_head }}" aria-label="Relationship"></td>
                                    <td><input form="{{ $rowForm }}" name="purok" value="{{ $record->purok }}" aria-label="Purok"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_place" value="{{ $record->birth_place }}" aria-label="Place of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_date" type="date" value="{{ optional($record->birth_date)->format('Y-m-d') }}" aria-label="Date of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="recorded_age" type="number" min="0" max="150" value="{{ $record->recorded_age }}" aria-label="Age"></td>
                                    <td><select form="{{ $rowForm }}" name="sex" aria-label="Sex"><option value="Male" @selected($record->sex === 'Male')>M</option><option value="Female" @selected($record->sex === 'Female')>F</option></select></td>
                                    <td><input form="{{ $rowForm }}" name="civil_status" value="{{ $record->civil_status }}" aria-label="Civil status"></td>
                                    <td><input form="{{ $rowForm }}" name="education_level" value="{{ $record->education_level }}" aria-label="Education"></td>
                                    <td><input form="{{ $rowForm }}" name="religion" value="{{ $record->religion }}" aria-label="Religion"></td>
                                    <td><input form="{{ $rowForm }}" name="occupation" value="{{ $record->occupation }}" aria-label="Occupation"></td>
                                    <td><input form="{{ $rowForm }}" name="remarks" value="{{ $record->remarks }}" aria-label="Remarks"></td>
                                    <td><input form="{{ $rowForm }}" name="death_date" type="date" value="{{ optional($record->death_date)->format('Y-m-d') }}" aria-label="Date of death"></td>
                                    <td class="rbi-row-save"><form id="{{ $rowForm }}" method="POST" action="{{ route('registry.deceased.update', $record) }}">@csrf @method('PUT')<button type="submit">Save row</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                        @elseif (request('sheet') === 'new-inhabitants')
                        @if ($usesFamilyNewInhabitants)
                            @include('registry._biasong_new_inhabitants')
                        @else
                        <thead>
                            <tr class="rbi-new-title"><th>HH<br>NO.</th><th colspan="12">NEW INHABITANT'S INFORMATIONS</th><th>MONTH<br>SUBMITTED</th><th>ACTION</th></tr>
                            <tr>
                                <th>HH No.</th><th>Last Name</th><th>First Name</th><th>Middle Name</th><th>Relationship</th><th>Purok / Sitio</th><th>Place of Birth</th><th>Date of Birth</th><th>Age</th><th>Sex</th><th>Civil Status</th><th>School Level</th><th>Religion</th><th>Month Submitted</th><th>Action</th>
                            </tr>
                        </thead>
                        <tbody class="rbi-new-body">
                            @foreach ($newInhabitantRecords as $record)
                                @php($rowForm = 'new-inhabitant-row-'.$record->id)
                                <tr>
                                    <td><input form="{{ $rowForm }}" name="household_number" value="{{ $record->household_number }}" required aria-label="Household number"></td>
                                    <td><input form="{{ $rowForm }}" name="last_name" value="{{ $record->last_name }}" required aria-label="Last name"></td>
                                    <td><input form="{{ $rowForm }}" name="first_name" value="{{ $record->first_name }}" required aria-label="First name"></td>
                                    <td><input form="{{ $rowForm }}" name="middle_name" value="{{ $record->middle_name }}" aria-label="Middle name"></td>
                                    <td><input form="{{ $rowForm }}" name="relationship_to_head" value="{{ $record->relationship_to_head }}" aria-label="Relationship"></td>
                                    <td><input form="{{ $rowForm }}" name="purok" value="{{ $record->purok }}" aria-label="Purok"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_place" value="{{ $record->birth_place }}" aria-label="Place of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_date" type="date" value="{{ optional($record->birth_date)->format('Y-m-d') }}" aria-label="Date of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="recorded_age" type="number" min="0" max="150" value="{{ $record->recorded_age }}" aria-label="Age"></td>
                                    <td><select form="{{ $rowForm }}" name="sex" aria-label="Sex"><option value="Male" @selected($record->sex === 'Male')>M</option><option value="Female" @selected($record->sex === 'Female')>F</option></select></td>
                                    <td><input form="{{ $rowForm }}" name="civil_status" value="{{ $record->civil_status }}" aria-label="Civil status"></td>
                                    <td><input form="{{ $rowForm }}" name="education_level" value="{{ $record->education_level }}" aria-label="Education"></td>
                                    <td><input form="{{ $rowForm }}" name="religion" value="{{ $record->religion }}" aria-label="Religion"></td>
                                    <td><input form="{{ $rowForm }}" name="month_submitted" value="{{ $record->month_submitted }}" aria-label="Month submitted"></td>
                                    <td class="rbi-row-save"><form id="{{ $rowForm }}" method="POST" action="{{ route('registry.new-inhabitants.update', $record) }}">@csrf @method('PUT')<button type="submit">Save row</button></form></td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                        @else
                        @if ($isBiasong)
                            @include('registry._biasong_active')
                        @else
                        <thead>
                            <tr class="rbi-title-row"><th colspan="19">CONSOLIDATED HOUSEHOLD RECORD OF BARANGAY INHABITANTS (RBI)</th></tr>
                            <tr class="rbi-spacer-row"><th colspan="19"></th></tr>
                            <tr class="rbi-meta-row">
                                <th colspan="2">A. REGION:</th><th colspan="4">VIII</th><th colspan="3"></th><th colspan="2">D. BARANGAY:</th><th colspan="8">{{ strtoupper($registryBarangay?->name ?? '') }}</th>
                            </tr>
                            <tr class="rbi-meta-row">
                                <th colspan="2">B. PROVINCE:</th><th colspan="4">SOUTHERN LEYTE</th><th colspan="3"></th><th colspan="2">E. HOUSEHOLD:</th><th colspan="8">{{ number_format($registryHouseholdCount) }} HOUSEHOLDS</th>
                            </tr>
                            <tr class="rbi-meta-row">
                                <th colspan="2">C. MUNICIPALITY:</th><th colspan="4">TOMAS OPPUS</th><th colspan="13"></th>
                            </tr>
                            <tr class="rbi-spacer-row"><th colspan="19"></th></tr>
                            <tr class="rbi-group-row">
                                <th rowspan="2">HH<br>No.</th><th rowspan="2">HH</th><th colspan="4">NAME</th><th>RELATIONSHIP</th><th rowspan="2">PUROK / SITIO</th><th rowspan="2">PLACE OF<br>BIRTH</th><th>DATE OF</th><th rowspan="2">AGE</th><th rowspan="2">SEX<br>(M/F)</th><th rowspan="2">CIVIL<br>STATUS</th><th>School</th><th rowspan="2">RELIGION</th><th rowspan="2">OCCUPATION</th><th>REMARKS</th><th rowspan="2">ETHNICITY</th><th rowspan="2">ACTION</th>
                            </tr>
                            <tr>
                                <th>Last Name</th>
                                <th>First Name</th>
                                <th>Middle Name</th>
                                <th>Qualifier</th>
                                <th>TO HOUSEHOLD<br>HEAD</th>
                                <th>BIRTH<br>(mm-dd-yy)</th>
                                <th>Grade/Year/<br>Level Completed</th>
                                <th>(OTHER INFO)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inhabitants as $inhabitant)
                                @php($rowForm = 'rbi-row-'.$inhabitant->id)
                                <tr class="{{ filled($inhabitant->registry_sequence) ? 'rbi-household-start' : '' }}">
                                    <td><input form="{{ $rowForm }}" name="registry_sequence" value="{{ $inhabitant->registry_sequence }}" aria-label="HH number sequence"></td>
                                    <td><input form="{{ $rowForm }}" name="household_number" value="{{ $inhabitant->household->household_number }}" required aria-label="Household number"></td>
                                    <td><input form="{{ $rowForm }}" name="last_name" value="{{ $inhabitant->last_name }}" required aria-label="Last name"></td>
                                    <td><input form="{{ $rowForm }}" name="first_name" value="{{ $inhabitant->first_name }}" required aria-label="First name"></td>
                                    <td><input form="{{ $rowForm }}" name="middle_name" value="{{ $inhabitant->middle_name }}" aria-label="Middle name"></td>
                                    <td><input form="{{ $rowForm }}" name="suffix" value="{{ $inhabitant->suffix }}" aria-label="Qualifier"></td>
                                    <td><input form="{{ $rowForm }}" name="relationship_to_head" value="{{ $inhabitant->relationship_to_head }}" aria-label="Relationship to household head"></td>
                                    <td><input form="{{ $rowForm }}" name="purok" value="{{ $inhabitant->household->purok }}" aria-label="Purok or sitio"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_place" value="{{ $inhabitant->birth_place }}" aria-label="Place of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="birth_date" type="date" value="{{ optional($inhabitant->birth_date)->format('Y-m-d') }}" aria-label="Date of birth"></td>
                                    <td><input form="{{ $rowForm }}" name="recorded_age" type="number" min="0" max="150" value="{{ $inhabitant->recorded_age }}" aria-label="Age"></td>
                                    <td><select form="{{ $rowForm }}" name="sex" required aria-label="Sex"><option value="Male" @selected($inhabitant->sex === 'Male')>M</option><option value="Female" @selected($inhabitant->sex === 'Female')>F</option></select></td>
                                    <td><input form="{{ $rowForm }}" name="civil_status" value="{{ $inhabitant->civil_status }}" aria-label="Civil status"></td>
                                    <td><input form="{{ $rowForm }}" name="education_level" value="{{ $inhabitant->education_level }}" aria-label="School level completed"></td>
                                    <td><input form="{{ $rowForm }}" name="religion" value="{{ $inhabitant->religion }}" aria-label="Religion"></td>
                                    <td><input form="{{ $rowForm }}" name="occupation" value="{{ $inhabitant->occupation }}" aria-label="Occupation"></td>
                                    <td><input form="{{ $rowForm }}" name="remarks" value="{{ $inhabitant->remarks }}" aria-label="Remarks"></td>
                                    <td><input form="{{ $rowForm }}" name="ethnicity" value="{{ $inhabitant->ethnicity }}" aria-label="Ethnicity"></td>
                                    <td class="rbi-row-save">
                                        <form id="{{ $rowForm }}" method="POST" action="{{ route('registry.update', $inhabitant) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="source" value="{{ request('source') }}">
                                            <input type="hidden" name="barangay_id" value="{{ $inhabitant->barangay_id }}">
                                            <input type="hidden" name="address" value="{{ $inhabitant->household->address }}">
                                            <input type="hidden" name="latitude" value="{{ $inhabitant->household->latitude }}">
                                            <input type="hidden" name="longitude" value="{{ $inhabitant->household->longitude }}">
                                            <input type="hidden" name="contact_number" value="{{ $inhabitant->contact_number }}">
                                            <input type="hidden" name="status" value="{{ $inhabitant->status }}">
                                            <button type="submit">Save row</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                        @endif
                        @else
                        <thead>
                            <tr><th>Name</th><th>Barangay</th><th>Household</th><th>Coordinates</th><th>Status</th><th>Migration Events</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($inhabitants as $inhabitant)
                                <tr>
                                    <td><strong>{{ $inhabitant->fullName() }}</strong><br>{{ $inhabitant->sex }} {{ optional($inhabitant->birth_date)->format('M d, Y') }}</td>
                                    <td>{{ $inhabitant->barangay->name }}</td>
                                    <td>{{ $inhabitant->household->household_number }}<br>{{ $inhabitant->household->address ?: 'No address' }}</td>
                                    <td>{{ $inhabitant->household->coordinate() }}</td>
                                    <td><span class="badge">{{ $inhabitant->statusLabel() }}</span></td>
                                    <td>{{ $inhabitant->migrationRecords->count() }}</td>
                                    <td class="row-actions"><a href="{{ route('registry.edit', $inhabitant) }}">Edit</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                        @endif
                    </table>
                </div>

                @if (!request('sheet')){{ $inhabitants->links() }}@endif
            @endif
        </div>
    </section>
@endsection
