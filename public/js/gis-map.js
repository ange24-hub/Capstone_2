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
    map.createPane('buildings');
    map.getPane('buildings').style.zIndex = 460;
    map.createPane('households');
    map.getPane('households').style.zIndex = 500;
    // SVG paths remain clickable above the boundary canvas without blocking the rest of the map.
    const householdRenderer = L.svg({ pane: 'households' });
    const buildingRenderer = L.canvas({ pane: 'buildings' });
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
        const hazardButton = document.createElement('button');
        hazardButton.type = 'button';
        hazardButton.className = 'gis-hazard-check';
        hazardButton.textContent = 'Check hazard overlays';
        const hazardResult = document.createElement('div');
        hazardResult.className = 'gis-hazard-result';
        hazardResult.setAttribute('role', 'status');
        hazardButton.addEventListener('click', event => {
            event.stopPropagation();
            inspectHazards(household, hazardButton, hazardResult);
        });
        content.append(hazardButton, hazardResult);
        const marker = L.circleMarker([household.latitude, household.longitude], {
            pane: 'households', renderer: householdRenderer, bubblingMouseEvents: false,
            radius: 5, color: '#fff', weight: 1.5, fillColor: '#2563eb', fillOpacity: 1
        }).bindTooltip(label).bindPopup(content, {
            className: 'gis-household-popup', minWidth: 200, maxWidth: 280, maxHeight: 300
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
        { key: 'building-points', name: 'Building polygons (symbols)', visible: true },
        { key: 'flood', name: 'Flood susceptibility' },
        { key: 'landslide', name: 'Landslide susceptibility' },
        { key: 'storm-surge', name: 'Storm surge susceptibility' }
    ];
    const layerRequests = new Map();
    async function fetchLayerData(descriptor) {
        if (!layerRequests.has(descriptor.key)) {
            const request = (async () => {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 30000);
                try {
                    const response = await fetch(config.layers[descriptor.key], {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal
                    });
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    const data = await response.json();
                    if (data.type !== 'FeatureCollection' || !Array.isArray(data.features)) throw new Error('Invalid map layer');
                    return data;
                } finally {
                    clearTimeout(timeout);
                }
            })().catch(error => {
                layerRequests.delete(descriptor.key);
                throw error;
            });
            layerRequests.set(descriptor.key, request);
        }
        return layerRequests.get(descriptor.key);
    }
    let areaBounds = null;
    const failed = new Set();
    const pending = new Set();
    const retry = document.getElementById('gis-retry');
    const report = () => {
        const errors = descriptors.filter(d => failed.has(d.key)).map(d => d.name);
        status.textContent = pending.size ? 'Loading map layers...' : errors.length
            ? `Could not load: ${errors.join(', ')}. Retry or refresh your sign-in.`
            : 'Map ready. Select a household or building polygon for details. Open the layers button to show hazard overlays.';
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
    async function inspectHazards(household, button, result) {
        button.disabled = true;
        result.textContent = 'Checking supplied map areas...';
        const point = [Number(household.longitude), Number(household.latitude)];
        const hazards = descriptors.filter(descriptor => ['flood', 'landslide', 'storm-surge'].includes(descriptor.key));
        const checks = await Promise.allSettled(hazards.map(async descriptor => {
            const data = await fetchLayerData(descriptor);
            const matches = data.features.filter(feature => window.GisGeometry.containsPoint(feature.geometry, point));
            if (!matches.length) return [descriptor.name, 'No supplied overlay at this point'];
            if (descriptor.key === 'storm-surge') return [descriptor.name, 'Within mapped storm-surge area; level not specified'];
            const labels = [...new Set(matches.map(feature => classification(feature, descriptor.key).label))];
            return [descriptor.name, `${labels.join(', ')} susceptibility`];
        }));
        const list = document.createElement('ul');
        checks.forEach((check, index) => {
            const item = document.createElement('li');
            const [name, value] = check.status === 'fulfilled' ? check.value
                : [hazards[index].name, 'Layer unavailable; try again'];
            const label = document.createElement('strong');
            label.textContent = `${name}: `;
            item.append(label, document.createTextNode(value));
            list.append(item);
        });
        const note = document.createElement('small');
        note.textContent = 'Map overlap only. Confirm site conditions with the LGU before decisions.';
        result.replaceChildren(list, note);
        button.disabled = false;
    }
    // Screen-sized polygon symbols keep point-only building references visible at every zoom.
    function buildingCorners(latlng) {
        const center = map.project(latlng);
        return [[-4, -3], [4, -3], [4, 3], [-4, 3]].map(([x, y]) =>
            map.unproject(L.point(center.x + x, center.y + y)));
    }
    map.on('zoomend', () => {
        const buildings = descriptors.find(descriptor => descriptor.key === 'building-points');
        buildings.group.eachLayer(group => group.eachLayer(layer => {
            const [longitude, latitude] = layer.feature.geometry.coordinates;
            layer.setLatLngs(buildingCorners(L.latLng(latitude, longitude)));
        }));
    });
    function makeLayer(data, descriptor) {
        const key = descriptor.key;
        const boundary = key.endsWith('boundary') || key === 'barangay-boundaries';
        const building = key === 'building-points';
        return L.geoJSON(data, {
            pane: boundary ? 'boundaries' : building ? 'buildings' : 'hazards',
            style: feature => boundary ? {
                color: key === 'municipal-boundary' ? '#172b43' : '#64748b',
                weight: key === 'municipal-boundary' ? 2.5 : 1.5,
                fill: false, dashArray: key === 'barangay-boundaries' ? '5 5' : null
            } : building ? {
                color: feature.properties.within_barangay_boundary ? '#0f766e' : '#9a3412', weight: 1,
                fillColor: feature.properties.within_barangay_boundary ? '#14b8a6' : '#f97316', fillOpacity: 0.85
            } : { color: classification(feature, key).color, weight: 0.5, fillOpacity: 0.45 },
            pointToLayer: (feature, latlng) => L.polygon(buildingCorners(latlng), {
                pane: 'buildings', renderer: buildingRenderer, bubblingMouseEvents: false
            }),
            onEachFeature: (feature, layer) => {
                if (building) {
                    const [longitude, latitude] = feature.geometry.coordinates;
                    const content = popup('Building reference polygon', [
                        ['Barangay', feature.properties.barangay], ['Latitude', latitude], ['Longitude', longitude],
                        ['Boundary check', feature.properties.within_barangay_boundary ? 'Inside assigned barangay on this map' : 'Outside assigned barangay on this map; confirm location and barangay'],
                        ['Source', 'Supplied Tomas Oppus building-point layer; not a registry household'],
                        ['Geometry', 'Polygon symbol at the source location; actual building outline unavailable']
                    ]);
                    const addButton = document.createElement('button');
                    addButton.type = 'button';
                    addButton.textContent = document.querySelector('#gis-household-form input[name="_method"]') ? 'Use this location' : 'Add household here';
                    addButton.addEventListener('click', () => {
                        const barangayInput = document.getElementById('gis-household-barangay');
                        const option = Array.from(barangayInput.options).find(option => normalize(option.textContent) === normalize(feature.properties.barangay));
                        if (option) barangayInput.value = option.value;
                        chooseLocation(latitude, longitude);
                        map.closePopup();
                        formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        document.getElementById('gis-household-name').focus({ preventScroll: true });
                    });
                    const normalize = name => {
                        const normalized = name.replace(/\([^)]*\)/g, '').replace(/[^a-z0-9]/gi, '').toLowerCase();
                        return ({ ponong: 'punong', higosan: 'higosoan' })[normalized] || normalized;
                    };
                    const barangayInput = document.getElementById('gis-household-barangay');
                    if (feature.properties.within_barangay_boundary && Array.from(barangayInput.options).some(option => option.value && normalize(option.textContent) === normalize(feature.properties.barangay))) {
                        content.append(addButton);
                    }
                    layer.bindPopup(content);
                } else if (boundary) {
                    const label = document.createElement('span');
                    label.textContent = feature.properties?.Name || 'Tomas Oppus';
                    layer.bindTooltip(label, { sticky: true });
                } else {
                    layer.bindPopup(popup(descriptor.name, [['Susceptibility', classification(feature, key).label], ['Source', 'Supplied Tomas Oppus hazard shapefile']]));
                }
            }
        });
    }
    async function load(descriptor) {
        if (descriptor.loaded || pending.has(descriptor.key)) return;
        pending.add(descriptor.key);
        failed.delete(descriptor.key);
        report();
        try {
            const data = await fetchLayerData(descriptor);
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
