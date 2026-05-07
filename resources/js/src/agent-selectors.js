import { byId, queryAll, toggleClass } from './dom';

const fieldsByContext = new Map();
const boundViewTriggers = new WeakSet();
const MODAL_HANDOFF_CLASS = 'ggwp-modal-handoff';

function safeJsonArray(value) {
  if (Array.isArray(value)) {
    return value;
  }

  if (typeof value !== 'string') {
    return [];
  }

  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed : [];
  } catch (_) {
    return [];
  }
}

function modalInstanceFor(element) {
  return element && window.bootstrap?.Modal
    ? window.bootstrap.Modal.getOrCreateInstance(element)
    : null;
}

function focusSafely(element) {
  if (!(element instanceof HTMLElement) || !document.body.contains(element)) {
    return;
  }

  window.requestAnimationFrame(() => {
    element.focus({ preventScroll: true });
  });
}

function normalizeCatalog() {
  const agents = Array.isArray(window.appState?.valorantAgents) ? window.appState.valorantAgents : [];

  return agents
    .map((agent) => ({
      uuid: String(agent?.uuid || '').trim().toLowerCase(),
      displayName: String(agent?.displayName || '').trim(),
      displayIcon: String(agent?.displayIcon || '').trim(),
      role: String(agent?.role || 'Agent').trim() || 'Agent',
    }))
    .filter((agent) => agent.uuid && agent.displayName && agent.displayIcon);
}

function normalizeSelection(values, lookup) {
  const seen = new Set();

  return safeJsonArray(values)
    .map((value) => String(value || '').trim().toLowerCase())
    .filter((value) => value && lookup.has(value) && !seen.has(value) && seen.add(value));
}

function resolveSelection(values, lookup) {
  return values
    .map((uuid) => lookup.get(uuid))
    .filter(Boolean);
}

function buildPreviewTile(agent) {
  const tile = document.createElement('div');
  tile.className = 'ggwp-agents-tile';

  const media = document.createElement('span');
  media.className = 'ggwp-agents-tile__media';

  const icon = document.createElement('img');
  icon.src = String(agent?.displayIcon || '');
  icon.alt = String(agent?.displayName || '');
  icon.className = 'ggwp-agents-tile__icon';
  media.appendChild(icon);

  const copy = document.createElement('span');
  copy.className = 'ggwp-agents-tile__copy';

  const name = document.createElement('span');
  name.className = 'ggwp-agents-tile__name';
  name.textContent = String(agent?.displayName || '');

  const role = document.createElement('span');
  role.className = 'ggwp-agents-tile__role';
  role.textContent = String(agent?.role || 'Agent');

  copy.append(name, role);
  tile.append(media, copy);

  return tile;
}

function renderPreviewTiles(container, agents = []) {
  if (!(container instanceof HTMLElement)) {
    return;
  }

  container.replaceChildren(...agents.map(buildPreviewTile));
}

function buildOptionContent(agent) {
  const fragment = document.createDocumentFragment();
  const media = document.createElement('span');
  media.className = 'ggwp-agents-option__media';

  const icon = document.createElement('img');
  icon.src = String(agent?.displayIcon || '');
  icon.alt = String(agent?.displayName || '');
  icon.className = 'ggwp-agents-option__icon';
  icon.decoding = 'async';
  media.appendChild(icon);

  const copy = document.createElement('span');
  copy.className = 'ggwp-agents-option__copy';

  const name = document.createElement('span');
  name.className = 'ggwp-agents-option__name';
  name.textContent = String(agent?.displayName || '');

  const role = document.createElement('span');
  role.className = 'ggwp-agents-option__role';
  role.textContent = String(agent?.role || 'Agent');

  copy.append(name, role);
  fragment.append(media, copy);

  return fragment;
}

function selectionLimit(value, fallback = null) {
  const parsed = Number.parseInt(String(value ?? ''), 10);

  return Number.isInteger(parsed) && parsed >= 0 ? parsed : fallback;
}

function pluralize(count, singular, plural = `${singular}s`) {
  return count === 1 ? singular : plural;
}

