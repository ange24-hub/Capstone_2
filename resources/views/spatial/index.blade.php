@extends('layouts.app')

@push('head')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
@endpush

@section('content')
    <section class="panel rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6 grid gap-6">
        <div class="page-kicker text-xs font-semibold uppercase tracking-widest text-blue-700">Spatial Visualization</div>
        <div class="page-head flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-5">
            <div>
                <h1>Household Population Map</h1>
                <p>View registered households across barangays using OpenStreetMap markers and household coordinates.</p>
            </div>
            <form class="toolbar compact-toolbar flex flex-wrap items-end gap-3" method="GET" action="{{ route('spatial.index') }}">
                <select name="barangay_id" aria-label="Filter map by barangay">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $barangay)
                        <option value="{{ $barangay->id }}" @selected((string) request('barangay_id') === (string) $barangay->id)>{{ $barangay->name }}</option>
                    @endforeach
                </select>
                <button class="secondary-button" type="submit">Filter</button>
            </form>
        </div>

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

        <div class="map-shell overflow-hidden rounded-xl border border-slate-200">
            <div id="household-map" class="large-map" aria-label="Interactive household distribution map"></div>
        </div>

        <div class="workflow-card rounded-xl border border-slate-200 bg-white bg-none shadow-sm min-w-0 p-5 sm:p-6">
            <h2 class="section-title text-lg font-semibold text-slate-900">Mapped Household List</h2>

            @if ($markers->isEmpty())
                <p>No households with coordinates yet. Add or edit a registry record, then click the coordinate map to record a location.</p>
            @else
                <div class="table-wrap w-full overflow-x-auto rounded-xl border border-slate-200">
                    <table>
                        <thead>
                            <tr>
                                <th>Household</th>
                                <th>Barangay</th>
                                <th>Address</th>
                                <th>Coordinates</th>
                                <th>Population</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($markers as $marker)
                                <tr>
                                    <td>{{ $marker['household_number'] }}</td>
                                    <td>{{ $marker['barangay'] }}</td>
                                    <td>{{ $marker['address'] }}</td>
                                    <td>{{ $marker['latitude'] }}, {{ $marker['longitude'] }}</td>
                                    <td>{{ $marker['population'] }}</td>
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
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const householdMarkers = @json($markers);
        const map = L.map('household-map', { scrollWheelZoom: true }).setView([12.8797, 121.7740], 6);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const markerGroup = L.featureGroup().addTo(map);

        householdMarkers.forEach((household) => {
            const residents = household.residents.length
                ? `<br><strong>Residents:</strong> ${household.residents.join(', ')}`
                : '';
            const popup = `
                <strong>${household.household_number}</strong><br>
                ${household.barangay}<br>
                ${household.address}<br>
                <strong>Population:</strong> ${household.population}
                ${residents}
            `;

            L.marker([household.latitude, household.longitude])
                .bindPopup(popup)
                .addTo(markerGroup);
        });

        if (householdMarkers.length > 0) {
            map.fitBounds(markerGroup.getBounds(), { padding: [28, 28], maxZoom: 17 });
        }
    </script>
@endpush
