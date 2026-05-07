function productConfig() {
  return window.ggwpProductConfig && typeof window.ggwpProductConfig === 'object'
    ? window.ggwpProductConfig
    : {};
}

let pricingConfigRequest = null;

function currentGameSlug() {
  return productConfig().gameSlug || window.appState?.gameSlug || 'valorant';
}

function currentGameName() {
  return productConfig().gameName || window.appState?.gameName || 'Valorant';
}

function applyDynamicPricingConfig(payload) {
  const config = payload?.pricingPreview && typeof payload.pricingPreview === 'object'
    ? payload.pricingPreview
    : payload;

  if (!config || typeof config !== 'object') {
    return false;
  }

  window.ggwpProductConfig = productConfig();
  window.ggwpProductConfig.gameSlug = payload?.gameSlug || config?.gameSlug || window.ggwpProductConfig.gameSlug || currentGameSlug();
  window.ggwpProductConfig.gameName = payload?.game?.name || payload?.gameName || window.ggwpProductConfig.gameName || currentGameName();
  window.ggwpProductConfig.pricingPreview = {
    ...pricingConfig(),
    ...config,
  };

  return true;
}

export function loadPricingConfig() {
  const url = window.appState?.pricingConfigUrl;

  if (!url) {
    return Promise.resolve(false);
  }

  if (pricingConfigRequest) {
    return pricingConfigRequest;
  }

  pricingConfigRequest = fetch(url, {
    method: 'GET',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    },
    cache: 'no-store',
  })
    .then((response) => {
      if (!response.ok) {
        return false;
      }

      return response.json()
        .then((payload) => applyDynamicPricingConfig(payload));
    })
    .catch(() => false)
    .finally(() => {
      pricingConfigRequest = null;
    });

  return pricingConfigRequest;
}

function pricingConfig() {
  const config = productConfig().pricingPreview;

  return config && typeof config === 'object' ? config : {};
}

export function hasPricingPreviewConfig() {
  const config = pricingConfig();

  return Array.isArray(config.rankOrder)
    && config.rankOrder.length > 0
    && config.services
    && typeof config.services === 'object'
    && Object.keys(config.services).length > 0
    && config.basePrices
    && typeof config.basePrices === 'object'
    && Object.keys(config.basePrices).length > 0;
}

function addonRuleConfig() {
  const config = productConfig().addonRules;

  return config && typeof config === 'object' ? config : {};
}

function servicesConfig() {
  const config = pricingConfig().services;

  return config && typeof config === 'object' ? config : {};
}

function rankOrder() {
  return Array.isArray(pricingConfig().rankOrder) ? pricingConfig().rankOrder : [];
}

function basePrices() {
  const config = pricingConfig().basePrices;

  return config && typeof config === 'object' ? config : {};
}

function specialRankBoostSteps() {
  const config = pricingConfig().specialRankBoostSteps;

  return config && typeof config === 'object' ? config : {};
}

function rrRules() {
  const config = pricingConfig().rrRules;

  return config && typeof config === 'object' ? config : {};
}

function addonDefinitions() {
  const config = pricingConfig().addons;

  return config && typeof config === 'object' ? config : {};
}

function modifierConfig() {
  const config = pricingConfig().modifiers;

  return config && typeof config === 'object' ? config : {};
}

function pricingLabels() {
  const config = pricingConfig().labels;

  return config && typeof config === 'object' ? config : {};
}

function roundMoney(value) {
  return Math.round((Number(value || 0) + Number.EPSILON) * 100) / 100;
}

