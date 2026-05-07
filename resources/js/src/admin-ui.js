import { setButtonBusy } from './common';

function initLoadingForms() {
  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-loading-form]')) {
      return;
    }

    const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : form.querySelector('button[type="submit"]');

    if (submitter instanceof HTMLButtonElement) {
      setButtonBusy(submitter, true, submitter.dataset.busyLabel || 'Saving...');
    }
  });
}

function initDirtyForms() {
  let isDirty = false;

  document.querySelectorAll('[data-dirty-form]').forEach((form) => {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const markDirty = () => {
      isDirty = true;
    };

    form.addEventListener('input', markDirty, { passive: true });
    form.addEventListener('change', markDirty, { passive: true });
    form.addEventListener('submit', () => {
      isDirty = false;
    });
  });

  window.addEventListener('beforeunload', (event) => {
    if (!isDirty) {
      return;
    }

    event.preventDefault();
    event.returnValue = '';
  });
}

function initSearchableSelects() {
  document.querySelectorAll('[data-searchable-select]').forEach((root) => {
    const input = root.querySelector('[data-searchable-select-input]');
    const select = root.querySelector('[data-searchable-select-target]');
    const emptyState = root.querySelector('[data-searchable-select-empty]');

    if (!(input instanceof HTMLInputElement) || !(select instanceof HTMLSelectElement)) {
      return;
    }

    const options = Array.from(select.options).map((option) => ({
      value: option.value,
      text: option.text,
      disabled: option.disabled,
    }));

    const render = (query = '') => {
      const normalizedQuery = query.trim().toLowerCase();
      const currentValue = select.value;
      const placeholder = options.find((option) => option.value === '');
      const currentOption = options.find((option) => option.value === currentValue);
      const matches = options.filter((option) => option.value !== '' && (normalizedQuery === '' || option.text.toLowerCase().includes(normalizedQuery)));
      const visibleOptions = [];
      const seen = new Set();

      const pushOption = (option) => {
        if (!option || seen.has(option.value)) {
          return;
        }

        seen.add(option.value);
        visibleOptions.push(option);
      };

      pushOption(placeholder);
      pushOption(currentOption);
      matches.forEach(pushOption);

      select.innerHTML = '';

      visibleOptions.forEach((option) => {
        const rendered = new Option(option.text, option.value, false, false);
        rendered.disabled = option.disabled;
        select.add(rendered);
      });

      if (visibleOptions.some((option) => option.value === currentValue)) {
        select.value = currentValue;
      } else if (placeholder) {
        select.value = '';
      }

      if (emptyState instanceof HTMLElement) {
        emptyState.classList.toggle('d-none', normalizedQuery === '' || matches.length > 0);
      }
    };

    input.addEventListener('input', () => {
      render(input.value);
    });

    const form = select.form;

    if (form instanceof HTMLFormElement) {
      form.addEventListener('reset', () => {
        window.requestAnimationFrame(() => {
          input.value = '';
          render('');
        });
      });
    }

    render(input.value);
  });
}

