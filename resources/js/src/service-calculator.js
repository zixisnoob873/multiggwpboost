import { isLoggedIn, redirectToLogin, requestJson } from './common';
import { trackEvent } from './analytics';
import { formatCurrency } from './formatting';
import { saveOrderToStorage } from './order-storage';

const CALCULATION_DEBOUNCE_MS = 140;
const ANALYTICS_DEBOUNCE_MS = 500;

function parseConfig(root) {
  const configId = root.dataset.serviceCalculatorConfig;
  const element = configId ? document.getElementById(configId) : null;

  if (!element) {
    return {};
  }

  try {
    const parsed = JSON.parse(element.textContent || '{}');

    return parsed && typeof parsed === 'object' ? parsed : {};
  } catch (_) {
    return {};
  }
}

function field(root, name) {
  return root.querySelector(`[data-service-field="${name}"]`);
}

function text(root, selector, value) {
  const element = root.querySelector(selector);

  if (element) {
    element.textContent = value;
  }
}

function setHidden(element, hidden) {
  if (element) {
    element.classList.toggle('d-none', hidden);
  }
}

function selectedAddonInputs(root) {
  return Array.from(root.querySelectorAll('[data-service-addon]:checked'));
}

function flattenErrors(errors = {}) {
  return Object.values(errors || {})
    .flat()
    .filter(Boolean);
}

function csrfToken(config) {
  return config.csrfToken || window.appState?.csrfToken || '';
}

function getValue(root, name, fallback = '') {
  const element = field(root, name);

  return String(element?.value || fallback || '').trim();
}