function normalizeComparable(value) {
  return String(value ?? '')
    .toLowerCase()
    .replaceAll('_', '-')
    .replace(/[()+$%]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function comparisonVariants(value) {
  const normalized = normalizeComparable(value);

  if (!normalized) {
    return [];
  }

  return Array.from(new Set([
    normalized,
    normalized
      .replace(/\biii\b/g, '3')
      .replace(/\bii\b/g, '2')
      .replace(/\bi\b/g, '1'),
    normalized
      .replace(/\b3\b/g, 'iii')
      .replace(/\b2\b/g, 'ii')
      .replace(/\b1\b/g, 'i'),
  ]));
}

function valuesMatch(left, right) {
  const leftVariants = new Set(comparisonVariants(left));

  return comparisonVariants(right).some((variant) => leftVariants.has(variant));
}

function canonicalFromList(value, list = []) {
  return list.find((candidate) => valuesMatch(candidate, value)) || null;
}

function normalizeServiceType(value) {
  return canonicalFromList(value, Object.keys(servicesConfig()));
}

function normalizeBoostModeCode(value) {
  const normalized = String(value ?? '')
    .trim()
    .toLowerCase()
    .replaceAll('-', '_')
    .replace(/[\/\\]+/g, '_')
    .replaceAll(' ', '_')
    .replace(/_+/g, '_')
    .replace(/^_+|_+$/g, '');

  if (normalized === 'account_shared' || normalized === 'normal') {
    return 'normal';
  }

  if (['self_play', 'duo', 'duo_self_play', 'self_play_duo'].includes(normalized)) {
    return 'self_play';
  }

  return null;
}

function boostModeLabel(code) {
  return pricingLabels().boost_modes?.[code] || null;
}

function normalizeBoostMode(value) {
  const code = normalizeBoostModeCode(value);

  if (code) {
    return code;
  }

  const labels = pricingLabels().boost_modes || {};
  const match = Object.entries(labels).find(([, label]) => valuesMatch(label, value));

  return match?.[0] || null;
}

function normalizeRegion(value) {
  return canonicalFromList(value, Object.keys(modifierConfig().region || {}));
}

function normalizePlatform(value) {
  return canonicalFromList(value, Object.keys(modifierConfig().platform || {}));
}

function normalizeAverageRr(value) {
  if (value === null || value === '') {
    return null;
  }

  const labels = pricingLabels().avg_rr || {};
  const match = Object.entries(labels).find(([key, label]) => String(value).trim() === String(key) || valuesMatch(label, value));

  if (match) {
    return match[0];
  }

  const normalized = String(value)
    .trim()
    .toUpperCase()
    .replace(' OR LOWER', '')
    .replace(' OR MORE', '')
    .replace('+', '');
  const number = Number.parseInt(normalized, 10);

  if (!Number.isInteger(number)) {
    return null;
  }

  if (number <= 16) {
    return '16';
  }

  if (number <= 18) {
    return '18';
  }

  if (number >= 20) {
    return '20';
  }

  return '18';
}

function normalizeInteger(value) {
  if (value === null || value === '') {
    return null;
  }

  const number = Number(value);

  return Number.isFinite(number) ? Math.trunc(number) : null;
}

function normalizeDivisionToken(value) {
  const token = String(value ?? '').trim().toUpperCase();

  if (token === '1') {
    return 'I';
  }

  if (token === '2') {
    return 'II';
  }

  if (token === '3') {
    return 'III';
  }

  return token;
}

function canonicalizeRankCandidate(value) {
  let candidate = String(value ?? '').trim().replace(/\s+/g, ' ');

  candidate = candidate.replace(/\b1\b/gu, 'I');
  candidate = candidate.replace(/\b2\b/gu, 'II');
  candidate = candidate.replace(/\b3\b/gu, 'III');
  candidate = candidate.toLowerCase().replace(/\b\w/g, (character) => character.toUpperCase());
  candidate = candidate.replace(/\bIii\b/gu, 'III');
  candidate = candidate.replace(/\bIi\b/gu, 'II');

  return candidate;
}

function normalizeFullRank(rank, division) {
  const rankValue = String(rank ?? '').trim().replace(/\s+/g, ' ');
  const divisionValue = normalizeDivisionToken(division);
  const candidates = [
    rankValue,
    divisionValue,
  ];

  if (rankValue && divisionValue && !rankValue.includes(' ')) {
    candidates.push(`${rankValue} ${divisionValue}`.trim());
  }

  const supportedRanks = rankOrder();

  for (const candidate of candidates) {
    if (!candidate) {
      continue;
    }

    const canonical = canonicalizeRankCandidate(candidate);
    const match = supportedRanks.find((rankLabel) => rankLabel === canonical);

    if (match) {
      return match;
    }
  }

  return null;
}

function splitRank(fullRank) {
  if (!fullRank) {
    return { tier: null, division: null };
  }

  const parts = String(fullRank).split(/\s+/);

  if (parts.length === 1) {
    return { tier: parts[0], division: null };
  }

  return {
    tier: parts[0] || null,
    division: parts[1] || null,
  };
}

function serviceKind(serviceType) {
  return servicesConfig()?.[serviceType]?.kind || null;
}

function rankIndex(rank) {
  const index = rankOrder().indexOf(rank);

  return index >= 0 ? index : -1;
}

function rankAtOrAbove(rank, threshold) {
  const currentIndex = rankIndex(rank);
  const thresholdIndex = rankIndex(threshold);

  return currentIndex >= 0 && thresholdIndex >= 0 && currentIndex >= thresholdIndex;
}

function normalizeAddons(values) {
  const definitions = productConfig().addons;
  const knownAddons = Array.isArray(definitions)
    ? definitions.flatMap((addon) => [addon?.label, addon?.slug]).filter(Boolean)
    : [];
  const seen = new Set();

  return (Array.isArray(values) ? values : [values])
    .flat()
    .map((value) => {
      const normalized = knownAddons.find((candidate) => valuesMatch(candidate, value));
      if (!normalized) {
        return null;
      }

      return Array.isArray(definitions)
        ? (definitions.find((addon) => valuesMatch(addon?.label, normalized) || valuesMatch(addon?.slug, normalized))?.label || normalized)
        : normalized;
    })
    .filter((value) => value && !seen.has(value) && seen.add(value));
}

function allowedAgentUuids() {
  return new Set(
    (Array.isArray(window.appState?.valorantAgents) ? window.appState.valorantAgents : [])
      .map((agent) => String(agent?.uuid || '').trim().toLowerCase())
      .filter(Boolean),
  );
}

function normalizeAgentSelection(values) {
  const allowed = allowedAgentUuids();
  const seen = new Set();
  let hasInvalidItems = false;
  let hasDuplicates = false;
  const uuids = (Array.isArray(values) ? values : [values])
    .flat()
    .map((value) => String(value || '').trim().toLowerCase())
    .filter((value) => {
      if (!value) {
        return false;
      }

      if (!allowed.has(value)) {
        hasInvalidItems = true;
        return false;
      }

      if (seen.has(value)) {
        hasDuplicates = true;
        return false;
      }

      seen.add(value);
      return true;
    });

  return {
    uuids,
    hasInvalidItems,
    hasDuplicates,
  };
}

function inspectSelections(payload) {
  return {
    specificAgents: normalizeAgentSelection(payload.specificAgents || []),
    oneTrickAgent: normalizeAgentSelection(payload.oneTrickAgent || []),
  };
}

function evaluateAddonRules(normalized) {
  const config = addonRuleConfig();
  const labels = config.labels || {};
  const messages = config.messages || {};
  const disabledAddons = [];
  const disabledAddonReasons = {};
  const validationErrors = {};
  const selectedAddons = normalized.selectedAddons;
  const boostMode = boostModeLabel(normalized.boostMode);

  if (valuesMatch(boostMode, labels.selfPlay)) {
    (config.selfPlayDisabledAddons || []).forEach((label) => {
      if (!disabledAddons.includes(label)) {
        disabledAddons.push(label);
      }
      disabledAddonReasons[label] = 'Unavailable for Duo / Self-Play.';
    });

    const invalidSelfPlayAddons = selectedAddons.filter((addon) => (config.selfPlayDisabledAddons || []).some((label) => valuesMatch(label, addon)));
    if (invalidSelfPlayAddons.length > 0) {
      validationErrors.selectedAddons = [messages.selfPlayAddons || 'Duo / Self-Play only allows Bonus Win and Express Order.'];
    }
  }

  if (selectedAddons.includes(labels.specificAgents)) {
    disabledAddons.push(labels.oneTrickAgent);
    disabledAddonReasons[labels.oneTrickAgent] = 'Unavailable while Specific Agents is selected.';
  }

  if (selectedAddons.includes(labels.oneTrickAgent)) {
    disabledAddons.push(labels.specificAgents);
    disabledAddonReasons[labels.specificAgents] = 'Unavailable while One-Trick Agent is selected.';
  }

  if (selectedAddons.includes(labels.specificAgents) && selectedAddons.includes(labels.oneTrickAgent)) {
    validationErrors.selectedAddons = [...(validationErrors.selectedAddons || []), messages.specificVsOneTrick || 'Specific Agents and One-Trick Agent cannot be selected together.'];
  }

  if (selectedAddons.includes(labels.soloQueueOnly)) {
    disabledAddons.push(labels.noFiveStack);
    disabledAddonReasons[labels.noFiveStack] = 'Unavailable while Solo-Queue Only is selected.';
  }

  if (selectedAddons.includes(labels.soloQueueOnly) && selectedAddons.includes(labels.noFiveStack)) {
    validationErrors.selectedAddons = [...(validationErrors.selectedAddons || []), messages.soloVsNoFiveStack || 'Solo-Queue Only and No 5-Stack cannot be selected together.'];
  }

  const currentThreshold = config.rankThresholds?.currentRankMin || 'Immortal I';
  const targetThreshold = config.rankThresholds?.targetRankMin || 'Immortal II';
  const currentRestrictedServices = config.selfPlayCurrentRankRestrictedServices || [];
  const targetRestrictedServices = config.selfPlayTargetRankRestrictedServices || [];
  const selfPlayDisabledByCurrentRank = currentRestrictedServices.some((service) => valuesMatch(service, normalized.serviceType))
    && rankAtOrAbove(normalized.currentFullRank, currentThreshold);
  const selfPlayDisabledByTargetRank = targetRestrictedServices.some((service) => valuesMatch(service, normalized.serviceType))
    && rankAtOrAbove(normalized.targetFullRank, targetThreshold);
  const selfPlayUnavailableMessage = selfPlayDisabledByTargetRank
    ? (messages.selfPlayTargetRank || 'Duo / Self-Play is only available through Immortal 1.')
    : (selfPlayDisabledByCurrentRank ? (messages.selfPlayCurrentRank || 'Duo / Self-Play is unavailable from Immortal 1 onward.') : null);

  if (valuesMatch(boostMode, labels.selfPlay) && selfPlayUnavailableMessage) {
    validationErrors.boostMode = [...(validationErrors.boostMode || []), selfPlayUnavailableMessage];
  }

  return {
    disabledAddons: normalizeAddons(disabledAddons),
    disabledAddonReasons,
    validationErrors,
    selfPlayUnavailable: Boolean(selfPlayUnavailableMessage),
    selfPlayDisabledByCurrentRank,
    selfPlayDisabledByTargetRank,
    selfPlayUnavailableMessage,
  };
}

function validateAgentSelections(normalized, addonRuleEvaluation, inspectedSelections) {
  const definitions = {
    specificAgents: productConfig().addonRules?.labels?.specificAgents,
    oneTrickAgent: productConfig().addonRules?.labels?.oneTrickAgent,
  };
  const selectionAddons = productConfig().addons || [];
  const selectionConfig = {
    specificAgents: {
      label: definitions.specificAgents,
      min: 3,
      max: null,
      required: 'Select at least 3 specific agents.',
      invalid: 'Specific agent selections must use supported Valorant agents.',
      duplicate: 'Each specific agent can only be selected once.',
      addonRequired: 'Specific agent selections require the Specific Agents addon.',
      disabled: 'Specific Agents is unavailable for Duo / Self-Play orders.',
    },
    oneTrickAgent: {
      label: definitions.oneTrickAgent,
      min: 1,
      max: 1,
      required: 'Select exactly 1 one-trick agent.',
      invalid: 'One-trick agent selections must use supported Valorant agents.',
      duplicate: 'The one-trick agent selection can only include one unique agent.',
      addonRequired: 'One-trick agent selections require the One-Trick Agent addon.',
      disabled: 'One-Trick Agent is unavailable for Duo / Self-Play orders.',
    },
  };
  const errors = {};
  const disabledAddons = addonRuleEvaluation.disabledAddons || [];

  Object.entries(selectionConfig).forEach(([key, definition]) => {
    const selection = inspectedSelections[key] || { uuids: [] };
    const count = selection.uuids.length;
    const hasAddon = normalized.selectedAddons.includes(definition.label);
    const isDisabled = disabledAddons.includes(definition.label);
    const messages = [];

    if (selection.hasInvalidItems) {
      messages.push(definition.invalid);
    }

    if (selection.hasDuplicates) {
      messages.push(definition.duplicate);
    }

    if (hasAddon && !isDisabled && count < definition.min) {
      messages.push(definition.required);
    }

    if (hasAddon && !isDisabled && definition.max !== null && count !== definition.max) {
      if (!messages.includes(definition.required)) {
        messages.push(definition.required);
      }
    }

    if (!hasAddon && count > 0) {
      messages.push(definition.addonRequired);
    }

    if (isDisabled && count > 0) {
      messages.push(definition.disabled);
    }

    if (messages.length > 0) {
      errors[key] = Array.from(new Set(messages));
    }
  });

  return errors;
}

function normalizeInput(payload = {}) {
  const serviceType = normalizeServiceType(payload.serviceType || payload.orderType);
  const currentFullRank = normalizeFullRank(payload.currentRank, payload.currentDivision);
  const targetFullRank = serviceType === 'Radiant Boost'
    ? 'Radiant'
    : normalizeFullRank(payload.targetRank || payload.desiredRank, payload.targetDivision || payload.desiredDivision);
  const currentRankData = splitRank(currentFullRank);
  const targetRankData = splitRank(targetFullRank);
  const boostMode = normalizeBoostMode(payload.boostMode || payload.accountType || payload.playType);
  const inspectedSelections = inspectSelections(payload);

  return {
    gameSlug: payload.gameSlug || payload.game_slug || currentGameSlug(),
    serviceType,
    serviceKind: serviceKind(serviceType),
    currentFullRank,
    currentRank: currentRankData.tier,
    currentDivision: currentRankData.division,
    targetFullRank,
    targetRank: targetRankData.tier,
    targetDivision: targetRankData.division,
    currentRR: normalizeInteger(payload.currentRR),
    avgRRPerWin: normalizeAverageRr(payload.avgRRPerWin || payload.averageRR),
    region: normalizeRegion(payload.region),
    platform: normalizePlatform(payload.platform),
    boostMode,
    numberOfWins: normalizeInteger(payload.numberOfWins),
    numberOfPlacementGames: normalizeInteger(payload.numberOfPlacementGames),
    selectedAddons: normalizeAddons(payload.selectedAddons || payload.addons || []),
    inspectedSelections,
    specificAgents: inspectedSelections.specificAgents.uuids,
    oneTrickAgent: inspectedSelections.oneTrickAgent.uuids,
  };
}

function uniqueErrors(errors = {}) {
  return Object.fromEntries(
    Object.entries(errors)
      .map(([key, messages]) => [key, Array.from(new Set((messages || []).filter(Boolean)))])
      .filter(([, messages]) => messages.length > 0),
  );
}

function validate(normalized, options = {}, addonRuleEvaluation = {}) {
  const errors = {};

  if (!normalized.serviceType) {
    errors.serviceType = ['Select a valid service type.'];
  }

  if (!normalized.currentFullRank) {
    errors.currentRank = ['Select a valid current rank.'];
  }

  if (!normalized.region) {
    errors.region = ['Select a valid region.'];
  }

  if (!normalized.platform) {
    errors.platform = ['Select a valid platform.'];
  }

  if (!normalized.boostMode) {
    errors.boostMode = ['Select a valid boost mode.'];
  }

  if (normalized.serviceKind === 'rank_boost' || normalized.serviceKind === 'radiant_boost') {
    if (!normalized.targetFullRank) {
      errors.targetRank = ['Select a valid target rank.'];
    } else if (rankIndex(normalized.targetFullRank) <= rankIndex(normalized.currentFullRank)) {
      errors.targetRank = ['Target rank must be higher than current rank.'];
    }

    if (!normalized.avgRRPerWin) {
      errors.avgRRPerWin = ['Select a valid average RR per win option.'];
    }

    if (normalized.serviceKind === 'rank_boost' && (normalized.currentRR === null || normalized.currentRR < 0 || normalized.currentRR > 100)) {
      errors.currentRR = ['Current RR must be between 0 and 100.'];
    }
  }

  if (normalized.serviceKind === 'ranked_wins') {
    if ((normalized.numberOfWins || 0) < 1) {
      errors.numberOfWins = ['Wins needed must be at least 1.'];
    }

    if (!options.allowExtendedRankedWins && (normalized.numberOfWins || 0) > 5) {
      errors.numberOfWins = ['Wins needed must be between 1 and 5.'];
    }
  }

  if (normalized.serviceKind === 'placement_matches') {
    if ((normalized.numberOfPlacementGames || 0) < 1 || (normalized.numberOfPlacementGames || 0) > 5) {
      errors.numberOfPlacementGames = ['Placement matches must be between 1 and 5.'];
    }
  }

  Object.entries(addonRuleEvaluation.validationErrors || {}).forEach(([key, messages]) => {
    errors[key] = [...(errors[key] || []), ...messages];
  });

  const selectionErrors = validateAgentSelections(normalized, addonRuleEvaluation, normalized.inspectedSelections);
  Object.entries(selectionErrors).forEach(([key, messages]) => {
    errors[key] = [...(errors[key] || []), ...messages];
  });

  return uniqueErrors(errors);
}

function desiredDivisionDisplay(normalized) {
  if (normalized.serviceKind === 'ranked_wins') {
    return normalized.numberOfWins > 0 ? `${normalized.numberOfWins} Wins` : null;
  }

  if (normalized.serviceKind === 'placement_matches') {
    return normalized.numberOfPlacementGames > 0 ? `${normalized.numberOfPlacementGames} Placement Matches` : null;
  }

  return normalized.targetFullRank;
}

function displayCurrentRr(normalized) {
  return ['rank_boost', 'radiant_boost'].includes(normalized.serviceKind) && normalized.currentRR !== null
    ? String(normalized.currentRR)
    : null;
}

function averageRrDisplay(normalized) {
  if (normalized.serviceKind === 'rank_boost' || normalized.serviceKind === 'radiant_boost') {
    return pricingLabels().avg_rr?.[normalized.avgRRPerWin] || null;
  }

  if (normalized.serviceKind === 'ranked_wins') {
    return normalized.numberOfWins > 0 ? `${normalized.numberOfWins} ranked wins` : null;
  }

  if (normalized.serviceKind === 'placement_matches') {
    return normalized.numberOfPlacementGames > 0 ? `${normalized.numberOfPlacementGames} placement matches` : null;
  }

  return null;
}

function regionMultiplier(region) {
  return Number(modifierConfig().region?.[region] ?? 1);
}

function platformMultiplier(platform) {
  return Number(modifierConfig().platform?.[platform] ?? 1);
}

function boostModeMultiplier(boostMode) {
  return Number(modifierConfig().boost_mode?.[boostMode] ?? 1);
}

function lookupBasePrice(serviceType, rank) {
  return Number(basePrices()?.[serviceType]?.[rank] ?? 0);
}

function calculateBasePrice(normalized) {
  if (normalized.serviceKind === 'placement_matches') {
    const unitPrice = lookupBasePrice('Placement Matches', normalized.currentFullRank);
    const quantity = Number(normalized.numberOfPlacementGames || 0);
    const basePrice = unitPrice * quantity;

    return [basePrice, [{
      label: normalized.currentFullRank,
      unitPrice: roundMoney(unitPrice),
      quantity,
      price: roundMoney(basePrice),
    }], 0];
  }

  if (normalized.serviceKind === 'ranked_wins') {
    const unitPrice = lookupBasePrice('Ranked Wins', normalized.currentFullRank);
    const quantity = Number(normalized.numberOfWins || 0);
    const basePrice = unitPrice * quantity;

    return [basePrice, [{
      label: normalized.currentFullRank,
      unitPrice: roundMoney(unitPrice),
      quantity,
      price: roundMoney(basePrice),
    }], 0];
  }

  if (normalized.serviceKind !== 'rank_boost' && normalized.serviceKind !== 'radiant_boost') {
    return [0, [], 0];
  }

  const ranks = rankOrder();
  const currentIndex = rankIndex(normalized.currentFullRank);
  const targetIndex = rankIndex(normalized.targetFullRank);
  const steps = [];
  let basePrice = 0;
  let firstStepPrice = 0;

  for (let index = currentIndex; index < targetIndex; index += 1) {
    const fromRank = ranks[index];
    const toRank = ranks[index + 1];
    const specialKey = `${fromRank}->${toRank}`;
    const stepPrice = Number(specialRankBoostSteps()?.[specialKey] ?? lookupBasePrice('Rank Boosting', fromRank));

    if (index === currentIndex) {
      firstStepPrice = stepPrice;
    }

    steps.push({
      from: fromRank,
      to: toRank,
      price: roundMoney(stepPrice),
    });
    basePrice += stepPrice;
  }

  return [basePrice, steps, firstStepPrice];
}

function applyCurrentRrDiscount(normalized, subtotal, firstStepPrice) {
  if (!['rank_boost', 'radiant_boost'].includes(normalized.serviceKind)) {
    return subtotal;
  }

  if ((normalized.currentRR || 0) < Number(rrRules().current_rr_discount_threshold ?? 50)) {
    return subtotal;
  }

  return Math.max(0, subtotal - (firstStepPrice * Number(rrRules().first_step_discount_multiplier ?? 0.5)));
}

function applyAverageRrModifier(normalized, subtotal) {
  if (!['rank_boost', 'radiant_boost'].includes(normalized.serviceKind)) {
    return subtotal;
  }

  return subtotal * Number(rrRules().avg_rr_modifiers?.[normalized.avgRRPerWin] ?? 1);
}

function bonusWinAmount(normalized) {
  const rankForBonus = ['rank_boost', 'radiant_boost'].includes(normalized.serviceKind)
    ? normalized.targetFullRank
    : normalized.currentFullRank;

  return lookupBasePrice('Ranked Wins', rankForBonus);
}

function calculateAddons(normalized, subtotalAfterRr, addons) {
  const definitions = addonDefinitions();
  const addonBreakdown = [];
  let total = 0;

  addons.forEach((label) => {
    const definition = definitions?.[label];

    if (!definition || typeof definition !== 'object') {
      return;
    }

    const type = String(definition.type || 'free');
    const amount = type === 'percent'
      ? subtotalAfterRr * Number(definition.value || 0)
      : (type === 'bonus_win' ? bonusWinAmount(normalized) : 0);

    addonBreakdown.push({
      label,
      type,
      amount: roundMoney(amount),
      value: type === 'percent' ? Number(definition.value || 0) : null,
    });
    total += amount;
  });

  return [addonBreakdown, total];
}

function buildBasePayload(normalized, requestedAddons, appliedAddons, disabledAddons, addonRuleEvaluation) {
  const config = pricingConfig();

  return {
    gameSlug: normalized.gameSlug || currentGameSlug(),
    game: currentGameName(),
    serviceType: normalized.serviceType,
    orderType: normalized.serviceType,
    currentRank: normalized.currentRank,
    targetRank: normalized.targetRank,
    currentDivision: normalized.currentFullRank,
    desiredDivision: desiredDivisionDisplay(normalized),
    currentRR: displayCurrentRr(normalized),
    avgRRPerWin: normalized.avgRRPerWin,
    averageRR: averageRrDisplay(normalized),
    region: normalized.region,
    platform: normalized.platform,
    boostMode: normalized.boostMode,
    accountType: boostModeLabel(normalized.boostMode),
    numberOfWins: normalized.numberOfWins,
    numberOfPlacementGames: normalized.numberOfPlacementGames,
    requestedAddons,
    selectedAddons: requestedAddons,
    addons: appliedAddons,
    disabledAddons,
    disabledAddonReasons: addonRuleEvaluation.disabledAddonReasons || {},
    selfPlayUnavailable: Boolean(addonRuleEvaluation.selfPlayUnavailable),
    selfPlayDisabledByCurrentRank: Boolean(addonRuleEvaluation.selfPlayDisabledByCurrentRank),
    selfPlayDisabledByTargetRank: Boolean(addonRuleEvaluation.selfPlayDisabledByTargetRank),
    selfPlayUnavailableMessage: addonRuleEvaluation.selfPlayUnavailableMessage || null,
    specificAgents: normalized.specificAgents,
    oneTrickAgent: normalized.oneTrickAgent,
    pricingConfig: {
      version: Number(config.version || 0),
      gameSlug: config.gameSlug || currentGameSlug(),
      checksum: String(config.checksum || ''),
      source: String(config.source || 'embedded'),
      updatedAt: config.updatedAt || null,
    },
  };
}

function buildFailurePayload(normalized, requestedAddons, appliedAddons, disabledAddons, addonRuleEvaluation, validationErrors) {
  return {
    ...buildBasePayload(normalized, requestedAddons, appliedAddons, disabledAddons, addonRuleEvaluation),
    basePrice: 0,
    rankPath: [],
    addonBreakdown: [],
    subtotalBeforeModifiers: 0,
    subtotalAfterRR: 0,
    subtotalAfterAddons: 0,
    subtotalAfterGlobalModifiers: 0,
    finalPrice: 0,
    validationErrors,
    pricing: {
      base: 0,
      basePrice: 0,
      subtotal: 0,
      subtotalBeforeModifiers: 0,
      subtotalAfterRR: 0,
      subtotalAfterAddons: 0,
      subtotalAfterGlobalModifiers: 0,
      addons: 0,
      fee: 0,
      tax: 0,
      total: 0,
      finalPrice: 0,
      currency: 'USD',
    },
    modifiers: {
      region: {
        code: normalized.region,
        multiplier: regionMultiplier(normalized.region),
      },
      platform: {
        code: normalized.platform,
        multiplier: platformMultiplier(normalized.platform),
      },
      boostMode: {
        code: normalized.boostMode,
        label: boostModeLabel(normalized.boostMode),
        multiplier: boostModeMultiplier(normalized.boostMode),
      },
    },
  };
}

export function calculatePricingPreview(payload, options = {}) {
  if (!hasPricingPreviewConfig()) {
    return {
      basePrice: 0,
      rankPath: [],
      addonBreakdown: [],
      subtotalBeforeModifiers: 0,
      subtotalAfterRR: 0,
      subtotalAfterAddons: 0,
      subtotalAfterGlobalModifiers: 0,
      finalPrice: 0,
      validationErrors: {},
      pricing: {
        base: 0,
        basePrice: 0,
        subtotal: 0,
        subtotalBeforeModifiers: 0,
        subtotalAfterRR: 0,
        subtotalAfterAddons: 0,
        subtotalAfterGlobalModifiers: 0,
        addons: 0,
        fee: 0,
        tax: 0,
        total: 0,
        finalPrice: 0,
        currency: 'USD',
      },
      modifiers: {},
      disabledAddons: [],
      pricingConfig: {
        version: 0,
        checksum: '',
        source: 'unavailable',
        updatedAt: null,
      },
    };
  }

  const normalized = normalizeInput(payload);
  const addonRuleEvaluation = evaluateAddonRules(normalized);

  if (!normalized.selectedAddons.includes(addonRuleConfig().labels?.specificAgents) || addonRuleEvaluation.disabledAddons.includes(addonRuleConfig().labels?.specificAgents)) {
    normalized.specificAgents = [];
  }

  if (!normalized.selectedAddons.includes(addonRuleConfig().labels?.oneTrickAgent) || addonRuleEvaluation.disabledAddons.includes(addonRuleConfig().labels?.oneTrickAgent)) {
    normalized.oneTrickAgent = [];
  }

  const validationErrors = validate(normalized, options, addonRuleEvaluation);
  const disabledAddons = addonRuleEvaluation.disabledAddons || [];
  const requestedAddons = normalized.selectedAddons;
  const appliedAddons = requestedAddons.filter((addon) => !disabledAddons.includes(addon));

  if (Object.keys(validationErrors).length > 0) {
    return buildFailurePayload(normalized, requestedAddons, appliedAddons, disabledAddons, addonRuleEvaluation, validationErrors);
  }

  const [basePrice, rankPath, firstStepPrice] = calculateBasePrice(normalized);
  const subtotalBeforeModifiers = basePrice;
  const subtotalAfterCurrentRr = applyCurrentRrDiscount(normalized, subtotalBeforeModifiers, firstStepPrice);
  const subtotalAfterRr = applyAverageRrModifier(normalized, subtotalAfterCurrentRr);
  const [addonBreakdown, addonTotal] = calculateAddons(normalized, subtotalAfterRr, appliedAddons);
  const subtotalAfterAddons = subtotalAfterRr + addonTotal;
  const subtotalAfterRegion = subtotalAfterAddons * regionMultiplier(normalized.region);
  const subtotalAfterPlatform = subtotalAfterRegion * platformMultiplier(normalized.platform);
  const subtotalAfterGlobalModifiers = subtotalAfterPlatform * boostModeMultiplier(normalized.boostMode);
  const finalPrice = roundMoney(subtotalAfterGlobalModifiers);

  return {
    ...buildBasePayload(normalized, requestedAddons, appliedAddons, disabledAddons, addonRuleEvaluation),
    basePrice: roundMoney(basePrice),
    rankPath,
    addonBreakdown,
    subtotalBeforeModifiers: roundMoney(subtotalBeforeModifiers),
    subtotalAfterRR: roundMoney(subtotalAfterRr),
    subtotalAfterAddons: roundMoney(subtotalAfterAddons),
    subtotalAfterGlobalModifiers: roundMoney(subtotalAfterGlobalModifiers),
    finalPrice,
    validationErrors: {},
    pricing: {
      base: roundMoney(basePrice),
      basePrice: roundMoney(basePrice),
      subtotal: finalPrice,
      subtotalBeforeModifiers: roundMoney(subtotalBeforeModifiers),
      subtotalAfterRR: roundMoney(subtotalAfterRr),
      subtotalAfterAddons: roundMoney(subtotalAfterAddons),
      subtotalAfterGlobalModifiers: roundMoney(subtotalAfterGlobalModifiers),
      addons: roundMoney(addonTotal),
      fee: 0,
      tax: 0,
      total: finalPrice,
      finalPrice,
      currency: 'USD',
    },
    modifiers: {
      region: {
        code: normalized.region,
        multiplier: regionMultiplier(normalized.region),
      },
      platform: {
        code: normalized.platform,
        multiplier: platformMultiplier(normalized.platform),
      },
      boostMode: {
        code: normalized.boostMode,
        label: boostModeLabel(normalized.boostMode),
        multiplier: boostModeMultiplier(normalized.boostMode),
      },
    },
  };
}

export function pricingPayloadSignature(payload) {
  if (!hasPricingPreviewConfig()) {
    const sortedArray = (values) => (Array.isArray(values) ? values : [values])
      .flat()
      .map((value) => String(value || '').trim())
      .filter(Boolean)
      .sort();

    return JSON.stringify({
      serviceType: payload.serviceType || payload.orderType || null,
      gameSlug: payload.gameSlug || payload.game_slug || currentGameSlug(),
      currentRank: payload.currentRank || null,
      currentDivision: payload.currentDivision || payload.current_division || null,
      targetRank: payload.targetRank || payload.desiredRank || null,
      targetDivision: payload.targetDivision || payload.desiredDivision || null,
      currentRR: payload.currentRR ?? payload.current_rr ?? null,
      avgRRPerWin: payload.avgRRPerWin || payload.averageRR || null,
      region: payload.region || null,
      platform: payload.platform || null,
      boostMode: payload.boostMode || payload.accountType || payload.playType || null,
      numberOfWins: payload.numberOfWins || payload.wins || null,
      numberOfPlacementGames: payload.numberOfPlacementGames || payload.placementGames || payload.games || null,
      selectedAddons: sortedArray(payload.selectedAddons || payload.addons || []),
      specificAgents: sortedArray(payload.specificAgents || []),
      oneTrickAgent: sortedArray(payload.oneTrickAgent || []),
    });
  }

  const normalized = normalizeInput(payload);

  return JSON.stringify({
    serviceType: normalized.serviceType,
    gameSlug: normalized.gameSlug || currentGameSlug(),
    currentFullRank: normalized.currentFullRank,
    targetFullRank: normalized.targetFullRank,
    currentRR: normalized.currentRR,
    avgRRPerWin: normalized.avgRRPerWin,
    region: normalized.region,
    platform: normalized.platform,
    boostMode: normalized.boostMode,
    numberOfWins: normalized.numberOfWins,
    numberOfPlacementGames: normalized.numberOfPlacementGames,
    selectedAddons: [...normalized.selectedAddons].sort(),
    specificAgents: [...normalized.specificAgents].sort(),
    oneTrickAgent: [...normalized.oneTrickAgent].sort(),
  });
}