function selectionsEqual(left = [], right = []) {
  return left.length === right.length
    && left.every((value, index) => value === right[index]);
}

function contextStore(context) {
  if (!fieldsByContext.has(context)) {
    fieldsByContext.set(context, new Map());
  }

  return fieldsByContext.get(context);
}

function buildSelectionMeta(field, count) {
  if (field.maxSelections !== null && field.minSelections === field.maxSelections) {
    return `Select exactly ${field.minSelections} ${pluralize(field.minSelections, 'agent')}. ${count} selected.`;
  }

  if (field.minSelections > 0 && field.maxSelections !== null) {
    return `Select between ${field.minSelections} and ${field.maxSelections} agents. ${count} selected.`;
  }

  if (field.minSelections > 0) {
    return `Select at least ${field.minSelections} agents. ${count} selected.`;
  }

  if (field.maxSelections !== null) {
    return `Select up to ${field.maxSelections} agents. ${count} selected.`;
  }

  return `${count} ${pluralize(count, 'agent')} selected.`;
}

class AgentSelectorField {
  constructor(controller, container) {
    this.controller = controller;
    this.container = container;
    this.id = container.dataset.agentSelectorFieldId || `${container.dataset.agentSelectorContext || 'global'}-${container.dataset.agentSelectorKey || 'agents'}`;
    this.context = container.dataset.agentSelectorContext || this.id;
    this.selectorKey = container.dataset.agentSelectorKey || 'specificAgents';
    this.label = container.dataset.agentSelectorLabel || 'Agent Selection';
    this.title = container.dataset.agentSelectorTitle || 'Choose agents';
    this.description = container.dataset.agentSelectorDescription || 'Select the agents tied to this order.';
    this.summaryEmpty = container.dataset.agentSelectorSummaryEmpty || 'No agents selected yet.';
    this.requiredMessage = container.dataset.agentSelectorRequiredMessage || 'Select the required agents before saving.';
    this.inputName = container.dataset.agentSelectorInputName || '';
    this.minSelections = selectionLimit(container.dataset.agentSelectorMinSelections, 0) ?? 0;
    this.maxSelections = selectionLimit(container.dataset.agentSelectorMaxSelections, null);
    this.singleSelect = container.dataset.agentSelectorSingleSelect === 'true' || this.maxSelections === 1;
    this.addonInput = byId(container.dataset.agentSelectorAddonInputId || '');
    this.panel = container.querySelector('[data-agent-selector-field-panel]');
    this.inlineError = container.querySelector('[data-agent-selector-field-error]');
    this.status = container.querySelector('[data-agent-selector-field-status]');
    this.preview = container.querySelector('[data-agent-selector-field-preview]');
    this.hiddenInputs = container.querySelector('[data-agent-selector-field-inputs]');
    this.openButton = container.querySelector('[data-agent-selector-field-open]');
    this.selection = normalizeSelection(container.dataset.agentSelectorSelection || '[]', controller.lookup);

    this.bind();
    this.syncHiddenInputs();

    if (this.addonInput instanceof HTMLInputElement) {
      this.syncFromAddonState();

      if ((this.addonInput.checked && this.hasVisibleError()) || this.shouldAutoOpenEditor({ forceRequired: true })) {
        window.requestAnimationFrame(() => {
          if (!(this.addonInput instanceof HTMLInputElement) || !this.addonInput.checked || this.controller.isEditing(this)) {
            return;
          }

          this.controller.openEditor(this, {
            activateAddon: false,
            opener: this.openButton || this.addonInput,
            showValidation: this.hasVisibleError(),
          });
        });
      }

      return;
    }

    this.render();
  }

