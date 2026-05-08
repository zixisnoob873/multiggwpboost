import { isLoggedIn, redirectToLogin, requestJson } from './common';
import { syncAddonRulesForContext } from './addon-rules';
import { trackEvent } from './analytics';
import { byId, queryAll, setText, toggleClass } from './dom';
import { formatCurrency } from './formatting';
import { saveOrderToStorage } from './order-storage';
import { getAgentSelectionForContext } from './agent-selectors';
import { calculatePricingPreview, hasPricingPreviewConfig, loadPricingConfig, pricingPayloadSignature } from './pricing-preview';

const SERVICE_CONFIG = {
  'Rank Boosting': {
    paneSelector: '#pane-boosting',
    addonContext: 'boost',
    checkoutButtonId: 'checkoutBtn',
    priceIds: {
      base: 'boostBasePrice',
      addon: 'boostAddonPrice',
      afterRr: 'boostAfterRrPrice',
      modifiers: 'boostModifierSummary',
      disabledAddons: 'boostDisabledAddons',
      state: 'boostPriceState',
      total: 'boostPrice',
      error: 'boostPriceError',
      retryWrap: 'boostPriceRetryWrap',
      retryButton: 'boostPriceRetryBtn',
    },
  },
  'Placement Matches': {
    paneSelector: '#pane-placement',
    addonContext: 'placement',
    checkoutButtonId: 'placementCheckoutBtn',
    priceIds: {
      base: 'placementBasePrice',
      addon: 'placementAddonPrice',
      afterRr: 'placementAfterRrPrice',
      modifiers: 'placementModifierSummary',
      disabledAddons: 'placementDisabledAddons',
      state: 'placementPriceState',
      total: 'placementPrice',
      error: 'placementPriceError',
      retryWrap: 'placementPriceRetryWrap',
      retryButton: 'placementPriceRetryBtn',
    },
  },
  'Radiant Boost': {
    paneSelector: '#pane-radiant',
    addonContext: 'radiant',
    checkoutButtonId: 'radiantCheckoutBtn',
    priceIds: {
      base: 'radiantBasePrice',
      addon: 'radiantAddonPrice',
      afterRr: 'radiantAfterRrPrice',
      modifiers: 'radiantModifierSummary',
      disabledAddons: 'radiantDisabledAddons',
      state: 'radiantPriceState',
      total: 'radiantPrice',
      error: 'radiantPriceError',
      retryWrap: 'radiantPriceRetryWrap',
      retryButton: 'radiantPriceRetryBtn',
    },
  },
  'Ranked Wins': {
    paneSelector: '#pane-ranked',
    addonContext: 'ranked',
    checkoutButtonId: 'rankedCheckoutBtn',
    priceIds: {
      base: 'rankedBasePrice',
      addon: 'rankedAddonPrice',
      afterRr: 'rankedAfterRrPrice',
      modifiers: 'rankedModifierSummary',
      disabledAddons: 'rankedDisabledAddons',
      state: 'rankedPriceState',
      total: 'rankedPrice',
      error: 'rankedPriceError',
      retryWrap: 'rankedPriceRetryWrap',
      retryButton: 'rankedPriceRetryBtn',
    },
  },
};

let activeRequestController = null;
let activeRequestToken = 0;
let scheduledCalculationTimer = null;
let scheduledAnalyticsTimer = null;
let suppressScheduledCalculation = false;
const lastSuccessfulResults = new Map();
const lastRequestedPayloadSignatures = new Map();
const lastSettledPayloadSignatures = new Map();
const RANKED_WINS_LIMITS = { min: 1, max: 5 };
const CALCULATION_DEBOUNCE_MS = 120;
const ANALYTICS_DEBOUNCE_MS = 500;

function getSelectedServiceName() {
  const activeTab = document.querySelector('#servicesTab .nav-link.active');

  return activeTab?.textContent?.trim() || 'Rank Boosting';
}

function getInputValue(id, fallback = '') {
  const element = byId(id);

  return element ? String(element.value || fallback).trim() : fallback;
}

