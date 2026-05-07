import { byId, queryAll, setText, toggleClass } from './dom';
import { formatCurrency } from './formatting';
import { getAgentSelectionForContext } from './agent-selectors';
import { calculatePricingPreview, loadPricingConfig } from './pricing-preview';

const SERVICE_FIELD_RULES = {
  'Rank Boosting': {
    showRanks: true,
    showDesiredRank: true,
    showCurrentRr: true,
    showAverageRr: true,
    showWins: false,
    showPlacementGames: false,
    fixedDesiredRank: null,
  },
  'Radiant Boost': {
    showRanks: true,
    showDesiredRank: true,
    showCurrentRr: false,
    showAverageRr: true,
    showWins: false,
    showPlacementGames: false,
    fixedDesiredRank: 'Radiant',
  },
  'Ranked Wins': {
    showRanks: true,
    showDesiredRank: false,
    showCurrentRr: false,
    showAverageRr: false,
    showWins: true,
    showPlacementGames: false,
    fixedDesiredRank: null,
  },
  'Placement Matches': {
    showRanks: true,
    showDesiredRank: false,
    showCurrentRr: false,
    showAverageRr: false,
    showWins: false,
    showPlacementGames: true,
    fixedDesiredRank: null,
  },
};

function fieldValue(id, fallback = '') {
  const element = byId(id);

  return element ? String(element.value || fallback).trim() : fallback;
}

function numberValue(id, fallback = null) {
  const raw = fieldValue(id);

  if (raw === '') {
    return fallback;
  }

  const parsed = Number(raw);

  return Number.isFinite(parsed) ? parsed : fallback;
}

function selectedAddons() {
  return queryAll('[data-addon-grid="admin-manual"] input[data-addon-label]:checked')
    .map((input) => input.dataset.addonLabel)
    .filter(Boolean);
}

function flattenValidationErrors(validationErrors = {}) {
  return Object.values(validationErrors)
    .flat()
    .filter(Boolean);
}

function buildPayload(serviceType) {
  return {
    serviceType,
    currentDivision: fieldValue('manualCurrentDivision'),
    targetDivision: fieldValue('manualDesiredDivision'),
    currentRR: numberValue('manualCurrentRR'),
    avgRRPerWin: fieldValue('manualAverageRr'),
    region: fieldValue('manualRegion'),
    platform: fieldValue('manualPlatform'),
    boostMode: fieldValue('manualAccountType'),
    selectedAddons: selectedAddons(),
    specificAgents: getAgentSelectionForContext('admin-manual', 'specificAgents'),
    oneTrickAgent: getAgentSelectionForContext('admin-manual', 'oneTrickAgent'),
    numberOfWins: numberValue('manualWinsNeeded'),
    numberOfPlacementGames: numberValue('manualPlacementGames'),
  };
}

function clampInputValue(element, min, max, fallback) {
  if (!(element instanceof HTMLInputElement)) {
    return;
  }

  const parsed = Number(element.value);
  const clamped = Number.isFinite(parsed) ? Math.min(max, Math.max(min, Math.trunc(parsed))) : fallback;

  element.value = String(clamped);
}

