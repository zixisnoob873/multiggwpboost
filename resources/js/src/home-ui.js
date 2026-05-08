import { extractApiErrorMessage, requestJson, setButtonBusy } from './common';
import { byId, on, query, queryAll } from './dom';

function setupTabs() {
  queryAll('[data-tabs]').forEach((group) => {
    const buttons = queryAll('.tab-btn', group);
    const panels = queryAll('.tab-panel', group);

    buttons.forEach((button) => {
      on(button, 'click', () => {
        const id = button.getAttribute('data-target');
        buttons.forEach((entry) => entry.classList.remove('active'));
        panels.forEach((panel) => panel.classList.remove('active'));
        button.classList.add('active');
        query(`#${id}`, group)?.classList.add('active');
      });
    });
  });
}

function setupDivisionGuards() {
  const lockedDesiredValues = {
    homeRadiantDesiredDivision: 'Radiant',
  };

  [
    ['homeBoostCurrentDivision', 'homeBoostDesiredDivision'],
    ['homeRadiantCurrentDivision', 'homeRadiantDesiredDivision'],
  ].forEach(([currentId, desiredId]) => {
    const currentSelect = query(`#${currentId}`);
    const desiredSelect = query(`#${desiredId}`);
    if (!currentSelect || !desiredSelect) {
      return;
    }

    let enforcing = false;

    const enforceDesiredNotLower = () => {
      if (enforcing) {
        return;
      }

      enforcing = true;
      const lockedValue = lockedDesiredValues[desiredId];

      if (lockedValue) {
        const lockedOption = Array.from(desiredSelect.options).find((option) => option.value === lockedValue);
        if (lockedOption) {
          desiredSelect.value = lockedValue;
        }

        desiredSelect.disabled = true;
        desiredSelect.dispatchEvent(new Event('change'));
        enforcing = false;
        return;
      }

      const currentIndex = currentSelect.selectedIndex;
      Array.from(desiredSelect.options).forEach((option, index) => {
        option.disabled = index <= currentIndex;
      });

      if (desiredSelect.selectedIndex <= currentIndex) {
        const nextIndex = Array.from(desiredSelect.options).findIndex((option, index) => index > currentIndex && !option.disabled);
        desiredSelect.selectedIndex = nextIndex >= 0 ? nextIndex : currentIndex;
        desiredSelect.dispatchEvent(new Event('change'));
      }

      enforcing = false;
    };

    on(currentSelect, 'change', enforceDesiredNotLower);
    enforceDesiredNotLower();
  });
}

function setupHeroPromoSlider() {
  const slider = query('#heroPromoCarousel');

  if (!slider || !window.bootstrap?.Carousel) {
    return;
  }

  const carousel = window.bootstrap.Carousel.getOrCreateInstance(slider, {
    interval: 4200,
    pause: 'hover',
    ride: 'carousel',
    wrap: true,
    touch: true,
  });

  const toggle = query('[data-hero-promo-toggle]', slider);

  if (!toggle) {
    return;
  }

  let paused = false;
  const syncToggle = () => {
    toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
    toggle.setAttribute('aria-label', paused ? 'Play promotion carousel' : 'Pause promotion carousel');
    toggle.textContent = paused ? 'Play' : 'Pause';
  };

  toggle.addEventListener('click', () => {
    paused = !paused;

    if (paused) {
      carousel.pause();
    } else {
      carousel.cycle();
    }

    syncToggle();
  });

  syncToggle();
}

