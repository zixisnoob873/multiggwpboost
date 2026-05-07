import { extractApiErrorMessage, isLoggedIn, redirectToLogin, requestJson, setButtonBusy } from './common';
import { byId, queryAll, setText, toggleClass, toggleRequired } from './dom';
import { displayValue, formatCurrency } from './formatting';
import { clearCheckoutPromoFromStorage, loadCheckoutPromoFromStorage, loadOrderFromStorage, saveCheckoutPromoToStorage } from './order-storage';
import { pricingPayloadSignature } from './pricing-preview';

const summaryFields = {
  osOrderType: (order) => order.orderType,
  osCurrentDivision: (order) => order.currentDivision,
  osDesiredDivision: (order) => order.desiredDivision,
  osCurrentRR: (order) => String(order.currentRR || displayValue('')),
  osAvgRR: (order) => displayValue(order.averageRR),
  osPlayType: (order) => displayBoostMode(order.accountType || order.boostMode || order.region),
};

function displayBoostMode(value) {
  const label = displayValue(value);

  return String(label).toLowerCase() === 'self-play' ? 'Duo / Self-Play' : label;
}

function resolveAgentNames(values) {
  const lookup = new Map(
    (Array.isArray(window.appState?.valorantAgents) ? window.appState.valorantAgents : [])
      .map((agent) => [String(agent?.uuid || '').trim().toLowerCase(), String(agent?.displayName || '').trim()])
      .filter(([uuid, displayName]) => uuid && displayName),
  );
  const seen = new Set();

  return (Array.isArray(values) ? values : [])
    .map((value) => String(value || '').trim().toLowerCase())
    .filter((value) => value && !seen.has(value) && seen.add(value))
    .map((value) => lookup.get(value) || value)
    .filter(Boolean);
}

function renderSummaryList(target, values, emptyLabel = 'None') {
  const element = typeof target === 'string' ? byId(target) : target;

  if (!element) {
    return;
  }

  const items = Array.isArray(values) ? values.filter(Boolean) : [];
  const fragment = document.createDocumentFragment();

  if (!items.length) {
    const emptyItem = document.createElement('li');
    emptyItem.className = 'text-secondary';
    emptyItem.textContent = emptyLabel;
    fragment.appendChild(emptyItem);
    element.replaceChildren(fragment);
    return;
  }

  items.forEach((value) => {
    const item = document.createElement('li');
    item.textContent = value;
    fragment.appendChild(item);
  });

  element.replaceChildren(fragment);
}

function renderAddonSummary(order, promo = null) {
  const element = byId('osAddons');

  if (!element) {
    return;
  }

  const addons = Array.isArray(order?.addons)
    ? order.addons.filter(Boolean)
    : Array.isArray(order?.selectedAddons)
      ? order.selectedAddons.filter(Boolean)
      : [];
  const adjustments = Array.isArray(promo?.promoAddonAdjustments)
    ? promo.promoAddonAdjustments
    : Array.isArray(order?.promoAddonAdjustments)
      ? order.promoAddonAdjustments
      : [];
  const adjustmentMap = new Map(adjustments.map((adjustment) => [adjustment.label, adjustment]));

  if (!addons.length) {
    const item = document.createElement('li');
    item.className = 'text-secondary';
    item.textContent = 'None';
    element.replaceChildren(item);
    return;
  }

  const fragment = document.createDocumentFragment();

  addons.forEach((label) => {
    const adjustment = adjustmentMap.get(label);
    const item = document.createElement('li');

    if (!adjustment) {
      item.textContent = label;
      fragment.appendChild(item);
      return;
    }

    const originalAmount = Number(adjustment.originalAmount ?? 0);
    const discountedAmount = Number(adjustment.discountedAmount ?? 0);
    const badge = adjustment.addedByPromo ? 'Added via promocode' : 'Promo Applied';
    const line = document.createElement('div');
    line.className = 'd-flex flex-wrap align-items-center gap-2';

    const labelText = document.createElement('span');
    labelText.textContent = label;

    const badgeText = document.createElement('span');
    badgeText.className = 'badge text-bg-success';
    badgeText.textContent = badge;

    line.append(labelText, badgeText);
    item.appendChild(line);

    if (originalAmount > 0 || discountedAmount > 0) {
      const priceLine = document.createElement('div');
      priceLine.className = 'text-secondary small';
      priceLine.textContent = `${formatCurrency(originalAmount)} -> ${formatCurrency(discountedAmount)}`;
      item.appendChild(priceLine);
    }

    fragment.appendChild(item);
  });

  element.replaceChildren(fragment);
}

