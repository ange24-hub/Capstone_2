const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const geometryScope = { window: {} };
vm.runInNewContext(fs.readFileSync('public/js/gis-geometry.js', 'utf8'), geometryScope);
const geometry = geometryScope.window.GisGeometry;

function startMap({ failBuildingsOnce = false } = {}) {
    const nodes = new Map();
    const element = () => ({
        value: '', open: false, hidden: false, options: [], events: {},
        append() {}, setAttribute() {},
        addEventListener(event, handler, options) { this.events[event] = handler; (this.eventOptions ||= {})[event] = options; },
    });
    const node = id => {
        if (!nodes.has(id)) nodes.set(id, element());
        return nodes.get(id);
    };
    const bounds = { isValid: () => true, pad() { return this; } };
    const active = new Set();
    const map = {
        events: {}, options: {},
        setView() { return this; }, createPane() {}, getPane: () => ({ style: {} }),
        on(event, handler) { (this.events[event] ||= []).push(handler); return this; },
        getZoom: () => 13, hasLayer: layer => active.has(layer),
        removeLayer(layer) { active.delete(layer); return this; },
        fitBounds() {}, getBoundsZoom: () => 11, setMinZoom() {}, setMaxBounds() {}, panInsideBounds() {},
        invalidateSize() {},
        project: () => ({ x: 100, y: 100 }), unproject: point => point,
        getContainer: () => node('household-map'), mouseEventToLatLng: event => event.latlng,
    };
    const layer = (options = {}) => ({
        options, children: [], events: {},
        on(event, handler) { this.events[event] = handler; return this; },
        addTo(target) {
            if (target === map && !active.has(this)) { active.add(this); this.events.add?.(); }
            return this;
        },
        addLayer(child) { this.children.push(child); return this; },
        eachLayer(handler) { this.children.forEach(handler); },
        getBounds: () => bounds, bringToFront() {},
        bindPopup() { return this; }, bindTooltip() { return this; }, setLatLngs() {},
        setContent(content) { this.content = content; return this; },
        setLatLng(position) { this.position = position; return this; },
    });
    const overlays = new Map();
    const control = {
        expanded: false, addTo() { return this; },
        addOverlay(group, name) { overlays.set(name, group); },
        expand() { this.expanded = true; return this; },
    };
    const keys = ['municipal-boundary', 'barangay-boundaries', 'building-points', 'flood', 'landslide', 'storm-surge'];
    node('gis-map-config').textContent = JSON.stringify({ markers: [], layers: Object.fromEntries(keys.map(key => [key, key])), scoped: false });
    const requests = new Map();
    let tooltip;
    const fetch = async key => {
        requests.set(key, (requests.get(key) || 0) + 1);
        if (key === 'building-points' && failBuildingsOnce && requests.get(key) === 1) {
            return { ok: false, status: 503 };
        }
        return { ok: true, headers: { get: () => 'application/geo+json' }, json: async () => ({
            type: 'FeatureCollection',
            features: key === 'building-points' ? [{
                type: 'Feature', geometry: { type: 'Point', coordinates: [125.02, 10.26] },
                properties: { barangay: 'San Isidro', within_barangay_boundary: true },
            }] : key === 'barangay-boundaries' ? [
                { type: 'Feature', properties: { Name: 'San Isidro' }, geometry: { type: 'Polygon', coordinates: [[[125, 10.2], [125.05, 10.2], [125.05, 10.3], [125, 10.3], [125, 10.2]]] } },
                { type: 'Feature', properties: { Name: 'Canlupao' }, geometry: { type: 'Polygon', coordinates: [[[125.05, 10.2], [125.1, 10.2], [125.1, 10.3], [125.05, 10.3], [125.05, 10.2]]] } },
            ] : [],
        }) };
    };
    const L = {
        map: () => map, tileLayer: (url, options) => layer(options),
        svg: () => ({}), canvas: () => ({}), featureGroup: () => layer(), layerGroup: () => layer(),
        point: (x, y) => ({ x, y }), latLng: (lat, lng) => ({ lat, lng }), polygon: () => layer(),
        tooltip: () => (tooltip = layer()),
        control: { layers(base, initial) { Object.entries(initial).forEach(([name, group]) => overlays.set(name, group)); return control; } },
        geoJSON(data, options) {
            const group = layer();
            data.features.forEach(feature => {
                const featureLayer = feature.geometry.type === 'Point'
                    ? options.pointToLayer(feature, { lat: 10.26, lng: 125.02 }) : layer();
                featureLayer.feature = feature;
                options.onEachFeature(feature, featureLayer);
                group.addLayer(featureLayer);
            });
            return group;
        },
    };
    const document = { getElementById: node, createElement: element, querySelector: () => null };
    vm.runInNewContext(fs.readFileSync('public/js/gis-map.js', 'utf8'), {
        document, window: { L, GisGeometry: geometry }, L, fetch, AbortController, setTimeout, clearTimeout,
        console: { error() {} },
    });
    return { map, active, overlays, control, requests, node, tooltip };
}

