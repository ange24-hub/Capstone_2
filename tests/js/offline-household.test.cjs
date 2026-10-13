const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const test = require('node:test');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/households/offline-create.blade.php'), 'utf8');
const script = view.match(/<script>([\s\S]*?)<\/script>/)?.[1];

function page(syncResult = { success: true, household_id: 42 }, syncStatus = 200, catalog = null, offline = false, cacheState = {}) {
    const saved = [];
    const elements = Object.fromEntries([
        'household_number', 'household_name', 'latitude', 'longitude', 'accuracy',
        'saveButton', 'saveStatus', 'locationButton', 'locationStatus',
        'syncButton', 'syncStatus', 'pendingCount', 'pendingList',
        'householdSuggestions', 'registryStatus', 'refreshRegistryButton',
        'clearDeviceButton', 'clearDeviceStatus',
    ].map(id => [id, {
        value: '', innerHTML: '', disabled: false, events: {},
        addEventListener(name, handler) { this.events[name] = handler; },
        focus() {},
        children: [],
        replaceChildren() { this.children = []; }, appendChild(child) { this.children.push(child); },
    }]));
    const stores = new Set();
    const database = {
        objectStoreNames: { contains: name => stores.has(name) },
        createObjectStore(name, options) {
            assert.equal(options.keyPath, name === 'households' ? 'offline_uuid' : 'id');
            stores.add(name);
        },
        transaction(name, mode) {
            const transaction = {
                objectStore(storeName = name) {
                    if (storeName === 'registry_lookup') {
                        const finish = value => {
                            const request = { result: value };
                            queueMicrotask(() => { request.onsuccess(); transaction.oncomplete(); });
                            return request;
                        };
                        return {
                            get() { return finish(cacheState.value); },
                            put(value) { cacheState.value = value; return finish(value); },
                            delete() { cacheState.value = null; return finish(null); },
                            clear() { cacheState.value = null; queueMicrotask(() => transaction.oncomplete()); },
                        };
                    }
                    const result = value => {
                        const request = { result: value };
                        queueMicrotask(() => request.onsuccess());
                        return request;
                    };
                    return {
                        add(record) { saved.push(record); queueMicrotask(() => transaction.oncomplete()); },
                        getAll() { return result(saved); },
                        get(uuid) { return result(saved.find(record => record.offline_uuid === uuid)); },
                        put() { queueMicrotask(() => transaction.oncomplete()); },
                        clear() { saved.length = 0; },
                    };
                },
            };
            return transaction;
        },
    };
    const indexedDB = {
        open(name, version) {
            assert.equal(name, 'SBISPGM_OFFLINE_DB');
            assert.equal(version, 2);
            const request = { result: database };
            queueMicrotask(() => {
                if (!stores.size) request.onupgradeneeded({ target: request });
                request.onsuccess();
            });
            return request;
        },
    };
    const context = {
        window: { indexedDB, crypto: { randomUUID: () => 'offline-test-uuid' }, confirm: () => true }, indexedDB,
        document: { getElementById: id => elements[id], createElement: () => ({ style: {}, children: [], appendChild(child) { this.children.push(child); }, addEventListener(name, handler) { this[name] = handler; } }) },
        navigator: { onLine: !offline, geolocation: {
            getCurrentPosition(success) {
                success({ timestamp: Date.UTC(2026, 9, 7), coords: {
                    latitude: 10.3456789, longitude: 124.1234567, accuracy: 8.5,
                } });
            },
        } },
        crypto: { randomUUID: () => 'offline-test-uuid' },
        fetch: async url => url.endsWith('/registry')
            ? { ok: Boolean(catalog), status: catalog ? 200 : 503, json: async () => catalog }
            : url.endsWith('/session')
            ? { ok: true, json: async () => ({ csrf_token: 'test-token', barangay_id: 1, barangay_name: 'Banday' }) }
            : { ok: syncStatus === 200, status: syncStatus, json: async () => syncResult },
        console,
    };
    vm.runInNewContext(script, context);
    return { elements, saved, navigator: context.navigator, window: context.window, ready: new Promise(resolve => setImmediate(resolve)) };
}

