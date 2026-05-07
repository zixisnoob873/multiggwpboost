import { byId, query, queryAll } from './dom';
import { getRankTierMeta, parseRankValue, rankTierDefinitions } from './home-rank-picker-data';
import { normalizeDivisionName, valorantDivisionIcons } from './rank-icons';

function getRankIcon(value, fallbackValue = 'Radiant') {
  const key = normalizeDivisionName(value || fallbackValue);

  return valorantDivisionIcons[key] || valorantDivisionIcons.unranked;
}

function getFieldParts(select) {
  const field = select.closest('[data-rank-picker-field]');

  if (!field) {
    return {};
  }

  return {
    field,
    trigger: query('[data-rank-picker-trigger]', field),
    art: query('[data-rank-picker-trigger-art]', field),
    value: query('[data-rank-picker-trigger-value]', field),
  };
}

function buildTierGroups(select) {
  const optionMap = new Map();

  Array.from(select.options).forEach((option) => {
    if (!option.value) {
      return;
    }

    const parsed = parseRankValue(option.value);
    if (!parsed.tier) {
      return;
    }

    if (!optionMap.has(parsed.tier)) {
      optionMap.set(parsed.tier, []);
    }

    optionMap.get(parsed.tier).push({
      value: option.value,
      label: parsed.division || option.label,
      disabled: option.disabled,
      selected: option.selected,
    });
  });

  return rankTierDefinitions
    .map((definition) => {
      const options = optionMap.get(definition.tier) || [];

      if (!options.length) {
        return null;
      }

      return {
        ...definition,
        options,
      };
    })
    .filter(Boolean);
}

function syncTrigger(select) {
  const { trigger, art, value } = getFieldParts(select);

  if (!trigger || !art || !value) {
    return;
  }

  const parsed = parseRankValue(select.value);
  const meta = getRankTierMeta(parsed.tier || 'Radiant');
  const selectedValue = String(select.value || '').trim() || select.dataset.rankPickerPlaceholder || 'Choose rank';
  const rankIcon = getRankIcon(selectedValue, meta.iconValue);

  trigger.setAttribute('aria-label', `${select.dataset.rankPickerLabel || 'Rank'}: ${selectedValue}`);
  value.textContent = selectedValue;
  art.innerHTML = `
    <span class="ggwp-rank-picker-trigger__glow" style="--rank-accent:${meta.accent}; --rank-glow:${meta.glow};"></span>
    <img src="${rankIcon}" alt="" class="ggwp-rank-picker-trigger__icon" loading="lazy" decoding="async">
  `;
}

function dispatchSelectEvents(select, value) {
  select.value = value;
  select.dispatchEvent(new Event('change', { bubbles: true }));
}

function focusFirstAvailable(root, selectors) {
  window.requestAnimationFrame(() => {
    for (const selector of selectors) {
      const element = query(selector, root);
      if (element) {
        element.focus();
        return;
      }
    }
  });
}

