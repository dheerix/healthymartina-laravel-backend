const { esc } = require("./utils");
const { toFraction } = require('./fractions');

function stripHtml(value) {
    return String(value ?? "")
        .replace(/<[^>]*>/g, "")
        .trim();
}

function renderRecipeStyles() {
    return `
  .recipe-page-flow{padding:12mm 12mm 16mm;overflow:visible;-webkit-box-decoration-break:clone;box-decoration-break:clone}
  .recipe-page-flow--compact{padding:10mm 10mm 12mm}
  .doc-header--recipe{border-bottom:0;margin-bottom:10px;text-align:center}
  .doc-header--recipe h1{margin:0;font-size:16px;letter-spacing:.02em;text-transform:uppercase;color:var(--hm-brand-color)}
  .recipe-subtitle{margin-top:5px;font-size:12px;color:#000}
  .recipe-top-image{width:100%;height:357pt;overflow:hidden;margin:0 0 14px;position:relative}
  .recipe-page-flow--compact .doc-header--recipe{margin-bottom:6px}
  .recipe-page-flow--compact .doc-header--recipe h1{font-size:15px}
  .recipe-page-flow--compact .recipe-subtitle{margin-top:3px;font-size:11px}
  .recipe-page-flow--compact .recipe-top-image{height:248pt;margin-bottom:16px}
  .recipe-page-flow{position:relative}
  .recipe-page-flow::before{
    content:'';
    position:absolute;
    left:0;
    top:0;
    width:100%;
    height:357pt;
    background:var(--hm-brand-color-soft);
    pointer-events:none;
    z-index:0;
  }
  .recipe-page-flow--compact::before{height:248pt}
  .recipe-page-flow > *{position:relative;z-index:1}
  .recipe-top-image img{width:100%;height:100%;object-fit:cover;display:block}
  .recipe-content-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px;align-items:start}
  .recipe-content-grid-top{margin-bottom:8mm}
  .recipe-content-grid-bottom{margin-bottom:2mm}
  .recipe-page-flow--compact .recipe-content-grid{gap:8px 14px}
  .recipe-page-flow--compact .recipe-content-grid-top{margin-bottom:6mm}
  .recipe-page-flow--compact .recipe-content-grid-bottom{margin-bottom:0}
  .recipe-flow-block{break-inside:auto;page-break-inside:auto;min-height:0;padding-right:4px}
  .recipe-flow-block .section-title{margin-top:0;font-size:12px}
  .recipe-page-flow--compact .recipe-flow-block .section-title{font-size:11px;margin-bottom:5px}
  .recipe-flow-block p{margin:0;font-size:10px;line-height:1.35}
  .tip-block{margin:0 0 8px;padding:1px 0}
  .tip-title{margin:0 0 4px;font-size:11px;line-height:1.2;font-weight:700;color:var(--hm-brand-color)}
  .tip-desc{margin:0;font-size:10px;line-height:1.38}
  .ingredient-list,.instruction-list{margin:0;padding-left:0;list-style:none}
  .ingredient-list li,.instruction-list li{margin:0 0 5px}
  .ingredient-list li{display:grid;grid-template-columns:96px 1fr;gap:10px;padding:1px 0;font-size:11px;line-height:1.28}
  .ingredient-list .ing-amount{font-weight:800;color:#000;white-space:nowrap;font-size:11px}
  .ingredient-list .ing-name{color:#000;font-size:11px}
  .ingredient-list .ing-note{color:#555;font-size:9px;line-height:1.22;font-style:italic}
  .instruction-list{padding-left:20px;list-style:decimal}
  .instruction-list li{font-size:12px;line-height:1.34;padding-left:2px}
  .recipe-page-flow--compact .ingredient-list li{grid-template-columns:84px 1fr;gap:8px;padding:0;margin-bottom:4px;font-size:10px;line-height:1.18}
  .recipe-page-flow--compact .ingredient-list .ing-amount,
  .recipe-page-flow--compact .ingredient-list .ing-name{font-size:10px}
  .recipe-page-flow--compact .ingredient-list .ing-note{font-size:8px}
  .recipe-page-flow--compact .instruction-list{padding-left:18px}
  .recipe-page-flow--compact .instruction-list li{font-size:11px;line-height:1.22;margin-bottom:4px}
  .nutrition-table{width:100%;border-collapse:collapse;font-size:9px}.nutrition-table td{padding:3px 0;border-bottom:1px solid rgba(0,0,0,.06)}`;
}

function nl2br(value) {
    return esc(value || "").replace(/\n/g, "<br/>");
}

function formatAmountWithFractions(raw) {
    const text = String(raw || "").trim();
    if (!text) return "";

    const match = text.match(/^(-?\d+(?:[.,]\d+)?)\s*(.*)$/);
    if (!match) return esc(text);

    const num = match[1].replace(",", ".");
    const rest = String(match[2] || "").trim();
    const frac = toFraction(num);
    if (!frac) return esc(text);

    return esc(`${frac}${rest ? ` ${rest}` : ""}`);
}

