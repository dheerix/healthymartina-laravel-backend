const assert = require('node:assert/strict');
const { toFraction } = require('../src/templates/legacy/components/fractions');
const { buildLegacyBoldModel } = require('../src/templates/legacy/model');
const { renderRecipes } = require('../src/templates/legacy/components/recipes');
const { formatAmountWithFractions } = require('../src/templates/legacy/components/lista');

assert.equal(toFraction(0.30), '1/3');
assert.equal(toFraction(0.25), '1/4');
assert.equal(toFraction(0.50), '1/2');
assert.equal(toFraction(0.625), '2/3');
assert.equal(toFraction(1.30), '1 1/3');
assert.equal(toFraction(-0.50), '-1/2');
assert.equal(toFraction(-1.30), '-1 1/3');
assert.equal(toFraction(0.99), '1');
assert.equal(formatAmountWithFractions('2/3 pza + 75 g'), '2/3 pza + 75 g');

const model = buildLegacyBoldModel({
    payload: {
        recipePages: [{
            recipe: {
                id: 42,
                titulo: 'Caldo',
                porciones: 1,
                porcion_nombre: 'Taza',
                porcion_nombre_plural: 'Tazas',
            },
            portion: 1,
            ingredients: [],
            nutrition: [],
        }],
    },
});
const recipeHtml = renderRecipes(model);

assert.match(recipeHtml, /1 Taza/);
assert.doesNotMatch(recipeHtml, /1 porción/i);

model.recipes[0].porciones = 2;
const pluralRecipeHtml = renderRecipes(model);
assert.match(pluralRecipeHtml, /2 Tazas/);

console.log('fraction conversion and recipe yield-unit tests passed');
