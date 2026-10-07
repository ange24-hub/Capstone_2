@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
    <link rel="stylesheet" href="{{ asset('css/gis-map.css') }}?v={{ filemtime(public_path('css/gis-map.css')) }}">
@endpush

@section('content')
    <section class="gis-workspace">
        <header class="gis-page-header">
            <div class="gis-heading">
                <span class="gis-heading-icon"><x-app-icon name="map" /></span>
                <div>
                    <span class="gis-eyebrow">Spatial visualization</span>
                    <h1>GIS Mapping</h1>
                    <p>Households, buildings, and hazard areas in {{ $scopeName }}.</p>
                </div>
            </div>
            <form class="gis-scope-filter" method="GET" action="{{ route('spatial.index') }}">
                @if (! auth()->user()->hasRole(App\Models\User::ROLE_BARANGAY))
                <label for="gis-scope"><x-app-icon name="filter" /> View area</label>
                <select id="gis-scope" name="barangay_id" aria-label="Filter map by barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) $selectedBarangayId === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
                <button class="secondary-button" type="submit">Apply filter</button>
                @else
                    <div class="gis-scope-note">
                        <span class="gis-scope-icon"><x-app-icon name="location" /></span>
                        <div><strong>Viewing all Tomas Oppus</strong><span>Add and edit in Barangay {{ $writableBarangayName }} only</span></div>
                    </div>
                @endif
                @error('barangay_id')<p role="alert">{{ $message }}</p>@enderror
            </form>
        </header>

        @if(session('mapped_household_id'))
            <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-900">{{ session('success') }}</p>
        @endif

        <div class="gis-metrics" aria-label="Map statistics">
            <div class="gis-metric gis-metric-blue">
                <span class="gis-metric-icon"><x-app-icon name="home" /></span>
                <div><span class="gis-metric-label">Mapped households</span><strong>{{ number_format($householdCount) }}</strong><small>With saved coordinates</small></div>
            </div>
            <div class="gis-metric gis-metric-teal">
                <span class="gis-metric-icon"><x-app-icon name="users" /></span>
                <div><span class="gis-metric-label">Mapped population</span><strong>{{ number_format($populationCount) }}</strong><small>Residents in mapped households</small></div>
            </div>
            <div class="gis-metric gis-metric-violet">
                <span class="gis-metric-icon"><x-app-icon name="map" /></span>
                <div><span class="gis-metric-label">Barangays shown</span><strong>{{ number_format($barangayCount) }}</strong><small>Boundaries on this map</small></div>
            </div>
            <div class="gis-metric gis-metric-amber">
                <span class="gis-metric-icon"><x-app-icon name="building" /></span>
                <div><span class="gis-metric-label">Building references</span><strong>{{ number_format($sourceBuildingCount) }}</strong><small>From the supplied map layer</small></div>
            </div>
        </div>

        <div class="gis-household-tools">
        @if($unmappedHouseholds->isNotEmpty())
            <details class="gis-legend gis-tool-card">
                <summary><span class="gis-tool-icon"><x-app-icon name="location" /></span><span class="gis-tool-copy"><strong>Map an existing household</strong><small>Set a location for a registered household</small></span><span class="gis-tool-count">{{ number_format($unmappedHouseholds->count()) }} to map</span><x-app-icon name="arrow-right" class="gis-disclosure-icon" /></summary>
                <p>Choose a registered household that needs coordinates, then pick its location on the map or use a building polygon.</p>
                <form method="GET" action="{{ route('spatial.index') }}" class="gis-map-actions">
                    @if($selectedBarangayId)<input type="hidden" name="barangay_id" value="{{ $selectedBarangayId }}">@endif
                    <label for="gis-unmapped-household">Registered household</label>
                    <select id="gis-unmapped-household" name="edit_household" required>
                        <option value="">Choose existing household</option>
                        @foreach($unmappedHouseholds as $household)
                            <option value="{{ $household->id }}">{{ $household->barangay?->name }} - {{ $household->household_name ?: 'Household '.$household->household_number }} ({{ $household->household_number }})</option>
                        @endforeach
                    </select>
                    <button type="submit">Set household location</button>
                </form>
            </details>
        @endif

        <details id="gis-household-form-panel" class="gis-legend gis-tool-card gis-add-tool" @if($editingHousehold || $errors->hasAny(['household_name', 'household_number', 'latitude', 'longitude', 'address', 'barangay_id'])) open @endif>
            <summary><span class="gis-tool-icon"><x-app-icon name="home" /></span><span class="gis-tool-copy"><strong>{{ $editingHousehold ? 'Edit household' : 'Add household' }}</strong><small>{{ $editingHousehold ? 'Update the details and saved location' : 'Register a household and choose its location' }}</small></span><x-app-icon name="arrow-right" class="gis-disclosure-icon" /></summary>
            <p>Enter the household name and coordinates, or click the map while this form is open to pick a location. You can drag the preview pin to adjust it. Choose a location inside the selected barangay boundary.</p>
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
        </div>

        <section class="gis-map-card" aria-labelledby="gis-map-title">
        <div class="gis-map-toolbar">
            <div class="gis-map-title"><span class="gis-map-title-icon"><x-app-icon name="map" /></span><div><h2 id="gis-map-title">Interactive map</h2><p>Explore locations. Use the layers button to show boundaries and hazards.</p></div></div>
            <div class="gis-map-actions">
                <button type="button" class="secondary-button" id="gis-fit-area"><x-app-icon name="map" />Fit area</button>
                <button type="button" id="gis-fit-households" @disabled($markers->isEmpty())><x-app-icon name="location" />Find households</button>
            </div>
        </div>
        <div class="gis-map-canvas">
            <div id="household-map" class="large-map" aria-label="Interactive household distribution map"></div>
        </div>
        <div class="gis-map-footer">
            <p id="gis-map-status" role="status" aria-live="polite">Loading GIS map...</p>
            <button type="button" class="secondary-button" id="gis-retry" hidden>Retry failed layers</button>
            <span class="gis-map-scope"><x-app-icon name="location" />{{ $scopeName }}</span>
        </div>
        </section>

        <details class="gis-legend" open>
            <summary>Map guide</summary>
            <div class="gis-legend-groups">
                <section class="gis-legend-group" aria-labelledby="legend-points">
                    <h2 id="legend-points">Households and buildings</h2>
                    <ul>
                        <li><i class="legend-symbol legend-dot legend-household" aria-hidden="true"></i><span><strong>Registered household</strong><small>Saved in the RBIM registry</small></span></li>
                        <li><i class="legend-symbol legend-building-polygon legend-building" aria-hidden="true"></i><span><strong>Building polygon</strong><small>Symbol at the source location; not counted as a registered household</small></span></li>
                        <li><i class="legend-symbol legend-building-polygon legend-building-review" aria-hidden="true"></i><span><strong>Building polygon for review</strong><small>Outside its assigned barangay boundary on this map</small></span></li>
                    </ul>
                </section>
                <section class="gis-legend-group" aria-labelledby="legend-boundaries">
                    <h2 id="legend-boundaries">Boundaries</h2>
                    <ul>
                        <li><i class="legend-symbol legend-line legend-municipal" aria-hidden="true"></i><span>Municipal boundary</span></li>
                        <li><i class="legend-symbol legend-line legend-barangay" aria-hidden="true"></i><span>Barangay boundary</span></li>
                    </ul>
                </section>
                <section class="gis-legend-group gis-hazard-legend" aria-labelledby="legend-hazards">
                    <h2 id="legend-hazards">Hazard areas</h2>
                    <ul>
                        <li><i class="legend-symbol legend-area legend-low" aria-hidden="true"></i><span>Low</span></li>
                        <li><i class="legend-symbol legend-area legend-moderate" aria-hidden="true"></i><span>Moderate</span></li>
                        <li><i class="legend-symbol legend-area legend-high" aria-hidden="true"></i><span>High flood or landslide susceptibility</span></li>
                        <li><i class="legend-symbol legend-area legend-very-high" aria-hidden="true"></i><span>Very high landslide area</span></li>
                        <li><i class="legend-symbol legend-area legend-unclassified" aria-hidden="true"></i><span>No classification</span></li>
                        <li><i class="legend-symbol legend-area legend-high" aria-hidden="true"></i><span>Storm surge area (level not specified)</span></li>
                    </ul>
                </section>
            </div>
            <div class="gis-legend-note">
                <strong>Source and limitation</strong>
                <span>Buildings use rectangular polygon symbols at the supplied locations. Their size and shape do not represent actual building outlines.</span>
                <span>Map layers come from the supplied Tomas Oppus shapefiles. The CLUP 2022–2031 lists related maps: MP-7 (landslide), MP-8 (flood), and MP-9 (storm surge).</span>
                <span>Open a registered household point and choose “Check hazard overlays” to compare its coordinates with the supplied flood, landslide, and storm-surge areas. This is a planning reference, not a live alert or site assessment.</span>
            </div>
        </details>

        <details class="gis-physical-profile">
            <summary>Physical profile of Tomas Oppus <span>CLUP 2022–2031</span></summary>
            <p class="gis-physical-intro">Municipality-wide land information for map context. These figures do not describe the exact soil or slope at a selected household.</p>
            <div class="gis-physical-grid">
                <section aria-labelledby="gis-soil-title">
                    <h2 id="gis-soil-title">Soil types</h2>
                    <ul class="gis-physical-bars">
                        <li><span>Maasin clay <strong>34.09%</strong></span><i style="--bar-width:34.09%" aria-hidden="true"></i></li>
                        <li><span>Malitbog clay <strong>33.64%</strong></span><i style="--bar-width:33.64%" aria-hidden="true"></i></li>
                        <li><span>Umingan clay loam <strong>20.14%</strong></span><i style="--bar-width:20.14%" aria-hidden="true"></i></li>
                        <li><span>Faraon clay <strong>12.13%</strong></span><i style="--bar-width:12.13%" aria-hidden="true"></i></li>
                    </ul>
                </section>
                <section aria-labelledby="gis-slope-title">
                    <h2 id="gis-slope-title">Slope</h2>
                    <ul class="gis-physical-bars">
                        <li><span>0–8% slope <strong>6.56%</strong></span><i style="--bar-width:6.56%" aria-hidden="true"></i></li>
                        <li><span>8–18% slope <strong>16.04%</strong></span><i style="--bar-width:16.04%" aria-hidden="true"></i></li>
                        <li><span>18–30% slope <strong>60.15%</strong></span><i style="--bar-width:60.15%" aria-hidden="true"></i></li>
                        <li><span>30–50% slope <strong>17.25%</strong></span><i style="--bar-width:17.25%" aria-hidden="true"></i></li>
                    </ul>
                </section>
                <section aria-labelledby="gis-land-title">
                    <h2 id="gis-land-title">Land classification</h2>
                    <ul class="gis-physical-bars">
                        <li><span>Alienable and disposable land <strong>79.98%</strong></span><i style="--bar-width:79.98%" aria-hidden="true"></i></li>
                        <li><span>Forestland <strong>20.02%</strong></span><i style="--bar-width:20.02%" aria-hidden="true"></i></li>
                    </ul>
                </section>
            </div>
            <p class="gis-physical-source">Source: Tomas Oppus CLUP 2022–2031, Volume III, Physical Features, Tables RM-1, RM-3 and RM-4 (pp. 49, 54 and 57). Values are reported area shares, not live measurements.</p>
        </details>

        <section class="gis-household-list" aria-labelledby="gis-household-list-title">
            <div class="gis-list-heading"><div><span class="gis-eyebrow">Registry locations</span><h2 id="gis-household-list-title">Mapped Household List</h2></div><span class="gis-list-count">{{ number_format($householdCount) }} {{ $householdCount === 1 ? 'household' : 'households' }}</span></div>

            @if ($markers->isEmpty())
                <div class="gis-empty-state"><span class="gis-tool-icon"><x-app-icon name="location" /></span><strong>No mapped households yet</strong><p>Open Add household above to save a name and location.</p></div>
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
        </section>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('js/gis-geometry.js') }}?v={{ filemtime(public_path('js/gis-geometry.js')) }}"></script>
    <script id="gis-map-config" type="application/json">{!! json_encode(['markers' => $markers, 'layers' => $layerUrls, 'scoped' => $selectedBarangayId !== null, 'focusHouseholdId' => session('mapped_household_id', $editingHousehold?->id), 'writableBarangayName' => $writableBarangayName], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    <script src="{{ asset('js/gis-map.js') }}?v={{ filemtime(public_path('js/gis-map.js')) }}" defer></script>
@endpush
