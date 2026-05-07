// No auth tokens or PII stored here — only pricing/config state
const ORDER_STORAGE_KEY = 'ggwpOrder';
const CHECKOUT_PROMO_STORAGE_KEY = 'ggwpCheckoutPromo';
const DEFAULT_GAME_SLUG = 'valorant';

const BLOCKED_STORAGE_KEYS = new Set(['__proto__', 'constructor', 'prototype']);

function sanitizeStorageValue(value) {
  if (Array.isArray(value)) {
    return value.map((entry) => sanitizeStorageValue(entry));
  }

  if (value && typeof value === 'object') {
    return Object.entries(value).reduce((sanitized, [key, entry]) => {
      if (BLOCKED_STORAGE_KEYS.has(key)) {
        return sanitized;
      }

      const nextValue = sanitizeStorageValue(entry);

      if (nextValue !== undefined) {
        sanitized[key] = nextValue;
      }

      return sanitized;
    }, {});
  }

  if (typeof value === 'string') {
    return value
      .replaceAll('\u0000', '')
      .replace(/[\u0001-\u001F\u007F]/g, ' ')
      .trim();
  }

  if (typeof value === 'number' || typeof value === 'boolean' || value === null) {
    return value;
  }

  return undefined;
}

export function saveOrderToStorage(order) {
  try {
    const sanitized = sanitizeStorageValue(order);
    const gameSlug = normalizeGameSlug(sanitized?.gameSlug || window.appState?.gameSlug);

    localStorage.setItem(orderStorageKey(gameSlug), JSON.stringify({
      ...sanitized,
      gameSlug,
    }));

    if (gameSlug === DEFAULT_GAME_SLUG) {
      localStorage.setItem(ORDER_STORAGE_KEY, JSON.stringify({
        ...sanitized,
        gameSlug,
      }));
    }
  } catch (_) {}
}

export function loadOrderFromStorage(gameSlug = window.appState?.gameSlug) {
  try {
    const normalizedGameSlug = normalizeGameSlug(gameSlug);
    const raw = localStorage.getItem(orderStorageKey(normalizedGameSlug))
      || (normalizedGameSlug === DEFAULT_GAME_SLUG ? localStorage.getItem(ORDER_STORAGE_KEY) : null);

    if (!raw) {
      return null;
    }

    const parsed = JSON.parse(raw);

    return parsed && typeof parsed === 'object'
      ? { ...parsed, gameSlug: parsed.gameSlug || normalizedGameSlug }
      : null;
  } catch (_) {
    return null;
  }
}

export function saveCheckoutPromoToStorage(promoState) {
  try {
    localStorage.setItem(CHECKOUT_PROMO_STORAGE_KEY, JSON.stringify(sanitizeStorageValue(promoState)));
  } catch (_) {}
}

export function loadCheckoutPromoFromStorage() {
  try {
    const raw = localStorage.getItem(CHECKOUT_PROMO_STORAGE_KEY);

    return raw ? JSON.parse(raw) : null;
  } catch (_) {
    return null;
  }
}

export function clearCheckoutPromoFromStorage() {
  try {
    localStorage.removeItem(CHECKOUT_PROMO_STORAGE_KEY);
  } catch (_) {}
}

function normalizeGameSlug(value) {
  const slug = String(value || DEFAULT_GAME_SLUG)
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return slug || DEFAULT_GAME_SLUG;
}

function orderStorageKey(gameSlug) {
  return `${ORDER_STORAGE_KEY}:${normalizeGameSlug(gameSlug)}`;
}
