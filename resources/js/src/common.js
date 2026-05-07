const getAppState = () => window.appState || {};

export function sleep(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

export function isLoggedIn() {
  return Boolean(getAppState().loggedIn);
}

export function redirectToLogin(returnPath = '') {
  const state = getAppState();
  const loginUrl = state.loginUrl || '/login';
  const target = new URL(loginUrl, window.location.origin);

  if (returnPath) {
    target.searchParams.set('return', returnPath);
  }

  window.location.href = target.toString();
}

export function onReady(callback) {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', callback, { once: true });
    return;
  }

  callback();
}

export function getProductConfig() {
  return window.ggwpProductConfig || {};
}

export function extractApiErrorMessage(payload, fallback = 'Something went wrong.') {
  if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
    return payload.message;
  }

  if (payload?.errors && typeof payload.errors === 'object') {
    const firstField = Object.keys(payload.errors)[0];
    const firstMessage = Array.isArray(payload.errors[firstField]) ? payload.errors[firstField][0] : null;

    if (typeof firstMessage === 'string' && firstMessage.trim() !== '') {
      return firstMessage;
    }
  }

  return fallback;
}

export async function requestJson(url, options = {}) {
  const {
    method = 'GET',
    headers = {},
    body,
    credentials = 'same-origin',
    retries = 0,
    retryDelayMs = 600,
    retryStatuses = [429, 502, 503, 504],
    signal,
    fetchOptions = {},
  } = options;

  let attempt = 0;

  while (true) {
    try {
      const response = await fetch(url, {
        method,
        credentials,
        signal,
        ...fetchOptions,
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...headers,
        },
        ...(body === undefined ? {} : { body }),
      });

      const contentType = response.headers.get('content-type') || '';
      const data = contentType.includes('application/json')
        ? await response.json().catch(() => ({}))
        : {};

      if (response.ok || attempt >= retries || !retryStatuses.includes(response.status)) {
        return {
          response,
          data: data && typeof data === 'object' ? data : {},
        };
      }
    } catch (error) {
      if (error?.name === 'AbortError' || attempt >= retries) {
        throw error;
      }
    }

    attempt += 1;
    await sleep(retryDelayMs * attempt);
  }
}

export function setButtonBusy(button, isBusy, busyLabel = '') {
  if (!(button instanceof HTMLElement)) {
    return;
  }

  if (isBusy) {
    if (!button.dataset.originalHtml) {
      button.dataset.originalHtml = button.innerHTML;
      button.dataset.originalDisabled = 'disabled' in button && button.disabled ? '1' : '0';
    }

    button.classList.add('is-busy');
    button.setAttribute('aria-busy', 'true');

    if (busyLabel) {
      button.textContent = busyLabel;
    }

    if ('disabled' in button) {
      button.disabled = true;
    }

    return;
  }

  button.classList.remove('is-busy');
  button.setAttribute('aria-busy', 'false');

  if (!button.dataset.originalHtml && !button.dataset.originalDisabled) {
    return;
  }

  if (button.dataset.originalHtml) {
    button.innerHTML = button.dataset.originalHtml;
    delete button.dataset.originalHtml;
  }

  if ('disabled' in button) {
    button.disabled = button.dataset.originalDisabled === '1';
    delete button.dataset.originalDisabled;
  }
}

export function setBusyState(element, isBusy) {
  if (!(element instanceof HTMLElement)) {
    return;
  }

  element.classList.toggle('is-busy', isBusy);
  element.setAttribute('aria-busy', isBusy ? 'true' : 'false');
}

export function initTooltips(scope = document) {
  if (!window.bootstrap?.Tooltip) {
    return;
  }

  const useTouchTrigger = window.matchMedia('(hover: none), (pointer: coarse)').matches;

  Array.from(scope.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach((element) => {
    window.bootstrap.Tooltip.getOrCreateInstance(element, {
      container: 'body',
      trigger: useTouchTrigger ? 'click focus' : 'hover focus',
    });
  });
}

export function initMobileNav() {
  const navCollapse = document.getElementById('navHome');
  const navToggler = document.querySelector('.navbar-toggler');

  if (!navCollapse || !navToggler || !window.bootstrap?.Collapse) {
    return;
  }

  const collapse = window.bootstrap.Collapse.getOrCreateInstance(navCollapse, {
    toggle: false,
  });

  const isMobileNav = () => window.getComputedStyle(navToggler).display !== 'none';
  let resizeFrame = 0;
  const syncBodyState = () => {
    if (resizeFrame) {
      window.cancelAnimationFrame(resizeFrame);
    }

    resizeFrame = window.requestAnimationFrame(() => {
      document.body.classList.toggle('nav-open', isMobileNav() && navCollapse.classList.contains('show'));
    });
  };

  navCollapse.addEventListener('shown.bs.collapse', syncBodyState);
  navCollapse.addEventListener('hidden.bs.collapse', syncBodyState);
  window.addEventListener('resize', syncBodyState, { passive: true });

  Array.from(navCollapse.querySelectorAll('a[href]')).forEach((link) => {
    link.addEventListener('click', () => {
      if (isMobileNav() && navCollapse.classList.contains('show')) {
        collapse.hide();
      }
    });
  });

  syncBodyState();
}

export function initConfirmableSubmissions() {
  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
      return;
    }

    const message = form.dataset.confirmSubmit;

    if (!message) {
      return;
    }

    if (!window.confirm(message)) {
      event.preventDefault();
    }
  });
}