function getDisplayValue(id, fallback = '') {
  const element = byId(id);

  if (!element) {
    return fallback;
  }

  if (element instanceof HTMLSelectElement) {
    const option = element.selectedOptions?.[0];
    return String(option?.textContent || element.value || fallback).trim();
  }

  return String(element.value || fallback).trim();
}

function withSummarySuffix(value, suffix = '') {
  const trimmedValue = String(value || '').trim();
  const trimmedSuffix = String(suffix || '').trim();

  if (!trimmedValue || !trimmedSuffix) {
    return trimmedValue;
  }

  const numericValue = Number(trimmedValue);
  const readableSuffix = numericValue === 1 && trimmedSuffix.endsWith('s')
    ? trimmedSuffix.slice(0, -1)
    : trimmedSuffix;

  return `${trimmedValue} ${readableSuffix}`.trim();
}

function syncQuoteSummary(serviceName) {
  const config = SERVICE_CONFIG[serviceName];
  const card = config ? document.querySelector(`${config.paneSelector} [data-quote-card]`) : null;

  if (!card) {
    return;
  }

  const { dataset } = card;
  const current = getDisplayValue(dataset.quoteCurrentInput, dataset.quoteCurrentFallback);
  const targetFromInput = withSummarySuffix(
    getDisplayValue(dataset.quoteTargetInput, ''),
    dataset.quoteTargetSuffix
  );
  const target = String(dataset.quoteTargetText || targetFromInput || dataset.quoteTargetFallback || '').trim();
  const region = getDisplayValue(dataset.quoteRegionInput, dataset.quoteRegionFallback);
  const platform = getDisplayValue(dataset.quotePlatformInput, dataset.quotePlatformFallback);
  const modeFromInput = getDisplayValue(dataset.quoteModeInput, '');
  const mode = String(dataset.quoteModeText || modeFromInput || dataset.quoteModeFallback || '').trim();

  setText(card.querySelector('[data-quote-summary-current]'), current);
  setText(card.querySelector('[data-quote-summary-target]'), target);
  setText(card.querySelector('[data-quote-summary-region]'), region);
  setText(card.querySelector('[data-quote-summary-platform]'), platform);
  setText(card.querySelector('[data-quote-summary-mode]'), mode);
}

function setAnimatedPrice(id, value) {
  const element = byId(id);

  if (!element) {
    return;
  }

  const nextValue = String(value);
  if (element.textContent === nextValue) {
    return;
  }

  element.textContent = nextValue;

  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  window.clearTimeout(Number(element.dataset.quotePriceTimer || 0));
  element.classList.remove('is-updating');

  window.requestAnimationFrame(() => {
    element.classList.add('is-updating');
    const timer = window.setTimeout(() => {
      element.classList.remove('is-updating');
      delete element.dataset.quotePriceTimer;
    }, 260);

    element.dataset.quotePriceTimer = String(timer);
  });
}

function getNumberValue(id, fallback = null) {
  const raw = getInputValue(id);

  if (raw === '') {
    return fallback;
  }

  const value = Number(raw);

  return Number.isFinite(value) ? value : fallback;
}

function clampInteger(value, min, max, fallback = min) {
  if (!Number.isFinite(value)) {
    return fallback;
  }

  return Math.min(max, Math.max(min, Math.trunc(value)));
}

function getClampedNumberValue(id, { min, max, fallback = min }) {
  const element = byId(id);
  const clamped = clampInteger(getNumberValue(id, fallback), min, max, fallback);

  if (element && String(element.value) !== String(clamped)) {
    element.value = String(clamped);
  }

  return clamped;
}

function setupBoundedNumberInput(id, { min, max }) {
  const element = byId(id);
  if (!element) {
    return;
  }

  element.min = String(min);
  element.max = String(max);
  element.step = '1';

  const clampValue = () => {
    element.value = String(clampInteger(Number(element.value), min, max, min));
  };

  element.addEventListener('keydown', (event) => {
    if (['e', 'E', '+', '-', '.'].includes(event.key)) {
      event.preventDefault();
    }
  });

  element.addEventListener('input', clampValue);
  element.addEventListener('change', clampValue);
  element.addEventListener('blur', clampValue);

  clampValue();
}

