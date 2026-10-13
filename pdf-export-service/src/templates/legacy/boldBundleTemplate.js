const { renderStyles } = require('./styles');
const { renderCover } = require('./components/cover');
const { renderWeeklyPlan } = require('./components/weeklyPlan');
const { renderNutritionSummary } = require('./components/nutrition');
const { renderLista } = require('./components/lista');
const { renderRecipes } = require('./components/recipes');
const { uniqueRecipePages } = require('./components/utils');

function esc(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/\"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function mealDefaultLabels() {
  return {
    meal_1: 'Desayuno',
    meal_2: 'Lunch',
    meal_3: 'Comida',
    meal_4: 'Snack',
    meal_5: 'Cena',
    meal_6: 'Otros',
  };
}

function dayDefaultLabels() {
  return {
    day_1: 'Lunes',
    day_2: 'Martes',
    day_3: 'Miércoles',
    day_4: 'Jueves',
    day_5: 'Viernes',
    day_6: 'Sábado',
    day_7: 'Domingo',
  };
}

function buildWeeklyPlan(model) {
  const dayLabels = { ...dayDefaultLabels(), ...(model.labels?.days || {}) };
  const mealLabels = { ...mealDefaultLabels(), ...(model.labels?.meals || {}) };
  const main = model.main_schedule || {};
  const sides = model.sides_schedule || {};
  const mainRacion = model.main_racion || {};
  const sidesRacion = model.sides_racion || {};
  const mainLeftovers = model.main_leftovers || {};
  const sidesLeftovers = model.sides_leftovers || {};
  const recipesMap = model.recipes_map || {};

  const visibleDayKeys = Object.keys(dayLabels).filter((dayKey) =>
    Object.keys(mealLabels).some((mealKey) => main?.[dayKey]?.[mealKey] || sides?.[dayKey]?.[mealKey])
  );

  const days = visibleDayKeys.map((dayKey) => {
    const meals = {};

    Object.keys(mealLabels).forEach((mealKey) => {
      const items = [];
      const mainId = main?.[dayKey]?.[mealKey];
      const sideId = sides?.[dayKey]?.[mealKey];

      if (mainId) {
        const recipe = recipesMap[String(mainId)] || {};
        items.push({
          title: recipe.titulo || String(mainId),
          image: recipe.imagen_principal || '',
          racion: mainRacion?.[dayKey]?.[mealKey] || null,
          leftover: !!mainLeftovers?.[dayKey]?.[mealKey],
        });
      }

      if (sideId) {
        const recipe = recipesMap[String(sideId)] || {};
        items.push({
          title: recipe.titulo || String(sideId),
          image: recipe.imagen_principal || '',
          racion: sidesRacion?.[dayKey]?.[mealKey] || null,
          leftover: !!sidesLeftovers?.[dayKey]?.[mealKey],
        });
      }

      if (items.length) {
        meals[mealKey] = items;
      }
    });

    return { dayKey, meals };
  });

  return { days };
}

function buildNutritionSummary(model) {
  const sourceDays = Array.isArray(model.nutritionByDay) ? model.nutritionByDay : [];
  const days = sourceDays.map((day) => {
    const sourceRows = Array.isArray(day.rows) ? day.rows : [];
    const rows = sourceRows.length
      ? sourceRows.map((row) => ({
        id: row.id != null ? Number(row.id) : null,
        name: row.nombre || row.name || 'Nutriente',
        unit: row.unidad_medida || row.unit || '',
        amount: row.cantidad != null ? Number(row.cantidad) : Number(row.amount || 0),
        percentage: row.porcentaje != null ? Number(row.porcentaje) : (row.percentage != null ? Number(row.percentage) : null),
        color: row.color || '',
      }))
      : [{
        id: null,
        name: 'Calorías',
        unit: '',
        amount: Number(day.calories || 0),
        percentage: null,
        color: '',
      }];

    return {
      dayKey: day.day_key || day.dayKey || '',
      label: day.label || '',
      rows,
    };
  });

  return { days };
}

function buildLista(model) {
  const categories = (model.listaData?.categories || []).map((category) => ({
    name: category.name || category.nombre || 'Categoría',
    items: (category.items || []).map((item) => ({
      id: item.id ?? item.ingrediente_id ?? null,
      name: item.name || item.nombre || item.ingrediente || 'Ingrediente',
      amount: `${item.cantidad || ''}${item.unidad ? ` ${item.unidad}` : ''}`.trim(),
    })),
  }));

  return {
    categories,
    takenIds: model.listaData?.taken_ids || [],
  };
}

function buildRecipes(model) {
  const recipePages = uniqueRecipePages(model.recipePages);

  return recipePages.map((page) => {
    const recipe = page.recipe || {};
    return {
      title: recipe.titulo || 'Receta',
      image: recipe.imagen_principal || model.placeholderImage || '',
      porciones: page.portion != null
        ? Number(page.portion)
        : recipe.porciones != null
          ? Number(recipe.porciones)
          : null,
      porcionNombre: recipe.porcion_nombre || 'Porción',
      porcionNombrePlural: recipe.porcion_nombre_plural || recipe.porcion_nombre || 'Porciones',
      minutos: recipe.tiempo_elaboracion != null ? Number(recipe.tiempo_elaboracion) : null,
      ingredients: (page.ingredients || []).map((item) => ({
        name: item.ingrediente || item.nombre || 'Ingrediente',
        amount: `${item.cantidad || ''} ${item.medida || item.unidad || ''}`.trim(),
        note: String(item.nota || '').trim(),
      })),
      instructions: Array.isArray(recipe.instrucciones) ? recipe.instrucciones : [],
      nutrition: (page.nutrition || []).map((item) => ({
        name: item.nombre || 'Nutriente',
        amount: `${item.cantidad || ''} ${item.unidad_medida || ''}`.trim(),
      })),
      tips: recipe.tips || '',
      tipsBlocks: Array.isArray(recipe.tipsBlocks) ? recipe.tipsBlocks : [],
    };
  });
}

function normalizeBundleModel(model = {}) {
  const heroRecipe = model.heroRecipe || null;
  const coverTitle = model.calendarTitle || heroRecipe?.titulo || 'Calendario';
  const coverImage = heroRecipe?.imagen_principal || model.placeholderImage || '';

  return {
    cover: heroRecipe ? {
      title: coverTitle,
      image: coverImage,
      brandName: model.brandName || 'Healthy Martina',
      brandEmail: model.brandEmail || 'cristina@healthymartina.com',
      brandLogo: model.brandLogo || '',
      brandColor: model.brandColor || '#36544e',
    } : null,
    weeklyPlan: buildWeeklyPlan(model),
    nutritionSummary: buildNutritionSummary(model),
    lista: buildLista(model),
    recipes: buildRecipes(model),
  };
}

function buildLegacyBoldBundleHtml(model = {}) {
  const normalized = normalizeBundleModel(model);

  return `<!doctype html><html lang="es"><head><meta charset="utf-8"/><title>${esc(normalized.cover?.title || model.calendarTitle || 'Calendario')}</title>${renderStyles(normalized)}</head><body>
    ${normalized.cover ? renderCover(normalized) : ''}
    ${normalized.weeklyPlan?.days?.length ? renderWeeklyPlan(normalized) : ''}
    ${normalized.nutritionSummary?.days?.length ? renderNutritionSummary(normalized) : ''}
    ${normalized.lista?.categories?.length ? renderLista(normalized) : ''}
    ${normalized.recipes?.length ? renderRecipes(normalized) : ''}
  </body></html>`;
}

module.exports = {
  buildLegacyBoldBundleHtml,
};
