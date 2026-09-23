/**
 * Unified fraction conversion for legacy-compatible exports.
 *
 * Quantities are matched to the approved common fractions while sign
 * handling and integer comparisons use the safer modern implementation.
 */
function toFraction(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return null;

  const sign = numeric < 0 ? '-' : '';
  const absoluteValue = Math.abs(numeric);
  const whole = Math.floor(absoluteValue);
  const decimal = absoluteValue - whole;
  const fractions = [
    { numerator: 1, denominator: 16, value: 1 / 16 },
    { numerator: 1, denominator: 8, value: 1 / 8 },
    { numerator: 1, denominator: 4, value: 1 / 4 },
    { numerator: 1, denominator: 3, value: 1 / 3 },
    { numerator: 1, denominator: 2, value: 1 / 2 },
    { numerator: 2, denominator: 3, value: 2 / 3 },
    { numerator: 3, denominator: 4, value: 3 / 4 },
    { numerator: 1, denominator: 1, value: 1 },
  ];

  if (decimal < 1e-6) return `${sign}${whole}`;

  const closest = fractions.reduce((best, candidate) => {
    const error = Math.abs(decimal - candidate.value);
    return !best || error < best.error ? { ...candidate, error } : best;
  }, null);

  if (!closest || closest.value >= 1 - 1e-9) return `${sign}${whole + 1}`;

  const fraction = `${closest.numerator}/${closest.denominator}`;
  return whole > 0 ? `${sign}${whole} ${fraction}` : `${sign}${fraction}`;
}

module.exports = { toFraction };