function getSelectedAddons(context) {
  return queryAll(`[data-addon-grid="${context}"] input[data-addon-label]:checked`)
    .map((input) => input.dataset.addonLabel)
    .filter(Boolean);
}

function calculatePriceUrl() {
  return window.appState?.calculatePriceUrl || '/calculate-price';
}

function csrfToken() {
  return window.appState?.csrfToken || '';
}

function currentGameSlug() {
  return window.ggwpProductConfig?.gameSlug || window.appState?.gameSlug || 'valorant';
}

function normalizeSlug(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function estimatorAnalyticsPayload(serviceName, extra = {}) {
  return {
    context: 'home_estimator',
    game_slug: currentGameSlug(),
    game_name: window.ggwpProductConfig?.gameName || window.appState?.gameName || 'Valorant',
    service_type: serviceName,
    ...extra,
  };
}

function controlFieldName(control) {
  if (!(control instanceof HTMLElement)) {
    return 'unknown';
  }

  return control.dataset.serviceField
    || control.getAttribute('name')
    || control.getAttribute('id')
    || 'unknown';
}

function controlFieldType(control) {
  if (control instanceof HTMLInputElement) {
    return control.type || 'input';
  }

  if (control instanceof HTMLSelectElement) {
    return 'select';
  }

  return 'control';
}

function scheduleCalculatorInteraction(control) {
  if (scheduledAnalyticsTimer) {
    window.clearTimeout(scheduledAnalyticsTimer);
  }

  const serviceName = getSelectedServiceName();

  scheduledAnalyticsTimer = window.setTimeout(() => {
    scheduledAnalyticsTimer = null;
    trackEvent('calculator_interaction', estimatorAnalyticsPayload(serviceName, {
      field: controlFieldName(control),
      field_type: controlFieldType(control),
    }));
  }, ANALYTICS_DEBOUNCE_MS);
}

function trackAddonSelection(control) {
  if (!(control instanceof HTMLInputElement) || !control.dataset.addonLabel) {
    return;
  }

  trackEvent('addon_selection', estimatorAnalyticsPayload(getSelectedServiceName(), {
    addon_slug: control.dataset.addonSlug || normalizeSlug(control.dataset.addonLabel),
    addon_label: control.dataset.addonLabel,
    selected: control.checked,
  }));
}

function buildBoostPayload() {
  return {
    serviceType: 'Rank Boosting',
    currentDivision: getInputValue('homeBoostCurrentDivision'),
    targetDivision: getInputValue('homeBoostDesiredDivision'),
    currentRR: getNumberValue('currentRR', 0),
    avgRRPerWin: getInputValue('averageRRgains'),
    region: getInputValue('homeBoostRegion'),
    platform: getInputValue('homeBoostPlatform'),
    boostMode: getInputValue('playType'),
    selectedAddons: getSelectedAddons('boost'),
    specificAgents: getAgentSelectionForContext('boost', 'specificAgents'),
    oneTrickAgent: getAgentSelectionForContext('boost', 'oneTrickAgent'),
  };
}

function buildPlacementPayload() {
  return {
    serviceType: 'Placement Matches',
    currentDivision: getInputValue('homePlacementLastTier'),
    numberOfPlacementGames: getNumberValue('homePlacementGames', 5),
    region: getInputValue('homePlacementRegion'),
    platform: getInputValue('homePlacementPlatform'),
    boostMode: getInputValue('homePlacementPlayType'),
    selectedAddons: getSelectedAddons('placement'),
    specificAgents: getAgentSelectionForContext('placement', 'specificAgents'),
    oneTrickAgent: getAgentSelectionForContext('placement', 'oneTrickAgent'),
  };
}

function buildRadiantPayload() {
  return {
    serviceType: 'Radiant Boost',
    currentDivision: getInputValue('homeRadiantCurrentDivision'),
    targetDivision: 'Radiant',
    currentRR: null,
    avgRRPerWin: getInputValue('averageRadiantRRgains'),
    region: getInputValue('homeRadiantRegion'),
    platform: getInputValue('homeRadiantPlatform'),
    boostMode: 'normal',
    selectedAddons: getSelectedAddons('radiant'),
    specificAgents: getAgentSelectionForContext('radiant', 'specificAgents'),
    oneTrickAgent: getAgentSelectionForContext('radiant', 'oneTrickAgent'),
  };
}

function buildRankedWinsPayload() {
  return {
    serviceType: 'Ranked Wins',
    currentDivision: getInputValue('homeRankedCurrentDivision'),
    numberOfWins: getClampedNumberValue('homeRankedWins', RANKED_WINS_LIMITS),
    region: getInputValue('homeRankedRegion'),
    platform: getInputValue('homeRankedPlatform'),
    boostMode: getInputValue('homeRankedPlayType'),
    selectedAddons: getSelectedAddons('ranked'),
    specificAgents: getAgentSelectionForContext('ranked', 'specificAgents'),
    oneTrickAgent: getAgentSelectionForContext('ranked', 'oneTrickAgent'),
  };
}

function buildPayload(serviceName) {
  let payload;

  switch (serviceName) {
    case 'Placement Matches':
      payload = buildPlacementPayload();
      break;
    case 'Radiant Boost':
      payload = buildRadiantPayload();
      break;
    case 'Ranked Wins':
      payload = buildRankedWinsPayload();
      break;
    default:
      payload = buildBoostPayload();
  }

  return {
    ...payload,
    gameSlug: currentGameSlug(),
    game: window.ggwpProductConfig?.gameName || window.appState?.gameName || 'Valorant',
  };
}

function syncAddonAvailability(serviceName) {
  const config = SERVICE_CONFIG[serviceName];
  if (!config) {
    return;
  }

  suppressScheduledCalculation = true;

  try {
    syncAddonRulesForContext(config.addonContext);
  } finally {
    suppressScheduledCalculation = false;
  }
}

async function requestCalculation(payload) {
  if (activeRequestController) {
    activeRequestController.abort();
  }

  const controller = new AbortController();
  activeRequestController = controller;

  try {
    const { response, data } = await requestJson(calculatePriceUrl(), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify(payload),
      signal: controller.signal,
      retries: 1,
      retryStatuses: [429, 502, 503, 504],
      fetchOptions: {
        cache: 'no-store',
      },
    });

    return {
      ok: response.ok,
      status: response.status,
      data,
    };
  } finally {
    if (activeRequestController === controller) {
      activeRequestController = null;
    }
  }
}

