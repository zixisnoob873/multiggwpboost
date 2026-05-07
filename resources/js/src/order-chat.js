import { setButtonBusy, sleep } from './common';

function clamp(value, min, max) {
  return Math.min(max, Math.max(min, value));
}

function setRankIcons(root = document) {
  root.querySelectorAll('[data-rank-bind]').forEach((image) => {
    const key = image.getAttribute('data-rank-bind');
    const scope = image.closest('[data-rank-scope]') || root;
    const source = key ? scope.querySelector(`[data-order-bind="${key}"]`) : null;
    const rankName = (source?.textContent || '').trim() || 'Unranked';
    const normalized = window.normalizeDivisionName ? window.normalizeDivisionName(rankName) : 'unranked';
    const fallbackIcon = image.getAttribute('data-rank-fallback-src') || '';
    const icon = window.valorantDivisionIcons?.[normalized] || window.valorantDivisionIcons?.unranked || fallbackIcon;

    if (icon) {
      image.setAttribute('src', icon);
    }

    image.setAttribute('alt', `${rankName} icon`);
  });
}

function initProgressForms(app) {
  app.querySelectorAll('[data-progress-form]').forEach((form) => {
    const rangeInput = form.querySelector('[data-progress-range]');
    const displayInput = form.querySelector('[data-progress-display]');

    if (!rangeInput || !displayInput) {
      return;
    }

    const syncFromRange = () => {
      displayInput.value = rangeInput.value;
    };

    const syncFromDisplay = () => {
      const parsedValue = Number(displayInput.value || 0);
      const nextValue = Number.isFinite(parsedValue) ? clamp(parsedValue, 0, 100) : 0;
      displayInput.value = String(nextValue);
      rangeInput.value = String(nextValue);
    };

    rangeInput.addEventListener('input', syncFromRange);
    displayInput.addEventListener('input', syncFromDisplay);
    syncFromRange();
  });
}

function safeStorageGet(key) {
  try {
    return window.localStorage.getItem(key);
  } catch {
    return null;
  }
}

function safeStorageSet(key, value) {
  try {
    window.localStorage.setItem(key, value);
  } catch {
    // Ignore storage failures and keep the in-memory state only.
  }
}

function normalizeMessage(rawMessage) {
  const messageId = Number(rawMessage?.id);

  if (!Number.isFinite(messageId) || messageId < 1) {
    return null;
  }

  return {
    id: messageId,
    threadType: String(rawMessage?.thread_type || ''),
    body: String(rawMessage?.body || ''),
    sender: {
      id: rawMessage?.sender?.id == null ? null : Number(rawMessage.sender.id),
      name: String(rawMessage?.sender?.name || 'Unknown User'),
      role: String(rawMessage?.sender?.role || 'system'),
    },
    createdAt: String(rawMessage?.created_at || ''),
    createdAtLabel: String(rawMessage?.created_at_label || ''),
  };
}

function mergeMessages(existingMessages, incomingMessages) {
  const unique = new Map();

  [...existingMessages, ...incomingMessages]
    .filter(Boolean)
    .forEach((message) => {
      unique.set(message.id, message);
    });

  return Array.from(unique.values()).sort((left, right) => left.id - right.id);
}

function roleBubbleClass(role) {
  switch (String(role || '').toLowerCase()) {
    case 'customer':
      return 'customer';
    case 'booster':
      return 'booster';
    case 'super_admin':
      return 'admin';
    default:
      return 'admin';
  }
}

function buildMessageNode(message, currentUserId) {
  const isOwn = currentUserId > 0 && Number(message.sender.id) === currentUserId;
  const roleClass = roleBubbleClass(message.sender.role);
  const wrapper = document.createElement('article');
  wrapper.className = `ggwp-message ${isOwn ? 'ggwp-message--right' : 'ggwp-message--left'} ggwp-message--${roleClass}`;
  wrapper.setAttribute('data-chat-message-id', String(message.id));

  const bubble = document.createElement('div');
  bubble.className = 'ggwp-message-bubble';

  const head = document.createElement('div');
  head.className = 'ggwp-message-head';

  const author = document.createElement('div');
  author.className = 'ggwp-message-author';
  author.textContent = isOwn ? 'You' : message.sender.name;

  const time = document.createElement('div');
  time.className = 'ggwp-message-time';
  time.textContent = message.createdAtLabel || 'Just now';

  head.appendChild(author);
  head.appendChild(time);

  const body = document.createElement('div');
  body.className = 'ggwp-message-body';
  body.textContent = message.body;

  bubble.appendChild(head);
  bubble.appendChild(body);
  wrapper.appendChild(bubble);

  return wrapper;
}

