const assert = require('node:assert/strict');
const test = require('node:test');
const { readFileSync } = require('node:fs');
const { runInNewContext } = require('node:vm');
const { resolve } = require('node:path');

const context = { module: { exports: {} } };
runInNewContext(readFileSync(resolve(__dirname, '../../public/js/gis-geometry.js'), 'utf8'), context);
const { containsPoint } = context.module.exports;

const square = [[0, 0], [10, 0], [10, 10], [0, 10], [0, 0]];
const hole = [[3, 3], [7, 3], [7, 7], [3, 7], [3, 3]];

test('polygon checks include boundaries and exclude holes', () => {
    const geometry = { type: 'Polygon', coordinates: [square, hole] };
    assert.equal(containsPoint(geometry, [2, 2]), true);
    assert.equal(containsPoint(geometry, [0, 5]), true);
    assert.equal(containsPoint(geometry, [5, 5]), false);
    assert.equal(containsPoint(geometry, [3, 5]), false);
    assert.equal(containsPoint(geometry, [11, 5]), false);
});

test('multipolygon checks any component without filling gaps', () => {
    const geometry = { type: 'MultiPolygon', coordinates: [
        [square],
        [[[20, 20], [25, 20], [25, 25], [20, 25], [20, 20]]],
    ] };
    assert.equal(containsPoint(geometry, [22, 22]), true);
    assert.equal(containsPoint(geometry, [15, 15]), false);
});

test('small geographic polygon edges do not include nearby exterior points', () => {
    const geometry = { type: 'Polygon', coordinates: [[
        [125, 10], [125.0001, 10], [125.0001, 10.0001], [125, 10.0001], [125, 10],
    ]] };
    assert.equal(containsPoint(geometry, [125.00005, 10.00005]), true);
    assert.equal(containsPoint(geometry, [125.0000005, 10.0000005]), true);
    assert.equal(containsPoint(geometry, [125.0000995, 10.0000005]), true);
    assert.equal(containsPoint(geometry, [125.00005, 10]), true);
    assert.equal(containsPoint(geometry, [125.00005, 9.9999995]), false);
});
