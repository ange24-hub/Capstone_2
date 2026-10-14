const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

class Node {
    constructor(tag) { this.tagName = tag; this.children = []; this.textContent = ''; this.className = ''; }
    appendChild(child) { this.children.push(child); return child; }
    append(...children) { children.forEach(child => this.appendChild(child)); }
    set innerHTML(_) { throw new Error('Responses must never be interpreted as HTML.'); }
}
const sandbox = { window: {}, document: { querySelector: () => null, createElement: tag => new Node(tag) } };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/js/assistant.js'), 'utf8'), sandbox);
const { render } = sandbox.window.RbimAssistantFormat;

test('registry names retain numbering and literal characters without executing markup', () => {
    const container = new Node('div');
    render(container, 'PWD names:\n1. Maria O\'Brien\n2. <img src=x onerror=alert(1)>\n\nBased only on recognized PWD remarks.');
    assert.equal(container.children[0].className, 'assistant-response-heading');
    const list = container.children[1];
    assert.equal(list.tagName, 'ol');
    assert.equal(list.start, 1);
    assert.deepEqual(list.children.map(item => item.textContent), ["Maria O'Brien", '<img src=x onerror=alert(1)>']);
    assert.ok(list.children.every(item => item.tagName === 'li' && item.children.length === 0));
    assert.equal(container.children[2].className, 'assistant-response-note');
});

test('summary cards preserve values including zero and negative net change', () => {
    const container = new Node('div');
    render(container, 'In-migration: 0 | Out-migration: 7\nNet change: -7\n\nMissing records do not confirm zero movement.');
    const metrics = container.children[0];
    assert.equal(metrics.tagName, 'dl');
    assert.deepEqual(metrics.children.map(row => row.children.map(node => node.textContent)), [
        ['In-migration', '0'], ['Out-migration', '7'], ['Net change', '-7'],
    ]);
    assert.equal(container.children[1].className, 'assistant-response-note');
});

test('guidance distinguishes paragraphs and steps, including Cebuano notes', () => {
    const container = new Node('div');
    render(container, 'Open My Requests.\n\n3. Choose your request.\n4. Review the payment instructions.\n\n- Contact barangay staff if needed.\n\nWalay records nga makakumpirma sa approval.');
    assert.deepEqual(container.children.map(node => node.tagName), ['p', 'ol', 'ul', 'p']);
    assert.equal(container.children[1].start, 3);
    assert.equal(container.children[3].className, 'assistant-response-note');
});