function updateSummaryWithOrder(order, promo = null) {
  Object.entries(summaryFields).forEach(([id, getter]) => {
    setText(id, displayValue(getter(order)));
  });

  const specificAgents = resolveAgentNames(order.specificAgents);
  const oneTrickAgent = resolveAgentNames(order.oneTrickAgent);

  renderAddonSummary(order, promo);
  renderSummaryList('osSpecificAgents', specificAgents);
  renderSummaryList('osOneTrickAgent', oneTrickAgent);
  toggleClass('osSpecificAgentsRow', 'd-none', specificAgents.length === 0);
  toggleClass('osOneTrickAgentRow', 'd-none', oneTrickAgent.length === 0);
}

function updateTotals(pricing = {}, promo = null) {
  const baseTotal = Number(pricing.total ?? pricing.finalPrice ?? 0);
  const originalTotal = Number(promo?.orderAmount ?? baseTotal);
  const discountAmount = Number(promo?.discountAmount ?? 0);
  const finalTotal = promo ? Number(promo.discountedTotal ?? originalTotal) : baseTotal;

  setText('osBase', formatCurrency(pricing.basePrice ?? pricing.base));
  setText('osAddonsPrice', formatCurrency(pricing.addons));
  setText('osPromoCode', promo?.code || 'None');
  setText('osOriginalTotal', formatCurrency(originalTotal));
  setText('osPromoDiscount', discountAmount > 0 ? `-${formatCurrency(discountAmount)}` : '-$0.00');
  toggleClass('osPromoCodeRow', 'd-none', !promo?.code);
  toggleClass('osOriginalTotalRow', 'd-none', !(promo?.code && originalTotal > 0));
  toggleClass('osPromoDiscountRow', 'd-none', !(discountAmount > 0));
  setText('osTotal', formatCurrency(finalTotal));
}

function showMissingOrder(statusElement, payButton) {
  const gameName = window.appState?.gameName || 'Valorant';

  if (statusElement) {
    statusElement.textContent = `Please configure a ${gameName} boost before checking out.`;
    toggleClass(statusElement, 'd-none', false);
    statusElement.classList.remove('alert-success');
    statusElement.classList.add('alert-warning');
  }

  if (payButton) {
    payButton.disabled = true;
  }
}

function toggleContactFields(method) {
  const whatsappWrap = byId('whatsappWrap');
  const discordWrap = byId('discordWrap');
  const whatsappInput = byId('whatsapp');
  const discordInput = byId('discord');
  const showWhatsapp = method === 'whatsapp';
  const showDiscord = method === 'discord';

  toggleClass(whatsappWrap, 'd-none', !showWhatsapp);
  toggleClass(discordWrap, 'd-none', !showDiscord);
  toggleRequired(whatsappInput, showWhatsapp);
  toggleRequired(discordInput, showDiscord);

  if (whatsappInput && !showWhatsapp) {
    whatsappInput.value = '';
  }

  if (discordInput && !showDiscord) {
    discordInput.value = '';
  }
}

function updateMethodDisplay(methodInputs, payButton, paymentNotice) {
  const selected = methodInputs.find((input) => input.checked && !input.disabled);
  const noticeText = selected?.dataset?.notice || 'Payments are unavailable right now. Please contact support.';
  const submitLabel = selected?.dataset?.submitLabel || 'Payment Unavailable';

  if (payButton) {
    payButton.textContent = submitLabel;
  }

  if (paymentNotice) {
    paymentNotice.textContent = noticeText;
  }
}

function csrfToken() {
  return window.appState?.csrfToken || '';
}

function promoPreviewUrl() {
  return window.appState?.promoPreviewUrl || '';
}

function setPromoFeedback(element, tone, message) {
  if (!element) {
    return;
  }

  const tones = ['alert-success', 'alert-danger', 'alert-warning', 'alert-info'];
  element.classList.remove(...tones);

  if (!message) {
    element.textContent = '';
    toggleClass(element, 'd-none', true);
    return;
  }

  element.textContent = message;
  element.classList.add(`alert-${tone}`);
  toggleClass(element, 'd-none', false);
}

