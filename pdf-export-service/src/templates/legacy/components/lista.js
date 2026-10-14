const { esc } = require('./utils');
const { toFraction } = require('./fractions');

const MAX_ROWS_PER_COLUMN = 16;

function formatAmountWithFractions(raw) {
  const text = String(raw || '').trim();
  if (!text) return '';
  if (/^-?\d+(?:\s+\d+)?\/\d+/.test(text)) return text;

  const match = text.match(/^(-?\d+(?:[.,]\d+)?)\s*(.*)$/);
  if (!match) return text;

  const num = match[1].replace(',', '.');
  const rest = String(match[2] || '').trim();
  const frac = toFraction(num);
  if (!frac) return text;

  return `${frac}${rest ? ` ${rest}` : ''}`;
}

function renderListaStyles() {
  return `
  .lista-grid{width:100%;border-collapse:collapse}.lista-grid td{width:50%;vertical-align:top;padding:0 12px 0 0}
  .lista-category{margin-bottom:14px;break-inside:avoid;page-break-inside:avoid}
  .lista-cat-title{font-size:12px;font-weight:700;color:#111;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #ececec;padding:0 0 4px;margin-bottom:6px}
  .lista-items{width:100%}
  .lista-item-row{display:flex;align-items:flex-start;gap:7px;margin:0 0 6px;padding:1px 0;font-size:11px;line-height:1.26}
  .checkbox-cell{display:inline-block;width:14px;min-width:14px;text-align:center;font-size:11px;line-height:1.2}
  .item-amount{display:inline-block;color:#555;font-size:11px;font-weight:700;min-width:64px;white-space:nowrap}
  .item-name{display:inline-block;flex:1 1 auto;text-align:left}`;
}

function estimateCategoryRows(category) {
  const itemCount = Array.isArray(category?.items) ? category.items.length : 0;
  // Title + spacing + items
  return Math.max(3, itemCount + 2);
}

function paginateCategories(categories) {
  const pages = [];
  let page = { left: [], right: [] };
  let leftRows = 0;
  let rightRows = 0;

  const pushPage = () => {
    if (page.left.length || page.right.length) {
      pages.push(page);
    }
    page = { left: [], right: [] };
    leftRows = 0;
    rightRows = 0;
  };

  categories.forEach((category) => {
    const rows = estimateCategoryRows(category);

    if (leftRows <= rightRows) {
      if (leftRows + rows <= MAX_ROWS_PER_COLUMN || !page.left.length) {
        page.left.push(category);
        leftRows += rows;
        return;
      }
      if (rightRows + rows <= MAX_ROWS_PER_COLUMN || !page.right.length) {
        page.right.push(category);
        rightRows += rows;
        return;
      }
      pushPage();
      page.left.push(category);
      leftRows += rows;
      return;
    }

    if (rightRows + rows <= MAX_ROWS_PER_COLUMN || !page.right.length) {
      page.right.push(category);
      rightRows += rows;
      return;
    }
    if (leftRows + rows <= MAX_ROWS_PER_COLUMN || !page.left.length) {
      page.left.push(category);
      leftRows += rows;
      return;
    }
    pushPage();
    page.left.push(category);
    leftRows += rows;
  });

  pushPage();
  return pages;
}

function renderLista(model) {
  const categories = model.lista?.categories || [];
  const taken = new Set((model.lista?.takenIds || []).map((id) => String(id)));
  if (!categories.length) return '';

  const renderCol = (arr) => arr.map((cat) => {
    const items = (cat.items || []).map((item) => {
      const ingredientId = item.id ?? item.ingrediente_id ?? null;
      const isTaken = ingredientId !== null && taken.has(String(ingredientId));
      const amount = formatAmountWithFractions(item.amount || '');
      return `<div class="lista-item-row">
        <span class="checkbox-cell">${isTaken ? '&#9745;' : '&#9744;'}</span>
        <span class="item-amount ${isTaken ? 'item-taken' : ''}">${esc(amount)}</span>
        <span class="item-name ${isTaken ? 'item-taken' : ''}">${esc(item.name)}</span>
      </div>`;
    }).join('');

    return `<div class="lista-category"><div class="lista-cat-title">${esc(cat.name)}</div><div class="lista-items">${items}</div></div>`;
  }).join('');

  const pages = paginateCategories(categories);
  return pages.map((pageCols) => {
    return `<section class="pdf-page section-break"><div class="section-title">Lista de Compras</div>
      <table class="lista-grid"><tr><td>${renderCol(pageCols.left)}</td><td>${renderCol(pageCols.right)}</td></tr></table>
    </section>`;
  }).join('');
}

module.exports = { renderLista, renderListaStyles, formatAmountWithFractions };