export function initHomeRankPicker() {
  const modalElement = byId('homepageRankPickerModal');

  if (!modalElement || !window.bootstrap?.Modal) {
    return;
  }

  const modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
  const title = byId('homepageRankPickerModalTitle');
  const tierGrid = byId('homepageRankTierGrid');
  const divisionTitle = byId('homepageRankDivisionTitle');
  const divisionGrid = byId('homepageRankDivisionGrid');
  const divisionPanel = divisionGrid?.closest('.ggwp-rank-picker-panel--division');
  const stage = query('.ggwp-rank-picker-modal__stage', modalElement);

  if (!title || !tierGrid || !divisionTitle || !divisionGrid || !divisionPanel || !stage) {
    return;
  }

  let activeState = null;
  let returnFocusTo = null;

  const focusActiveModalChoice = () => {
    if (!activeState) {
      return;
    }

    focusFirstAvailable(modalElement, [
      '.ggwp-rank-division-chip.is-selected',
      '.ggwp-rank-tier-card.is-selected',
      '.ggwp-rank-tier-card:not([disabled])',
    ]);
  };

  const setModalCopy = (select) => {
    title.textContent = select.dataset.rankPickerModalTitle || 'Choose a rank';
  };

  const closePicker = () => {
    modal.hide();
  };

  const setDivisionVisibility = (visible) => {
    divisionPanel.hidden = !visible;
    stage.classList.toggle('ggwp-rank-picker-modal__stage--single', !visible);
  };

  const applyValue = (value) => {
    if (!activeState?.select) {
      return;
    }

    dispatchSelectEvents(activeState.select, value);
    closePicker();
  };

  const renderDivisions = () => {
    const selectedGroup = activeState?.groups?.find((group) => group.tier === activeState.activeTier);

    if (!selectedGroup) {
      setDivisionVisibility(false);
      divisionTitle.textContent = 'Choose a division';
      divisionGrid.innerHTML = '';
      return;
    }

    if (!selectedGroup.divisions.length) {
      setDivisionVisibility(false);
      divisionTitle.textContent = selectedGroup.tier;
      divisionGrid.innerHTML = '';
      return;
    }

    setDivisionVisibility(true);
    divisionTitle.textContent = selectedGroup.divisions.length ? `${selectedGroup.tier} divisions` : selectedGroup.tier;
    divisionGrid.innerHTML = selectedGroup.options
      .map((option) => `
        <button
          type="button"
          class="ggwp-rank-division-chip${option.value === activeState.select.value ? ' is-selected' : ''}"
          data-rank-picker-value="${option.value}"
          aria-label="${option.value}"
          aria-pressed="${option.value === activeState.select.value ? 'true' : 'false'}"
          ${option.disabled ? 'disabled' : ''}
        >
          <span class="ggwp-rank-division-chip__value">${selectedGroup.divisions.length ? option.label : option.value}</span>
        </button>
      `)
      .join('');
  };

  const renderTiers = () => {
    tierGrid.innerHTML = activeState.groups
      .map((group) => {
        const disabled = group.options.every((option) => option.disabled);
        const isSelectedTier = activeState.activeTier === group.tier;
        const tierIcon = getRankIcon(group.iconValue, group.iconValue);

        return `
          <button
            type="button"
            class="ggwp-rank-tier-card${isSelectedTier ? ' is-selected' : ''}"
            data-rank-picker-tier="${group.tier}"
            aria-label="${group.tier}"
            aria-pressed="${isSelectedTier ? 'true' : 'false'}"
            style="--rank-accent:${group.accent}; --rank-glow:${group.glow};"
            ${disabled ? 'disabled' : ''}
          >
            <span class="ggwp-rank-tier-card__shine"></span>
            <span class="ggwp-rank-tier-card__icon-wrap">
              <img src="${tierIcon}" alt="" class="ggwp-rank-tier-card__icon" loading="lazy" decoding="async">
            </span>
            <span class="ggwp-rank-tier-card__name">${group.tier}</span>
          </button>
        `;
      })
      .join('');
  };

  const openPicker = (select, trigger) => {
    const groups = buildTierGroups(select);
    const selectedTier = parseRankValue(select.value).tier;
    const initialTier = groups.find((group) => group.tier === selectedTier)?.tier
      || groups.find((group) => group.options.some((option) => !option.disabled))?.tier
      || groups[0]?.tier
      || '';

    activeState = {
      select,
      groups,
      activeTier: initialTier,
    };
    returnFocusTo = trigger;

    trigger.setAttribute('aria-expanded', 'true');
    setModalCopy(select);
    renderTiers();
    renderDivisions();
    modal.show();
  };

  queryAll('[data-rank-picker-select]').forEach((select) => {
    syncTrigger(select);
    select.addEventListener('change', () => {
      syncTrigger(select);

      if (activeState?.select === select) {
        setModalCopy(select);
      }
    });

    const trigger = query(`[data-rank-picker-target="${select.id}"]`);
    if (!trigger) {
      return;
    }

    if (trigger.dataset.rankPickerLocked === 'true') {
      return;
    }

    trigger.addEventListener('click', () => openPicker(select, trigger));
  });

  modalElement.addEventListener('hidden.bs.modal', () => {
    const expandedTrigger = returnFocusTo;

    queryAll('[data-rank-picker-trigger][aria-expanded="true"]').forEach((trigger) => {
      trigger.setAttribute('aria-expanded', 'false');
    });

    activeState = null;
    returnFocusTo = null;
    if (expandedTrigger) {
      expandedTrigger.focus();
    }
  });

  modalElement.addEventListener('shown.bs.modal', focusActiveModalChoice);

  tierGrid.addEventListener('click', (event) => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-rank-picker-tier]')
      : null;

    if (!(button instanceof HTMLButtonElement) || !activeState) {
      return;
    }

    const tier = button.getAttribute('data-rank-picker-tier');
    const group = activeState.groups.find((entry) => entry.tier === tier);

    if (!tier || !group || group.options.every((option) => option.disabled)) {
      return;
    }

    if (group.options.length === 1 && !group.options[0].disabled) {
      applyValue(group.options[0].value);
      return;
    }

    activeState.activeTier = tier;
    renderTiers();
    renderDivisions();
    focusFirstAvailable(divisionGrid, ['.ggwp-rank-division-chip.is-selected', '.ggwp-rank-division-chip:not([disabled])']);
  });

  divisionGrid.addEventListener('click', (event) => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-rank-picker-value]')
      : null;

    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const nextValue = button.getAttribute('data-rank-picker-value');

    if (nextValue) {
      applyValue(nextValue);
    }
  });
}