async function previewPromoCode({ promoInput, orderPayloadInput, feedbackElement, applyButton, pricing, onApplied, onFailed }) {
  const code = promoInput?.value?.trim() || '';

  if (!code) {
    onFailed();
    promoInput?.classList.add('is-invalid');
    setPromoFeedback(feedbackElement, 'warning', 'Enter a promo code to preview the discount.');
    return;
  }

  const url = promoPreviewUrl();
  if (!url) {
    onFailed();
    setPromoFeedback(feedbackElement, 'danger', 'Promo code preview is unavailable right now.');
    return;
  }

  setButtonBusy(applyButton, true, 'Checking...');
  promoInput?.classList.remove('is-invalid');
  setPromoFeedback(feedbackElement, 'info', 'Checking promo code...');

  try {
    const { response, data: payload } = await requestJson(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
      },
      body: JSON.stringify({
        promoCode: code,
        orderPayload: orderPayloadInput?.value || '',
      }),
      retries: 1,
      retryStatuses: [429, 502, 503, 504],
    });

    if (!response.ok) {
      onFailed();
      promoInput?.classList.add('is-invalid');
      setPromoFeedback(
        feedbackElement,
        'danger',
        extractApiErrorMessage(payload, 'This promo code could not be applied.'),
      );
      updateTotals(pricing);
      return;
    }

    onApplied(payload);
    setPromoFeedback(feedbackElement, 'success', payload?.message || 'Promo code applied.');
  } catch (error) {
    onFailed();
    setPromoFeedback(feedbackElement, 'danger', 'We could not validate that promo code right now. Please try again.');
    updateTotals(pricing);
  } finally {
    setButtonBusy(applyButton, false);
  }
}