test('saves captured GPS household to IndexedDB and clears the form only after commit', async () => {
    const { elements, saved, ready } = page();
    await ready;
    elements.household_number.value = ' 135 ';
    elements.household_name.value = ' Family Head ';
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));

    assert.equal(saved.length, 1);
    assert.equal(saved[0].offline_uuid, 'offline-test-uuid');
    assert.equal(saved[0].household_number, '135');
    assert.equal(saved[0].household_name, 'Family Head');
    assert.equal(saved[0].latitude, 10.3456789);
    assert.equal(saved[0].longitude, 124.1234567);
    assert.equal(saved[0].gps_accuracy, 8.5);
    assert.equal(saved[0].location_captured_at, '2026-10-07T00:00:00.000Z');
    assert.equal(saved[0].sync_status, 'pending');
    assert.equal(elements.household_number.value, '');
    assert.match(elements.saveStatus.innerHTML, /saved on this device/);
});

test('keeps incomplete household details and does not save them', async () => {
    const { elements, saved, ready } = page();
    await ready;
    elements.household_number.value = '135';
    elements.household_name.value = 'Family Head';
    await elements.saveButton.events.click();

    assert.equal(saved.length, 0);
    assert.equal(elements.household_number.value, '135');
    assert.match(elements.saveStatus.innerHTML, /get the household location/);
});

test('shows saved records as pending and removes them from the list only after confirmed sync', async () => {
    const { elements, saved, ready } = page();
    await ready;
    elements.household_number.value = '135';
    elements.household_name.value = '<script>Family</script>';
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(elements.pendingCount.textContent, 1);
    await elements.syncButton.events.click();
    assert.equal(saved[0].sync_status, 'synced');
    assert.equal(saved[0].household_id, 42);
    assert.equal(elements.pendingCount.textContent, 0);
    assert.equal(elements.syncStatus.textContent, 'All records synced successfully.');
});

for (const [name, result, status] of [
    ['server validation failure', { message: 'Invalid GPS' }, 422],
    ['unconfirmed success response', {}, 200],
]) {
    test(name + ' keeps local records pending', async () => {
        const { elements, saved, ready } = page(result, status);
        await ready;
        elements.household_number.value = '135';
        elements.household_name.value = 'Family Head';
        elements.locationButton.events.click();
        await elements.saveButton.events.click();
        await new Promise(resolve => setImmediate(resolve));
        await elements.syncButton.events.click();
        assert.equal(saved[0].sync_status, 'pending');
        assert.equal(elements.pendingCount.textContent, 1);
        assert.match(elements.syncStatus.textContent, /still pending/);
        assert.equal(elements.syncButton.disabled, false);
    });
}

const catalog = {
    barangay_id: 1, barangay_name: 'Banday', records: [
        { key: 'resident-1', household_id: 135, household_number: '135', name: 'Eiries Maquilang', search_names: ['Eiries Maquilang', 'Maquilang Eiries'] },
    ],
};

test('clearing device data removes local names and records after confirmation', async () => {
    const cache = {};
    const { elements, saved, ready } = page(undefined, undefined, catalog, false, cache);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.household_name.events.input();
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved.length, 1);
    await elements.clearDeviceButton.events.click();
    assert.equal(saved.length, 0);
    assert.equal(cache.value, null);
    assert.equal(elements.pendingCount.textContent, 0);
    assert.match(elements.clearDeviceStatus.textContent, /Local data cleared/);
});

test('cancelling device cleanup preserves unsynced work and cached names', async () => {
    const cache = {};
    const { elements, saved, window, ready } = page(undefined, undefined, catalog, false, cache);
    await ready;
    saved.push({ offline_uuid: 'pending', household_name: 'Pending', sync_status: 'pending' });
    window.confirm = () => false;
    await elements.clearDeviceButton.events.click();
    assert.equal(saved.length, 1);
    assert.equal(cache.value.barangay_id, 1);
    assert.equal(elements.clearDeviceButton.disabled, false);
});