export function initValidatedForms() {
  const focusFirstInvalidField = (form) => {
    const invalidField = Array.from(form.querySelectorAll('input, select, textarea'))
      .find((field) => {
        if (!(field instanceof HTMLElement) || field.matches('[type="hidden"], [aria-hidden="true"]')) {
          return false;
        }

        if ('disabled' in field && field.disabled) {
          return false;
        }

        return field.matches(':invalid, .is-invalid');
      });

    if (!invalidField) {
      return;
    }

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    invalidField.scrollIntoView({ block: 'center', behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    invalidField.focus({ preventScroll: true });
  };

  document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-validate-form]')) {
      return;
    }

    form.classList.add('was-validated');

    if (!form.checkValidity()) {
      event.preventDefault();
      event.stopPropagation();
      focusFirstInvalidField(form);
    }
  });
}

export function applyResponsiveTableLabels(scope = document) {
  const tables = Array.from(scope.querySelectorAll('table.ggwp-data-table--stacked'));

  tables.forEach((table) => {
    if (!(table instanceof HTMLTableElement)) {
      return;
    }

    const headers = Array.from(table.querySelectorAll('thead th')).map((header) => (
      header.textContent?.replace(/\s+/g, ' ').trim() || ''
    ));

    if (!headers.length) {
      return;
    }

    Array.from(table.querySelectorAll('tbody tr')).forEach((row) => {
      const cells = Array.from(row.children).filter((cell) => cell instanceof HTMLTableCellElement);

      cells.forEach((cell, index) => {
        if (!(cell instanceof HTMLTableCellElement) || cell.colSpan > 1 || cell.hasAttribute('data-label')) {
          return;
        }

        const label = headers[index] || '';

        if (label) {
          cell.setAttribute('data-label', label);
        }
      });
    });
  });
}

export function initResponsiveTableRegions(scope = document) {
  applyResponsiveTableLabels(scope);

  const wrappers = Array.from(scope.querySelectorAll('.table-responsive'))
    .filter((wrapper) => wrapper instanceof HTMLElement && wrapper.querySelector('table'));

  if (!wrappers.length) {
    return;
  }

  const resolveLabel = (wrapper) => {
    const caption = wrapper.querySelector('caption');
    const captionText = caption?.textContent?.trim();

    if (captionText) {
      return captionText;
    }

    const cardBody = wrapper.closest('.card-body, section, main, .ggwp-page-shell');
    const heading = cardBody?.querySelector('h1, h2, h3, h4, h5, h6');
    const headingText = heading?.textContent?.trim();

    return headingText ? `${headingText} table` : 'Scrollable data table';
  };

  const syncWrapper = (wrapper) => {
    const isScrollable = wrapper.scrollWidth > wrapper.clientWidth + 1;

    if (!isScrollable) {
      if (wrapper.dataset.ggwpA11yTableRegion === '1') {
        wrapper.removeAttribute('tabindex');
        wrapper.removeAttribute('role');
        wrapper.removeAttribute('aria-label');
        delete wrapper.dataset.ggwpA11yTableRegion;
      }

      return;
    }

    if (!wrapper.hasAttribute('tabindex')) {
      wrapper.setAttribute('tabindex', '0');
    }

    if (!wrapper.hasAttribute('role')) {
      wrapper.setAttribute('role', 'region');
    }

    if (!wrapper.hasAttribute('aria-label') && !wrapper.hasAttribute('aria-labelledby')) {
      wrapper.setAttribute('aria-label', resolveLabel(wrapper));
    }

    wrapper.dataset.ggwpA11yTableRegion = '1';
  };

  const syncAll = () => {
    window.requestAnimationFrame(() => {
      applyResponsiveTableLabels(scope);
      wrappers.forEach(syncWrapper);
    });
  };

  syncAll();
  window.addEventListener('resize', syncAll, { passive: true });
}

