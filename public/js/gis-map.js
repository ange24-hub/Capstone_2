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
    const maxMapZoom = 22;
    const locationZoom = 21;
    const map = L.map('household-map', { preferCanvas: true, minZoom: 11, maxZoom: maxMapZoom }).setView([10.268841, 125.026688], 13);
    const streets = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxNativeZoom: 19, maxZoom: maxMapZoom, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    // Esri has zoom-18 imagery here, but some zoom-19 tiles are unavailable.
    // Request an error instead of Esri's gray placeholder so fallback can run.
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}?blankTile=false', {
        maxNativeZoom: 18, maxZoom: maxMapZoom, attribution: 'Tiles &copy; Esri'
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
    const locationZoomButton = (latitude, longitude, label) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'gis-location-zoom';
        button.textContent = label;
        button.addEventListener('click', event => {
            event.stopPropagation();
            map.closePopup();
            map.setView([latitude, longitude], Math.max(map.getZoom(), locationZoom), { animate: false });
        });
        return button;
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
        content.append(locationZoomButton(household.latitude, household.longitude, 'Zoom to household'));
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
    let barangayAreas = [];
    const barangayLabel = document.createElement('span');
    const barangayTooltip = L.tooltip({
        permanent: true, direction: 'top', offset: [0, -12], opacity: 1,
        className: 'gis-barangay-tooltip', interactive: false
    }).setContent(barangayLabel);
    const hideBarangay = () => {
        if (map.hasLayer(barangayTooltip)) map.removeLayer(barangayTooltip);
    };
    const showBarangay = latlng => {
        const point = [latlng.lng, latlng.lat];
        const area = barangayAreas.find(({ bounds, feature }) =>
            point[0] >= bounds[0] && point[0] <= bounds[2]
            && point[1] >= bounds[1] && point[1] <= bounds[3]
            && window.GisGeometry.containsPoint(feature.geometry, point));
        if (!area) { hideBarangay(); return; }
        barangayLabel.textContent = `Barangay ${area.feature.properties.Name}`;
        barangayTooltip.setLatLng(latlng);
        if (!map.hasLayer(barangayTooltip)) barangayTooltip.addTo(map);
    };
    const mapContainer = map.getContainer();
    const isMapControl = event => event.target.closest('.leaflet-control, .leaflet-popup');
    // Capture events before buildings or hazards stop Leaflet's event bubbling.
    mapContainer.addEventListener('pointermove', event => {
        if (event.pointerType === 'touch') return;
        if (event.buttons || isMapControl(event)) { hideBarangay(); return; }
        showBarangay(map.mouseEventToLatLng(event));
    }, { capture: true, passive: true });
    mapContainer.addEventListener('click', event => {
        if (!isMapControl(event)) showBarangay(map.mouseEventToLatLng(event));
    }, { capture: true, passive: true });
    mapContainer.addEventListener('pointerleave', hideBarangay);
    map.on('movestart', hideBarangay);
    async function fetchLayerData(descriptor) {
        if (!layerRequests.has(descriptor.key)) {
            const request = (async () => {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 30000);
                try {
                    const response = await fetch(config.layers[descriptor.key], {
                        headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: controller.signal
                    });
                    if ([401, 403, 419].includes(response.status)) throw new Error(`Sign in again (HTTP ${response.status})`);
                    if (!response.ok) throw new Error(`HTTP ${response.status}`);
                    if (response.headers.get('content-type')?.includes('text/html')) {
                        throw new Error('A web page was returned instead of map data; refresh your sign-in');
                    }
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
    let municipalBounds = null;
    const failed = new Set();
    const pending = new Set();
    const retry = document.getElementById('gis-retry');
    let basemapNotice = '';
    let satelliteFallbackPending = false;
    const report = () => {
        const errors = descriptors.filter(d => failed.has(d.key)).map(d => `${d.name} (${d.error})`);
        const layerStatus = pending.size ? 'Loading map layers...' : errors.length
            ? `Could not load: ${errors.join(', ')}. Retry or refresh your sign-in.`
            : 'Map ready. Select a household or building polygon for details. Open the layers button to show hazard overlays.';
        const activeBasemap = map.hasLayer(satellite) ? satellite : streets;
        const imageryNotice = map.getZoom() > activeBasemap.options.maxNativeZoom
            ? `${activeBasemap === satellite ? 'Satellite imagery' : 'Street map'} is enlarged at this zoom.` : '';
        status.textContent = [layerStatus, basemapNotice || imageryNotice].filter(Boolean).join(' ');
        retry.hidden = errors.length === 0 || pending.size > 0;
    };
    satellite.on('tileerror', () => {
        if (!map.hasLayer(satellite) || satelliteFallbackPending) return;
        satelliteFallbackPending = true;
        // Let Leaflet finish processing the failed tile before removing its layer.
        setTimeout(() => {
            satelliteFallbackPending = false;
            if (!map.hasLayer(satellite)) return;
            map.removeLayer(satellite);
            streets.addTo(map);
            basemapNotice = 'Satellite imagery could not load for this area. Showing OpenStreetMap. Select Satellite from the layers button to retry.';
            report();
        }, 0);
    });
    streets.on('tileerror', () => {
        if (!map.hasLayer(streets)) return;
        basemapNotice = 'The street-map background could not load. Check your connection or try Satellite from the layers button.';
        report();
    });
    map.on('baselayerchange', () => {
        basemapNotice = '';
        report();
    });
    map.on('zoomend', report);
    const fit = bounds => {
        if (bounds?.isValid()) map.fitBounds(bounds, { padding: [24, 24], maxZoom: 17 });
    };
    const constrainToMunicipality = () => {
        if (!municipalBounds?.isValid()) return;
        // Keep the whole municipality in reach without letting users zoom out to the world map.
        map.setMinZoom(Math.max(11, map.getBoundsZoom(municipalBounds, false, L.point(48, 48))));
        map.setMaxBounds(municipalBounds.pad(0.15));
        map.panInsideBounds(municipalBounds.pad(0.15), { animate: false });
    };
    document.getElementById('gis-fit-area').addEventListener('click', () => fit(areaBounds));
    document.getElementById('gis-fit-households').addEventListener('click', () => {
        households.addTo(map);
        fit(households.getBounds());
    });
    document.getElementById('gis-restore-layers').addEventListener('click', () => {
        households.addTo(map);
        descriptors.filter(descriptor => descriptor.visible).forEach(descriptor => {
            descriptor.group.addTo(map);
            // An already-visible group does not emit another add event after a failed request.
            if (failed.has(descriptor.key)) load(descriptor);
        });
        layersControl.expand();
        map.invalidateSize();
        fit(areaBounds);
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
                    content.append(locationZoomButton(latitude, longitude, 'Zoom to building'));
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
                    if (key !== 'barangay-boundaries') {
                        const label = document.createElement('span');
                        label.textContent = feature.properties?.Name || 'Tomas Oppus';
                        layer.bindTooltip(label, { sticky: true });
                    }
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
            if (descriptor.key === 'barangay-boundaries') {
                barangayAreas = data.features.map(feature => {
                    const bounds = [Infinity, Infinity, -Infinity, -Infinity];
                    const polygons = feature.geometry.type === 'Polygon'
                        ? [feature.geometry.coordinates] : feature.geometry.coordinates;
                    polygons.forEach(rings => rings.forEach(ring => ring.forEach(([x, y]) => {
                        bounds[0] = Math.min(bounds[0], x); bounds[1] = Math.min(bounds[1], y);
                        bounds[2] = Math.max(bounds[2], x); bounds[3] = Math.max(bounds[3], y);
                    })));
                    return { feature, bounds };
                });
            }
            if (descriptor.key === 'municipal-boundary') {
                municipalBounds = layer.getBounds();
                constrainToMunicipality();
            }
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
            descriptor.error = error.name === 'AbortError' ? 'Request timed out' : error.message;
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
    if (window.ResizeObserver) new ResizeObserver(() => {
        map.invalidateSize();
        constrainToMunicipality();
    }).observe(document.getElementById('household-map'));
})();