  bind() {
    this.openButton?.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      this.controller.openEditor(this, {
        activateAddon: false,
        opener: this.openButton,
      });
    });

    this.addonInput?.addEventListener('change', () => {
      this.syncFromAddonState({
        allowAutoOpen: true,
        opener: this.addonInput,
      });
    });

    const form = this.container.closest('form');

    if (form) {
      form.addEventListener('submit', (event) => {
        if (!this.requiresSelection()) {
          return;
        }

        const message = this.validationMessage(this.selection);

        if (!message) {
          this.setInlineError('');
          return;
        }

        event.preventDefault();
        this.setInlineError(message);
        this.controller.openEditor(this, {
          activateAddon: false,
          opener: this.openButton || this.addonInput || form.querySelector('[type="submit"]'),
        });
      });
    }
  }

  requiresSelection() {
    return !(this.addonInput instanceof HTMLInputElement) || (!this.addonInput.disabled && this.addonInput.checked);
  }

  validationMessage(selection = this.selection, { forceRequired = false } = {}) {
    if (!forceRequired && !this.requiresSelection()) {
      return '';
    }

    const count = selection.length;

    if (this.minSelections === this.maxSelections && this.maxSelections !== null) {
      return count === this.minSelections ? '' : this.requiredMessage;
    }

    if (count < this.minSelections) {
      return this.requiredMessage;
    }

    if (this.maxSelections !== null && count > this.maxSelections) {
      return this.requiredMessage;
    }

    return '';
  }

  hasVisibleError() {
    return Boolean(this.inlineError && !this.inlineError.classList.contains('d-none'));
  }

  hasValidSelection(selection = this.selection, options = {}) {
    return this.validationMessage(selection, options) === '';
  }

  shouldAutoOpenEditor({ forceRequired = false } = {}) {
    return this.addonInput instanceof HTMLInputElement
      && !this.addonInput.disabled
      && this.addonInput.checked
      && !this.controller.isEditing(this)
      && !this.hasValidSelection(this.selection, { forceRequired });
  }

  syncFromAddonState({ allowAutoOpen = false, opener = null } = {}) {
    if (!(this.addonInput instanceof HTMLInputElement)) {
      this.render();
      return;
    }

    if (this.addonInput.disabled || !this.addonInput.checked) {
      this.clearSelection();
      this.setInlineError('');
      this.controller.closeIfEditing(this);
      return;
    }

    this.render();
    if (this.selection.length > 0) {
      this.dispatchChanged();
    }

    if (allowAutoOpen && this.shouldAutoOpenEditor({ forceRequired: true })) {
      this.controller.openEditor(this, {
        activateAddon: false,
        opener: opener || this.openButton || this.addonInput,
        showValidation: false,
      });
    }
  }

  setInlineError(message = '') {
    if (!this.inlineError) {
      return;
    }

    this.inlineError.textContent = message || this.requiredMessage;
    toggleClass(this.inlineError, 'd-none', !message);
  }

  setSelection(selection, { dispatch = true } = {}) {
    const normalizedSelection = normalizeSelection(selection, this.controller.lookup);
    const selectionChanged = !selectionsEqual(this.selection, normalizedSelection);

    this.selection = normalizedSelection;
    this.container.dataset.agentSelectorSelection = JSON.stringify(this.selection);
    this.syncHiddenInputs();
    this.render();
    this.setInlineError('');

    if (dispatch && selectionChanged) {
      this.dispatchChanged();
    }
  }

  clearSelection() {
    this.setSelection([], { dispatch: true });
  }

  deactivateAndClearSelection() {
    if (this.addonInput instanceof HTMLInputElement && this.addonInput.checked) {
      this.addonInput.checked = false;
      this.addonInput.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }

    this.clearSelection();
    this.setInlineError('');
  }

  syncHiddenInputs() {
    if (!this.hiddenInputs) {
      return;
    }

    this.hiddenInputs.innerHTML = '';

    if (!this.inputName || this.selection.length === 0) {
      return;
    }

    this.selection.forEach((uuid) => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = `${this.inputName}[]`;
      input.value = uuid;
      this.hiddenInputs.appendChild(input);
    });
  }

  dispatchChanged() {
    this.container.dispatchEvent(new CustomEvent('agent-selector:changed', {
      bubbles: true,
      detail: {
        fieldId: this.id,
        context: this.context,
        selectorKey: this.selectorKey,
        selection: [...this.selection],
      },
    }));
  }

  render() {
    const shouldShow = !(this.addonInput instanceof HTMLInputElement) || this.addonInput.checked;
    const agents = resolveSelection(this.selection, this.controller.lookup);

    if (this.panel) {
      toggleClass(this.panel, 'd-none', !shouldShow);
    }

    if (this.status) {
      this.status.textContent = agents.length
        ? `${agents.length} ${pluralize(agents.length, 'agent')} selected`
        : this.summaryEmpty;
    }

    if (this.preview) {
      renderPreviewTiles(this.preview, agents);
    }
  }
}

