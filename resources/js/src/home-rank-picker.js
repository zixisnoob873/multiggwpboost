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
  const glow = document.createElement('span');
  glow.className = 'ggwp-rank-picker-trigger__glow';
  glow.style.setProperty('--rank-accent', meta.accent);
  glow.style.setProperty('--rank-glow', meta.glow);

  const img = document.createElement('img');
  img.src = rankIcon;
  img.alt = '';
  img.className = 'ggwp-rank-picker-trigger__icon';
  img.loading = 'lazy';
  img.decoding = 'async';

  art.replaceChildren(glow, img);
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
      divisionGrid.replaceChildren();
      return;
    }

    if (!selectedGroup.divisions.length) {
      setDivisionVisibility(false);
      divisionTitle.textContent = selectedGroup.tier;
      divisionGrid.replaceChildren();
      return;
    }

    setDivisionVisibility(true);
    divisionTitle.textContent = selectedGroup.divisions.length ? `${selectedGroup.tier} divisions` : selectedGroup.tier;
    const fragment = document.createDocumentFragment();

    selectedGroup.options.forEach((option) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `ggwp-rank-division-chip${option.value === activeState.select.value ? ' is-selected' : ''}`;
      button.dataset.rankPickerValue = option.value;
      button.setAttribute('aria-label', option.value);
      button.setAttribute('aria-pressed', option.value === activeState.select.value ? 'true' : 'false');
      if (option.disabled) button.disabled = true;

      const label = document.createElement('span');
      label.className = 'ggwp-rank-division-chip__value';
      label.textContent = selectedGroup.divisions.length ? option.label : option.value;
      button.appendChild(label);
      fragment.appendChild(button);
    });

    divisionGrid.replaceChildren(fragment);
  };

  const renderTiers = () => {
    const fragment = document.createDocumentFragment();

    activeState.groups.forEach((group) => {
      const disabled = group.options.every((option) => option.disabled);
      const isSelectedTier = activeState.activeTier === group.tier;
      const tierIcon = getRankIcon(group.iconValue, group.iconValue);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `ggwp-rank-tier-card${isSelectedTier ? ' is-selected' : ''}`;
      button.dataset.rankPickerTier = group.tier;
      button.setAttribute('aria-label', group.tier);
      button.setAttribute('aria-pressed', isSelectedTier ? 'true' : 'false');
      button.style.setProperty('--rank-accent', group.accent);
      button.style.setProperty('--rank-glow', group.glow);
      if (disabled) button.disabled = true;

      const shine = document.createElement('span');
      shine.className = 'ggwp-rank-tier-card__shine';

      const iconWrap = document.createElement('span');
      iconWrap.className = 'ggwp-rank-tier-card__icon-wrap';
      const image = document.createElement('img');
      image.src = tierIcon;
      image.alt = '';
      image.className = 'ggwp-rank-tier-card__icon';
      image.loading = 'lazy';
      image.decoding = 'async';
      iconWrap.appendChild(image);

      const name = document.createElement('span');
      name.className = 'ggwp-rank-tier-card__name';
      name.textContent = group.tier;

      button.append(shine, iconWrap, name);
      fragment.appendChild(button);
    });

    tierGrid.replaceChildren(fragment);
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


  const moveGridFocus = (grid, selector, direction) => {
    const buttons = queryAll(selector, grid).filter((button) => button instanceof HTMLButtonElement && !button.disabled);
    if (!buttons.length) {
      return;
    }
    const current = document.activeElement;
    const index = Math.max(0, buttons.indexOf(current));
    const next = buttons[(index + direction + buttons.length) % buttons.length];
    next.focus();
  };

  const handleGridKeydown = (event, grid, selector, onActivate) => {
    if (event.key === 'Home') {
      event.preventDefault();
      queryAll(selector, grid).find((button) => !button.disabled)?.focus();
      return;
    }
    if (event.key === 'End') {
      event.preventDefault();
      queryAll(selector, grid).filter((button) => !button.disabled).at(-1)?.focus();
      return;
    }
    if (['ArrowRight', 'ArrowDown'].includes(event.key)) {
      event.preventDefault();
      moveGridFocus(grid, selector, 1);
      return;
    }
    if (['ArrowLeft', 'ArrowUp'].includes(event.key)) {
      event.preventDefault();
      moveGridFocus(grid, selector, -1);
      return;
    }
    if (['Enter', ' '].includes(event.key) && document.activeElement instanceof HTMLButtonElement) {
      event.preventDefault();
      onActivate(document.activeElement);
    }
  };

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

  tierGrid.addEventListener('keydown', (event) => handleGridKeydown(event, tierGrid, '[data-rank-picker-tier]', (button) => button.click()));
  divisionGrid.addEventListener('keydown', (event) => handleGridKeydown(event, divisionGrid, '[data-rank-picker-value]', (button) => button.click()));

}
