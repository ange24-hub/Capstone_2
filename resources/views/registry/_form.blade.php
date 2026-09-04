@php
    $isEdit = isset($inhabitant);
    $household = $inhabitant->household ?? null;
    $selectedBarangay = old('barangay_id', $inhabitant->barangay_id ?? auth()->user()->barangay_id);
@endphp

@once
    @push('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endpush
@endonce

@if ($errors->any())
    <div class="errors">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $isEdit ? route('registry.update', $inhabitant) : route('registry.store') }}">
    @csrf
    @if (request('source'))
        <input type="hidden" name="source" value="{{ request('source') }}">
    @endif
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="form-grid">
        <div>
            <label for="registry_sequence">RBI row / HH sequence</label>
            <input id="registry_sequence" name="registry_sequence" type="text" value="{{ old('registry_sequence', $inhabitant->registry_sequence ?? '') }}">
        </div>
        <div>
            <label for="barangay_id">Barangay</label>
            <select id="barangay_id" name="barangay_id">
                <option value="">New barangay</option>
                @foreach ($barangays as $barangay)
                    <option value="{{ $barangay->id }}" @selected((string) $selectedBarangay === (string) $barangay->id)>{{ $barangay->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="barangay_name">New barangay name</label>
            <input id="barangay_name" name="barangay_name" type="text" value="{{ old('barangay_name') }}">
        </div>
        <div>
            <label for="municipality">Municipality</label>
            <input id="municipality" name="municipality" type="text" value="{{ old('municipality', $inhabitant->barangay->municipality ?? '') }}">
        </div>
        <div>
            <label for="household_number">Household number</label>
            <input id="household_number" name="household_number" type="text" value="{{ old('household_number', $household->household_number ?? '') }}" required>
        </div>
        <div>
            <label for="purok">Purok / Zone</label>
            <input id="purok" name="purok" type="text" value="{{ old('purok', $household->purok ?? '') }}">
        </div>
        <div>
            <label for="address">House address</label>
            <input id="address" name="address" type="text" value="{{ old('address', $household->address ?? '') }}">
        </div>
        <div>
            <label for="latitude">Latitude</label>
            <input id="latitude" name="latitude" type="number" step="0.0000001" value="{{ old('latitude', $household->latitude ?? '') }}">
        </div>
        <div>
            <label for="longitude">Longitude</label>
            <input id="longitude" name="longitude" type="number" step="0.0000001" value="{{ old('longitude', $household->longitude ?? '') }}">
        </div>
    </div>

    <div>
        <label>House coordinate picker</label>
        <div id="coordinate-picker-map" class="picker-map" aria-label="Interactive map for recording household coordinates"></div>
    </div>

    <div class="form-grid">
        <div>
            <label for="first_name">First name</label>
            <input id="first_name" name="first_name" type="text" value="{{ old('first_name', $inhabitant->first_name ?? '') }}" required>
        </div>
        <div>
            <label for="middle_name">Middle name</label>
            <input id="middle_name" name="middle_name" type="text" value="{{ old('middle_name', $inhabitant->middle_name ?? '') }}">
        </div>
        <div>
            <label for="last_name">Last name</label>
            <input id="last_name" name="last_name" type="text" value="{{ old('last_name', $inhabitant->last_name ?? '') }}" required>
        </div>
        <div>
            <label for="suffix">Suffix</label>
            <input id="suffix" name="suffix" type="text" value="{{ old('suffix', $inhabitant->suffix ?? '') }}">
        </div>
        <div>
            <label for="relationship_to_head">Relationship to head</label>
            <input id="relationship_to_head" name="relationship_to_head" type="text" value="{{ old('relationship_to_head', $inhabitant->relationship_to_head ?? '') }}">
        </div>
        <div>
            <label for="sex">Sex</label>
            <select id="sex" name="sex" required>
                <option value="">Select</option>
                <option value="Male" @selected(old('sex', $inhabitant->sex ?? '') === 'Male')>Male</option>
                <option value="Female" @selected(old('sex', $inhabitant->sex ?? '') === 'Female')>Female</option>
            </select>
        </div>
        <div>
            <label for="birth_date">Birth date</label>
            <input id="birth_date" name="birth_date" type="date" value="{{ old('birth_date', optional($inhabitant->birth_date ?? null)->format('Y-m-d')) }}">
        </div>
        <div>
            <label for="recorded_age">Age recorded in RBI</label>
            <input id="recorded_age" name="recorded_age" type="number" min="0" max="150" value="{{ old('recorded_age', $inhabitant->recorded_age ?? '') }}">
        </div>
        <div>
            <label for="birth_place">Birth place</label>
            <input id="birth_place" name="birth_place" type="text" value="{{ old('birth_place', $inhabitant->birth_place ?? '') }}">
        </div>
        <div>
            <label for="civil_status">Civil status</label>
            <input id="civil_status" name="civil_status" type="text" value="{{ old('civil_status', $inhabitant->civil_status ?? '') }}">
        </div>
        <div>
            <label for="religion">Religion</label>
            <input id="religion" name="religion" type="text" value="{{ old('religion', $inhabitant->religion ?? '') }}">
        </div>
        <div>
            <label for="occupation">Occupation</label>
            <input id="occupation" name="occupation" type="text" value="{{ old('occupation', $inhabitant->occupation ?? '') }}">
        </div>
        <div>
            <label for="education_level">Education level</label>
            <input id="education_level" name="education_level" type="text" value="{{ old('education_level', $inhabitant->education_level ?? '') }}">
        </div>
        <div>
            <label for="contact_number">Contact number</label>
            <input id="contact_number" name="contact_number" type="text" value="{{ old('contact_number', $inhabitant->contact_number ?? '') }}">
        </div>
        <div>
            <label for="ethnicity">Ethnicity</label>
            <input id="ethnicity" name="ethnicity" type="text" value="{{ old('ethnicity', $inhabitant->ethnicity ?? '') }}">
        </div>
        <div>
            <label for="status">Record status</label>
            <select id="status" name="status" required>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $inhabitant->status ?? App\Models\Inhabitant::STATUS_ACTIVE) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <label for="remarks">RBI remarks / other information</label>
    <textarea id="remarks" name="remarks">{{ old('remarks', $inhabitant->remarks ?? '') }}</textarea>

    <div class="form-grid">
        <div>
            <label for="migration_type">Migration event</label>
            <select id="migration_type" name="migration_type">
                <option value="">No new event</option>
                @foreach ($migrationTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('migration_type') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="movement_date">Movement date</label>
            <input id="movement_date" name="movement_date" type="date" value="{{ old('movement_date') }}">
        </div>
        <div>
            <label for="origin">Origin</label>
            <input id="origin" name="origin" type="text" value="{{ old('origin') }}">
        </div>
        <div>
            <label for="destination">Destination</label>
            <input id="destination" name="destination" type="text" value="{{ old('destination') }}">
        </div>
    </div>

    <label for="reason">Migration reason / notes</label>
    <textarea id="reason" name="reason">{{ old('reason') }}</textarea>

    <div class="form-actions">
        <button type="submit">{{ $isEdit ? 'Save Changes' : 'Create Record' }}</button>
    </div>
</form>

@once
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            const pickerElement = document.getElementById('coordinate-picker-map');

            if (pickerElement) {
                const latitudeInput = document.getElementById('latitude');
                const longitudeInput = document.getElementById('longitude');
                const startingLatitude = Number(latitudeInput.value) || 12.8797;
                const startingLongitude = Number(longitudeInput.value) || 121.7740;
                const startingZoom = latitudeInput.value && longitudeInput.value ? 17 : 6;
                const pickerMap = L.map(pickerElement).setView([startingLatitude, startingLongitude], startingZoom);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(pickerMap);

                let coordinateMarker = null;

                const setCoordinateMarker = (latitude, longitude) => {
                    latitudeInput.value = Number(latitude).toFixed(7);
                    longitudeInput.value = Number(longitude).toFixed(7);

                    if (coordinateMarker) {
                        coordinateMarker.setLatLng([latitude, longitude]);
                        return;
                    }

                    coordinateMarker = L.marker([latitude, longitude], { draggable: true }).addTo(pickerMap);
                    coordinateMarker.on('dragend', (event) => {
                        const position = event.target.getLatLng();
                        latitudeInput.value = position.lat.toFixed(7);
                        longitudeInput.value = position.lng.toFixed(7);
                    });
                };

                if (latitudeInput.value && longitudeInput.value) {
                    setCoordinateMarker(startingLatitude, startingLongitude);
                }

                pickerMap.on('click', (event) => {
                    setCoordinateMarker(event.latlng.lat, event.latlng.lng);
                });

                setTimeout(() => pickerMap.invalidateSize(), 120);
            }
        </script>
    @endpush
@endonce
