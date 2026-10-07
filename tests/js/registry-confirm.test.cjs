const assert = require('node:assert/strict');
const test = require('node:test');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const { resolve } = require('node:path');

function editor({ deceased = true, confirmed = true, remarks = '' } = {}) {
    const listeners = new Map();
    const field = (name, value, type = 'text') => ({ name, value, type,
        addEventListener(event, callback) { listeners.set(`${name}:${event}`, callback); } });
    const fields = [field('_method', 'PUT', 'hidden'), field('first_name', 'Original'), field('remarks', remarks)];
    fields.namedItem = name => fields.find(item => item.name === name);
    const messages = [];
    let prevented = false;
    let submitted = 0;
    const form = {
        id: deceased ? 'deceased-row-1' : 'rbi-row-1',
        action: deceased ? 'http://localhost/registry/deceased/1' : 'http://localhost/registry/1',
        elements: fields,
        hasAttribute: name => deceased && name === 'data-deceased-update',
        querySelector: () => fields[0],
        addEventListener(event, callback) { listeners.set(`form:${event}`, callback); },
        append(input) { fields.push(input); },
        requestSubmit() {
            prevented = false;
            listeners.get('form:submit')({ preventDefault() { prevented = true; } });
            if (!prevented) submitted++;
        },
    };
    runInNewContext(readFileSync(resolve(__dirname, '../../public/js/registry-confirm.js'), 'utf8'), {
        URL,
        document: { forms: [form], createElement: () => ({}) },
        window: { confirm(message) { messages.push(message); return confirmed; },
            prompt() { throw new Error('Unexpected transfer prompt'); } },
    });
    return { fields, messages, change(value) {
        fields[1].value = value;
        listeners.get('first_name:change')();
    }, get submitted() { return submitted; } };
}

test('deceased cell edits submit after confirmation without a save button', () => {
    const row = editor({ remarks: 'Transferred to another town before death' });
    row.change('Updated');
    assert.equal(row.submitted, 1);
    assert.deepEqual(row.messages, ['Are you sure you want to save these changes?']);
    assert.equal(row.fields.some(field => field.name === 'transfer_confirmed'), false);
});

test('declining a deceased edit restores the original value and allows another edit', () => {
    const row = editor({ confirmed: false });
    row.change('Updated');
    assert.equal(row.submitted, 0);
    assert.equal(row.fields[1].value, 'Original');
    row.change('Another edit');
    assert.equal(row.messages.length, 2);
    assert.equal(row.submitted, 0);
});

test('active registry edits still confirm transfers and include the destination', () => {
    const row = editor({ deceased: false, remarks: 'Transferred to Cebu City' });
    row.change('Updated');
    assert.equal(row.submitted, 1);
    assert.match(row.messages[0], /transfer this resident to Cebu City/);
    assert.equal(row.fields.namedItem('transfer_destination').value, 'Cebu City');
    assert.equal(row.fields.namedItem('transfer_confirmed').value, '1');
});