function normalizeSlug(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

function calculatorAnalyticsPayload(config, extra = {}) {
  return {
    context: 'service_calculator',
    game_slug: config.gameSlug || window.appState?.gameSlug || '',
    game_name: config.gameName || window.appState?.gameName || '',
    service_slug: config.serviceSlug || '',
    service_name: config.serviceName || '',
    service_type: config.serviceType || '',
    ...extra,
  };
}

function controlFieldName(control) {
  if (!(control instanceof HTMLElement)) {
    return 'unknown';
  }

  return control.dataset.serviceField
    || (control.matches('[data-service-addon]') ? 'addon' : '')
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

function syncQueueControls(root) {
  const queueType = field(root, 'queueType');
  const duoInput = root.querySelector('[data-addon-controls-queue="self_play"]');

  if (!queueType || !duoInput) {
    return;
  }

  if (duoInput.checked && queueType.value !== 'self_play') {
    queueType.value = 'self_play';
  }

  if (queueType.value === 'self_play' && !duoInput.checked) {
    duoInput.checked = true;
  }

  if (queueType.value !== 'self_play' && duoInput.checked) {
    duoInput.checked = false;
  }
}

function buildPayload(root, config) {
  syncQueueControls(root);

  const addonInputs = selectedAddonInputs(root);
  const queueType = getValue(root, 'queueType', config.defaults?.queueType || 'normal');

  return {
    gameSlug: config.gameSlug,
    game: config.gameName,
    serviceSlug: config.serviceSlug,
    serviceType: config.serviceType,
    orderType: config.serviceType,
    currentRank: getValue(root, 'currentRank', config.defaults?.currentRank),
    currentDivision: getValue(root, 'currentRank', config.defaults?.currentRank),
    desiredRank: getValue(root, 'desiredRank', config.defaults?.desiredRank),
    targetRank: getValue(root, 'desiredRank', config.defaults?.desiredRank),
    targetDivision: getValue(root, 'desiredRank', config.defaults?.desiredRank),
    desiredDivision: getValue(root, 'desiredRank', config.defaults?.desiredRank),
    currentLevel: Number(getValue(root, 'currentLevel') || config.defaults?.currentLevel || '') || null,
    desiredLevel: Number(getValue(root, 'desiredLevel') || config.defaults?.desiredLevel || '') || null,
    queueType,
    boostMode: queueType,
    accountType: queueType === 'self_play' ? 'Duo / Self-Play' : 'Account Shared',
    currentRR: Number(config.defaults?.currentRR ?? 0),
    avgRRPerWin: String(config.defaults?.avgRRPerWin || '18'),
    region: String(config.defaults?.region || 'EU'),
    platform: String(config.defaults?.platform || 'PC'),
    selectedAddons: addonInputs
      .map((input) => input.dataset.addonValue || input.value)
      .filter(Boolean),
    selectedOptions: {
      game: config.gameName,
      service: config.serviceType,
      currentRank: getValue(root, 'currentRank', config.defaults?.currentRank),
      desiredRank: getValue(root, 'desiredRank', config.defaults?.desiredRank),
      currentLevel: getValue(root, 'currentLevel', config.defaults?.currentLevel),
      desiredLevel: getValue(root, 'desiredLevel', config.defaults?.desiredLevel),
      queueType,
    },
    duoQueue: addonInputs.some((input) => input.dataset.addonControlsQueue === 'self_play'),
    streamGames: addonInputs.some((input) => input.dataset.addonFlag === 'streamGames'),
    expressDelivery: addonInputs.some((input) => input.dataset.addonFlag === 'expressDelivery'),
  };
}

function updatePrice(root, result = {}) {
  const pricing = result.pricing || {};
  const errors = flattenErrors(result.validationErrors);
  const hasErrors = errors.length > 0;
  const checkout = root.querySelector('[data-service-checkout]');
  const errorElement = root.querySelector('[data-service-price-error]');

  text(root, '[data-service-price-base]', formatCurrency(result.basePrice ?? pricing.basePrice ?? 0));
  text(root, '[data-service-price-addons]', `+${formatCurrency(pricing.addons ?? 0)}`);
  text(root, '[data-service-price-total]', formatCurrency(result.finalPrice ?? pricing.total ?? 0));

  if (errorElement) {
    errorElement.textContent = errors.join(' ');
    setHidden(errorElement, !hasErrors);
  }

  if (checkout) {
    checkout.classList.toggle('disabled', hasErrors);
    checkout.setAttribute('aria-disabled', hasErrors ? 'true' : 'false');
  }
}

function setState(root, message, tone = 'muted') {
  const element = root.querySelector('[data-service-price-state]');

  if (!element) {
    return;
  }

  element.textContent = message || '';
  element.classList.remove('text-secondary', 'text-warning', 'text-danger', 'text-success');
  element.classList.add(
    tone === 'danger'
      ? 'text-danger'
      : tone === 'warning'
        ? 'text-warning'
        : tone === 'success'
          ? 'text-success'
          : 'text-secondary',
  );
  setHidden(element, !message);
}

function initCalculator(root) {
  const config = parseConfig(root);
  const endpoint = config.pricingEndpoint || window.appState?.calculatePriceUrl || '/calculate-price';
  let timer = null;
  let analyticsTimer = null;
  let activeController = null;
  let lastResult = null;

  const calculate = async ({ force = false } = {}) => {
    if (timer) {
      window.clearTimeout(timer);
      timer = null;
    }

    if (activeController) {
      activeController.abort();
    }

    const payload = buildPayload(root, config);
    const controller = new AbortController();
    activeController = controller;
    setState(root, 'Refreshing quote...');

    try {
      const { response, data } = await requestJson(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken(config),
        },
        body: JSON.stringify(payload),
        signal: controller.signal,
        retries: force ? 1 : 0,
        retryStatuses: [429, 502, 503, 504],
        fetchOptions: {
          cache: 'no-store',
        },
      });

      if (!response.ok && response.status !== 422) {
        setState(root, 'Pricing is temporarily unavailable. Please retry in a moment.', 'warning');
        return lastResult;
      }

      lastResult = data;
      updatePrice(root, data);

      if (flattenErrors(data.validationErrors).length) {
        setState(root, 'Adjust the highlighted selections to continue.', 'warning');
        return data;
      }

      saveOrderToStorage(data);
      setState(root, '');

      return data;
    } catch (error) {
      if (error?.name !== 'AbortError') {
        setState(root, 'Pricing is temporarily unavailable. Please retry in a moment.', 'warning');
      }

      return lastResult;
    } finally {
      if (activeController === controller) {
        activeController = null;
      }
    }
  };

  const schedule = () => {
    if (timer) {
      window.clearTimeout(timer);
    }

    timer = window.setTimeout(() => {
      void calculate();
    }, CALCULATION_DEBOUNCE_MS);
  };

  const scheduleInteractionEvent = (control) => {
    if (analyticsTimer) {
      window.clearTimeout(analyticsTimer);
    }

    analyticsTimer = window.setTimeout(() => {
      analyticsTimer = null;
      trackEvent('calculator_interaction', calculatorAnalyticsPayload(config, {
        field: controlFieldName(control),
        field_type: controlFieldType(control),
      }));
    }, ANALYTICS_DEBOUNCE_MS);
  };

  const trackAddonSelection = (control) => {
    if (!(control instanceof HTMLInputElement) || !control.matches('[data-service-addon]')) {
      return;
    }

    const label = control.dataset.addonLabel || control.value || '';

    trackEvent('addon_selection', calculatorAnalyticsPayload(config, {
      addon_slug: control.dataset.addonSlug || normalizeSlug(label),
      addon_label: label,
      selected: control.checked,
    }));
  };

  root.querySelectorAll('select, input').forEach((control) => {
    control.addEventListener('change', () => {
      schedule();
      scheduleInteractionEvent(control);
      trackAddonSelection(control);
    });
    control.addEventListener('input', () => {
      schedule();

      if (!control.matches('[type="checkbox"], [type="radio"]')) {
        scheduleInteractionEvent(control);
      }
    });
  });

  field(root, 'queueType')?.addEventListener('change', () => {
    const duoInput = root.querySelector('[data-addon-controls-queue="self_play"]');

    if (duoInput) {
      duoInput.checked = field(root, 'queueType')?.value === 'self_play';
    }
  });

  root.querySelector('[data-addon-controls-queue="self_play"]')?.addEventListener('change', (event) => {
    const queueType = field(root, 'queueType');

    if (queueType) {
      queueType.value = event.currentTarget.checked ? 'self_play' : 'normal';
    }
  });

  root.querySelector('[data-service-checkout]')?.addEventListener('click', async (event) => {
    event.preventDefault();

    const result = await calculate({ force: true });

    if (!result || flattenErrors(result.validationErrors).length) {
      return;
    }

    if (!isLoggedIn()) {
      redirectToLogin('checkout');
      return;
    }

    window.location.href = config.checkoutUrl || event.currentTarget.href;
  });

  void calculate();
}

export function initServiceCalculators() {
  document.querySelectorAll('[data-service-calculator]').forEach(initCalculator);
}
