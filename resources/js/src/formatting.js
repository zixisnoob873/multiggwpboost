export const EMPTY_PLACEHOLDER = '--';

export function formatCurrency(value) {
  return `$${Number(value || 0).toFixed(2)}`;
}

export function displayValue(value, fallback = EMPTY_PLACEHOLDER) {
  return value || fallback;
}