class AgentSelectorModalController {
  constructor(root) {
    this.root = root;
    this.lookup = new Map(normalizeCatalog().map((agent) => [agent.uuid, agent]));
    this.catalog = Array.from(this.lookup.values());
    this.instance = modalInstanceFor(root);
    this.eyebrow = root.querySelector('[data-agent-selector-modal-eyebrow]');
    this.title = root.querySelector('[data-agent-selector-modal-title]');
    this.description = root.querySelector('[data-agent-selector-modal-description]');
    this.meta = root.querySelector('[data-agent-selector-modal-meta]');
    this.error = root.querySelector('[data-agent-selector-modal-error]');
    this.empty = root.querySelector('[data-agent-selector-modal-empty]');
    this.grid = root.querySelector('[data-agent-selector-modal-grid]');
    this.viewGrid = root.querySelector('[data-agent-selector-modal-view-grid]');
    this.saveButton = root.querySelector('[data-agent-selector-modal-save]');
    this.closeButton = root.querySelector('[data-agent-selector-modal-close]');
    this.activeSession = null;
    this.optionElements = new Map();

    this.buildOptionGrid();
    this.preloadCatalogIcons();
    this.bind();
  }

  bind() {
    this.root.addEventListener('shown.bs.modal', () => {
      this.endModalHandoff();
      this.focusActiveSurface();
    });

    this.grid?.addEventListener('click', (event) => {
      if (!this.activeSession || this.activeSession.mode !== 'edit') {
        return;
      }

      const option = event.target.closest('[data-agent-selector-option]');

      if (!(option instanceof HTMLElement) || !(this.activeSession.field instanceof AgentSelectorField)) {
        return;
      }

      const uuid = String(option.dataset.agentUuid || '').trim().toLowerCase();

      if (!uuid || !this.lookup.has(uuid)) {
        return;
      }

      if (this.activeSession.field.singleSelect) {
        this.activeSession.selection = this.activeSession.selection.includes(uuid) ? [] : [uuid];
      } else {
        this.activeSession.selection = this.activeSession.selection.includes(uuid)
          ? this.activeSession.selection.filter((value) => value !== uuid)
          : [...this.activeSession.selection, uuid];
      }

      this.render({ showValidation: true });
    });

    this.saveButton?.addEventListener('click', () => {
      if (!this.activeSession || this.activeSession.mode !== 'edit' || !(this.activeSession.field instanceof AgentSelectorField)) {
        return;
      }

      const validationMessage = this.validationMessageForSession(this.activeSession);

      if (validationMessage) {
        this.setError(validationMessage);
        return;
      }

      this.saveButton.disabled = true;
      this.saveButton.setAttribute('aria-busy', 'true');
      this.instance?.hide();
    });

    this.root.addEventListener('hidden.bs.modal', () => {
      const session = this.activeSession;

      if (this.saveButton) {
        this.saveButton.disabled = false;
      }
      this.saveButton?.removeAttribute('aria-busy');
      this.activeSession = null;
      this.setError('');

      if (!session) {
        this.endModalHandoff();
        return;
      }

      if (session.mode === 'edit') {
        this.finalizeEditSession(session);
      }

      if (session.returnModal instanceof HTMLElement && document.body.contains(session.returnModal)) {
        const opener = session.opener;

        this.startModalHandoff();

        session.returnModal.addEventListener('shown.bs.modal', () => {
          this.endModalHandoff();
          focusSafely(opener);
        }, { once: true });

        window.requestAnimationFrame(() => {
          modalInstanceFor(session.returnModal)?.show();
        });

        return;
      }

      this.endModalHandoff();
      focusSafely(session.opener);
    });
  }

