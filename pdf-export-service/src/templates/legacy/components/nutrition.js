const { esc } = require('./utils');

const MACRO_IDS = { carbs: 99, protein: 96, fat: 97 };
const LEFT_ORDER = [
  'calorias',
  'carbohidratos',
  'fibra',
  'azucar',
  'proteina',
  'grasa total',
  'saturada',
  'poliinsaturada',
  'monoinsaturada',
  'colesterol',
  'calcio',
  'hierro',
  'magnesio',
  'fosforo',
  'potasio',
];
const RIGHT_ORDER = [
  'sodio',
  'zinc',
  'selenio',
  'vitamina a',
  'vit b1 tiamina',
  'vit b2 riboflavina',
  'vit b3 niacina',
  'vitamina b6',
  'vitamina b12',
  'vitamina c',
  'vitamina d',
  'vitamina e',
  'vitamina k',
  'folato',
  'colina',
];

function renderNutritionSummaryStyles() {
  return `
  .nutrition-reference-page{background:#fff}
  .nutrition-day-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 22px;padding-right:0}
  .nutrition-day-card{break-inside:avoid;page-break-inside:avoid;margin-bottom:12px;padding-right:2mm}
  .nutrition-day-title{margin:0 0 10px;font-size:14px;font-weight:700;color:var(--hm-brand-color);line-height:1}
  .nutrition-macros-line{display:grid;grid-template-columns:repeat(3,max-content);column-gap:12px;row-gap:0;align-items:baseline;margin-bottom:8px;font-size:10px;font-weight:700;line-height:1.18}
  .macro-carb,.macro-protein,.macro-fat{white-space:nowrap}
  .macro-carb{color:#b279eb}.macro-protein{color:#3afe72}.macro-fat{color:#e79ccd}
  .nutrition-rows{font-size:10px;line-height:1.3;color:#000}
  .nutrition-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:6px}
  .nutrition-cell{font-weight:400;padding:1px 0}
  .nutrition-bold{font-weight:700}`;
}

function formatAmount(amount, unit) {
  if (amount == null || Number.isNaN(Number(amount))) return '-';
  const value = Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return `${value} ${unit || ''}`.trim();
}

function normalizeName(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

function pickOrderedRows(rows, orderedNames) {
  const remaining = [...rows];
  const picked = [];

  orderedNames.forEach((name) => {
    const idx = remaining.findIndex((row) => normalizeName(row.name) === normalizeName(name));
    if (idx >= 0) {
      picked.push(remaining[idx]);
      remaining.splice(idx, 1);
    }
  });

  return { picked, remaining };
}

function dayCard(day) {
  const rows = day.rows || [];
  const byId = new Map(rows.map((r) => [r.id, r]));
  const carbs = byId.get(MACRO_IDS.carbs);
  const protein = byId.get(MACRO_IDS.protein);
  const fat = byId.get(MACRO_IDS.fat);
  const macroCells = [
    { row: carbs, label: 'Carbohidratos', className: 'macro-carb' },
    { row: protein, label: 'Proteína', className: 'macro-protein' },
    { row: fat, label: 'Grasa total', className: 'macro-fat' },
  ].filter(({ row }) => row);
  const macroLine = macroCells.length
    ? `<div class="nutrition-macros-line">${macroCells.map(({ row, label, className }) => (
      `<span class="macro ${className}">${label} ${esc(row.percentage != null ? `${Number(row.percentage).toFixed(2)}%` : '')}</span>`
    )).join('')}</div>`
    : '';

  const { picked: leftPicked, remaining: afterLeft } = pickOrderedRows(rows, LEFT_ORDER);
  const { picked: rightPicked, remaining: afterRight } = pickOrderedRows(afterLeft, RIGHT_ORDER);
  const leftovers = afterRight || [];
  const leftRows = [...leftPicked, ...leftovers.filter((_r, i) => i % 2 === 0)];
  const rightRows = [...rightPicked, ...leftovers.filter((_r, i) => i % 2 === 1)];
  const maxRows = Math.max(leftRows.length, rightRows.length);
  const nutrientRows = Array.from({ length: maxRows }).map((_, idx) => {
    const left = leftRows[idx] || null;
    const right = rightRows[idx] || null;
    const isLeftMacro = left?.id === MACRO_IDS.carbs || left?.id === MACRO_IDS.protein || left?.id === MACRO_IDS.fat;
    const isRightMacro = right?.id === MACRO_IDS.carbs || right?.id === MACRO_IDS.protein || right?.id === MACRO_IDS.fat;
    return `<div class="nutrition-row">
      <div class="nutrition-cell ${isLeftMacro ? 'nutrition-bold' : ''}">
        ${left ? `${esc(left.name)} ${esc(formatAmount(left.amount, left.unit))}` : ''}
      </div>
      <div class="nutrition-cell ${isRightMacro ? 'nutrition-bold' : ''}">
        ${right ? `${esc(right.name)} ${esc(formatAmount(right.amount, right.unit))}` : ''}
      </div>
    </div>`;
  }).join('');

  return `<article class="nutrition-day-card">
    <h3 class="nutrition-day-title">${esc(day.label || '')}</h3>
    ${macroLine}
    <div class="nutrition-rows">${nutrientRows}</div>
  </article>`;
}

function renderNutritionSummary(model) {
  const days = model.nutritionSummary?.days || [];
  if (!days.length) return '';

  const cards = days.map(dayCard).join('');
  return `<section class="pdf-page section-break nutrition-reference-page"><div class="nutrition-day-grid">${cards}</div></section>`;
}

module.exports = { renderNutritionSummary, renderNutritionSummaryStyles };