export function initAdminManualOrderPricing() {
  const serviceInput = byId('manualServiceProduct');

  if (!(serviceInput instanceof HTMLSelectElement)) {
    return;
  }

  const rankFields = byId('manualRankFields');
  const currentDivision = byId('manualCurrentDivision');
  const desiredDivision = byId('manualDesiredDivision');
  const currentRrWrap = byId('manualCurrentRrWrap');
  const averageRrWrap = byId('manualAverageRrWrap');
  const winsWrap = byId('manualWinsWrap');
  const placementWrap = byId('manualPlacementWrap');
  const desiredDivisionWrap = byId('manualDesiredDivisionWrap');
  const currentRrInput = byId('manualCurrentRR');
  const averageRrInput = byId('manualAverageRr');
  const winsInput = byId('manualWinsNeeded');
  const placementInput = byId('manualPlacementGames');
  const priceInput = byId('manualPriceInput');
  const status = byId('manualPricingStatus');
  let priceTouched = Boolean(priceInput instanceof HTMLInputElement && priceInput.value.trim() !== '');

  const syncSuggestedPrice = (result, hasErrors) => {
    if (!(priceInput instanceof HTMLInputElement)) {
      return;
    }

    if (hasErrors) {
      if (!priceTouched && priceInput.value.trim() === '') {
        priceInput.placeholder = 'Enter custom price';
      }

      return;
    }

    const suggestedTotal = Number(result.finalPrice || 0).toFixed(2);

    if (!priceTouched || priceInput.value.trim() === '') {
      priceInput.value = suggestedTotal;
      priceInput.placeholder = suggestedTotal;
    }
  };

  const updatePreview = () => {
    const serviceType = fieldValue('manualServiceProduct');
    const rules = SERVICE_FIELD_RULES[serviceType] || SERVICE_FIELD_RULES['Rank Boosting'];

    toggleClass(rankFields, 'd-none', !rules.showRanks);
    toggleClass(desiredDivisionWrap, 'd-none', !rules.showDesiredRank);
    toggleClass(currentRrWrap, 'd-none', !rules.showCurrentRr);
    toggleClass(averageRrWrap, 'd-none', !rules.showAverageRr);
    toggleClass(winsWrap, 'd-none', !rules.showWins);
    toggleClass(placementWrap, 'd-none', !rules.showPlacementGames);

    if (currentDivision instanceof HTMLSelectElement) {
      currentDivision.disabled = !rules.showRanks;
    }

    if (desiredDivision instanceof HTMLSelectElement) {
      desiredDivision.disabled = !rules.showDesiredRank;

      desiredDivision.querySelectorAll('option').forEach((option) => {
        option.hidden = Boolean(rules.fixedDesiredRank) && option.value !== '' && option.value !== rules.fixedDesiredRank;
      });

      if (rules.fixedDesiredRank) {
        desiredDivision.value = rules.fixedDesiredRank;
      } else if (!rules.showDesiredRank) {
        desiredDivision.value = '';
      }
    }

    if (!rules.showCurrentRr && currentRrInput instanceof HTMLInputElement) {
      currentRrInput.value = '';
    }

    if (!rules.showAverageRr && averageRrInput instanceof HTMLSelectElement) {
      averageRrInput.value = '';
    }

    if (!rules.showWins && winsInput instanceof HTMLInputElement) {
      winsInput.value = '1';
    }

    if (!rules.showPlacementGames && placementInput instanceof HTMLInputElement) {
      placementInput.value = '5';
    }

    const result = calculatePricingPreview(buildPayload(serviceType));
    const messages = flattenValidationErrors(result.validationErrors);
    const hasErrors = messages.length > 0;

    syncSuggestedPrice(result, hasErrors);

    setText('manualPricingBase', formatCurrency(result.basePrice));
    setText('manualPricingAddons', formatCurrency(result.pricing?.addons || 0));
    setText('manualPricingAfterModifiers', formatCurrency(result.subtotalAfterGlobalModifiers));
    setText('manualPricingTotal', formatCurrency(result.finalPrice));

    if (status) {
      status.textContent = hasErrors
        ? `Customer-flow preview is unavailable for this setup. Admin override is still allowed here; enter a custom price to continue. ${messages.join(' ')}`.trim()
        : 'Customer-flow preview updated. You can keep the suggested total or override it with a custom admin price.';
      status.classList.remove('text-danger');
      status.classList.toggle('text-warning', hasErrors);
      status.classList.toggle('text-secondary', !hasErrors);
    }
  };

  if (priceInput instanceof HTMLInputElement) {
    priceInput.addEventListener('input', () => {
      priceTouched = priceInput.value.trim() !== '';
    });
  }

  [winsInput, placementInput].forEach((input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    input.addEventListener('input', () => {
      clampInputValue(input, 1, 5, 1);
    });
  });

  queryAll(
    '#manualServiceProduct, #manualCurrentDivision, #manualDesiredDivision, #manualCurrentRR, #manualAverageRr, #manualRegion, #manualPlatform, #manualAccountType, #manualWinsNeeded, #manualPlacementGames'
  ).forEach((element) => {
    const eventName = element instanceof HTMLInputElement ? 'input' : 'change';
    element.addEventListener(eventName, updatePreview);

    if (!(element instanceof HTMLInputElement)) {
      return;
    }

    element.addEventListener('change', updatePreview);
  });

  document.addEventListener('agent-selector:changed', updatePreview);
  document.addEventListener('addon-rules:sync', updatePreview);

  updatePreview();
  void loadPricingConfig().then((loaded) => {
    if (loaded) {
      updatePreview();
    }
  });
}