test('a pending record from another barangay cannot be reassigned through local choices', async () => {
    const { elements, saved, ready } = page(undefined, undefined, catalog);
    await ready;
    saved.push({ offline_uuid: 'foreign', household_name: 'Eiries Maquilang', barangay_id: 2, sync_status: 'pending' });
    await elements.refreshRegistryButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(elements.pendingList.children[0].children.filter(child => child.type === 'button').length, 0);
    assert.equal(saved[0].barangay_id, 2);
});

test('typing a consolidated name fills its number and stores the selected household identity', async () => {
    const { elements, saved, ready } = page(undefined, undefined, catalog);
    await ready;
    elements.household_name.value = 'MAQUILANG, EIRIES';
    elements.household_name.events.input();
    assert.equal(elements.household_number.value, '135');
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved[0].household_id, 135);
    assert.equal(saved[0].barangay_id, 1);
});

test('partial names offer choices and editing to an unmatched name clears the old number', async () => {
    const { elements, ready } = page(undefined, undefined, catalog);
    await ready;
    elements.household_name.value = 'Eir';
    elements.household_name.events.input();
    assert.equal(elements.household_number.value, '');
    assert.equal(elements.householdSuggestions.children.length, 1);
    elements.householdSuggestions.children[0].click();
    assert.equal(elements.household_number.value, '135');
    elements.household_name.value = 'Unknown resident';
    elements.household_name.events.input();
    assert.equal(elements.household_number.value, '');
});

test('same names in different households require a choice instead of guessing', async () => {
    const duplicate = { ...catalog, records: [...catalog.records, { ...catalog.records[0], key: 'resident-2', household_id: 136, household_number: '136' }] };
    const { elements, ready } = page(undefined, undefined, duplicate);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.household_name.events.input();
    assert.equal(elements.household_number.value, '');
    assert.equal(elements.householdSuggestions.children.length, 2);
    elements.householdSuggestions.children[1].click();
    assert.equal(elements.household_number.value, '136');
});

test('cached consolidated names still fill the number after reopening offline', async () => {
    const cache = {};
    await page(undefined, undefined, catalog, false, cache).ready;
    const { elements, ready } = page(undefined, undefined, null, true, cache);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.household_name.events.input();
    assert.equal(elements.household_number.value, '135');
});

test('offline save keeps the name and GPS pending when household number is blank', async () => {
    const { elements, saved, ready } = page(undefined, undefined, null, true);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved.length, 1);
    assert.equal(saved[0].household_number, '');
    assert.equal(saved[0].sync_status, 'pending');
    assert.equal(elements.pendingCount.textContent, 1);
    assert.match(elements.saveStatus.innerHTML, /Match its household number/);
});

test('an offline record without a number matches and syncs after reconnecting', async () => {
    const { elements, saved, navigator, ready } = page(undefined, undefined, catalog, true);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(saved[0].household_number, '');
    navigator.onLine = true;
    await elements.syncButton.events.click();
    assert.equal(saved[0].household_number, '135');
    assert.equal(saved[0].household_id, 42);
    assert.equal(saved[0].sync_status, 'synced');
    assert.equal(elements.pendingCount.textContent, 0);
});

test('ambiguous offline record stays pending until the correct household is chosen', async () => {
    const ambiguous = { ...catalog, records: [...catalog.records, { ...catalog.records[0], key: 'resident-2', household_id: 136, household_number: '136' }] };
    const { elements, saved, navigator, ready } = page(undefined, undefined, ambiguous, true);
    await ready;
    elements.household_name.value = 'Eiries Maquilang';
    elements.locationButton.events.click();
    await elements.saveButton.events.click();
    await new Promise(resolve => setImmediate(resolve));
    navigator.onLine = true;
    await elements.syncButton.events.click();
    assert.equal(saved[0].sync_status, 'pending');
    assert.equal(elements.pendingCount.textContent, 1);
    const choice = elements.pendingList.children[0].children.find(child => child.textContent?.includes('Household 136'));
    assert.ok(choice);
    await choice.click();
    assert.equal(saved[0].household_number, '136');
    await elements.syncButton.events.click();
    assert.equal(saved[0].sync_status, 'synced');
});