function initOrderFilterPresets() {
  const root = document.querySelector('[data-order-filter-presets]');

  if (!(root instanceof HTMLElement)) {
    return;
  }

  const key = root.dataset.orderFilterPresets || 'ggwp-admin-order-presets';
  const saveButton = root.querySelector('[data-save-order-preset]');
  const presetSelect = root.querySelector('[data-order-preset-select]');
  const presetName = root.querySelector('[data-order-preset-name]');
  const form = root.querySelector('form[data-order-filter-form]');

  if (!(saveButton instanceof HTMLButtonElement) || !(presetSelect instanceof HTMLSelectElement) || !(presetName instanceof HTMLInputElement) || !(form instanceof HTMLFormElement)) {
    return;
  }

  const readPresets = () => {
    try {
      const parsed = JSON.parse(window.localStorage.getItem(key) || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  };

  const writePresets = (items) => {
    window.localStorage.setItem(key, JSON.stringify(items.slice(0, 8)));
  };

  const formState = () => {
    const params = new URLSearchParams(new FormData(form));
    params.delete('_token');
    return params.toString();
  };

  const renderPresets = () => {
    const presets = readPresets();
    presetSelect.innerHTML = '<option value="">Saved presets</option>';

    presets.forEach((preset, index) => {
      const option = document.createElement('option');
      option.value = String(index);
      option.textContent = preset.name;
      presetSelect.appendChild(option);
    });
  };

  saveButton.addEventListener('click', () => {
    const name = presetName.value.trim();

    if (!name) {
      presetName.focus();
      return;
    }

    const presets = readPresets();
    presets.unshift({
      name,
      query: formState(),
    });
    writePresets(presets);
    presetName.value = '';
    renderPresets();
  });

  presetSelect.addEventListener('change', () => {
    const index = Number(presetSelect.value);
    const preset = readPresets()[index];

    if (!preset?.query) {
      return;
    }

    window.location.href = `${window.location.pathname}?${preset.query}`;
  });

  renderPresets();
}

function initAdminSidebarDrawer() {
  const sidebar = document.getElementById('adminSidebar');
  const toggle = document.querySelector('[data-bs-target="#adminSidebar"]');

  if (!(sidebar instanceof HTMLElement) || !window.bootstrap?.Offcanvas) {
    return;
  }

  const isDrawerLayout = () => window.matchMedia('(max-width: 991.98px)').matches;

  sidebar.querySelectorAll('a[href]').forEach((link) => {
    link.addEventListener('click', () => {
      if (!isDrawerLayout() || !sidebar.classList.contains('show')) {
        return;
      }

      window.bootstrap.Offcanvas.getOrCreateInstance(sidebar).hide();
    });
  });

  sidebar.addEventListener('hidden.bs.offcanvas', () => {
    if (toggle instanceof HTMLElement && isDrawerLayout()) {
      toggle.focus({ preventScroll: true });
    }
  });
}

function validatePricingNumber(input) {
  if (!(input instanceof HTMLInputElement)) {
    return;
  }

  const rawValue = input.value.trim();
  const minValue = input.dataset.min ?? input.getAttribute('min');
  const maxValue = input.dataset.max ?? input.getAttribute('max');
  const min = minValue === null || minValue === '' ? Number.NEGATIVE_INFINITY : Number(minValue);
  const max = maxValue === null || maxValue === '' ? Number.POSITIVE_INFINITY : Number(maxValue);
  const number = Number(rawValue);
  let message = '';

  if (rawValue === '' && input.required) {
    message = 'Enter a value.';
  } else if (rawValue !== '' && !Number.isFinite(number)) {
    message = 'Enter a numeric value.';
  } else if (Number.isFinite(number) && Number.isFinite(min) && number < min) {
    message = `Enter a value of at least ${min}.`;
  } else if (Number.isFinite(number) && Number.isFinite(max) && number > max) {
    message = `Enter a value no greater than ${max}.`;
  } else if (input.dataset.integer === '1' && rawValue !== '' && !Number.isInteger(number)) {
    message = 'Enter a whole number.';
  }

  input.setCustomValidity(message);
  input.classList.toggle('is-invalid', Boolean(message));
}

function specialStepKey(row) {
  const from = row.querySelector('[data-special-from]');
  const to = row.querySelector('[data-special-to]');
  const price = row.querySelector('input[name$="[price]"]');

  if (!(from instanceof HTMLSelectElement) || !(to instanceof HTMLSelectElement) || !(price instanceof HTMLInputElement)) {
    return null;
  }

  const fromValue = from.value.trim();
  const toValue = to.value.trim();
  const priceValue = price.value.trim();

  if (!fromValue && !toValue && !priceValue) {
    [from, to, price].forEach((field) => {
      field.setCustomValidity('');
      field.classList.remove('is-invalid');
    });
    return null;
  }

  return {
    row,
    from,
    to,
    price,
    key: `${fromValue}->${toValue}`,
    fromIndex: Number(from.selectedOptions?.[0]?.dataset?.rankIndex),
    toIndex: Number(to.selectedOptions?.[0]?.dataset?.rankIndex),
  };
}

function validateSpecialSteps(form) {
  const error = form.querySelector('[data-pricing-special-error]');
  const entries = Array.from(form.querySelectorAll('[data-pricing-special-step]'))
    .map(specialStepKey)
    .filter(Boolean);
  const seen = new Map();
  const messages = [];

  entries.forEach(({ from, to, price, key, fromIndex, toIndex }) => {
    [from, to].forEach((field) => {
      field.setCustomValidity('');
      field.classList.remove('is-invalid');
    });
    validatePricingNumber(price);

    if (!from.value || !to.value || !price.value.trim()) {
      const message = 'Complete or remove the special rank step.';
      [from, to, price].forEach((field) => {
        field.setCustomValidity(message);
        field.classList.add('is-invalid');
      });
      messages.push(message);
      return;
    }

    if (!Number.isFinite(fromIndex) || !Number.isFinite(toIndex) || toIndex !== fromIndex + 1) {
      const message = `${key} must use consecutive ranks.`;
      from.setCustomValidity(message);
      to.setCustomValidity(message);
      from.classList.add('is-invalid');
      to.classList.add('is-invalid');
      messages.push(message);
      return;
    }

    if (seen.has(key)) {
      const message = `${key} is duplicated.`;
      [from, to].forEach((field) => {
        field.setCustomValidity(message);
        field.classList.add('is-invalid');
      });
      const previous = seen.get(key);
      previous.from.setCustomValidity(message);
      previous.to.setCustomValidity(message);
      previous.from.classList.add('is-invalid');
      previous.to.classList.add('is-invalid');
      messages.push(message);
      return;
    }

    seen.set(key, { from, to });
  });

  if (error instanceof HTMLElement) {
    const uniqueMessages = Array.from(new Set(messages));
    error.textContent = uniqueMessages.join(' ');
    error.classList.toggle('d-none', uniqueMessages.length === 0);
  }
}

function validateAddonPricingRows(form) {
  form.querySelectorAll('select[data-addon-type]').forEach((select) => {
    if (!(select instanceof HTMLSelectElement)) {
      return;
    }

    const row = select.closest('tr');
    const value = row?.querySelector('input[name$="[value]"]');

    if (!(value instanceof HTMLInputElement)) {
      return;
    }

    if (select.value === 'percent' && value.value.trim() === '') {
      value.setCustomValidity('Enter a percent multiplier.');
      value.classList.add('is-invalid');
      return;
    }

    if (value.validationMessage === 'Enter a percent multiplier.') {
      value.setCustomValidity('');
      value.classList.remove('is-invalid');
    }
  });
}

function pricingRiskRequiresConfirmation(form) {
  let changedCount = 0;
  let largeChange = false;

  form.querySelectorAll('[data-pricing-number]').forEach((field) => {
    if (!(field instanceof HTMLInputElement)) {
      return;
    }

    const originalRaw = field.dataset.originalValue;
    if (originalRaw === undefined || originalRaw === '') {
      return;
    }

    const original = Number(originalRaw);
    const current = Number(field.value);
    if (!Number.isFinite(original) || !Number.isFinite(current) || original === current) {
      return;
    }

    changedCount += 1;

    if (original === 0 ? current > 0 : Math.abs(current - original) / Math.abs(original) >= 0.5) {
      largeChange = true;
    }
  });

  return changedCount > 10 || largeChange;
}

function initPricingEditor() {
  const form = document.querySelector('[data-pricing-editor-form]');

  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  const validateAll = () => {
    form.querySelectorAll('[data-pricing-number]').forEach(validatePricingNumber);
    validateAddonPricingRows(form);
    validateSpecialSteps(form);
  };

  form.addEventListener('input', (event) => {
    if (event.target instanceof HTMLInputElement && event.target.matches('[data-pricing-number]')) {
      validatePricingNumber(event.target);
    }

    validateAddonPricingRows(form);
    validateSpecialSteps(form);
  }, { passive: true });

  form.addEventListener('change', validateAll, { passive: true });

  form.addEventListener('submit', (event) => {
    validateAll();

    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      form.classList.add('was-validated');
      form.querySelector(':invalid')?.focus?.();
      return;
    }

    if (form.dataset.pricingRiskConfirmed === '1' || !pricingRiskRequiresConfirmation(form)) {
      return;
    }

    if (!window.confirm('This pricing update changes many values or changes a value by at least 50%. Save it anyway?')) {
      event.preventDefault();
      event.stopPropagation();
      return;
    }

    form.dataset.pricingRiskConfirmed = '1';
  }, { capture: true });

  const body = form.querySelector('[data-pricing-special-steps]');
  const template = form.querySelector('[data-pricing-special-row-template]');
  const addButton = form.querySelector('[data-pricing-add-special-step]');
  let nextSpecialStepIndex = form.querySelectorAll('[data-pricing-special-step]').length;

  addButton?.addEventListener('click', () => {
    if (!(body instanceof HTMLElement) || !(template instanceof HTMLTemplateElement)) {
      return;
    }

    const html = template.innerHTML.replaceAll('__INDEX__', String(nextSpecialStepIndex));
    nextSpecialStepIndex += 1;
    body.insertAdjacentHTML('beforeend', html);
    validateAll();
  });

  form.addEventListener('click', (event) => {
    const button = event.target instanceof HTMLElement
      ? event.target.closest('[data-pricing-remove-special-step]')
      : null;

    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const row = button.closest('[data-pricing-special-step]');
    if (!row) {
      return;
    }

    if (form.querySelectorAll('[data-pricing-special-step]').length <= 1) {
      row.querySelectorAll('input, select').forEach((field) => {
        if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement) {
          field.value = '';
        }
      });
    } else {
      row.remove();
    }

    validateAll();
  });

  validateAll();
}

