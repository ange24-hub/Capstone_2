/* Adapted from SB-ISPGM's Leaflet GIS map for the RBIM registry. */
(() => {
    'use strict';
    const configNode = document.getElementById('gis-map-config');
    const status = document.getElementById('gis-map-status');
    if (!configNode) return;
    if (!window.L) {
        status.textContent = 'The map library could not load. Refresh the page to try again.';
        return;
    }
    const config = JSON.parse(configNode.textContent);
    const map = L.map('household-map', { preferCanvas: true }).setView([10.268841, 125.026688], 13);
    const streets = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19, attribution: 'Tiles &copy; Esri'
    });
    map.createPane('hazards');
    map.getPane('hazards').style.zIndex = 350;
    map.createPane('boundaries');
    map.getPane('boundaries').style.zIndex = 450;
    map.createPane('households');
    map.getPane('households').style.zIndex = 500;
    // SVG paths remain clickable above the boundary canvas without blocking the rest of the map.
    const householdRenderer = L.svg({ pane: 'households' });
    const households = L.featureGroup().addTo(map);
    const layersControl = L.control.layers({ OpenStreetMap: streets, Satellite: satellite }, {
        'Registered households': households
    }, { collapsed: true }).addTo(map);
    const popup = (title, rows) => {
        const box = document.createElement('div');
        box.className = 'gis-popup';
        const heading = document.createElement('strong');
        heading.textContent = title;
        box.append(heading);
        rows.forEach(([label, value]) => {
            const line = document.createElement('div');
            line.textContent = `${label}: ${value}`;
            box.append(line);
        });
        return box;
    };
    let savedMarker = null;
    config.markers.forEach(household => {
        const name = household.household_name || `Household ${household.household_number}`;
        const label = document.createElement('span');
        label.textContent = name;
        const content = popup(name, [
            ['Household number', household.household_number],
            ['Barangay', household.barangay], ['Address', household.address],
            ['Registered population', household.population],
            ['Residents (up to 6)', household.residents.join(', ') || 'None recorded']
        ]);
        if (household.edit_url) {
            const edit = document.createElement('a');
            edit.href = household.edit_url;
            edit.textContent = 'Edit household';
            content.append(edit);
        }
        const marker = L.circleMarker([household.latitude, household.longitude], {
            pane: 'households', renderer: householdRenderer, bubblingMouseEvents: false,
            radius: 5, color: '#fff', weight: 1.5, fillColor: '#2563eb', fillOpacity: 1
        }).bindTooltip(label).bindPopup(content, {
            className: 'gis-household-popup', minWidth: 180, maxWidth: 240, maxHeight: 180
        }).addTo(households);
        if (Number(config.focusHouseholdId) === household.id) savedMarker = marker;
    });
    if (savedMarker) {
        map.setView(savedMarker.getLatLng(), 17);
        savedMarker.openPopup();
    }

    const formPanel = document.getElementById('gis-household-form-panel');
    const latitudeInput = document.getElementById('gis-household-latitude');
    const longitudeInput = document.getElementById('gis-household-longitude');
    let previewMarker = null;
    function previewLocation(focus = false) {
        if (!latitudeInput.value || !longitudeInput.value || !latitudeInput.validity.valid || !longitudeInput.validity.valid) {
            if (previewMarker) map.removeLayer(previewMarker);
            return;
        }
        const position = [Number(latitudeInput.value), Number(longitudeInput.value)];
        if (!previewMarker) {
            previewMarker = L.marker(position, { draggable: true }).bindTooltip('Selected household location');
            previewMarker.on('dragend', () => {
                const point = previewMarker.getLatLng();
                latitudeInput.value = point.lat.toFixed(7);
                longitudeInput.value = point.lng.toFixed(7);
            });
        }
        previewMarker.setLatLng(position).addTo(map);
        if (focus) map.setView(position, Math.max(map.getZoom(), 16));
    }
    function chooseLocation(latitude, longitude) {
        formPanel.open = true;
        latitudeInput.value = Number(latitude).toFixed(7);
        longitudeInput.value = Number(longitude).toFixed(7);
        previewLocation();
    }
    map.on('click', event => {
        if (formPanel.open) chooseLocation(event.latlng.lat, event.latlng.lng);
    });
    [latitudeInput, longitudeInput].forEach(input => input.addEventListener('input', () => {
        if (formPanel.open) previewLocation();
    }));
    document.getElementById('gis-preview-location').addEventListener('click', () => {
        if (latitudeInput.reportValidity() && longitudeInput.reportValidity()) previewLocation(true);
    });
    document.getElementById('gis-cancel-household')?.addEventListener('click', () => { formPanel.open = false; });
    formPanel.addEventListener('toggle', () => {
        if (formPanel.open) previewLocation();
        else if (previewMarker) map.removeLayer(previewMarker);
    });
    if (formPanel.open) previewLocation();

    const descriptors = [
        { key: 'municipal-boundary', name: 'Municipal boundary', visible: true },
        { key: 'barangay-boundaries', name: 'Barangay boundaries', visible: true },
        { key: 'building-points', name: 'Source building points', visible: true },
        { key: 'flood', name: 'Flood susceptibility' },
        { key: 'landslide', name: 'Landslide susceptibility' },
        { key: 'storm-surge', name: 'Storm surge susceptibility' }
    ];
    let areaBounds = null;
    const failed = new Set();
    const pending = new Set();
    const retry = document.getElementById('gis-retry');
    const report = () => {
        const errors = descriptors.filter(d => failed.has(d.key)).map(d => d.name);
        status.textContent = pending.size ? 'Loading map layers...' : errors.length
            ? `Could not load: ${errors.join(', ')}. Retry or refresh your sign-in.`
            : 'Map ready. Select a point for details. Open the layers button to show hazard overlays.';
        retry.hidden = errors.length === 0 || pending.size > 0;
    };
    const fit = bounds => {
        if (bounds?.isValid()) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 17 });
    };
    document.getElementById('gis-fit-area').addEventListener('click', () => fit(areaBounds));
    document.getElementById('gis-fit-households').addEventListener('click', () => {
        households.addTo(map);
        fit(households.getBounds());
    });
    const classification = (feature, key) => {
        if (key === 'storm-surge') return { color: '#e51919', label: 'Storm surge susceptible' };
        const value = String(feature.properties?.[key === 'flood' ? 'FloodSusc' : 'LndslideSu'] || '').toUpperCase();
        if (value === 'VHL' || value.includes('VERY HIGH')) return { color: '#e600a9', label: 'Very high' };
        if (['HF', 'HL'].includes(value) || value.includes('HIGH')) return { color: '#e51919', label: 'High' };
        if (['MF', 'ML'].includes(value) || value.includes('MOD')) return { color: '#ffff00', label: 'Moderate' };
        if (['LF', 'LL'].includes(value) || value.includes('LOW')) return { color: '#1f78b4', label: 'Low' };
        return { color: '#64748b', label: 'Unclassified' };
    };
    function makeLayer(data, descriptor) {
        const key = descriptor.key;
        const boundary = key.endsWith('boundary') || key === 'barangay-boundaries';
        const building = key === 'building-points';
        return L.geoJSON(data, {
            pane: boundary ? 'boundaries' : building ? 'overlayPane' : 'hazards',
            style: feature => boundary ? {
                color: key === 'municipal-boundary' ? '#172b43' : '#64748b',
                weight: key === 'municipal-boundary' ? 2.5 : 1.5,
                fillOpacity: 0, dashArray: key === 'barangay-boundaries' ? '5 5' : null
            } : { color: classification(feature, key).color, weight: 0.5, fillOpacity: 0.45 },
            pointToLayer: (_feature, latlng) => L.circleMarker(latlng, {
                radius: 3, color: '#0f766e', weight: 0.5, fillColor: '#14b8a6', fillOpacity: 0.85
            }),
            onEachFeature: (feature, layer) => {
                if (building) {
                    const [longitude, latitude] = feature.geometry.coordinates;
                    const content = popup('Source building point', [
                        ['Barangay', feature.properties.barangay], ['Latitude', latitude], ['Longitude', longitude],
                        ['Source', 'SB-ISPGM reference layer; not a registry household']
                    ]);
                    const addButton = document.createElement('button');
                    addButton.type = 'button';
                    addButton.textContent = document.querySelector('#gis-household-form input[name="_method"]') ? 'Use this location' : 'Add household here';
                    addButton.addEventListener('click', () => {
                        chooseLocation(latitude, longitude);
                        map.closePopup();
                        formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        document.getElementById('gis-household-name').focus({ preventScroll: true });
                    });
                    const normalize = name => {
                        const normalized = name.replace(/\([^)]*\)/g, '').replace(/[^a-z0-9]/gi, '').toLowerCase();
                        return ({ ponong: 'punong', higosan: 'higosoan' })[normalized] || normalized;
                    };
                    if (!config.writableBarangayName || normalize(config.writableBarangayName) === normalize(feature.properties.barangay)) {
                        content.append(addButton);
                    }
                    layer.bindPopup(content);
                } else if (boundary) {
                    const label = document.createElement('span');
                    label.textContent = feature.properties?.Name || 'Tomas Oppus';
                    layer.bindTooltip(label, { sticky: true });
                } else {
                    layer.bindPopup(popup(descriptor.name, [['Susceptibility', classification(feature, key).label], ['Source', 'SB-ISPGM supplied layer']]));
                }
            }
        });
    }
    async function load(descriptor) {
        if (descriptor.loaded || pending.has(descriptor.key)) return;
        pending.add(descriptor.key);
        failed.delete(descriptor.key);
        report();
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(config.layers[descriptor.key], {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) throw new Error('Invalid map layer');
            const layer = makeLayer(data, descriptor);
            descriptor.group.addLayer(layer);
            descriptor.loaded = true;
            if (descriptor.key === (config.scoped ? 'barangay-boundaries' : 'municipal-boundary')) {
                areaBounds = layer.getBounds();
                if (!savedMarker) fit(areaBounds);
            }
            if (descriptor.key === 'barangay-boundaries' && config.scoped && !data.features.length) {
                descriptor.loaded = false;
                throw new Error('No matching barangay boundary');
            }
            households.bringToFront();
        } catch (error) {
            failed.add(descriptor.key);
            console.error(`GIS ${descriptor.key}:`, error);
        } finally {
            clearTimeout(timeout);
            pending.delete(descriptor.key);
            report();
        }
    }
    descriptors.forEach(descriptor => {
        descriptor.group = L.layerGroup();
        layersControl.addOverlay(descriptor.group, descriptor.name);
        descriptor.group.on('add', () => load(descriptor));
        if (descriptor.visible) descriptor.group.addTo(map);
    });
    retry.addEventListener('click', () => descriptors.filter(d => failed.has(d.key)).forEach(load));
    if (window.ResizeObserver) new ResizeObserver(() => map.invalidateSize()).observe(document.getElementById('household-map'));
})();