const settle = () => new Promise(resolve => setImmediate(resolve));

test('buildings and boundaries load by default and can be restored without duplicate requests', async () => {
    const app = startMap();
    await settle();
    assert.equal(app.overlays.size, 7);
    for (const name of ['Municipal boundary', 'Barangay boundaries', 'Building polygons (symbols)']) {
        const group = app.overlays.get(name);
        assert.equal(app.active.has(group), true);
        assert.equal(group.children.length, 1);
        app.map.removeLayer(group);
    }
    app.node('gis-restore-layers').events.click();
    await settle();
    for (const name of ['Municipal boundary', 'Barangay boundaries', 'Building polygons (symbols)']) {
        assert.equal(app.active.has(app.overlays.get(name)), true);
    }
    assert.equal(app.requests.get('building-points'), 1);
    assert.equal(app.control.expanded, true);
});

test('restore retries an empty visible building layer after its request failed', async () => {
    const app = startMap({ failBuildingsOnce: true });
    await settle();
    const buildings = app.overlays.get('Building polygons (symbols)');
    assert.equal(app.active.has(buildings), true);
    assert.equal(buildings.children.length, 0);
    assert.equal(app.node('gis-retry').hidden, false);
    app.node('gis-restore-layers').events.click();
    await settle();
    assert.equal(app.requests.get('building-points'), 2);
    assert.equal(buildings.children[0].children.length, 1);
    assert.equal(app.node('gis-retry').hidden, true);
});

test('hazard overlays remain available and load when selected', async () => {
    const app = startMap();
    await settle();
    assert.equal(app.requests.has('flood'), false);
    app.overlays.get('Flood susceptibility').addTo(app.map);
    await settle();
    assert.equal(app.requests.get('flood'), 1);
    app.node('gis-restore-layers').events.click();
    await settle();
    assert.equal(app.active.has(app.overlays.get('Flood susceptibility')), true);
    assert.equal(app.requests.has('landslide'), false);
});

test('barangay label follows the cursor across areas and works with boundary display disabled', async () => {
    const app = startMap();
    await settle();
    const canvas = app.node('household-map');
    const target = { closest: () => null };
    assert.equal(canvas.eventOptions.pointermove.capture, true);
    canvas.events.pointermove({ target, pointerType: 'mouse', latlng: { lat: 10.26, lng: 125.02 } });
    assert.equal(app.tooltip.content.textContent, 'Barangay San Isidro');
    assert.equal(app.active.has(app.tooltip), true);
    app.map.removeLayer(app.overlays.get('Barangay boundaries'));
    canvas.events.pointermove({ target, pointerType: 'mouse', latlng: { lat: 10.26, lng: 125.075 } });
    assert.equal(app.tooltip.content.textContent, 'Barangay Canlupao');
    canvas.events.pointermove({ target, pointerType: 'mouse', latlng: { lat: 11, lng: 125 } });
    assert.equal(app.active.has(app.tooltip), false);
});

test('phone taps show the barangay and controls or leaving the map dismiss the label', async () => {
    const app = startMap();
    await settle();
    const canvas = app.node('household-map');
    canvas.events.click({ target: { closest: () => null }, latlng: { lat: 10.26, lng: 125.02 } });
    assert.equal(app.tooltip.content.textContent, 'Barangay San Isidro');
    assert.equal(app.active.has(app.tooltip), true);
    canvas.events.pointermove({ target: { closest: () => ({}) }, pointerType: 'mouse' });
    assert.equal(app.active.has(app.tooltip), false);
    canvas.events.click({ target: { closest: () => null }, latlng: { lat: 10.26, lng: 125.075 } });
    assert.equal(app.active.has(app.tooltip), true);
    canvas.events.pointerleave();
    assert.equal(app.active.has(app.tooltip), false);
});