export function initAutoUploadForms() {
  Array.from(document.querySelectorAll('[data-auto-upload-form]')).forEach((form) => {
    const fileInput = form.querySelector('[data-file-input]');
    const trigger = form.querySelector('[data-file-trigger]');
    const feedback = form.querySelector('[data-file-feedback]');
    const maxBytes = Number(form.getAttribute('data-max-bytes') || 4194304);
    let isSubmitting = false;
    const allowedTypes = String(fileInput?.getAttribute('accept') || '')
      .split(',')
      .map((entry) => entry.trim().toLowerCase())
      .filter(Boolean);

    if (!(fileInput instanceof HTMLInputElement) || !(trigger instanceof HTMLElement)) {
      return;
    }

    const setFeedback = (message = '') => {
      if (!feedback) {
        return;
      }

      feedback.textContent = message;
      feedback.classList.toggle('d-none', message === '');
    };

    const fileIsAllowed = (file) => {
      if (!file) {
        return false;
      }

      if (!allowedTypes.length) {
        return true;
      }

      const extension = `.${(file.name.split('.').pop() || '').toLowerCase()}`;
      const mimeType = String(file.type || '').toLowerCase();

      return allowedTypes.includes(mimeType) || allowedTypes.includes(extension);
    };

    trigger.addEventListener('click', () => {
      fileInput.click();
    });

    fileInput.addEventListener('change', () => {
      const file = fileInput.files?.[0];

      if (!file) {
        return;
      }

      if (!fileIsAllowed(file)) {
        fileInput.value = '';
        setFeedback('Choose a JPG, PNG, or WEBP image.');
        return;
      }

      if (Number.isFinite(maxBytes) && file.size > maxBytes) {
        fileInput.value = '';
        setFeedback('Choose an image smaller than 4MB.');
        return;
      }

      setFeedback('');

      if (isSubmitting) {
        return;
      }

      isSubmitting = true;

      if (trigger instanceof HTMLButtonElement) {
        setButtonBusy(trigger, true, 'Uploading...');
      }

      fileInput.disabled = true;

      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
        return;
      }

      form.submit();
    });
  });
}

export function initResponsiveCarouselLayout(scope = document) {
  const carousels = Array.from(scope.querySelectorAll('.ggwp-blog-carousel'));

  if (!carousels.length) {
    return;
  }

  const measureCarousel = (carousel) => {
    const inner = carousel.querySelector('.carousel-inner');
    const items = Array.from(carousel.querySelectorAll('.carousel-item'));
    const cardSelector = carousel.dataset.cardSelector || '.ggwp-home-blog-card';
    const cards = Array.from(carousel.querySelectorAll(cardSelector));

    if (!inner || !items.length || !cards.length) {
      return;
    }

    cards.forEach((card) => {
      card.style.height = '';
    });

    const maxCardHeight = cards.reduce((largest, card) => {
      return Math.max(largest, card.offsetHeight);
    }, 0);

    if (maxCardHeight > 0) {
      cards.forEach((card) => {
        card.style.height = `${maxCardHeight}px`;
      });
    }

    const snapshots = items.map((item) => ({
      element: item,
      style: item.getAttribute('style'),
      active: item.classList.contains('active'),
    }));

    let maxHeight = 0;

    items.forEach((item) => {
      item.classList.add('active');
      item.style.display = 'block';
      item.style.position = 'absolute';
      item.style.inset = '0';
      item.style.visibility = 'hidden';
      item.style.pointerEvents = 'none';
    });

    maxHeight = items.reduce((largest, item) => {
      return Math.max(largest, item.offsetHeight);
    }, 0);

    snapshots.forEach(({ element, style, active }) => {
      if (!active) {
        element.classList.remove('active');
      }

      if (style === null) {
        element.removeAttribute('style');
      } else {
        element.setAttribute('style', style);
      }
    });

    inner.style.minHeight = maxHeight > 0 ? `${maxHeight}px` : '';
  };

  const scheduleMeasure = (carousel) => {
    window.requestAnimationFrame(() => measureCarousel(carousel));
  };

  carousels.forEach((carousel) => {
    scheduleMeasure(carousel);

    if (document.fonts?.ready) {
      document.fonts.ready.then(() => scheduleMeasure(carousel));
    }

    window.addEventListener('resize', () => scheduleMeasure(carousel));

    if (window.bootstrap?.Carousel) {
      carousel.addEventListener('slid.bs.carousel', () => scheduleMeasure(carousel));
    }
  });
}