function parseThreads(app) {
  return Array.from(app.querySelectorAll('[data-chat-channel]')).map((tab) => ({
    key: tab.getAttribute('data-chat-channel') || '',
    label: tab.getAttribute('data-chat-channel-label') || tab.textContent.trim() || 'Conversation',
    titleLabel: tab.getAttribute('data-chat-channel-title-label') || tab.getAttribute('data-chat-channel-label') || tab.textContent.trim() || 'Conversation',
    hint: tab.getAttribute('data-chat-channel-hint') || '',
    stateLabel: tab.getAttribute('data-chat-channel-state') || 'Ready',
    channelName: tab.getAttribute('data-chat-channel-name') || '',
    historyUrl: tab.getAttribute('data-chat-history-url') || '',
    sendUrl: tab.getAttribute('data-chat-send-url') || '',
    canSend: tab.getAttribute('data-chat-can-send') === '1',
    emptyTitle: tab.getAttribute('data-chat-empty-title') || 'No Conversation yet.',
    emptyCopy: tab.getAttribute('data-chat-empty-copy') || '',
    tab,
    messages: [],
    hasLoaded: false,
    hasMore: false,
    nextCursor: null,
    isLoading: false,
  }));
}

function extractErrorMessage(error, fallbackMessage) {
  const responseMessage = error?.response?.data?.message;
  const responseErrors = error?.response?.data?.errors;

  if (typeof responseMessage === 'string' && responseMessage.trim() !== '') {
    return responseMessage;
  }

  if (responseErrors && typeof responseErrors === 'object') {
    const firstField = Object.keys(responseErrors)[0];
    const firstMessage = Array.isArray(responseErrors[firstField]) ? responseErrors[firstField][0] : null;

    if (typeof firstMessage === 'string' && firstMessage.trim() !== '') {
      return firstMessage;
    }
  }

  return fallbackMessage;
}