  buildOptionGrid() {
    if (!this.grid || this.optionElements.size > 0) {
      return;
    }

    const fragment = document.createDocumentFragment();

    this.catalog.forEach((agent) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'ggwp-agents-option';
      button.dataset.agentSelectorOption = '';
      button.dataset.agentUuid = agent.uuid;
      button.setAttribute('aria-pressed', 'false');
      button.appendChild(buildOptionContent(agent));

      this.optionElements.set(agent.uuid, button);
      fragment.appendChild(button);
    });

    this.grid.replaceChildren(fragment);
  }

  preloadCatalogIcons() {
    this.catalog.forEach((agent) => {
      if (!agent.displayIcon) {
        return;
      }

      const image = new Image();
      image.decoding = 'async';
      image.src = agent.displayIcon;
    });
  }

  startModalHandoff() {
    document.body.classList.add(MODAL_HANDOFF_CLASS);
  }

  endModalHandoff() {
    document.body.classList.remove(MODAL_HANDOFF_CLASS);
  }

  isEditing(field) {
    return this.activeSession?.mode === 'edit' && this.activeSession.field === field;
  }

  closeIfEditing(field) {
    if (this.isEditing(field)) {
      this.instance?.hide();
    }
  }

  validationMessageForSession(session) {
    const field = session?.field instanceof AgentSelectorField
      ? session.field
      : null;

    if (!field) {
      return '';
    }

    return field.validationMessage(session.selection, {
      forceRequired: Boolean(
        session.activateAddon
        || field.requiresSelection()
        || session.selection.length > 0
        || field.selection.length > 0
      ),
    });
  }

  finalizeEditSession(session) {
    const field = session?.field instanceof AgentSelectorField
      ? session.field
      : null;

    if (!field) {
      return;
    }

    const validationMessage = this.validationMessageForSession(session);

    if (validationMessage) {
      field.deactivateAndClearSelection();
      return;
    }

    field.setSelection(session.selection, { dispatch: true });
  }

  openEditor(field, { activateAddon = false, opener = null, showValidation = false } = {}) {
    this.open({
      mode: 'edit',
      field,
      selection: [...field.selection],
      label: field.label,
      title: field.title,
      description: field.description,
      opener: opener || field.openButton || field.addonInput,
      activateAddon,
      showValidation,
    });
  }

  openViewer({
    selection = [],
    selectorKey = 'specificAgents',
    label = 'Agent Selection',
    title = 'Selected agent selection',
    description = 'Review the agents attached to this order.',
    opener = null,
  } = {}) {
    const normalizedSelection = normalizeSelection(selection, this.lookup);

    if (normalizedSelection.length === 0) {
      return;
    }

    this.open({
      mode: 'view',
      selectorKey,
      label,
      selection: normalizedSelection,
      title,
      description,
      opener,
      activateAddon: false,
    });
  }

  open(session) {
    const originModal = session.opener?.closest?.('.modal.show') || null;

    this.activeSession = {
      ...session,
      returnModal: originModal instanceof HTMLElement && originModal !== this.root ? originModal : null,
    };

    this.render({ showValidation: Boolean(this.activeSession.showValidation) });

    if (this.activeSession.returnModal) {
      this.startModalHandoff();
      this.activeSession.returnModal.addEventListener('hidden.bs.modal', () => {
        this.instance?.show();
      }, { once: true });

      modalInstanceFor(this.activeSession.returnModal)?.hide();
      return;
    }

    this.instance?.show();
  }

  setError(message = '') {
    if (!this.error) {
      return;
    }

    this.error.textContent = message;
    toggleClass(this.error, 'd-none', !message);
  }

  focusActiveSurface() {
    if (!this.activeSession) {
      return;
    }

    const selectedOption = this.grid?.querySelector('.ggwp-agents-option.is-selected');
    const firstOption = this.grid?.querySelector('.ggwp-agents-option');
    const preferredTarget = this.activeSession.mode === 'edit'
      ? selectedOption || firstOption || this.saveButton || this.closeButton
      : this.closeButton;

    focusSafely(preferredTarget);
  }

  render({ showValidation = false } = {}) {
    if (!this.activeSession) {
      return;
    }

    const selection = resolveSelection(this.activeSession.selection, this.lookup);
    const editMode = this.activeSession.mode === 'edit';
    const field = this.activeSession.field instanceof AgentSelectorField
      ? this.activeSession.field
      : null;
    const label = this.activeSession.label || field?.label || 'Agent Selection';
    const validationMessage = editMode ? this.validationMessageForSession(this.activeSession) : '';

    if (this.eyebrow) {
      this.eyebrow.textContent = label;
    }

    if (this.title) {
      this.title.textContent = this.activeSession.title || label;
    }

    if (this.description) {
      this.description.textContent = this.activeSession.description || 'Select the agents linked to this order.';
    }

    if (this.meta) {
      const metaText = editMode && field
        ? buildSelectionMeta(field, this.activeSession.selection.length)
        : `${selection.length} ${pluralize(selection.length, 'agent')} selected`;

      this.meta.textContent = metaText;
      toggleClass(this.meta, 'd-none', !metaText);
    }

    if (showValidation) {
      this.setError(validationMessage);
    } else {
      this.setError('');
    }

    if (this.closeButton) {
      this.closeButton.textContent = 'Close';
    }

    if (this.saveButton) {
      toggleClass(this.saveButton, 'd-none', !editMode);
    }

    if (this.empty) {
      const isEmpty = editMode ? this.catalog.length === 0 : selection.length === 0;
      toggleClass(this.empty, 'd-none', !isEmpty);
      this.empty.textContent = editMode
        ? 'No agents are available for selection right now.'
        : 'No agents were selected for this order.';
    }

    if (!this.grid) {
      return;
    }

    if (editMode) {
      toggleClass(this.grid, 'd-none', false);
      toggleClass(this.viewGrid, 'd-none', true);
      this.optionElements.forEach((option, uuid) => {
        const isSelected = this.activeSession.selection.includes(uuid);
        option.classList.toggle('is-selected', isSelected);
        option.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
      });

      return;
    }

    toggleClass(this.grid, 'd-none', true);
    toggleClass(this.viewGrid, 'd-none', false);

    if (this.viewGrid) {
      renderPreviewTiles(this.viewGrid, selection);
    }
  }
}

