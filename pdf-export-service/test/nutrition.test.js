const assert = require('node:assert/strict');
const { renderNutritionSummary } = require('../src/templates/legacy/components/nutrition');

const magnesiumOnlyHtml = renderNutritionSummary({
  nutritionSummary: {
    days: [{
      label: 'Lunes',
      rows: [{ id: 104, name: 'Magnesio', amount: 20, unit: 'mg', percentage: 5 }],
    }],
  },
});

assert.match(magnesiumOnlyHtml, /Magnesio/);
assert.doesNotMatch(magnesiumOnlyHtml, /Carbohidratos/);
assert.doesNotMatch(magnesiumOnlyHtml, /Proteína/);
assert.doesNotMatch(magnesiumOnlyHtml, /Grasa total/);

const macrosHtml = renderNutritionSummary({
  nutritionSummary: {
    days: [{
      label: 'Martes',
      rows: [
        { id: 99, name: 'Carbohidratos', amount: 10, unit: 'g', percentage: 11 },
        { id: 96, name: 'Proteína', amount: 20, unit: 'g', percentage: 22 },
        { id: 97, name: 'Grasa total', amount: 30, unit: 'g', percentage: 33 },
      ],
    }],
  },
});

assert.match(macrosHtml, /Carbohidratos 11\.00%/);
assert.match(macrosHtml, /Proteína 22\.00%/);
assert.match(macrosHtml, /Grasa total 33\.00%/);

console.log('nutrition preference PDF rendering tests passed');