export function initCheckoutFlow() {
  const form = byId('checkoutForm');
  if (!form) {
    return;
  }

  const orderPayloadInput = byId('orderPayload');
  const payButton = byId('payBtn');
  const statusElement = byId('paymentStatus');
  const contactMethod = byId('contactMethod');
  const methodInputs = queryAll('input[name="paymentMethod"]');
  const paymentNotice = byId('paymentMethodNotice');
  const promoInput = byId('promoCode');
  const promoFeedback = byId('promoCodeFeedback');
  const applyPromoButton = byId('applyPromoCodeBtn');
  const removePromoButton = byId('removePromoCodeBtn');
  const policyInput = byId('policy');
  const complianceInput = byId('compliance');
  const acknowledgementError = byId('checkoutAcknowledgementError');
  const order = loadOrderFromStorage();
  const orderSignature = pricingPayloadSignature(order || {});
  const storedPromo = loadCheckoutPromoFromStorage();
  let appliedPromo = null;
  let displayedOrder = order;
  let checkoutSubmitting = false;

  if (!order) {
    showMissingOrder(statusElement, payButton);
    return;
  }

  if (order.validationErrors && Object.keys(order.validationErrors).length) {
    showMissingOrder(statusElement, payButton);
    return;
  }

  if (orderPayloadInput) {
    orderPayloadInput.value = JSON.stringify(order);
  }

  updateSummaryWithOrder(order);
  updateTotals(order.pricing, appliedPromo);

  const syncContactFields = () => {
    toggleContactFields(contactMethod?.value || 'email');
  };

  const hasReadyProvider = () => methodInputs.some((input) => input.checked && !input.disabled && input.dataset.providerReady === '1');
  const hasAcceptedPolicies = () => Boolean(policyInput?.checked) && Boolean(complianceInput?.checked);

  const syncSubmitState = () => {
    if (!payButton) {
      return;
    }

    payButton.disabled = !hasReadyProvider() || !hasAcceptedPolicies();
    toggleClass(acknowledgementError, 'd-none', hasAcceptedPolicies());

    if (policyInput?.checked) {
      policyInput.classList.remove('is-invalid');
    }

    if (complianceInput?.checked) {
      complianceInput.classList.remove('is-invalid');
    }
  };

  contactMethod?.addEventListener('change', syncContactFields);
  syncContactFields();

  methodInputs.forEach((input) => {
    input.addEventListener('change', () => {
      updateMethodDisplay(methodInputs, payButton, paymentNotice);
      syncSubmitState();
    });
  });
  updateMethodDisplay(methodInputs, payButton, paymentNotice);

  policyInput?.addEventListener('change', syncSubmitState);
  complianceInput?.addEventListener('change', syncSubmitState);
  syncSubmitState();

  const syncPromoControls = () => {
    const hasPromo = Boolean(appliedPromo?.code);

    if (promoInput) {
      promoInput.readOnly = hasPromo;
    }

    toggleClass(applyPromoButton, 'd-none', hasPromo);
    toggleClass(removePromoButton, 'd-none', !hasPromo);
  };

  const clearAppliedPromo = ({ silent = false, clearInput = false } = {}) => {
    appliedPromo = null;
    displayedOrder = order;
    updateSummaryWithOrder(order);
    updateTotals(order.pricing, null);
    syncPromoControls();
    clearCheckoutPromoFromStorage();

    if (clearInput && promoInput) {
      promoInput.value = '';
    }

    if (!silent) {
      setPromoFeedback(promoFeedback, 'info', '');
    }
  };

  const applyResolvedPromo = (payload) => {
    appliedPromo = payload?.promo || null;
    displayedOrder = payload?.order || order;
    promoInput?.classList.remove('is-invalid');
    updateSummaryWithOrder(displayedOrder, appliedPromo);
    updateTotals(displayedOrder?.pricing || order.pricing, appliedPromo);
    syncPromoControls();
    saveCheckoutPromoToStorage({
      code: appliedPromo?.code,
      signature: orderSignature,
    });
  };

  applyPromoButton?.addEventListener('click', async () => {
    await previewPromoCode({
      promoInput,
      orderPayloadInput,
      feedbackElement: promoFeedback,
      applyButton: applyPromoButton,
      pricing: order.pricing,
      onApplied: (payload) => {
        applyResolvedPromo(payload);
      },
      onFailed: () => {
        clearAppliedPromo({ silent: true });
      },
    });
  });

  removePromoButton?.addEventListener('click', () => {
    clearAppliedPromo({ clearInput: true });
  });

  if (storedPromo?.signature && storedPromo.signature !== orderSignature) {
    clearCheckoutPromoFromStorage();
  }

  if (!promoInput?.value?.trim() && storedPromo?.signature === orderSignature && storedPromo?.code) {
    promoInput.value = storedPromo.code;
  }

  if (promoInput?.value?.trim()) {
    void previewPromoCode({
      promoInput,
      orderPayloadInput,
      feedbackElement: promoFeedback,
      applyButton: applyPromoButton,
      pricing: order.pricing,
      onApplied: (payload) => {
        applyResolvedPromo(payload);
      },
      onFailed: () => {
        clearAppliedPromo({ silent: true });
      },
    });
  }

  window.addEventListener('pageshow', () => {
    checkoutSubmitting = false;
    setButtonBusy(payButton, false);
  });

  syncPromoControls();

  form.addEventListener('submit', (event) => {
    if (!isLoggedIn()) {
      event.preventDefault();
      checkoutSubmitting = false;
      setButtonBusy(payButton, false);
      redirectToLogin('checkout');
      return;
    }

    const selectedProvider = methodInputs.find((input) => input.checked && !input.disabled && input.dataset.providerReady === '1');
    const missingPolicies = !hasAcceptedPolicies();

    form.classList.add('was-validated');

    if (!selectedProvider || missingPolicies || !form.checkValidity()) {
      event.preventDefault();
      checkoutSubmitting = false;
      setButtonBusy(payButton, false);

      if (missingPolicies) {
        policyInput?.classList.toggle('is-invalid', !policyInput?.checked);
        complianceInput?.classList.toggle('is-invalid', !complianceInput?.checked);
        toggleClass(acknowledgementError, 'd-none', false);
      }

      syncSubmitState();
      return;
    }

    if (checkoutSubmitting) {
      event.preventDefault();
      return;
    }

    checkoutSubmitting = true;
    setButtonBusy(payButton, true, 'Redirecting...');

    if (statusElement) {
      statusElement.className = 'alert alert-info';
      statusElement.textContent = 'Preparing your secure payment...';
      toggleClass(statusElement, 'd-none', false);
    }
  });
}