function renderFaqAccordion(items) {
  const accordion = query('#faqAccordion[data-faq-remote]');
  if (!accordion || !Array.isArray(items) || !items.length) {
    return;
  }

  accordion.replaceChildren();

  items.forEach((item, index) => {
    const faqId = `faqItem${index + 1}`;
    const headingId = `${faqId}Heading`;
    const expanded = index === 0;
    const accordionItem = document.createElement('div');
    const header = document.createElement('h3');
    const button = document.createElement('button');
    const buttonText = document.createElement('span');
    const collapse = document.createElement('div');
    const body = document.createElement('div');

    accordionItem.className = 'accordion-item ggwp-accordion-item';
    header.className = 'accordion-header';
    header.id = headingId;
    button.className = `accordion-button${expanded ? '' : ' collapsed'}`;
    button.type = 'button';
    button.setAttribute('data-bs-toggle', 'collapse');
    button.setAttribute('data-bs-target', `#${faqId}`);
    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    button.setAttribute('aria-controls', faqId);

    buttonText.className = 'ggwp-accordion-question';
    buttonText.textContent = item.question || 'Question';
    button.appendChild(buttonText);

    collapse.id = faqId;
    collapse.className = `accordion-collapse collapse${expanded ? ' show' : ''}`;
    collapse.setAttribute('data-bs-parent', '#faqAccordion');
    collapse.setAttribute('aria-labelledby', headingId);

    body.className = 'accordion-body text-secondary';
    body.textContent = item.answer || '';

    header.appendChild(button);
    collapse.appendChild(body);
    accordionItem.appendChild(header);
    accordionItem.appendChild(collapse);
    accordion.appendChild(accordionItem);
  });
}

async function setupFaqAccordion() {
  const accordion = query('#faqAccordion[data-faq-remote]');
  const loadingState = query('[data-faq-loading]');
  const errorBox = query('[data-faq-error]');
  const errorMessage = query('[data-faq-error-message]');
  const retryButton = query('[data-faq-retry]');

  if (!accordion) {
    return;
  }

  const setLoading = (isLoading) => {
    loadingState?.classList.toggle('d-none', !isLoading);
  };

  const setError = (message = '') => {
    if (errorMessage) {
      errorMessage.textContent = message || 'Could not load FAQs right now.';
    }

    errorBox?.classList.toggle('d-none', !message);
  };

  const showFallback = (message) => {
    const accordionItem = document.createElement('div');
    accordionItem.className = 'accordion-item';

    const body = document.createElement('div');
    body.className = 'accordion-body text-secondary';
    body.textContent = message;

    accordionItem.appendChild(body);
    accordion.replaceChildren(accordionItem);
    accordion.classList.remove('d-none');
  };

  const loadFaqs = async () => {
    const gameSlug = window.ggwpProductConfig?.gameSlug || window.appState?.gameSlug || 'valorant';
    const faqUrl = `/api/faqs?game=${encodeURIComponent(gameSlug)}`;

    setLoading(true);
    setError('');
    accordion.classList.add('d-none');
    setButtonBusy(retryButton, true, 'Retrying...');

    try {
      const { response, data } = await requestJson(faqUrl, {
        retries: 1,
        retryStatuses: [429, 502, 503, 504],
        fetchOptions: {
          cache: 'no-store',
        },
      });

      if (!response.ok) {
        throw new Error(extractApiErrorMessage(data, 'Could not load FAQs right now.'));
      }

      const items = Array.isArray(data.faqs) ? data.faqs : [];

      if (!items.length) {
        showFallback('FAQs will appear here shortly.');
        return;
      }

      renderFaqAccordion(items);
      accordion.classList.remove('d-none');
    } catch (error) {
      setError(error instanceof Error ? error.message : 'Could not load FAQs right now.');
      showFallback('FAQs are temporarily unavailable. Please try again in a moment.');
    } finally {
      setLoading(false);
      setButtonBusy(retryButton, false);
    }
  };

  retryButton?.addEventListener('click', () => {
    void loadFaqs();
  });

  await loadFaqs();
}

function setupServiceDeepLink() {
  const service = (new URLSearchParams(window.location.search).get('service') || '').toLowerCase().trim();
  if (!service) {
    return;
  }

  const tabId = {
    boosting: 'tab-boosting',
    placement: 'tab-placement',
    radiant: 'tab-radiant',
    ranked: 'tab-ranked',
  }[service];

  if (!tabId) {
    return;
  }

  const button = byId(tabId);
  if (!button || typeof bootstrap === 'undefined') {
    return;
  }

  try {
    bootstrap.Tab.getOrCreateInstance(button).show();
  } catch (_) {}

  const anchor = byId('servicesTab');
  if (anchor) {
    setTimeout(() => {
      anchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 50);
  }
}

export function initHomeUi() {
  setupTabs();
  setupDivisionGuards();
  setupServiceDeepLink();
  setupHeroPromoSlider();
  setupFaqAccordion();
}
