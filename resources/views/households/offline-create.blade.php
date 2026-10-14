<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#293e55">
    <title>Household survey | RBIM</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f6f8; color: #172b45; font: 16px/1.5 system-ui, sans-serif; }
        .container { width: min(100% - 32px, 720px); margin: 32px auto; }
        .survey-header { margin-bottom: 24px; }
        .back-link { color: #2457a7; text-underline-offset: 3px; }
        h1 { margin: 16px 0 4px; font-size: clamp(24px, 5vw, 28px); line-height: 1.2; }
        h2 { margin: 0; font-size: 19px; }
        p { margin: 8px 0 16px; }
        .survey-header p, .field-help { color: #5c6d82; }
        .card { margin-bottom: 16px; padding: 24px; background: #fff; border: 1px solid #dce3ed; border-radius: 8px; }
        .step-heading { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; }
        .step-number { display: grid; place-items: center; width: 32px; height: 32px; flex: 0 0 32px; border-radius: 6px; background: #edf1f4; color: #2457a7; font-weight: 700; }
        label { display: block; margin: 16px 0 6px; font-weight: 600; }
        input { width: 100%; min-height: 44px; padding: 10px 12px; border: 1px solid #a9bac7; border-radius: 6px; background: #fff; color: inherit; font: inherit; }
        input[readonly] { background: #f8fafc; color: #5c6d82; }
        button { min-height: 44px; margin-top: 12px; padding: 10px 16px; border: 1px solid #afbec9; border-radius: 6px; color: #172b45; background: #fff; font: inherit; font-weight: 600; cursor: pointer; }
        button:hover { background: #f8fafc; }
        button:disabled { opacity: .6; cursor: wait; }
        .primary-button { border-color: #2457a7; background: #2457a7; color: white; }
        .primary-button:hover { background: #194587; }
        .save-button { width: 100%; margin-top: 20px; }
        .field-help { display: block; margin-top: 6px; font-size: 14px; }
        #registryStatus { margin-bottom: 0; font-size: 14px; color: #5c6d82; }
        #householdSuggestions { display: grid; gap: 8px; }
        #householdSuggestions button { text-align: left; margin-top: 0; }
        #householdSuggestions:not(:empty) { margin-top: 12px; }
        .sync-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 16px; }
        .sync-actions button { margin-top: 0; }
        .sync-actions a { color: #2457a7; }
        .success, .warning, .error { margin-top: 14px; padding: 12px; border-radius: 6px; }
        .success { background: #e1f3e8; color: #205539; }
        .warning { background: #fff3d4; color: #76541d; }
        .error { background: #fbe4e4; color: #8b2929; }
        #pendingList { overflow-wrap: anywhere; }
        #pendingList > div { padding: 12px 0; border-bottom: 1px solid #dce3ed; }
        details { margin-top: 16px; }
        summary { min-height: 44px; padding: 10px 0; color: #4d5b68; cursor: pointer; font-weight: 600; }
        .coordinates { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
        .device-settings { margin-top: 0; }
        .device-settings p { font-size: 14px; color: #5c6d82; }
        #clearDeviceButton { color: #8b2929; border-color: #e2bebe; }
        :is(a, button, input, summary):focus-visible { outline: 3px solid #2457a7; outline-offset: 3px; }
        @media (max-width: 480px) { .container { margin-block: 20px; } .card { padding: 18px; } .coordinates { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<main class="container">
    <header class="survey-header">
        <a class="back-link" href="/dashboard">&larr; Overview</a>
        <h1>Household survey</h1>
        <p>Choose a household, capture its location, and save on this device. Sync when online.</p>
    </header>

    <section class="card" aria-labelledby="household-title">
        <div class="step-heading"><span class="step-number" aria-hidden="true">1</span><h2 id="household-title">Choose a household</h2></div>
        <label for="household_name">Household head or resident name</label>
        <input type="text" id="household_name" autocomplete="off" aria-describedby="registryStatus" placeholder="Type a name to find a household">
        <div id="householdSuggestions" aria-label="Matching households"></div>
        <p id="registryStatus" role="status" aria-live="polite">Loading household names...</p>
        <button type="button" id="refreshRegistryButton">Refresh names</button>
        <label for="household_number">Household number</label>
        <input type="text" id="household_number" readonly placeholder="Filled when a household is matched" aria-describedby="household-number-help">
        <small class="field-help" id="household-number-help">You can save without a match. Match the name before syncing.</small>
    </section>

    <section class="card" aria-labelledby="location-title">
        <div class="step-heading"><span class="step-number" aria-hidden="true">2</span><h2 id="location-title">Capture location and save</h2></div>
        <button type="button" id="locationButton">Get current location</button>
        <div id="locationStatus" role="status" aria-live="polite"></div>
        <details>
            <summary>Location details</summary>
            <div class="coordinates">
                <div><label for="latitude">Latitude</label><input type="text" id="latitude" readonly></div>
                <div><label for="longitude">Longitude</label><input type="text" id="longitude" readonly></div>
                <div><label for="accuracy">GPS accuracy</label><input type="text" id="accuracy" readonly></div>
            </div>
        </details>
        <button type="button" id="saveButton" class="primary-button save-button">Save on this device</button>
        <div id="saveStatus" role="status" aria-live="polite"></div>
    </section>

    <section class="card" aria-labelledby="pending-title">
        <div class="step-heading"><span class="step-number" aria-hidden="true">3</span><h2 id="pending-title">Sync saved records (<span id="pendingCount">0</span>)</h2></div>
        <p class="field-help">Saved records stay on this device until synchronization succeeds. When online, sign in as your barangay secretary to upload these records.</p>
        <div class="sync-actions">
            <button type="button" id="syncButton" class="primary-button">Sync saved records</button>
            <a href="/login" target="_blank" rel="noopener">Sign in <span class="field-help">Opens in a new tab</span></a>
        </div>
        <div id="syncStatus" role="status" aria-live="polite"></div>
        <div id="pendingList"></div>
    </section>

    <details class="card device-settings">
        <summary>Data on this device</summary>
        <p>Names and locations stay in this browser for offline use. Use an authorized, locked device. Sync before clearing data or sharing the device.</p>
        <button type="button" id="clearDeviceButton">Clear device data</button>
        <div id="clearDeviceStatus" role="status" aria-live="polite"></div>
    </details>
</main>

<script>
    const DB_NAME = 'SBISPGM_OFFLINE_DB';
    const DB_VERSION = 2;
    const STORE_NAME = 'households';
    const LOOKUP_STORE = 'registry_lookup';

    let db;
    let locationCapturedAt = null;
    let registryCatalog = null;
    let selectedHousehold = null;

    function openDatabase() {
        if (db) return Promise.resolve(db);
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB is unavailable.'));
                return;
            }

            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                db = request.result;
                resolve(db);
            };
            request.onupgradeneeded = (event) => {
                const database = event.target.result;
                if (!database.objectStoreNames.contains(STORE_NAME)) {
                    database.createObjectStore(STORE_NAME, { keyPath: 'offline_uuid' });
                }
                if (!database.objectStoreNames.contains(LOOKUP_STORE)) {
                    database.createObjectStore(LOOKUP_STORE, { keyPath: 'id' });
                }
            };
        });
    }

    function generateUUID() {
        if (window.crypto && crypto.randomUUID) {
            return crypto.randomUUID();
        }

        return Date.now().toString() + '-' + Math.random().toString(16).slice(2);
    }

    async function cachedRegistry(value) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(LOOKUP_STORE, value === undefined ? 'readonly' : 'readwrite');
            const store = transaction.objectStore(LOOKUP_STORE);
            const request = value === undefined ? store.get('current') : value === null ? store.delete('current') : store.put({ ...value, id: 'current' });
            let result;
            request.onsuccess = () => { result = request.result; };
            transaction.oncomplete = () => resolve(value === undefined ? result : value);
            transaction.onerror = transaction.onabort = () => reject(transaction.error);
        });
    }

    const normalizeName = value => String(value || '').normalize('NFKC').toLocaleLowerCase().replace(/[,\.]/g, ' ').replace(/\s+/g, ' ').trim();

    function selectHousehold(record) {
        selectedHousehold = record;
        document.getElementById('household_number').value = record.household_number;
        document.getElementById('household_name').value = record.name;
        document.getElementById('householdSuggestions').replaceChildren();
        document.getElementById('registryStatus').textContent = 'Consolidated household ' + record.household_number + ' · Barangay ' + registryCatalog.barangay_name;
    }

    function matchHouseholdName() {
        selectedHousehold = null;
        document.getElementById('household_number').value = '';
        const suggestions = document.getElementById('householdSuggestions');
        suggestions.replaceChildren();
        const name = normalizeName(document.getElementById('household_name').value);
        if (!registryCatalog) {
            document.getElementById('registryStatus').textContent = 'Sign in as secretary and refresh consolidated names while online first.';
            return;
        }
        if (!name) return;
        const records = registryCatalog.records;
        const exact = records.filter(record => record.search_names.some(alias => normalizeName(alias) === name));
        if (exact.length && new Set(exact.map(record => record.household_id)).size === 1) {
            selectedHousehold = exact[0];
            document.getElementById('household_number').value = exact[0].household_number;
            document.getElementById('registryStatus').textContent = 'Consolidated household ' + exact[0].household_number + ' · Barangay ' + registryCatalog.barangay_name;
            return;
        }
        const matches = records.filter(record => record.search_names.some(alias => normalizeName(alias).includes(name)));
        document.getElementById('registryStatus').textContent = matches.length
            ? 'Choose the correct resident and household below.' : 'No matching name in the consolidated records.';
        matches.slice(0, 20).forEach(record => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = record.name + ' — Household ' + record.household_number;
            button.addEventListener('click', () => selectHousehold(record));
            suggestions.appendChild(button);
        });
    }

    async function loadConsolidatedLookup() {
        const status = document.getElementById('registryStatus');
        const refresh = document.getElementById('refreshRegistryButton');
        refresh.disabled = true;
        try {
            registryCatalog = await cachedRegistry();
            if (navigator.onLine) {
                try {
                    const response = await fetch('/api/households/offline-sync/registry', {
                        credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }
                    });
                    if ([401, 403].includes(response.status)) {
                        registryCatalog = null;
                        await cachedRegistry(null);
                    } else if (response.ok) {
                        const catalog = await response.json();
                        if (Array.isArray(catalog.records) && catalog.barangay_id) {
                            registryCatalog = catalog;
                            await cachedRegistry(catalog);
                        }
                    }
                } catch (_) { /* Use the previously downloaded barangay lookup while disconnected. */ }
            }
            status.textContent = registryCatalog
                ? 'Names ready for Barangay ' + registryCatalog.barangay_name + '. Type a consolidated resident name.'
                : 'Sign in as secretary and refresh consolidated names while online first.';
            matchHouseholdName();
            await updatePendingList();
        } finally {
            refresh.disabled = false;
        }
    }

    async function getPendingRecords() {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(STORE_NAME, 'readonly');
            const request = transaction.objectStore(STORE_NAME).getAll();
            request.onsuccess = () => resolve(request.result.filter(record => record.sync_status === 'pending'));
            request.onerror = () => reject(request.error);
            transaction.onabort = () => reject(transaction.error);
        });
    }

    function consolidatedMatches(name) {
        if (!registryCatalog) return [];
        const search = normalizeName(name);
        if (!search) return [];
        const exact = registryCatalog.records.filter(record =>
            record.search_names.some(alias => normalizeName(alias) === search));
        return exact.length ? exact : registryCatalog.records.filter(record =>
            record.search_names.some(alias => normalizeName(alias).includes(search)));
    }

    async function assignPendingHousehold(uuid, match) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(uuid);
            let record;
            request.onsuccess = () => {
                record = request.result;
                if (!record || record.sync_status !== 'pending') return;
                record.household_id = match.household_id;
                if (record.barangay_id && record.barangay_id !== registryCatalog.barangay_id) {
                    transaction.abort();
                    return;
                }
                record.household_number = match.household_number;
                record.barangay_id = registryCatalog.barangay_id;
                store.put(record);
            };
            transaction.oncomplete = () => resolve(record);
            transaction.onerror = transaction.onabort = () => reject(transaction.error);
        });
    }

    async function updatePendingList() {
        const records = await getPendingRecords();
        document.getElementById('pendingCount').textContent = records.length;
        const list = document.getElementById('pendingList');
        list.replaceChildren();
        if (!records.length) {
            list.textContent = 'No pending household records.';
            return;
        }
        records.forEach(record => {
            const card = document.createElement('div');
            card.style.cssText = 'border:1px solid #ccc;padding:10px;margin-top:10px;overflow-wrap:anywhere';
            const heading = document.createElement('strong');
            heading.textContent = record.household_number || 'Household number: to be matched';
            card.appendChild(heading);
            [record.household_name, 'Lat: ' + record.latitude, 'Lng: ' + record.longitude,
                'Accuracy: ±' + record.gps_accuracy + ' m', 'Status: Pending Sync'].forEach(text => {
                const line = document.createElement('div');
                line.textContent = text;
                card.appendChild(line);
            });
            if (!record.household_id && registryCatalog &&
                (!record.barangay_id || record.barangay_id === registryCatalog.barangay_id)) {
                const matches = consolidatedMatches(record.household_name);
                const households = new Set(matches.map(match => match.household_id));
                if (households.size > 1 || !matches.length) {
                    const hint = document.createElement('div');
                    hint.textContent = matches.length ? 'Choose the correct household:' : 'No consolidated match yet. Check the name or refresh the list.';
                    card.appendChild(hint);
                }
                matches.slice(0, 20).forEach(match => {
                    const choice = document.createElement('button');
                    choice.type = 'button';
                    choice.textContent = match.name + ' — Household ' + match.household_number;
                    choice.addEventListener('click', async () => {
                        try {
                            await assignPendingHousehold(record.offline_uuid, match);
                            await updatePendingList();
                        } catch (_) {
                            document.getElementById('syncStatus').textContent = 'Could not assign household. Please try again.';
                        }
                    });
                    card.appendChild(choice);
                });
            }
            list.appendChild(card);
        });
    }

    async function markAsSynced(uuid, householdId) {
        const database = await openDatabase();
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(uuid);
            request.onsuccess = () => {
                const record = request.result;
                if (!record) return;
                record.sync_status = 'synced';
                record.synced_at = new Date().toISOString();
                record.household_id = householdId;
                store.put(record);
            };
            transaction.oncomplete = resolve;
            transaction.onerror = transaction.onabort = () => reject(transaction.error);
        });
    }

    let syncing = false;
    async function syncPendingRecords() {
        if (syncing) return;
        const status = document.getElementById('syncStatus');
        const button = document.getElementById('syncButton');
        if (!navigator.onLine) {
            status.textContent = 'No internet connection. Records remain saved on this device.';
            return;
        }
        syncing = true;
        button.disabled = true;
        let failure = '';
        try {
            const records = await getPendingRecords();
            if (!records.length) {
                status.textContent = 'No pending records.';
                return;
            }
            const sessionResponse = await fetch('/api/households/offline-sync/session', {
                headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store'
            });
            if (!sessionResponse.ok) {
                const problem = await sessionResponse.json().catch(() => ({}));
                throw new Error(sessionResponse.status === 401 ? 'Sign in as your barangay secretary, then try syncing again.' : (problem.message || 'Your account cannot sync household records.'));
            }
            const session = await sessionResponse.json();
            if (records.some(record => !record.household_number)) {
                await loadConsolidatedLookup();
                if (!registryCatalog || registryCatalog.barangay_id !== session.barangay_id) {
                    throw new Error('Refresh consolidated names for your assigned barangay before syncing.');
                }
            }
            await updatePendingList();
            status.textContent = 'Synchronizing to Barangay ' + session.barangay_name + '...';
            for (const record of records) {
                try {
                    if (record.barangay_id && record.barangay_id !== session.barangay_id) {
                        failure = 'A pending record belongs to another barangay.';
                        continue;
                    }
                    if (!record.household_number) {
                        const matches = consolidatedMatches(record.household_name).filter(match =>
                            match.search_names.some(alias => normalizeName(alias) === normalizeName(record.household_name)));
                        const householdIds = new Set(matches.map(match => match.household_id));
                        if (householdIds.size !== 1) {
                            failure = 'Choose the correct consolidated household for records without a number.';
                            continue;
                        }
                        await assignPendingHousehold(record.offline_uuid, matches[0]);
                        record.household_id = matches[0].household_id;
                        record.household_number = matches[0].household_number;
                        record.barangay_id = registryCatalog.barangay_id;
                    }
                    const response = await fetch('/api/households/offline-sync', {
                        method: 'POST', credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': session.csrf_token },
                        body: JSON.stringify(record)
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok || result.success !== true || !result.household_id) {
                        failure = result.message || 'The server could not confirm this record.';
                        if ([401, 403, 419, 429].includes(response.status)) break;
                        continue;
                    }
                    await markAsSynced(record.offline_uuid, result.household_id);
                } catch (error) {
                    failure = 'Connection or local storage failed. Please retry syncing.';
                    break;
                }
            }
            await updatePendingList();
            const remaining = await getPendingRecords();
            status.textContent = remaining.length
                ? remaining.length + ' record(s) still pending. ' + failure
                : 'All records synced successfully.';
        } catch (error) {
            status.textContent = error.message || 'Sync failed. Your records remain pending.';
        } finally {
            syncing = false;
            button.disabled = false;
        }
    }

    async function saveHouseholdOffline() {
        const householdNumber = document.getElementById('household_number').value.trim();
        const householdName = document.getElementById('household_name').value.trim();
        const latitudeText = document.getElementById('latitude').value.trim();
        const longitudeText = document.getElementById('longitude').value.trim();
        const accuracyText = document.getElementById('accuracy').value.trim();
        const saveStatus = document.getElementById('saveStatus');
        const saveButton = document.getElementById('saveButton');

        if (!householdName) {
            saveStatus.innerHTML = '<div class="error">Please enter Household Name / Head.</div>';
            document.getElementById('household_name').focus();
            return;
        }

        if (!latitudeText || !longitudeText) {
            saveStatus.innerHTML = '<div class="error">Please get the household location first.</div>';
            return;
        }

        const latitude = Number(latitudeText);
        const longitude = Number(longitudeText);
        const accuracy = parseFloat(accuracyText);

        if (!Number.isFinite(latitude) || latitude < -90 || latitude > 90 ||
            !Number.isFinite(longitude) || longitude < -180 || longitude > 180 ||
            !Number.isFinite(accuracy) || accuracy < 0) {
            saveStatus.innerHTML = '<div class="error">Please capture a valid GPS location first.</div>';
            return;
        }

        const record = {
            offline_uuid: generateUUID(),
            household_number: householdNumber,
            household_name: householdName,
            latitude,
            longitude,
            gps_accuracy: accuracy,
            location_captured_at: locationCapturedAt || new Date().toISOString(),
            sync_status: 'pending'
        };
        if (selectedHousehold && registryCatalog) {
            record.household_id = selectedHousehold.household_id;
            record.barangay_id = registryCatalog.barangay_id;
        }

        saveButton.disabled = true;
        saveStatus.innerHTML = '<div class="warning">Saving on this device...</div>';

        try {
            const database = await openDatabase();
            const transaction = database.transaction(STORE_NAME, 'readwrite');
            const store = transaction.objectStore(STORE_NAME);

            transaction.oncomplete = () => {
                saveStatus.innerHTML = '<div class="success">Household saved on this device. ' +
                    (householdNumber ? 'Pending sync.' : 'Match its household number when back online, then sync.') + '</div>';
                ['household_number', 'household_name', 'latitude', 'longitude', 'accuracy'].forEach((id) => {
                    document.getElementById(id).value = '';
                });
                locationCapturedAt = null;
                selectedHousehold = null;
                document.getElementById('householdSuggestions').replaceChildren();
                locationStatus.innerHTML = '';
                saveButton.disabled = false;
                updatePendingList().catch(() => {
                    document.getElementById('syncStatus').textContent = 'Saved, but the pending list could not refresh. Reload this page.';
                });
            };
            transaction.onerror = transaction.onabort = () => {
                saveStatus.innerHTML = '<div class="error">Unable to save household. Please try again.</div>';
                saveButton.disabled = false;
            };

            store.add(record);
        } catch (error) {
            console.error(error);
            saveStatus.innerHTML = '<div class="error">Offline database error. Your entries were kept; please try again.</div>';
            saveButton.disabled = false;
        }
    }

    const locationButton =
        document.getElementById('locationButton');

    const locationStatus =
        document.getElementById('locationStatus');

    document.getElementById('saveButton').addEventListener('click', saveHouseholdOffline);

    async function clearDeviceData() {
        const status = document.getElementById('clearDeviceStatus');
        const button = document.getElementById('clearDeviceButton');
        button.disabled = true;
        try {
            const pending = await getPendingRecords();
            const message = pending.length
                ? 'Delete all local data, including ' + pending.length + ' unsynced household record(s)? Those records cannot be recovered from the server.'
                : 'Clear downloaded resident names and saved household data from this device? Server records will stay saved.';
            if (!window.confirm(message)) return;
            const database = await openDatabase();
            await new Promise((resolve, reject) => {
                const transaction = database.transaction([STORE_NAME, LOOKUP_STORE], 'readwrite');
                transaction.objectStore(STORE_NAME).clear();
                transaction.objectStore(LOOKUP_STORE).clear();
                transaction.oncomplete = resolve;
                transaction.onerror = transaction.onabort = () => reject(transaction.error);
            });
            registryCatalog = null;
            selectedHousehold = null;
            locationCapturedAt = null;
            ['household_number', 'household_name', 'latitude', 'longitude', 'accuracy'].forEach(id => {
                document.getElementById(id).value = '';
            });
            matchHouseholdName();
            await updatePendingList();
            locationStatus.innerHTML = '';
            status.textContent = 'Local data cleared. Server records were kept.';
        } catch (_) {
            status.textContent = 'Could not clear local data. Please try again.';
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('clearDeviceButton').addEventListener('click', clearDeviceData);
    document.getElementById('syncButton').addEventListener('click', syncPendingRecords);
    document.getElementById('household_name').addEventListener('input', matchHouseholdName);
    document.getElementById('refreshRegistryButton').addEventListener('click', () => loadConsolidatedLookup().catch(() => {
        document.getElementById('registryStatus').textContent = 'Unable to load consolidated names. Please retry.';
    }));

    locationButton.addEventListener(
        'click',
        function () {

            locationCapturedAt = null;
            ['latitude', 'longitude', 'accuracy'].forEach((id) => {
                document.getElementById(id).value = '';
            });

            if (!navigator.geolocation) {
                locationStatus.innerHTML =
                    '<div class="error">' +
                    'Geolocation is not supported by this browser.' +
                    '</div>';

                return;
            }

            locationStatus.innerHTML =
                '<div class="warning">' +
                'Getting current location...' +
                '</div>';

            navigator.geolocation.getCurrentPosition(

                function (position) {

                    locationCapturedAt = new Date(position.timestamp).toISOString();

                    const latitude =
                        position.coords.latitude;

                    const longitude =
                        position.coords.longitude;

                    const accuracy =
                        position.coords.accuracy;

                    document.getElementById(
                        'latitude'
                    ).value =
                        latitude.toFixed(7);

                    document.getElementById(
                        'longitude'
                    ).value =
                        longitude.toFixed(7);

                    document.getElementById(
                        'accuracy'
                    ).value =
                        accuracy.toFixed(2) +
                        ' meters';

                    if (accuracy <= 10) {

                        locationStatus.innerHTML =
                            '<div class="success">' +
                            'Good GPS accuracy: ±' +
                            accuracy.toFixed(2) +
                            ' meters' +
                            '</div>';

                    } else if (accuracy <= 20) {

                        locationStatus.innerHTML =
                            '<div class="warning">' +
                            'Acceptable GPS accuracy: ±' +
                            accuracy.toFixed(2) +
                            ' meters' +
                            '</div>';

                    } else {

                        locationStatus.innerHTML =
                            '<div class="error">' +
                            'Poor GPS accuracy: ±' +
                            accuracy.toFixed(2) +
                            ' meters. Try moving to an open area.' +
                            '</div>';
                    }
                },

                function (error) {

                    let message =
                        'Unable to get current location.';

                    if (error.code === 1) {
                        message =
                            'Location permission denied.';
                    }

                    if (error.code === 2) {
                        message =
                            'Location is currently unavailable.';
                    }

                    if (error.code === 3) {
                        message =
                            'GPS request timed out. Try again.';
                    }

                    locationStatus.innerHTML =
                        '<div class="error">' +
                        message +
                        '</div>';
                },

                {
                    enableHighAccuracy: true,
                    timeout: 30000,
                    maximumAge: 0
                }
            );
        }
    );

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js').catch(console.error);
        });
    }

    openDatabase().then(async () => {
        await updatePendingList();
        await loadConsolidatedLookup();
    }).catch((error) => {
        console.error(error);
        document.getElementById('saveStatus').innerHTML =
            '<div class="error">Offline storage is unavailable in this browser.</div>';
    });
</script>

</body>
</html>