function flattenValidationErrors(validationErrors = {}) {
  return Object.values(validationErrors)
    .flat()
    .filter(Boolean);
}

function baseCalculationResult() {
  return {
    basePrice: 0,
    subtotalAfterRR: 0,
    subtotalAfterAddons: 0,
    subtotalAfterGlobalModifiers: 0,
    finalPrice: 0,
    disabledAddons: [],
    modifiers: {},
    pricing: {
      addons: 0,
    },
    validationErrors: {},
  };
}

function normalizeCalculationResult(result = {}) {
  const normalized = {
    ...baseCalculationResult(),
    ...(result && typeof result === 'object' ? result : {}),
  };

  normalized.pricing = {
    ...baseCalculationResult().pricing,
    ...(result?.pricing && typeof result.pricing === 'object' ? result.pricing : {}),
  };
  normalized.modifiers = result?.modifiers && typeof result.modifiers === 'object'
    ? result.modifiers
    : {};
  normalized.disabledAddons = Array.isArray(result?.disabledAddons)
    ? result.disabledAddons
    : [];
  normalized.validationErrors = result?.validationErrors && typeof result.validationErrors === 'object'
    ? result.validationErrors
    : {};
  normalized.gameSlug = normalized.gameSlug || currentGameSlug();
  normalized.game = normalized.game || window.ggwpProductConfig?.gameName || window.appState?.gameName || 'Valorant';

  return normalized;
}

