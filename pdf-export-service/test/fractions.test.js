const assert = require('node:assert/strict');
const { toFraction } = require('../src/templates/legacy/components/fractions');

assert.equal(toFraction(0.30), '1/3');
assert.equal(toFraction(0.25), '1/4');
assert.equal(toFraction(0.50), '1/2');
assert.equal(toFraction(0.625), '2/3');
assert.equal(toFraction(1.30), '1 1/3');
assert.equal(toFraction(-0.50), '-1/2');
assert.equal(toFraction(-1.30), '-1 1/3');
assert.equal(toFraction(0.99), '1');

console.log('fraction conversion tests passed');
