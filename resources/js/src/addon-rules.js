import { byId, queryAll, toggleClass } from './dom';
import { syncAgentSelectorsForContext } from './agent-selectors';

const controllers = new Map();

function productConfig() {
  return window.ggwpProductConfig && typeof window.ggwpProductConfig === 'object'
    ? window.ggwpProductConfig
    : {};
}

function addonRuleConfig() {
  const config = productConfig().addonRules;

  return config && typeof config === 'object' ? config : {};
}

function addonLabels() {
  const labels = addonRuleConfig().labels;

  return labels && typeof labels === 'object' ? labels : {};
}

function ruleMessages() {
  const messages = addonRuleConfig().messages;

  return messages && typeof messages === 'object' ? messages : {};
}

function rankThresholds() {
  const thresholds = addonRuleConfig().rankThresholds;

  return thresholds && typeof thresholds === 'object' ? thresholds : {};
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

function canonicalRank(value) {
  return canonicalFromList(value, productConfig().ranksWithRadiant || productConfig().ranks || []);
}

function canonicalService(value) {
  return canonicalFromList(value, productConfig().services || []);
}

function rankAtOrAbove(value, threshold) {
  const rankOrder = productConfig().ranksWithRadiant || productConfig().ranks || [];
  const rank = canonicalRank(value);
  const thresholdRank = canonicalRank(threshold);
  const rankIndex = rankOrder.indexOf(rank);
  const thresholdIndex = rankOrder.indexOf(thresholdRank);

  return rankIndex >= 0 && thresholdIndex >= 0 && rankIndex >= thresholdIndex;
}

function isServiceInList(serviceType, list = []) {
  const normalized = canonicalService(serviceType);

  return normalized ? list.some((service) => valuesMatch(service, normalized)) : false;
}

class AddonRuleController {
  constructor(grid) {
    this.grid = grid;
    this.context = grid.dataset.addonGrid || grid.dataset.addonRuleContext || 'global';
    this.fixedServiceType = grid.dataset.addonRuleServiceType || '';
    this.serviceInput = byId(grid.dataset.addonRuleServiceInputId || '');
    this.boostModeInput = byId(grid.dataset.addonRuleBoostModeInputId || '');
    this.currentRankInput = byId(grid.dataset.addonRuleCurrentRankInputId || '');
    this.targetRankInput = byId(grid.dataset.addonRuleTargetRankInputId || '');
    this.message = byId(grid.dataset.addonRuleMessageId || '')
      || document.querySelector(`[data-addon-rule-message="${this.context}"]`);
    this.allowAdminOverride = grid.dataset.addonRuleAllowAdminOverride === 'true';
    this.lastChangedAddon = null;

    this.bind();
    this.sync();
  }

  bind() {
    this.addonInputs().forEach((input) => {
      input.addEventListener('change', () => {
        this.lastChangedAddon = {
          label: input.dataset.addonLabel || '',
          checked: input.checked,
        };

        this.sync();
      });
    });

    [this.serviceInput, this.boostModeInput, this.currentRankInput, this.targetRankInput]
      .filter(Boolean)
      .forEach((element) => {
        element.addEventListener('change', () => {
          this.lastChangedAddon = null;
          this.sync();
        });
        element.addEventListener('input', () => {
          this.lastChangedAddon = null;
          this.sync();
        });
      });
  }

  addonInputs() {
    return queryAll('input[data-addon-label]', this.grid);
  }

  selectedAddons() {
    return this.addonInputs()
      .filter((input) => input.checked)
      .map((input) => input.dataset.addonLabel || '')
      .filter(Boolean);
  }

  currentServiceType() {
    return this.fixedServiceType || this.serviceInput?.value || '';
  }

  currentBoostMode() {
    return this.boostModeInput?.value || '';
  }

  currentRank() {
    return this.currentRankInput?.value || '';
  }

  targetRank() {
    return this.targetRankInput?.value || '';
  }

  matchingBoostOption(label) {
    if (!(this.boostModeInput instanceof HTMLSelectElement)) {
      return null;
    }

    return Array.from(this.boostModeInput.options).find((option) => valuesMatch(option.value || option.textContent, label)) || null;
  }

  applySelfPlayAvailability(serviceType) {
    const labels = addonLabels();
    const messages = ruleMessages();
    const thresholds = rankThresholds();
    const currentRestrictedServices = addonRuleConfig().selfPlayCurrentRankRestrictedServices || [];
    const targetRestrictedServices = addonRuleConfig().selfPlayTargetRankRestrictedServices || [];
    const selfPlayOption = this.matchingBoostOption(labels.selfPlay);
    const accountSharedOption = this.matchingBoostOption(labels.accountShared);
    const currentRankBlocked = isServiceInList(serviceType, currentRestrictedServices)
      && rankAtOrAbove(this.currentRank(), thresholds.currentRankMin);
    const targetRankBlocked = isServiceInList(serviceType, targetRestrictedServices)
      && rankAtOrAbove(this.targetRank(), thresholds.targetRankMin);
    const unavailable = currentRankBlocked || targetRankBlocked;
    const unavailableMessage = targetRankBlocked
      ? messages.selfPlayTargetRank || ''
      : (currentRankBlocked ? messages.selfPlayCurrentRank || '' : '');
    let changed = false;

    if (selfPlayOption && selfPlayOption.disabled !== unavailable) {
      selfPlayOption.disabled = unavailable;
      changed = true;
    }

    if (unavailable && accountSharedOption && valuesMatch(this.currentBoostMode(), labels.selfPlay)) {
      if (this.boostModeInput.value !== accountSharedOption.value) {
        this.boostModeInput.value = accountSharedOption.value;
        changed = true;
      }
    }

    return {
      changed,
      selfPlayUnavailable: unavailable,
      selfPlayDisabledByCurrentRank: currentRankBlocked,
      selfPlayDisabledByTargetRank: targetRankBlocked,
      selfPlayUnavailableMessage: unavailableMessage,
    };
  }

  disabledAddonReasons(boostMode, selectedAddons) {
    const labels = addonLabels();
    const messages = ruleMessages();
    const selected = new Set(selectedAddons);
    const disabled = new Map();

    if (valuesMatch(boostMode, labels.selfPlay)) {
      (addonRuleConfig().selfPlayDisabledAddons || []).forEach((label) => {
        disabled.set(label, 'Unavailable for Duo / Self-Play.');
      });
    }

    if (selected.has(labels.specificAgents)) {
      disabled.set(labels.oneTrickAgent, 'Unavailable while Specific Agents is selected.');
    }

    if (selected.has(labels.oneTrickAgent)) {
      disabled.set(labels.specificAgents, 'Unavailable while One-Trick Agent is selected.');
    }

    if (selected.has(labels.soloQueueOnly)) {
      disabled.set(labels.noFiveStack, 'Unavailable while Solo-Queue Only is selected.');
    }

    return {
      disabled,
      selfPlayAddonMessage: messages.selfPlayAddons || '',
    };
  }

  uncheckAddon(label) {
    const input = this.addonInputs().find((candidate) => candidate.dataset.addonLabel === label);

    if (!(input instanceof HTMLInputElement) || !input.checked) {
      return false;
    }

    input.checked = false;

    return true;
  }

  resolveMutualExclusion(selectedAddons) {
    const labels = addonLabels();
    let changed = false;
    const selected = new Set(selectedAddons);

    if (selected.has(labels.specificAgents) && selected.has(labels.oneTrickAgent)) {
      const lastChangedLabel = this.lastChangedAddon?.checked ? this.lastChangedAddon.label : null;
      const fallbackLabel = labels.oneTrickAgent;
      const labelToClear = valuesMatch(lastChangedLabel, labels.specificAgents) || valuesMatch(lastChangedLabel, labels.oneTrickAgent)
        ? lastChangedLabel
        : fallbackLabel;

      changed = this.uncheckAddon(labelToClear) || changed;
    }

    if (selected.has(labels.soloQueueOnly) && selected.has(labels.noFiveStack)) {
      changed = this.uncheckAddon(labels.noFiveStack) || changed;
    }

    return changed;
  }

  applyDisabledState(disabledReasons) {
    let changed = false;

    this.addonInputs().forEach((input) => {
      const label = input.dataset.addonLabel || '';
      const reason = disabledReasons.get(label) || '';
      const card = input.closest('.ggwp-addon-option');
      const shouldDisable = Boolean(reason);

      if (input.disabled !== shouldDisable) {
        input.disabled = shouldDisable;
        changed = true;
      }

      if (shouldDisable && input.checked) {
        input.checked = false;
        changed = true;
      }

      if (shouldDisable) {
        input.setAttribute('aria-disabled', 'true');
      } else {
        input.removeAttribute('aria-disabled');
      }

      if (card instanceof HTMLElement) {
        if (reason) {
          card.setAttribute('title', reason);
        } else {
          card.removeAttribute('title');
        }

        card.classList.toggle('is-disabled', shouldDisable);
      }
    });

    return changed;
  }

  clearDisabledState() {
    let changed = false;

    this.addonInputs().forEach((input) => {
      const card = input.closest('.ggwp-addon-option');

      if (input.disabled) {
        input.disabled = false;
        changed = true;
      }

      if (input.hasAttribute('aria-disabled')) {
        input.removeAttribute('aria-disabled');
      }

      if (card instanceof HTMLElement) {
        if (card.hasAttribute('title')) {
          card.removeAttribute('title');
        }

        if (card.classList.contains('is-disabled')) {
          card.classList.remove('is-disabled');
        }
      }
    });

    if (this.boostModeInput instanceof HTMLSelectElement) {
      Array.from(this.boostModeInput.options).forEach((option) => {
        if (option.disabled) {
          option.disabled = false;
          changed = true;
        }
      });
    }

    return changed;
  }

  renderMessage(message) {
    if (!(this.message instanceof HTMLElement)) {
      return;
    }

    this.message.textContent = message || '';
    toggleClass(this.message, 'd-none', !message);
  }

  sync() {
    const serviceType = this.currentServiceType();
    let changed = false;

    if (this.allowAdminOverride) {
      changed = this.clearDisabledState() || changed;
      syncAgentSelectorsForContext(this.context);
      this.renderMessage('');
      this.lastChangedAddon = null;

      if (changed) {
        this.grid.dispatchEvent(new CustomEvent('addon-rules:sync', {
          bubbles: true,
          detail: {
            context: this.context,
          },
        }));
      }

      return;
    }

    const selfPlayState = this.applySelfPlayAvailability(serviceType);

    changed = selfPlayState.changed || changed;
    changed = this.resolveMutualExclusion(this.selectedAddons()) || changed;

    const { disabled } = this.disabledAddonReasons(this.currentBoostMode(), this.selectedAddons());

    changed = this.applyDisabledState(disabled) || changed;
    syncAgentSelectorsForContext(this.context);
    this.renderMessage(selfPlayState.selfPlayUnavailableMessage);
    this.lastChangedAddon = null;

    if (changed) {
      this.grid.dispatchEvent(new CustomEvent('addon-rules:sync', {
        bubbles: true,
        detail: {
          context: this.context,
        },
      }));
    }
  }
}

export function syncAddonRulesForContext(context) {
  controllers.get(context)?.sync();
}

export function initAddonRules() {
  queryAll('[data-addon-grid]').forEach((grid) => {
    const context = grid.dataset.addonGrid || grid.dataset.addonRuleContext || '';

    if (!context || controllers.has(context)) {
      return;
    }

    controllers.set(context, new AddonRuleController(grid));
  });
}