export function initOrderChatPage() {
  const app = document.querySelector('[data-order-chat-app]');

  if (!app) {
    return;
  }

  const currentUserId = Number(app.getAttribute('data-chat-user-id') || 0);
  const feed = app.querySelector('[data-chat-feed]');
  const emptyState = app.querySelector('[data-chat-empty]');
  const emptyTitle = app.querySelector('[data-chat-empty-title]');
  const emptyCopy = app.querySelector('[data-chat-empty-copy]');
  const composeForm = app.querySelector('[data-chat-compose-form]');
  const composeTextarea = composeForm?.querySelector('textarea[name="body"]');
  const sendButton = app.querySelector('[data-chat-send-button]');
  const alertBox = app.querySelector('[data-chat-alert]');
  const title = app.querySelector('[data-chat-channel-title]');
  const hint = app.querySelector('[data-chat-channel-hint]');
  const state = app.querySelector('[data-chat-channel-state]');
  const composeNotice = app.querySelector('[data-compose-notice]');
  const lastSync = app.querySelector('[data-chat-last-sync]');
  const loadOlderWrap = app.querySelector('[data-chat-load-older-wrap]');
  const loadOlderButton = app.querySelector('[data-chat-load-older]');
  const orderIdButtons = Array.from(app.querySelectorAll('[data-copy-order]'));
  const openModalId = (app.getAttribute('data-open-modal') || '').trim();
  const storageKey = `ggwp-order-workspace:${window.location.pathname}:channel`;
  const threads = parseThreads(app);
  const threadsByKey = new Map(threads.map((thread) => [thread.key, thread]));
  const defaultChannel = threads.find((thread) => thread.tab.classList.contains('active'))?.key || threads[0]?.key || '';
  const storedChannel = safeStorageGet(storageKey);
  let currentChannel = threadsByKey.has(storedChannel) ? storedChannel : defaultChannel;
  const subscriptions = new Set();

  const setAlert = (message, tone = 'info') => {
    if (!alertBox) {
      return;
    }

    if (!message) {
      alertBox.className = 'alert alert-info mb-0 d-none';
      alertBox.textContent = '';
      return;
    }

    alertBox.className = `alert alert-${tone} mb-0`;
    alertBox.textContent = message;
  };

  const setLastSync = (value) => {
    if (lastSync) {
      lastSync.textContent = value;
    }
  };

  const resizeComposeTextarea = () => {
    if (!composeTextarea) {
      return;
    }

    composeTextarea.style.height = 'auto';

    const computedStyles = window.getComputedStyle(composeTextarea);
    const minHeight = Number.parseFloat(computedStyles.minHeight) || 56;
    const maxHeight = Number.parseFloat(computedStyles.maxHeight) || 176;
    const nextHeight = Math.min(Math.max(composeTextarea.scrollHeight, minHeight), maxHeight);

    composeTextarea.style.height = `${nextHeight}px`;
    composeTextarea.style.overflowY = composeTextarea.scrollHeight > maxHeight ? 'auto' : 'hidden';
  };

  const currentThread = () => threadsByKey.get(currentChannel) || threads[0] || null;

  const renderFeed = (thread, scrollMetrics = null) => {
    if (!feed || !thread) {
      return;
    }

    feed.innerHTML = '';
    thread.messages.forEach((message) => {
      feed.appendChild(buildMessageNode(message, currentUserId));
    });

    const isEmpty = thread.messages.length === 0;
    feed.classList.toggle('d-none', isEmpty);
    emptyState?.classList.toggle('d-none', !isEmpty);
    loadOlderWrap?.classList.toggle('d-none', !thread.hasMore);

    if (loadOlderButton) {
      loadOlderButton.disabled = thread.isLoading || !thread.hasMore;
    }

    if (scrollMetrics) {
      feed.scrollTop = Math.max(0, feed.scrollHeight - scrollMetrics.height + scrollMetrics.top);
      return;
    }

    feed.scrollTop = feed.scrollHeight;
  };

  const updateComposeState = (thread) => {
    if (!composeTextarea || !sendButton) {
      return;
    }

    composeTextarea.disabled = !thread?.canSend;
    composeTextarea.setAttribute('aria-disabled', thread?.canSend ? 'false' : 'true');
    composeTextarea.placeholder = thread?.canSend
      ? 'Type a message...'
      : 'This thread is read only for your role.';

    sendButton.disabled = !thread?.canSend;
    sendButton.setAttribute('aria-disabled', thread?.canSend ? 'false' : 'true');

    if (composeNotice) {
      composeNotice.textContent = '';
    }

    resizeComposeTextarea();
  };

  const updateChannelUi = () => {
    const thread = currentThread();

    threads.forEach((entry) => {
      entry.tab.classList.toggle('active', entry.key === currentChannel);
    });

    if (!thread) {
      return;
    }

    if (title) {
      title.textContent = thread.titleLabel;
    }

    if (hint) {
      hint.textContent = thread.hint;
    }

    if (state) {
      state.textContent = thread.stateLabel;
    }

    if (emptyTitle) {
      emptyTitle.textContent = thread.emptyTitle;
    }

    if (emptyCopy) {
      emptyCopy.textContent = thread.emptyCopy;
      emptyCopy.classList.toggle('d-none', !thread.emptyCopy);
    }

    updateComposeState(thread);
    safeStorageSet(storageKey, currentChannel || '');
  };

  const loadThreadHistory = async (thread, options = {}) => {
    const appendOlder = Boolean(options.appendOlder);
    const before = options.before ?? null;

    if (!thread || thread.isLoading || !thread.historyUrl) {
      return;
    }

    const scrollMetrics = appendOlder && feed
      ? { height: feed.scrollHeight, top: feed.scrollTop }
      : null;

    thread.isLoading = true;
    setLastSync(appendOlder ? 'Loading earlier messages...' : 'Loading messages...');

    if (loadOlderButton && currentChannel === thread.key) {
      setButtonBusy(loadOlderButton, true, 'Loading...');
    }

    try {
      let attempt = 0;
      let response = null;

      while (attempt < 2) {
        try {
          response = await window.axios.get(thread.historyUrl, {
            params: {
              limit: 25,
              before,
            },
          });
          break;
        } catch (error) {
          const status = Number(error?.response?.status || 0);
          const shouldRetry = attempt === 0 && (status === 429 || status >= 500 || status === 0);

          if (!shouldRetry) {
            throw error;
          }

          attempt += 1;
          setAlert('Connection hiccup. Retrying messages...', 'warning');
          await sleep(500);
        }
      }

      const incomingMessages = Array.isArray(response?.data?.messages)
        ? response.data.messages.map(normalizeMessage).filter(Boolean)
        : [];

      thread.messages = appendOlder
        ? mergeMessages(incomingMessages, thread.messages)
        : mergeMessages([], incomingMessages);
      thread.hasLoaded = true;
      thread.hasMore = Boolean(response?.data?.meta?.has_more);
      thread.nextCursor = response?.data?.meta?.next_cursor ?? null;

      if (currentChannel === thread.key) {
        renderFeed(thread, scrollMetrics);
      }

      setLastSync(new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
      setAlert('', 'info');
    } catch (error) {
      setLastSync('Connection issue');
      setAlert(extractErrorMessage(error, 'Could not load chat history right now.'), 'warning');
    } finally {
      thread.isLoading = false;

      if (loadOlderButton && currentChannel === thread.key) {
        setButtonBusy(loadOlderButton, false);
        loadOlderButton.disabled = !thread.hasMore;
      }
    }
  };

  const subscribeToThread = (thread) => {
    if (!thread || subscriptions.has(thread.key) || !window.Echo || !thread.channelName) {
      return;
    }

    window.Echo.private(thread.channelName)
      .listen('.order.chat.message.sent', (payload) => {
        const message = normalizeMessage(payload?.message);

        if (!message || message.threadType !== thread.key) {
          return;
        }

        thread.messages = mergeMessages(thread.messages, [message]);
        thread.hasLoaded = true;

        if (currentChannel === thread.key) {
          renderFeed(thread);
        }

        setLastSync(new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
      });

    subscriptions.add(thread.key);
  };

  threads.forEach((thread) => {
    subscribeToThread(thread);

    thread.tab.addEventListener('click', () => {
      currentChannel = thread.key;
      updateChannelUi();

      if (thread.hasLoaded) {
        renderFeed(thread);
        setAlert('', 'info');
        return;
      }

      loadThreadHistory(thread);
    });
  });

  composeTextarea?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing) {
      return;
    }

    event.preventDefault();

    if (!composeForm || sendButton?.disabled) {
      return;
    }

    const body = composeTextarea.value.trim();

    if (!body) {
      return;
    }

    if (typeof composeForm.requestSubmit === 'function') {
      composeForm.requestSubmit();
      return;
    }

    composeForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });

  composeTextarea?.addEventListener('input', () => {
    resizeComposeTextarea();
  });

  composeForm?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const thread = currentThread();

    if (!thread || !thread.canSend || !composeTextarea || !thread.sendUrl) {
      setAlert('This thread is read only for your role.', 'info');
      return;
    }

    const body = composeTextarea.value.trim();

    if (!body) {
      setAlert('Write a message before sending it.', 'warning');
      return;
    }

    setButtonBusy(sendButton, true, 'Sending...');

    if (composeTextarea) {
      composeTextarea.disabled = true;
    }

    if (composeNotice) {
      composeNotice.textContent = 'Sending message...';
    }

    try {
      const response = await window.axios.post(thread.sendUrl, { body });
      const message = normalizeMessage(response?.data?.message);

      if (message) {
        thread.messages = mergeMessages(thread.messages, [message]);
        thread.hasLoaded = true;
        composeTextarea.value = '';
        resizeComposeTextarea();

        if (currentChannel === thread.key) {
          renderFeed(thread);
        }

        setLastSync(new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }));
      }

      setAlert('Message sent.', 'success');
    } catch (error) {
      setAlert(extractErrorMessage(error, 'Could not send the message right now.'), 'danger');
    } finally {
      setButtonBusy(sendButton, false);
      updateComposeState(currentThread());
    }
  });

  loadOlderButton?.addEventListener('click', () => {
    const thread = currentThread();

    if (!thread || !thread.hasMore || !thread.nextCursor) {
      return;
    }

    loadThreadHistory(thread, {
      appendOlder: true,
      before: thread.nextCursor,
    });
  });

  orderIdButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.getAttribute('data-copy-order') || '';

      if (!value) {
        return;
      }

      try {
        await navigator.clipboard.writeText(value);
        setAlert('Order ID copied to clipboard.', 'success');
      } catch {
        setAlert('Could not copy the order ID on this device.', 'warning');
      }
    });
  });

  setRankIcons(document);
  initProgressForms(app);
  updateChannelUi();
  resizeComposeTextarea();
  setLastSync(window.Echo ? 'Realtime ready' : 'HTTP only');

  if (openModalId && window.bootstrap?.Modal) {
    const modalElement = document.getElementById(openModalId);

    if (modalElement) {
      window.requestAnimationFrame(() => {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
      });
    }
  }

  const thread = currentThread();

  if (thread) {
    loadThreadHistory(thread);
  }
}