function estimateIngredientWeight(ingredientsList) {
    return (ingredientsList || []).reduce((sum, ingredient) => {
        const nameWords = String(ingredient?.name || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        const amountWords = String(ingredient?.amount || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        return sum + 1 + nameWords / 10 + amountWords / 6;
    }, 0);
}

function estimateInstructionWeight(instructionsList) {
    return (instructionsList || []).reduce((sum, step) => {
        const wordCount = String(step || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        return sum + 1 + wordCount / 16;
    }, 0);
}

function estimatePrimaryContentWeight(ingredientsList, instructionsList) {
    return (
        estimateIngredientWeight(ingredientsList) +
        estimateInstructionWeight(instructionsList)
    );
}

function estimateSecondaryContentWeight(nutritionList, tipsList, fallbackTips) {
    const nutritionWeight = (nutritionList || []).length * 1.2;
    const tipsWeight = (tipsList || []).reduce((sum, tip) => {
        const titleWords = String(tip?.title || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        const descWords = String(tip?.description || "")
            .trim()
            .split(/\s+/)
            .filter(Boolean).length;
        return sum + 1 + titleWords / 8 + descWords / 18;
    }, 0);
    const fallbackTipsWeight = fallbackTips
        ? String(fallbackTips).trim().split(/\s+/).filter(Boolean).length / 18
        : 0;

    return nutritionWeight + tipsWeight + fallbackTipsWeight;
}

function splitByWeight(items, getWeight, capacity) {
    const list = items || [];
    if (!list.length) {
        return { head: [], tail: [] };
    }

    let used = 0;
    let index = 0;
    while (index < list.length) {
        const itemWeight = Math.max(1, getWeight(list[index]));
        if (index > 0 && used + itemWeight > capacity) {
            break;
        }
        used += itemWeight;
        index += 1;
    }

    return {
        head: list.slice(0, index),
        tail: list.slice(index),
    };
}

function renderRecipes(model, options = {}) {
    const onRecipeRendered =
        typeof options.onRecipeRendered === "function"
            ? options.onRecipeRendered
            : null;
    const recipes = model.recipes || [];
    if (!recipes.length) return "";

    return recipes
        .map((r, index) => {
            if (onRecipeRendered) {
                onRecipeRendered(index + 1, recipes.length, r);
            }
            const nutrition = (r.nutrition || [])
                .map(
                    (n) =>
                        `<tr><td>${esc(n.name)}</td><td class="right">${esc(n.amount || "")}</td></tr>`,
                )
                .join("");
            const tipsBlocks = (r.tipsBlocks || []).length
                ? (r.tipsBlocks || [])
                      .map(
                          (tip) => `<div class="tip-block">
          ${tip.title ? `<h3 class="tip-title">${esc(tip.title)}</h3>` : ""}
          ${tip.description ? `<p class="tip-desc">${nl2br(tip.description)}</p>` : ""}
        </div>`,
                      )
                      .join("")
                : r.tips
                  ? `<p class="tip-desc">${nl2br(r.tips)}</p>`
                  : "";
            const ingredientItems = r.ingredients || [];
            const instructionItems = r.instructions || [];
            const useCompactPrimary = true;
            const renderIngredientItems = (items) =>
                items
                    .map(
                        (i) =>
                            `<li><span class="ing-amount">${formatAmountWithFractions(i.amount)}</span><span class="ing-name">${esc(stripHtml(i.name))}${i.note ? ` <span class="ing-note">(${nl2br(stripHtml(i.note))})</span>` : ""}</span></li>`,
                    )
                    .join("");
            const renderInstructionItems = (items) =>
                items.map((s) => `<li>${esc(s)}</li>`).join("");

            const metaPorciones =
                r.porciones != null
                    ? `${esc(r.porciones)} ${Number(r.porciones) === 1 ? 'porción' : 'porciones'}`
                    : "";
            const metaMinutos =
                r.minutos != null && r.minutos > 0
                    ? `${esc(r.minutos)} minutos`
                    : "";
            const metaLine = [metaPorciones, metaMinutos]
                .filter(Boolean)
                .join("  ");
            return `<section class="pdf-page section-break recipe-page-flow${useCompactPrimary ? " recipe-page-flow--compact" : ""}">
      <div class="doc-header doc-header--recipe">
        <h1>${esc(r.title)}</h1>
        ${metaLine ? `<div class="recipe-subtitle"><strong>${metaPorciones}</strong>${metaMinutos ? `&nbsp;&nbsp;${metaMinutos}` : ""}</div>` : ""}
      </div>
      <div class="recipe-top-image"><img src="${esc(r.image || "")}" alt="${esc(r.title)}" /></div>
      <div class="recipe-content-grid recipe-content-grid-top">
        <article class="recipe-flow-block">
          <h2 class="section-title">Ingredientes</h2>
          <ul class="ingredient-list">${renderIngredientItems(ingredientItems)}</ul>
        </article>
        <article class="recipe-flow-block">
          <h2 class="section-title">Instrucciones</h2>
          <ol class="instruction-list" start="1">${renderInstructionItems(instructionItems)}</ol>
        </article>
      </div>
      <div class="recipe-content-grid recipe-content-grid-bottom">
        <article class="recipe-flow-block">
          <h2 class="section-title">Información nutricional</h2>
          <table class="nutrition-table">${nutrition}</table>
        </article>
        <article class="recipe-flow-block">
          <h2 class="section-title">Tips</h2>
          ${tipsBlocks}
        </article>
      </div>
    </section>`;
        })
        .join("");
}

module.exports = { renderRecipes, renderRecipeStyles };
