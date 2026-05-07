function parseItems(root) {
  const script = root.querySelector('[data-public-social-proof-items]');

  if (!script) {
    return [];
  }

  try {
    const parsed = JSON.parse(script.textContent || '[]');

    if (!Array.isArray(parsed)) {
      return [];
    }

    return parsed.filter((item) => item && typeof item === 'object');
  } catch {
    return [];
  }
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

const DEFAULT_TIMINGS = {
  initialDelay: 7000,
  displayDuration: 8000,
  hiddenDuration: 25000,
};

const COMPACT_TIMINGS = {
  initialDelay: 12000,
  displayDuration: 5200,
  hiddenDuration: 32000,
};

function formatRelativeTime(timestamp, fallback = '') {
  const date = new Date(timestamp);

  if (Number.isNaN(date.getTime())) {
    return fallback;
  }

  const diffMs = Math.max(0, Date.now() - date.getTime());
  const minutes = Math.max(1, Math.floor(diffMs / 60000));

  if (minutes < 60) {
    return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
  }

  const hours = Math.floor(minutes / 60);

  if (hours < 48) {
    return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
  }

  const days = Math.floor(hours / 24);
  return `${days} ${days === 1 ? 'day' : 'days'} ago`;
}

function signatureFor(item) {
  return [
    item.id || '',
    item.customer || '',
    item.service || '',
    item.from?.label || '',
    item.to?.label || '',
    item.goal || '',
  ].join('|');
}

function chooseNextIndex(items, currentIndex) {
  if (items.length <= 1) {
    return currentIndex;
  }

  for (let attempt = 0; attempt < 12; attempt += 1) {
    const nextIndex = randomInt(0, items.length - 1);

    if (nextIndex !== currentIndex) {
      return nextIndex;
    }
  }

  return (currentIndex + 1) % items.length;
}

function renderItem(root, item) {
  const avatar = root.querySelector('[data-public-social-proof-avatar]');
  const name = root.querySelector('[data-public-social-proof-name]');
  const service = root.querySelector('[data-public-social-proof-service]');
  const time = root.querySelector('[data-public-social-proof-time]');
  const from = root.querySelector('[data-public-social-proof-from]');
  const fromIcon = root.querySelector('[data-public-social-proof-from-icon]');
  const targetLabel = root.querySelector('[data-public-social-proof-target-label]');
  const to = root.querySelector('[data-public-social-proof-to]');
  const toIcon = root.querySelector('[data-public-social-proof-to-icon]');

  if (avatar) {
    avatar.textContent = item.initials || 'C';
  }

  if (name) {
    name.textContent = item.customer || 'Customer';
  }

  if (service) {
    service.textContent = item.service || 'Rank Boosting';
  }

  if (time) {
    time.textContent = formatRelativeTime(item.occurredAt, item.timeLabel || '');
  }

  if (from) {
    from.textContent = item.from?.label || 'Unranked';
  }

  if (fromIcon) {
    fromIcon.src = item.from?.icon || '';
    fromIcon.alt = `${item.from?.label || 'Current rank'} icon`;
  }

  if (targetLabel) {
    targetLabel.textContent = item.to ? 'Desired' : 'Goal';
  }

  if (to) {
    to.textContent = item.to?.label || item.goal || 'Progress target';
  }

  if (toIcon) {
    if (item.to?.icon) {
      toIcon.hidden = false;
      toIcon.src = item.to.icon;
      toIcon.alt = `${item.to.label || 'Desired rank'} icon`;
    } else {
      toIcon.hidden = true;
      toIcon.removeAttribute('src');
      toIcon.alt = '';
    }
  }
}

export function initPublicSocialProof() {
  const root = document.querySelector('[data-public-social-proof]');
  const compactToggle = root?.querySelector('[data-public-social-proof-toggle]');
  const compactToggleIcon = root?.querySelector('[data-public-social-proof-toggle-icon]');

  if (!root) {
    return;
  }

  const items = parseItems(root);

  if (!items.length) {
    root.remove();
    return;
  }

  let currentIndex = 0;
  let cycleTimer = null;
  let swapTimer = null;
  let revealTimer = null;
  let lastSignature = signatureFor(items[0]);
  let isCollapsed = false;

  const compactViewportQuery = window.matchMedia('(max-width: 1024px)');
  const coarsePointerQuery = window.matchMedia('(hover: none), (pointer: coarse)');
  const shouldUseCompactMode = () => compactViewportQuery.matches || coarsePointerQuery.matches;
  const getTimings = () => (shouldUseCompactMode() ? COMPACT_TIMINGS : DEFAULT_TIMINGS);

  const clearTimers = () => {
    if (cycleTimer) {
      window.clearTimeout(cycleTimer);
      cycleTimer = null;
    }

    if (swapTimer) {
      window.clearTimeout(swapTimer);
      swapTimer = null;
    }

    if (revealTimer) {
      window.clearTimeout(revealTimer);
      revealTimer = null;
    }
  };

  const syncToggleUi = () => {
    if (!compactToggle) {
      return;
    }

    compactToggle.setAttribute('aria-expanded', String(!isCollapsed));
    compactToggle.setAttribute('aria-label', isCollapsed ? 'Expand recent orders widget' : 'Collapse recent orders widget');

    if (compactToggleIcon) {
      compactToggleIcon.textContent = isCollapsed ? '+' : '−';
    }
  };

  const setCollapsed = (nextState) => {
    isCollapsed = Boolean(nextState) && shouldUseCompactMode();
    root.classList.toggle('is-collapsed', isCollapsed);
    syncToggleUi();
  };

  const scheduleNext = () => {
    const { hiddenDuration } = getTimings();

    if (shouldUseCompactMode()) {
      setCollapsed(true);
    } else {
      root.classList.remove('is-visible');
    }

    swapTimer = window.setTimeout(() => {
      let nextIndex = chooseNextIndex(items, currentIndex);
      const currentSignature = lastSignature;

      if (items.length > 1) {
        for (let attempt = 0; attempt < items.length; attempt += 1) {
          const candidate = items[nextIndex];

          if (signatureFor(candidate) !== currentSignature) {
            break;
          }

          nextIndex = (nextIndex + 1) % items.length;
        }
      }

      currentIndex = nextIndex;
      const nextItem = items[currentIndex];

      renderItem(root, nextItem);
      lastSignature = signatureFor(nextItem);
      showCurrentItem();
    }, hiddenDuration);
  };

  const showCurrentItem = (delay = 0) => {
    const { displayDuration } = getTimings();

    clearTimers();

    revealTimer = window.setTimeout(() => {
      root.classList.add('is-visible');
      setCollapsed(false);
      cycleTimer = window.setTimeout(scheduleNext, displayDuration);
    }, delay);
  };

  renderItem(root, items[currentIndex]);
  syncToggleUi();

  showCurrentItem(getTimings().initialDelay);

  if (compactToggle) {
    compactToggle.addEventListener('click', () => {
      if (isCollapsed) {
        showCurrentItem();
        return;
      }

      scheduleNext();
    });
  }

  const handleViewportChange = () => {
    if (!shouldUseCompactMode()) {
      if (isCollapsed) {
        root.classList.remove('is-visible');
      }

      setCollapsed(false);
    }
  };

  compactViewportQuery.addEventListener('change', handleViewportChange);
  coarsePointerQuery.addEventListener('change', handleViewportChange);

  window.addEventListener('pagehide', () => {
    clearTimers();
    compactViewportQuery.removeEventListener('change', handleViewportChange);
    coarsePointerQuery.removeEventListener('change', handleViewportChange);
  }, { once: true });
}