function isStructuredCalculationResult(result) {
  return Boolean(
    result
    && typeof result === 'object'
    && 'pricing' in result
    && 'validationErrors' in result
  );
}

function transientFailureResult(serviceName, message) {
  const fallback = lastSuccessfulResults.get(serviceName) || baseCalculationResult();

  return {
    ...normalizeCalculationResult(fallback),
    validationErrors: {
      pricing: [message],
    },
  };
}

function previewResult(serviceName) {
  syncAddonAvailability(serviceName);
  const payload = buildPayload(serviceName);
  const preview = normalizeCalculationResult(calculatePricingPreview(payload));

  if (!hasPricingPreviewConfig()) {
    setPricingState(serviceName, {
      message: 'Refreshing quote...',
      tone: 'muted',
      showRetry: false,
    });
  }

  updatePriceCard(serviceName, preview);

  return {
    serviceName,
    payload,
    preview,
    signature: pricingPayloadSignature(payload),
  };
}

function hasPendingRequestFor(serviceName, signature) {
  return lastRequestedPayloadSignatures.get(serviceName) === signature;
}

function cancelPendingRequest() {
  if (!activeRequestController) {
    return;
  }

  activeRequestController.abort();
  lastRequestedPayloadSignatures.clear();
}

function setPricingState(serviceName, { message = '', tone = 'muted', showRetry = false } = {}) {
  const config = SERVICE_CONFIG[serviceName];
  const stateElement = byId(config?.priceIds?.state);
  const retryWrap = byId(config?.priceIds?.retryWrap);

  if (stateElement) {
    stateElement.textContent = message;
    stateElement.classList.remove('text-secondary', 'text-warning', 'text-danger', 'text-success');
    stateElement.classList.add(
      tone === 'danger'
        ? 'text-danger'
        : tone === 'warning'
          ? 'text-warning'
          : tone === 'success'
            ? 'text-success'
            : 'text-secondary'
    );
    stateElement.classList.toggle('d-none', !message);
  }

  if (retryWrap) {
    retryWrap.classList.toggle('d-none', !showRetry);
  }
}

function updatePriceCard(serviceName, result) {
  const config = SERVICE_CONFIG[serviceName];
  if (!config) {
    return;
  }

  const { priceIds } = config;
  const normalized = normalizeCalculationResult(result);
  const pricing = normalized.pricing;
  const messages = flattenValidationErrors(normalized.validationErrors);
  const hasErrors = messages.length > 0;
  const disabledAddons = normalized.disabledAddons;
  const modifiers = normalized.modifiers;

  syncQuoteSummary(serviceName);
  setText(priceIds.base, formatCurrency(normalized.basePrice));
  setText(priceIds.addon, `+${formatCurrency(pricing.addons)}`);
  setText(priceIds.afterRr, formatCurrency(normalized.subtotalAfterRR));
  setText(
    priceIds.modifiers,
    [
      `${modifiers.region?.code || '--'} x${Number(modifiers.region?.multiplier ?? 1).toFixed(2)}`,
      `${modifiers.platform?.code || '--'} x${Number(modifiers.platform?.multiplier ?? 1).toFixed(2)}`,
      `${modifiers.boostMode?.label || 'Account Shared'} x${Number(modifiers.boostMode?.multiplier ?? 1).toFixed(2)}`,
    ].join(' • ')
  );
  setText(priceIds.disabledAddons, disabledAddons.length ? disabledAddons.join(', ') : 'None');
  setAnimatedPrice(priceIds.total, formatCurrency(normalized.finalPrice));

  const errorElement = byId(priceIds.error);
  const checkoutButton = byId(config.checkoutButtonId);

  if (errorElement) {
    errorElement.textContent = hasErrors ? messages.join(' ') : '';
    toggleClass(errorElement, 'd-none', !hasErrors);
  }

  if (checkoutButton) {
    checkoutButton.classList.toggle('disabled', hasErrors);
    checkoutButton.setAttribute('aria-disabled', hasErrors ? 'true' : 'false');
  }
}