function focusFirstModalField(root) {
  const target = root.querySelector(
    '.is-invalid, [autofocus], input:not([type="hidden"]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button:not([disabled]):not([data-bs-dismiss="modal"])'
  );

  if (target instanceof HTMLElement) {
    target.focus({ preventScroll: true });
  }
}

function resetManagedModalForm(form) {
  if (!(form instanceof HTMLFormElement)) {
    return;
  }

  form.reset();
  form.classList.remove('was-validated');

  form.querySelectorAll('.is-invalid').forEach((field) => {
    field.classList.remove('is-invalid');
  });

  form.querySelectorAll('.invalid-feedback.d-block').forEach((feedback) => {
    feedback.classList.remove('d-block');
  });

  form.querySelectorAll('button.is-busy, button[aria-busy="true"]').forEach((button) => {
    if (button instanceof HTMLButtonElement) {
      setButtonBusy(button, false);
    }
  });
}

function initManagedModals() {
  if (!window.bootstrap?.Modal) {
    return;
  }

  const modalElements = Array.from(document.querySelectorAll('.modal[data-admin-modal]'));

  if (!modalElements.length) {
    return;
  }

  modalElements.forEach((modalElement) => {
    modalElement.addEventListener('shown.bs.modal', () => {
      focusFirstModalField(modalElement);
    });

    modalElement.addEventListener('hidden.bs.modal', () => {
      modalElement.querySelectorAll('form[data-modal-reset-form]').forEach((form) => {
        resetManagedModalForm(form);
      });
    });
  });

  const openModalId = document.querySelector('[data-open-admin-modal]')?.getAttribute('data-open-admin-modal')?.trim() || '';

  if (!openModalId) {
    return;
  }

  const modalElement = document.getElementById(openModalId);

  if (!(modalElement instanceof HTMLElement) || !modalElement.matches('.modal[data-admin-modal]')) {
    return;
  }

  window.requestAnimationFrame(() => {
    window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
  });
}

export function initAdminUi() {
  initAdminSidebarDrawer();
  initPricingEditor();
  initLoadingForms();
  initDirtyForms();
  initManagedModals();
  initSearchableSelects();
  initOrderFilterPresets();
}
