const { dayDefaultLabels, uniqueRecipePages } = require('./components/utils');

function stripHtml(value) {
  return String(value ?? '')
    .replace(/<[^>]*>/g, '')
    .trim();
}

function normalizeTipsBlocks(rawTips) {
  if (Array.isArray(rawTips)) {
    return rawTips
      .map((item) => {
        if (item && typeof item === 'object') {
          return {
            title: String(item.title || item.titulo || '').trim(),
            description: String(item.description || item.descripcion || item.text || '').trim(),
          };
        }
        return null;
      })
      .filter((item) => item && (item.title || item.description));
  }

  const text = String(rawTips || '').trim();
  if (!text) return [];

  const isHeading = (value) => {
    const v = String(value || '').trim();
    if (!v) return false;
    if (v.includes(':')) return true;
    if (/[.!?]$/.test(v)) return false;
    const words = v.split(/\s+/).filter(Boolean);
    return v.length <= 48 && words.length <= 6;
  };

  const lines = text.split(/\r?\n/).map((l) => l.trim());
  const hasBlankLines = lines.some((l) => l === '');

  const paragraphs = hasBlankLines
    ? text.split(/\r?\n\s*\r?\n+/).map((p) => p.trim()).filter(Boolean)
    : lines.filter(Boolean);

  const blocks = [];
  for (let i = 0; i < paragraphs.length; i += 1) {
    const current = paragraphs[i];
    const colon = current.indexOf(':');
    if (colon > 0 && colon < 40) {
      blocks.push({
        title: current.slice(0, colon).trim(),
        description: current.slice(colon + 1).trim(),
      });
      continue;
    }

    if (isHeading(current)) {
      let j = i + 1;
      const descLines = [];
      while (j < paragraphs.length && !isHeading(paragraphs[j])) {
        descLines.push(paragraphs[j]);
        j += 1;
      }
      blocks.push({
        title: current,
        description: descLines.join('\n').trim(),
      });
      i = j - 1;
      continue;
    }

    blocks.push({
      title: '',
      description: current,
    });
  }

  return blocks.filter((item) => item && (item.title || item.description));
}

function formatRoundedNutritionAmount(amount, unit) {
  const numeric = Number(amount);
  if (!Number.isFinite(numeric)) {
    return String(unit || '').trim();
  }

  const rounded = String(Math.round(numeric));

  return `${rounded}${unit ? ` ${unit}` : ''}`.trim();
}

function buildLegacyBoldModel(job) {
  const payload = job.payload || {};
  const snapshot = payload.calendar_snapshot || {};
  const labels = snapshot.labels || {};
  const dayLabels = { ...dayDefaultLabels(), ...(labels.days || {}) };
  const mealLabels = labels.meals || {};

  const recipesMap = snapshot.recipes_map || {};
  const dayKeys = Object.keys(dayLabels).filter((dayKey) => {
    const meals = snapshot.main_schedule?.[dayKey] || {};
    return Object.keys(meals).some((mealKey) => meals[mealKey] || snapshot.sides_schedule?.[dayKey]?.[mealKey]);
  });

  const days = dayKeys.map((dayKey) => {
    const mainMeals = snapshot.main_schedule?.[dayKey] || {};
    const mealEntries = {};

    Object.keys({ ...mealLabels, ...mainMeals }).forEach((mealKey) => {
      const items = [];
      const mainId = mainMeals?.[mealKey];
      const sideId = snapshot.sides_schedule?.[dayKey]?.[mealKey];

      if (mainId) {
        const main = recipesMap[String(mainId)] || { titulo: String(mainId) };
        items.push({
          title: main.titulo || String(mainId),
          image: main.imagen_principal || '',
          role: 'Principal',
          racion: snapshot.main_racion?.[dayKey]?.[mealKey] || null,
          leftover: !!snapshot.main_leftovers?.[dayKey]?.[mealKey],
        });
      }

      if (sideId) {
        const side = recipesMap[String(sideId)] || { titulo: String(sideId) };
        items.push({
          title: side.titulo || String(sideId),
          image: side.imagen_principal || '',
          role: 'Acompañante',
          racion: snapshot.sides_racion?.[dayKey]?.[mealKey] || null,
          leftover: !!snapshot.sides_leftovers?.[dayKey]?.[mealKey],
        });
      }

      if (items.length) {
        mealEntries[mealKey] = {
          key: mealKey,
          label: mealLabels[mealKey] || mealKey,
          items,
        };
      }
    });

    return {
      dayKey,
      label: dayLabels[dayKey] || dayKey,
      meals: mealEntries,
    };
  });

  const categories = (payload.listaData?.categories || []).map((cat) => ({
    name: cat.name || cat.nombre || 'Categoría',
    items: (cat.items || []).map((i) => ({
      id: i.ingrediente_id || null,
      name: i.nombre || i.ingrediente || 'Ingrediente',
      amount: `${i.cantidad || ''}${i.unidad ? ` ${i.unidad}` : ''}`.trim(),
    })),
  }));

  const recipePages = uniqueRecipePages(payload.recipePages || []);
  const recipes = recipePages.map((p) => ({
    title: p.recipe?.titulo || 'Receta',
    image: p.recipe?.imagen_principal || '',
    porciones: p.portion != null
      ? Number(p.portion)
      : p.recipe?.porciones != null
        ? Number(p.recipe.porciones)
        : null,
    porcionNombre: p.recipe?.porcion_nombre || 'Porción',
    porcionNombrePlural: p.recipe?.porcion_nombre_plural || p.recipe?.porcion_nombre || 'Porciones',
    minutos: p.recipe?.tiempo_elaboracion != null ? Number(p.recipe.tiempo_elaboracion) : null,
    ingredients: (p.ingredients || []).map((i) => ({
      name: stripHtml(i.ingrediente || i.nombre || 'Ingrediente'),
      amount: `${i.cantidad || ''} ${i.medida || i.unidad || ''}`.trim(),
      note: stripHtml(i.nota || ''),
    })),
    instructions: p.recipe?.instrucciones || [],
    nutrition: (p.nutrition || []).map((n) => ({
      name: n.nombre || 'Nutriente',
      amount: formatRoundedNutritionAmount(n.cantidad, n.unidad_medida || ''),
    })),
    tips: p.recipe?.tips || '',
    tipsBlocks: normalizeTipsBlocks(p.recipe?.tips),
  }));

  const nutritionDays = (payload.nutritionByDay || []).map((day) => ({
    dayKey: day.day_key || '',
    label: day.label || '',
    rows: (day.rows || []).map((r) => ({
      id: r.id != null ? Number(r.id) : null,
      name: r.nombre || 'Nutriente',
      unit: r.unidad_medida || '',
      amount: r.cantidad != null ? Number(r.cantidad) : 0,
      percentage: r.porcentaje != null ? Number(r.porcentaje) : null,
      color: r.color || '',
    })),
  }));

  return {
    cover: {
      title: snapshot.title || `Calendario ${job.calendarId}`,
      image: payload.heroRecipe?.imagen_principal || payload.placeholderImage || payload.placeholderImageSrc || '',
      brandName: payload.brandName || 'Healthy Martina',
      brandEmail: payload.brandEmail || 'cristina@healthymartina.com',
      brandLogo: payload.brandLogo || '',
      brandColor: payload.brandColor || '#36544e',
    },
    weeklyPlan: {
      days,
      dayLabels,
      mealLabels,
    },
    nutritionSummary: { days: nutritionDays },
    lista: { categories, takenIds: payload.listaData?.taken_ids || [] },
    recipes,
  };
}

module.exports = { buildLegacyBoldModel };