function persistOrderIfActive(serviceName, result) {
  if (serviceName !== getSelectedServiceName()) {
    return;
  }

  saveOrderToStorage(normalizeCalculationResult(result));
}

function scheduleCalculation() {
  const { serviceName, preview, signature } = previewResult(getSelectedServiceName());
  const hasErrors = flattenValidationErrors(preview.validationErrors).length > 0;

  if (!hasPendingRequestFor(serviceName, signature)) {
    cancelPendingRequest();
  }

  if (scheduledCalculationTimer) {
    window.clearTimeout(scheduledCalculationTimer);
  }

  if (hasErrors) {
    setPricingState(serviceName, {
      message: 'Adjust the highlighted selections to continue.',
      tone: 'warning',
      showRetry: false,
    });
    return;
  }

  if (lastSettledPayloadSignatures.get(serviceName) === signature) {
    setPricingState(serviceName, {
      message: '',
      showRetry: false,
    });
    return;
  }

  scheduledCalculationTimer = window.setTimeout(() => {
    scheduledCalculationTimer = null;
    void calculateAndPersistActiveOrder();
  }, CALCULATION_DEBOUNCE_MS);
}

async function calculateAndPersistActiveOrder({ forceRequest = false } = {}) {
  const { serviceName, payload, preview, signature } = previewResult(getSelectedServiceName());
  const hasPreviewErrors = flattenValidationErrors(preview.validationErrors).length > 0;

  if (!forceRequest) {
    if (hasPreviewErrors) {
      setPricingState(serviceName, {
        message: 'Adjust the highlighted selections to continue.',
        tone: 'warning',
      });
      return preview;
    }

    if (lastSettledPayloadSignatures.get(serviceName) === signature) {
      setPricingState(serviceName, {
        message: '',
        showRetry: false,
      });
      return lastSuccessfulResults.get(serviceName) || preview;
    }

    if (lastRequestedPayloadSignatures.get(serviceName) === signature) {
      return preview;
    }
  }

  lastRequestedPayloadSignatures.set(serviceName, signature);
  const requestToken = ++activeRequestToken;
  setPricingState(serviceName, {
    message: 'Refreshing quote...',
    tone: 'muted',
    showRetry: false,
  });

  try {
    const { ok, status, data } = await requestCalculation(payload);
    if (requestToken !== activeRequestToken) {
      return null;
    }

    if (!isStructuredCalculationResult(data)) {
      const message = 'Pricing is temporarily unavailable. Showing the last stable estimate for now.';
      const fallback = transientFailureResult(serviceName, message);

      updatePriceCard(serviceName, fallback);
      setPricingState(serviceName, {
        message,
        tone: 'warning',
        showRetry: true,
      });
      return preview;
    }

    const normalized = normalizeCalculationResult(data);

    if (!ok && status !== 422) {
      const message = status === 429
        ? 'You changed options too quickly. Pause for a moment and the price will refresh.'
        : 'Pricing is temporarily unavailable. Showing the last stable estimate for now.';

      updatePriceCard(serviceName, transientFailureResult(serviceName, message));
      setPricingState(serviceName, {
        message,
        tone: 'warning',
        showRetry: true,
      });
      return preview;
    }

    lastSettledPayloadSignatures.set(serviceName, signature);
    updatePriceCard(serviceName, normalized);

    if (!flattenValidationErrors(normalized.validationErrors).length) {
      lastSuccessfulResults.set(serviceName, normalized);
      persistOrderIfActive(serviceName, normalized);
      setPricingState(serviceName, {
        message: '',
        showRetry: false,
      });
    } else {
      setPricingState(serviceName, {
        message: 'Adjust the highlighted selections to continue.',
        tone: 'warning',
        showRetry: false,
      });
    }

    return normalized;
  } catch (error) {
    if (error.name === 'AbortError') {
      return preview;
    }

    if (requestToken !== activeRequestToken) {
      return preview;
    }

    const message = 'Pricing is temporarily unavailable. Showing the last stable estimate for now.';

    updatePriceCard(serviceName, transientFailureResult(serviceName, message));
    setPricingState(serviceName, {
      message,
      tone: 'warning',
      showRetry: true,
    });

    return preview;
  } finally {
    if (lastRequestedPayloadSignatures.get(serviceName) === signature) {
      lastRequestedPayloadSignatures.delete(serviceName);
    }
  }
}

