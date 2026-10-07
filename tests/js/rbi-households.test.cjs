const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');
const assert = require('node:assert/strict');

const context = { window: {} };
vm.runInNewContext(readFileSync(join(__dirname, '../../public/js/rbi-households.js'), 'utf8'), context);
const { resolve, confirmUnmatched } = context.window.RbiHouseholds;
const households = [
    { id: 1, label: 'Ana Example', household_number: '10' },
    { id: 2, label: 'Same Name', household_number: '20' },
    { id: 3, label: 'Same Name', household_number: '30' },
];

test('existing heads match names regardless of case or extra whitespace', () => {
    const result = resolve(households, '  ANA   example ', '', '', '');
    assert.equal(result.status, 'matched');
    assert.equal(result.household.id, 1);
});

test('datalist choice distinguishes households with the same head name', () => {
    const result = resolve(households, 'Same Name [Household 30]', '', '', '');
    assert.equal(result.status, 'matched');
    assert.equal(result.household.id, 3);
    assert.equal(resolve(households, 'Same Name', '', '', '').status, 'ambiguous');
});

test('unchanged saved household links survive an older spelling of the head name', () => {
    assert.equal(resolve(households, 'Old head spelling', '1', 'Old head spelling', '1').household.id, 1);
    const changed = resolve(households, 'Unregistered Person', '1', 'Old head spelling', '1');
    assert.equal(changed.status, 'unmatched');
    assert.ok(!changed.household);
});

test('blank heads and removed household links do not count as registry matches', () => {
    assert.equal(resolve(households, '', '', '', '').status, 'empty');
    assert.equal(resolve(households, 'Removed Person', '99', 'Removed Person', '99').status, 'unmatched');
});

test('registered heads do not prompt, while cancellation stops saving unmatched heads', () => {
    assert.equal(confirmUnmatched([], () => { throw new Error('Unexpected confirmation'); }), true);
    let prompts = 0;
    assert.equal(confirmUnmatched(['New Person', ' new   person ', 'Other Person'], message => {
        prompts++;
        assert.match(message, /No matching household head/);
        assert.match(message, /Continue saving/);
        assert.equal((message.match(/• /g) || []).length, 2);
        return false;
    }), false);
    assert.equal(prompts, 1);
    assert.equal(confirmUnmatched(['New Person'], () => true), true);
});