export function getAgentSelectionForContext(context, selectorKey) {
  const field = fieldsByContext.get(context)?.get(selectorKey);
  return field ? [...field.selection] : [];
}

export function syncAgentSelectorsForContext(context) {
  fieldsByContext.get(context)?.forEach((field) => {
    field.syncFromAddonState();
  });
}

export function initAgentSelectors() {
  const root = document.querySelector('[data-agent-selector-modal-root]');

  if (!(root instanceof HTMLElement) || !window.bootstrap?.Modal) {
    return;
  }

  const controller = new AgentSelectorModalController(root);

  queryAll('[data-agent-selector-field]').forEach((container) => {
    const field = new AgentSelectorField(controller, container);
    contextStore(field.context).set(field.selectorKey, field);
  });

  queryAll('[data-agent-selector-view-trigger]').forEach((button) => {
    if (boundViewTriggers.has(button)) {
      return;
    }

    boundViewTriggers.add(button);

    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      controller.openViewer({
        selectorKey: button.dataset.agentSelectorKey || 'specificAgents',
        label: button.dataset.agentSelectorLabel || 'Agent Selection',
        selection: button.dataset.agentSelectorSelection || '[]',
        title: button.dataset.agentSelectorTitle || 'Selected agent selection',
        description: button.dataset.agentSelectorDescription || 'Review the agents attached to this order.',
        opener: button,
      });
    });
  });
}
