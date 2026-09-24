@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gis-map.css') }}?v={{ filemtime(public_path('css/gis-map.css')) }}">
@endpush

@section('content')
    <section class="panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
        <div class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <x-workspace-heading icon="map"><div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Spatial Visualization</div>
                <h1>GIS Mapping - Household Population Map</h1>
                <p>Explore {{ $scopeName }} with barangay boundaries, building points, hazard layers and registered household locations.</p>
            </x-workspace-heading>
            <form class="toolbar compact-toolbar flex flex-wrap items-end gap-3" method="GET" action="{{ route('spatial.index') }}">
                @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                <select name="barangay_id" aria-label="Filter map by barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) $selectedBarangayId === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
                <button class="secondary-button" type="submit">Filter</button>
                @else
                    <span>Viewing all Tomas Oppus. Add/edit: Barangay {{ $writableBarangayName }} only.</span>
                @endif
                @error('barangay_id')<p role="alert">{{ $message }}</p>@enderror
            </form>
        </div>

        @if(session('mapped_household_id'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</p>
        @endif

        <div class="meta-grid">
            <div class="meta">
                <strong>Mapped households</strong>
                {{ number_format($householdCount) }}
            </div>
            <div class="meta">
                <strong>Mapped population</strong>
                {{ number_format($populationCount) }}
            </div>
            <div class="meta">
                <strong>Barangays shown</strong>
                {{ number_format($barangayCount) }}
            </div>
        </div>

        <details id="gis-household-form-panel" class="gis-legend" @if($editingHousehold || $errors->hasAny(['household_name', 'household_number', 'latitude', 'longitude', 'address', 'barangay_id'])) open @endif>
            <summary>{{ $editingHousehold ? 'Edit household' : 'Add household' }}</summary>
            <p>Enter the household name and coordinates, or click the map while this form is open to pick a location. You can drag the preview pin to adjust it.</p>
            <form method="POST" action="{{ $editingHousehold ? route('spatial.households.update', $editingHousehold) : route('spatial.households.store') }}" id="gis-household-form">
                @csrf
                @if($editingHousehold) @method('PUT') @endif
                <div class="form-grid grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div><label for="gis-household-name">Household name</label><input id="gis-household-name" name="household_name" value="{{ old('household_name', $editingHousehold?->household_name) }}" maxlength="255" placeholder="e.g. Santos Family" required>@error('household_name')<p role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="gis-household-number">Household number (optional)</label><input id="gis-household-number" name="household_number" value="{{ old('household_number', $editingHousehold?->household_number) }}" maxlength="255" placeholder="Generated automatically if blank">@error('household_number')<p role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="gis-household-barangay">Barangay</label><select id="gis-household-barangay" name="barangay_id" required>
                        @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))<option value="">Choose barangay</option>@endif
                        @foreach($barangays as $barangay)@if(! $editingHousehold || $barangay->id === $editingHousehold->barangay_id)<option value="{{ $barangay->id }}" @selected((string) old('barangay_id', $defaultFormBarangayId) === (string) $barangay->id)>{{ $barangay->name }}</option>@endif @endforeach
                    </select>@error('barangay_id')<p role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="gis-household-address">Address (optional)</label><input id="gis-household-address" name="address" value="{{ old('address', $editingHousehold?->address) }}" maxlength="255">@error('address')<p role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="gis-household-latitude">Latitude</label><input id="gis-household-latitude" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="{{ old('latitude', $editingHousehold?->latitude) }}" placeholder="10.2688410" required>@error('latitude')<p role="alert">{{ $message }}</p>@enderror</div>
                    <div><label for="gis-household-longitude">Longitude</label><input id="gis-household-longitude" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="{{ old('longitude', $editingHousehold?->longitude) }}" placeholder="125.0266880" required>@error('longitude')<p role="alert">{{ $message }}</p>@enderror</div>
                </div>
                <p>Save the household location here, then add its members through the resident registry.</p>
                <div class="gis-map-actions"><button type="submit">{{ $editingHousehold ? 'Save changes' : 'Save household' }}</button><button type="button" class="secondary-button" id="gis-preview-location">Preview location</button>@if($editingHousehold)<a class="button secondary-button" href="{{ route('spatial.index') }}">Cancel edit</a>@else<button type="button" class="secondary-button" id="gis-cancel-household">Cancel</button>@endif</div>
            </form>
        </details>

        <div class="gis-map-toolbar">
            <div><strong>Map layers</strong><p>Use the layers button on the map to switch basemaps and turn overlays on or off.</p></div>
            <div class="gis-map-actions">
                <button type="button" class="secondary-button" id="gis-fit-area">Fit area</button>
                <button type="button" class="secondary-button" id="gis-fit-households" @disabled($markers->isEmpty())>Find households</button>
            </div>
        </div>
        <p id="gis-map-status" role="status" aria-live="polite">Loading GIS map...</p>
        <button type="button" class="secondary-button" id="gis-retry" hidden>Retry failed layers</button>
        <div class="map-shell overflow-hidden rounded-xl border border-slate-200">
            <div id="household-map" class="large-map" aria-label="Interactive household distribution map"></div>
        </div>

        <details class="gis-legend" open>
            <summary>Map legend and source</summary>
            <div class="gis-legend-items">
                <span><i style="background:#2563eb"></i>Registered household</span>
                <span><i style="background:#14b8a6"></i>Source building point</span>
                <span><i style="background:#1f78b4"></i>Low susceptibility</span>
                <span><i style="background:#ffff00"></i>Moderate susceptibility</span>
                <span><i style="background:#e51919"></i>High / storm surge susceptible</span>
                <span><i style="background:#e600a9"></i>Very high landslide susceptibility</span>
                <span><i style="background:#64748b"></i>Unclassified</span>
            </div>
            <p>GIS layers copied from SB-ISPGM. Building points are reference locations, not registered households or population counts. Boundaries use a dark outline; barangay boundaries are dashed. Hazard overlays show the supplied municipal susceptibility data, not live conditions, and remain municipal-wide when a barangay is selected.</p>
        </details>

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
            <h2 class="section-title text-lg font-semibold text-slate-900">Mapped Household List</h2>

            @if ($markers->isEmpty())
                <p>No households with coordinates yet. Open Add household above to save a name and location.</p>
            @else
                <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                    <table>
                        <thead>
                            <tr>
                                <th>Household</th>
                                <th>Barangay</th>
                                <th>Address</th>
                                <th>Coordinates</th>
                                <th>Population</th><th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($markers as $marker)
                                <tr>
                                    <td><strong>{{ $marker['household_name'] }}</strong><br>{{ $marker['household_number'] }}</td>
                                    <td>{{ $marker['barangay'] }}</td>
                                    <td>{{ $marker['address'] }}</td>
                                    <td>{{ $marker['latitude'] }}, {{ $marker['longitude'] }}</td>
                                    <td>{{ $marker['population'] }}</td><td>@if($marker['edit_url'])<a href="{{ $marker['edit_url'] }}">Edit household</a>@else<span>View only</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script id="gis-map-config" type="application/json">{!! json_encode(['markers' => $markers, 'layers' => $layerUrls, 'scoped' => $selectedBarangayId !== null, 'focusHouseholdId' => session('mapped_household_id', $editingHousehold?->id), 'writableBarangayName' => $writableBarangayName], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/gis-map.js') }}?v={{ filemtime(public_path('js/gis-map.js')) }}" defer></script>
@endpush
