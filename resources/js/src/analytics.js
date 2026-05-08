const ALLOWED_PAYLOAD_KEYS = new Set([
  'addon_count',
  'addon_label',
  'addon_slug',
  'checkout_kind',
  'component',
  'context',
  'cta',
  'cta_id',
  'field',
  'field_type',
  'game_name',
  'game_slug',
  'has_addons',
  'has_order_reference',
  'has_promo',
  'href_path',
  'is_logged_in',
  'label',
  'location',
  'payment_method',
  'provider',
  'selected',
  'service_name',
  'service_slug',
  'service_type',
  'source',
]);

const DATASET_PAYLOAD_MAP = {
  analyticsAddonLabel: 'addon_label',
  analyticsAddonSlug: 'addon_slug',
  analyticsComponent: 'component',
  analyticsContext: 'context',
  analyticsCta: 'cta',
  analyticsGameName: 'game_name',
  analyticsGameSlug: 'game_slug',
  analyticsLabel: 'label',
  analyticsLocation: 'location',
  analyticsPaymentMethod: 'payment_method',
  analyticsProvider: 'provider',
  analyticsServiceName: 'service_name',
  analyticsServiceSlug: 'service_slug',
  analyticsServiceType: 'service_type',
  analyticsSource: 'source',
  conversionCta: 'cta_id',
};

const CLICK_TRACKERS = [
  ['[data-analytics-event]', null],
  ['[data-conversion-cta]', 'homepage_cta_click'],
  ['[data-browse-games]', 'browse_games_click'],
  ['[data-analytics-game-card]', 'game_card_click'],
  ['[data-analytics-service-card]', 'service_card_click'],
];

function analyticsConfig() {
  return window.ggwpAnalyticsConfig || window.appState?.analytics || {};
}

function normalizeEventName(name) {
  return String(name || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9_]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 80);
}

function normalizeString(value) {
  return String(value || '')
    .replace(/\s+/g, ' ')
    .trim()
    .slice(0, 120);
}

function sanitizeValue(value) {
  if (typeof value === 'boolean') {
    return value;
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null;
  }

  if (typeof value === 'string') {
    const normalized = normalizeString(value);

    return normalized === '' ? null : normalized;
  }

  return null;
}

export function sanitizeAnalyticsPayload(payload = {}) {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    return {};
  }

  return Object.entries(payload).reduce((safe, [key, value]) => {
    if (!ALLOWED_PAYLOAD_KEYS.has(key)) {
      return safe;
    }

    const safeValue = sanitizeValue(value);

    if (safeValue !== null) {
      safe[key] = safeValue;
    }

    return safe;
  }, {});
}

function dispatchGoogle(eventName, payload) {
  if (typeof window.gtag !== 'function') {
    return false;
  }

  window.gtag('event', eventName, payload);

  return true;
}

function dispatchPostHog(eventName, payload) {
  if (typeof window.posthog?.capture !== 'function') {
    return false;
  }

  window.posthog.capture(eventName, payload);

  return true;
}

export function trackEvent(name, payload = {}) {
  const config = analyticsConfig();
  const eventName = normalizeEventName(name);

  if (!eventName || !config.enabled) {
    return false;
  }

  const safePayload = sanitizeAnalyticsPayload(payload);
  let dispatched = false;

  try {
    dispatched = dispatchGoogle(eventName, safePayload) || dispatched;
  } catch (error) {
    dispatched = false || dispatched;
  }

  try {
    dispatched = dispatchPostHog(eventName, safePayload) || dispatched;
  } catch (error) {
    dispatched = false || dispatched;
  }

  return dispatched;
}

function safeHrefPath(element) {
  if (!(element instanceof HTMLAnchorElement) || !element.href) {
    return '';
  }

  try {
    return new URL(element.href, window.location.origin).pathname;
  } catch (error) {
    return '';
  }
}

function payloadFromElement(element) {
  const payload = {};

  Object.entries(DATASET_PAYLOAD_MAP).forEach(([datasetKey, payloadKey]) => {
    const value = element.dataset?.[datasetKey];

    if (value) {
      payload[payloadKey] = value;
    }
  });

  const hrefPath = safeHrefPath(element);

  if (hrefPath) {
    payload.href_path = hrefPath;
  }

  return payload;
}

function resolveClickTracker(target) {
  for (const [selector, fallbackEvent] of CLICK_TRACKERS) {
    const element = target.closest(selector);

    if (!(element instanceof HTMLElement)) {
      continue;
    }

    return {
      element,
      eventName: element.dataset.analyticsEvent || fallbackEvent,
    };
  }

  return null;
}

function bindClickTracking() {
  document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;

    if (!target) {
      return;
    }

    const tracker = resolveClickTracker(target);

    if (!tracker?.eventName) {
      return;
    }

    const payload = payloadFromElement(tracker.element);
    const eventNames = new Set([tracker.eventName]);

    if (tracker.element.hasAttribute('data-conversion-cta')) {
      eventNames.add('homepage_cta_click');
    }

    if (tracker.element.hasAttribute('data-browse-games')) {
      eventNames.add('browse_games_click');
    }

    if (tracker.element.hasAttribute('data-analytics-game-card')) {
      eventNames.add('game_card_click');
    }

    if (tracker.element.hasAttribute('data-analytics-service-card')) {
      eventNames.add('service_card_click');
    }

    eventNames.forEach((eventName) => {
      trackEvent(eventName, payload);
    });
  });
}

function queuedEvents() {
  const stateEvents = window.appState?.queuedAnalyticsEvents;
  const globalEvents = window.ggwpQueuedAnalyticsEvents;
  const events = Array.isArray(stateEvents) ? stateEvents : globalEvents;

  return Array.isArray(events) ? events : [];
}

function dispatchQueuedEvents() {
  queuedEvents().forEach((event) => {
    if (typeof event === 'string') {
      trackEvent(event);
      return;
    }

    if (!event || typeof event !== 'object') {
      return;
    }

    trackEvent(event.name || event.event, event.payload || event.properties || {});
  });

  if (window.appState) {
    window.appState.queuedAnalyticsEvents = [];
  }

  window.ggwpQueuedAnalyticsEvents = [];
}

export function initAnalyticsTracking() {
  window.ggwpTrackEvent = trackEvent;

  if (window.ggwpAnalyticsTrackingInitialized) {
    dispatchQueuedEvents();
    return;
  }

  window.ggwpAnalyticsTrackingInitialized = true;
  bindClickTracking();
  dispatchQueuedEvents();
}