function bindCheckoutButton(serviceName) {
  const config = SERVICE_CONFIG[serviceName];
  const button = byId(config?.checkoutButtonId);

  if (!button) {
    return;
  }

  button.addEventListener('click', async (event) => {
    trackEvent('homepage_cta_click', estimatorAnalyticsPayload(serviceName, {
      cta_id: config.checkoutButtonId,
      label: 'continue_to_checkout',
    }));

    if (scheduledCalculationTimer) {
      window.clearTimeout(scheduledCalculationTimer);
      scheduledCalculationTimer = null;
    }

    const result = await calculateAndPersistActiveOrder({ forceRequest: true });
    const hasErrors = !result || flattenValidationErrors(result.validationErrors).length > 0;

    if (hasErrors) {
      event.preventDefault();
      return;
    }

    if (!isLoggedIn()) {
      event.preventDefault();
      redirectToLogin('checkout');
    }
  });
}

function bindRetryButton(serviceName) {
  const config = SERVICE_CONFIG[serviceName];
  const button = byId(config?.priceIds?.retryButton);

  if (!button) {
    return;
  }

  button.addEventListener('click', () => {
    void calculateAndPersistActiveOrder({ forceRequest: true });
  });
}

function bindControlListeners() {
  const controls = queryAll(
    '#pane-boosting select, #pane-boosting input,' +
    '#pane-placement select, #pane-placement input,' +
    '#pane-radiant select, #pane-radiant input,' +
    '#pane-ranked select, #pane-ranked input'
  );

  controls.forEach((element) => {
    const handleCalculationChange = () => {
      scheduleCalculation();
      scheduleCalculatorInteraction(element);
      trackAddonSelection(element);
    };

    const handleCalculationInput = () => {
      scheduleCalculation();

      if (!(element instanceof HTMLInputElement) || !['checkbox', 'radio'].includes((element.type || '').toLowerCase())) {
        scheduleCalculatorInteraction(element);
      }
    };

    if (element instanceof HTMLInputElement) {
      const type = (element.type || '').toLowerCase();
      const useInputEvent = ['number', 'range', 'text', 'search', 'email', 'tel', 'url'].includes(type);

      element.addEventListener(useInputEvent ? 'input' : 'change', useInputEvent ? handleCalculationInput : handleCalculationChange);
      return;
    }

    element.addEventListener('change', handleCalculationChange);
  });
}

export function initEstimator() {
  const servicesTab = byId('servicesTab');
  if (!servicesTab) {
    return;
  }

  Object.keys(SERVICE_CONFIG).forEach(syncAddonAvailability);
  setupBoundedNumberInput('homeRankedWins', RANKED_WINS_LIMITS);
  bindControlListeners();

  queryAll('#servicesTab .nav-link').forEach((tab) => {
    tab.addEventListener('shown.bs.tab', () => {
      if (scheduledCalculationTimer) {
        window.clearTimeout(scheduledCalculationTimer);
        scheduledCalculationTimer = null;
      }

      scheduleCalculation();
    });
  });

  Object.keys(SERVICE_CONFIG).forEach(bindCheckoutButton);
  Object.keys(SERVICE_CONFIG).forEach(bindRetryButton);
  document.addEventListener('agent-selector:changed', scheduleCalculation);
  document.addEventListener('addon-rules:sync', () => {
    if (suppressScheduledCalculation) {
      return;
    }

    scheduleCalculation();
  });

  scheduleCalculation();
  void loadPricingConfig().then((loaded) => {
    if (loaded) {
      scheduleCalculation();
    }
  });
}
