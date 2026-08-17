(function () {
  'use strict';

  const config = window.GeekyBotConfig || {};
  const settings = config.settings || {};
  const i18n = config.i18n || {};
  const root = document.getElementById('geekybot-sales-assistant');

  if (!root || !config.restUrl || !config.nonce) {
    return;
  }

  let isSending = false;
  let isRestoringHistory = false;
  let historySaveTimer = null;
  let invitationTimer = null;
  const productExpertSourcesSeen = new Set();
  const storageKeys = buildStorageKeys();
  let sessionKey = window.localStorage ? localStorage.getItem('geekybot_session_key') || '' : '';
  const launcherStyle = settings.launcherStyle === 'pill' ? 'pill' : 'icon';
  const headerStyle = settings.headerStyle === 'solid' ? 'solid' : 'gradient';
  const launcherMark = settings.launcherIconUrl ? '<img src="' + escapeAttr(settings.launcherIconUrl) + '" alt="" />' : brandMarkSvg();
  const headerLogo = settings.headerLogoSource === 'hide' ? '' : '<span class="gb-window__brand-logo">' + (settings.headerLogoUrl ? '<img src="' + escapeAttr(settings.headerLogoUrl) + '" alt="" />' : brandMarkSvg()) + '</span>';

  root.className = 'gb-widget gb-widget--' + (settings.buttonPosition === 'left' ? 'left' : 'right') + ' gb-widget--launcher-' + launcherStyle + ' gb-widget--header-' + headerStyle;
  root.innerHTML = '' +
    '<aside class="gb-shopper-invitation" aria-label="' + escapeAttr(i18n.invitation || 'Shopping assistant invitation') + '" hidden>' +
      '<button class="gb-shopper-invitation__open" type="button">' + escapeHtml(settings.shopperInvitationMessage || 'Need help choosing? Ask me about products, prices, or options.') + '</button>' +
      '<button class="gb-shopper-invitation__dismiss" type="button" aria-label="' + escapeAttr(i18n.dismissInvitation || 'Dismiss shopping assistant invitation') + '">×</button>' +
    '</aside>' +
    '<button class="gb-launcher" type="button" aria-label="' + escapeHtml(i18n.open || 'Open assistant') + '">' +
      '<span class="gb-launcher__icon">' + launcherMark + '</span>' +
      '<span class="gb-launcher__text">' + escapeHtml(settings.launcherText || 'Ask about products') + '</span>' +
    '</button>' +
    '<section class="gb-window" aria-live="polite" aria-hidden="true">' +
      '<header class="gb-window__header">' +
        '<div class="gb-window__brand">' + headerLogo + '<div><strong>' + escapeHtml(settings.assistantName || 'Geeky Bot') + '</strong><span>' + escapeHtml(settings.assistantSubtitle || 'WooCommerce shopping assistant') + '</span></div></div>' +
        '<div class="gb-window__header-actions">' +
          '<details class="gb-window__menu">' +
            '<summary aria-label="' + escapeHtml(i18n.moreActions || 'More actions') + '">•••</summary>' +
            '<div class="gb-window__menu-panel">' +
              '<button class="gb-clear-conversation" type="button">' + escapeHtml(i18n.clearConversation || 'Clear conversation') + '</button>' +
            '</div>' +
          '</details>' +
          '<button class="gb-close" type="button" aria-label="' + escapeHtml(i18n.close || 'Close') + '">×</button>' +
        '</div>' +
      '</header>' +
      '<div class="gb-messages" role="log"></div>' +
      '<form class="gb-form">' +
        '<input class="gb-input" type="text" autocomplete="off" maxlength="1000" placeholder="' + escapeHtml(i18n.placeholder || 'Ask about products…') + '" />' +
        '<button class="gb-send" type="submit">' + escapeHtml(i18n.send || 'Send') + '</button>' +
      '</form>' +
    '</section>';

  const launcher = root.querySelector('.gb-launcher');
  const shopperInvitation = root.querySelector('.gb-shopper-invitation');
  const shopperInvitationOpen = root.querySelector('.gb-shopper-invitation__open');
  const shopperInvitationDismiss = root.querySelector('.gb-shopper-invitation__dismiss');
  const closeBtn = root.querySelector('.gb-close');
  const clearBtn = root.querySelector('.gb-clear-conversation');
  const windowEl = root.querySelector('.gb-window');
  const messagesEl = root.querySelector('.gb-messages');
  const form = root.querySelector('.gb-form');
  const input = root.querySelector('.gb-input');

  if (!restoreConversationHistory()) {
    initializeFreshConversation();
  }

  observeConversationChanges();

  launcher.addEventListener('click', openWidget);
  if (shopperInvitationOpen) {
    shopperInvitationOpen.addEventListener('click', openWidget);
  }
  if (shopperInvitationDismiss) {
    shopperInvitationDismiss.addEventListener('click', function (event) {
      event.stopPropagation();
      hideShopperInvitation(true);
      launcher.focus();
    });
  }
  closeBtn.addEventListener('click', closeWidget);
  if (clearBtn) {
    clearBtn.addEventListener('click', clearConversation);
  }
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    sendMessage(input.value);
  });
  root.addEventListener('pointerdown', handleTrackedProductInteraction, true);
  root.addEventListener('click', handleTrackedProductInteraction, true);

  restoreWidgetOpenState();
  loadGuidedDemoPhrase();
  bindStorageSync();
  scheduleShopperInvitation();

  function openWidget() {
    hideShopperInvitation(true);
    root.classList.add('gb-widget--open');
    windowEl.setAttribute('aria-hidden', 'false');
    saveWidgetOpenState(true);
    setTimeout(function () { input.focus(); }, 50);
  }

  function loadGuidedDemoPhrase() {
    if (!window.URLSearchParams) {
      return;
    }
    var params = new URLSearchParams(window.location.search || '');
    var phrase = String(params.get('geekybot_demo') || '').trim();
    if (!phrase) {
      return;
    }
    phrase = phrase.slice(0, 1000);
    openWidget();
    input.value = phrase;
    input.setAttribute('aria-label', (i18n.placeholder || 'Ask about products…') + ': ' + phrase);
    setTimeout(function () {
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    }, 80);

    params.delete('geekybot_demo');
    if (window.history && window.history.replaceState) {
      var query = params.toString();
      var cleanUrl = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
      window.history.replaceState({}, document.title, cleanUrl);
    }
  }

  function closeWidget() {
    root.classList.remove('gb-widget--open');
    windowEl.setAttribute('aria-hidden', 'true');
    saveWidgetOpenState(false);
    launcher.focus();
  }

  function scheduleShopperInvitation() {
    if (settings.shopperInvitationEnabled === 'no'
      || !String(settings.shopperInvitationMessage || '').trim()
      || shopperInvitationWasSeen()
      || root.classList.contains('gb-widget--open')) {
      return;
    }

    if (document.hidden) {
      document.addEventListener('visibilitychange', function waitForVisiblePage() {
        if (!document.hidden) {
          scheduleShopperInvitation();
        }
      }, { once: true });
      return;
    }

    const delaySeconds = Math.max(3, Math.min(60, parseInt(settings.shopperInvitationDelay || '12', 10) || 12));
    invitationTimer = window.setTimeout(showShopperInvitation, delaySeconds * 1000);
  }

  function showShopperInvitation() {
    invitationTimer = null;
    if (!shopperInvitation
      || document.hidden
      || root.classList.contains('gb-widget--open')) {
      if (document.hidden) {
        scheduleShopperInvitation();
      }
      return;
    }

    markShopperInvitationSeen();
    shopperInvitation.hidden = false;
    window.requestAnimationFrame(function () {
      shopperInvitation.classList.add('is-visible');
    });
  }

  function hideShopperInvitation(markSeen) {
    if (invitationTimer) {
      window.clearTimeout(invitationTimer);
      invitationTimer = null;
    }
    if (markSeen) {
      markShopperInvitationSeen();
    }
    if (!shopperInvitation) {
      return;
    }
    shopperInvitation.classList.remove('is-visible');
    shopperInvitation.hidden = true;
  }

  function shopperInvitationWasSeen() {
    try {
      return window.sessionStorage && sessionStorage.getItem(storageKeys.invitation) === '1';
    } catch (error) {
      return false;
    }
  }

  function markShopperInvitationSeen() {
    try {
      if (window.sessionStorage) {
        sessionStorage.setItem(storageKeys.invitation, '1');
      }
    } catch (error) {}
  }

  function initializeFreshConversation() {
    messagesEl.innerHTML = '';
    addBotMessage(settings.welcomeMessage || 'Hi! Ask me what you are looking for.');
    addSuggestionChips((i18n.suggestions || ['Latest products', 'Sale products', 'Top rated', 'Products under 50']).slice(0, 4));
    saveConversationState();
  }

  function clearConversation() {
    const menu = root.querySelector('.gb-window__menu');
    if (menu) {
      menu.open = false;
    }
    sessionKey = '';
    productExpertSourcesSeen.clear();
    clearStoredConversation();
    if (window.localStorage) {
      try {
        localStorage.removeItem('geekybot_session_key');
      } catch (error) {}
    }
    document.dispatchEvent(new CustomEvent('geekybot:conversationCleared', {
      detail: { root: root, messagesEl: messagesEl }
    }));
    initializeFreshConversation();
    scrollToEnd();
    setTimeout(function () { input.focus(); }, 50);
  }

  function safeChatError(data, status) {
    const code = data && typeof data.code === 'string' ? data.code : '';
    let message = i18n.error || "I couldn\'t complete that request right now. Please try again.";

    if ((code === 'message_too_long' || code === 'geekybot_message_too_long') && data && typeof data.message === 'string') {
      message = data.message;
    } else if (code === 'rate_limited' || code === 'geekybot_rate_limited' || code === 'geekybot_catalog_rate_limited') {
      message = data && typeof data.message === 'string'
        ? data.message
        : (i18n.rateLimited || 'Too many requests were sent. Please wait a moment and try again.');
    } else if (code === 'shopping_session_expired' || code === 'geekybot_bad_nonce' || Number(status) === 403) {
      message = i18n.sessionExpired || 'Your shopping session expired. Please refresh the page and try again.';
    }

    const error = new Error(message);
    error.geekyBotSafe = true;
    return error;
  }

  function sendMessage(message) {
    message = (message || '').trim();
    if (!message || isSending) {
      return;
    }

    const beforeSendEvent = new CustomEvent('geekybot:beforeSendMessage', {
      cancelable: true,
      detail: {
        root: root,
        message: message,
        sessionKey: sessionKey
      }
    });

    if (!document.dispatchEvent(beforeSendEvent)) {
      input.value = '';
      saveConversationState();
      return;
    }

    addUserMessage(message);
    input.value = '';
    setSending(true);
    const thinking = addBotMessage(i18n.thinking || 'Let me check that', true);
    const controller = window.AbortController ? new AbortController() : null;
    let requestFinished = false;
    const timeout = window.setTimeout(function () {
      if (requestFinished) {
        return;
      }
      requestFinished = true;
      if (controller) {
        controller.abort();
      }
      replaceBotMessage(thinking, i18n.timeout || 'The store is taking longer than expected. Please try again.', [], []);
      setSending(false);
      saveConversationState();
    }, 30000);

    const requestOptions = {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify({ message: message, sessionKey: sessionKey })
    };
    if (controller) {
      requestOptions.signal = controller.signal;
    }

    fetch(config.restUrl.replace(/\/$/, '') + '/chat', requestOptions)
      .then(function (response) {
        return response.json().catch(function () {
          return null;
        }).then(function (data) {
          if (!response.ok) {
            throw safeChatError(data, response.status);
          }
          if (!data || typeof data !== 'object') {
            throw safeChatError(null, response.status);
          }
          return data;
        });
      })
      .then(function (data) {
        if (requestFinished) {
          return;
        }
        requestFinished = true;
        if (data.sessionKey) {
          sessionKey = data.sessionKey;
          if (window.localStorage) {
            localStorage.setItem('geekybot_session_key', sessionKey);
          }
        }
        const responseEvent = new CustomEvent('geekybot:chatResponse', {
          cancelable: true,
          detail: { root: root, data: data || {}, thinking: thinking }
        });
        if (!document.dispatchEvent(responseEvent)) {
          return;
        }
        replaceBotMessage(thinking, data.message || 'Here is what I found.', data.products || [], data.knowledge || [], data.product_expert || null);
      })
      .catch(function (error) {
        if (requestFinished) {
          return;
        }
        requestFinished = true;
        const message = error && error.geekyBotSafe && error.message
          ? error.message
          : (i18n.error || "I couldn\'t complete that request right now. Please try again.");
        replaceBotMessage(thinking, message, [], []);
      })
      .finally(function () {
        window.clearTimeout(timeout);
        setSending(false);
      });
  }


  function addSuggestionChips(labels) {
    if (!labels || !labels.length) {
      return null;
    }
    const wrap = document.createElement('div');
    wrap.className = 'gb-suggestion-chips';
    labels.forEach(function (label) {
      const text = String(label || '').trim();
      if (!text) {
        return;
      }
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'gb-suggestion-chip';
      button.textContent = text;
      button.addEventListener('click', function () {
        sendMessage(text);
      });
      wrap.appendChild(button);
    });
    if (!wrap.children.length) {
      return null;
    }
    messagesEl.appendChild(wrap);
    scrollToEnd();
    return wrap;
  }


  function buildStorageKeys() {
    const base = 'geekybot_widget_' + String(location.host || 'site').replace(/[^a-z0-9._-]/gi, '_');
    return {
      history: base + '_history_v1',
      timestamp: base + '_history_saved_at',
      open: base + '_open',
      invitation: base + '_invitation_seen_v1'
    };
  }

  function historyEnabled() {
    return settings.chatHistoryEnabled !== 'no';
  }

  function historyRetentionMs() {
    const days = Math.max(1, Math.min(365, parseInt(settings.retentionDays || '30', 10) || 30));
    return days * 24 * 60 * 60 * 1000;
  }

  function restoreConversationHistory() {
    if (!window.localStorage || !historyEnabled()) {
      clearStoredConversation();
      return false;
    }

    try {
      const savedAt = parseInt(localStorage.getItem(storageKeys.timestamp) || '0', 10) || 0;
      const html = localStorage.getItem(storageKeys.history) || '';
      if (!html || !savedAt || (Date.now() - savedAt) > historyRetentionMs()) {
        clearStoredConversation();
        return false;
      }

      const cleanHtml = sanitizeStoredConversationHtml(html);
      if (!cleanHtml) {
        clearStoredConversation();
        return false;
      }

      isRestoringHistory = true;
      messagesEl.innerHTML = cleanHtml;
      removeTransientMessages(messagesEl);
      enhanceRestoredUserMessages();
      messagesEl.querySelectorAll('[data-gbcp-enhanced]').forEach(function (node) {
        node.removeAttribute('data-gbcp-enhanced');
      });
      isRestoringHistory = false;
      scrollToEnd();
      setTimeout(function () {
        document.dispatchEvent(new CustomEvent('geekybot:historyRestored', { detail: { root: root, messagesEl: messagesEl } }));
      }, 0);
      return messagesEl.children.length > 0;
    } catch (error) {
      clearStoredConversation();
      isRestoringHistory = false;
      return false;
    }
  }

  function removeTransientMessages(scope) {
    const context = scope || messagesEl;
    if (!context || !context.querySelectorAll) {
      return;
    }
    context.querySelectorAll('[data-gb-transient="1"], .gb-message--loading').forEach(function (node) {
      node.remove();
    });
  }

  function sanitizeStoredConversationHtml(html) {
    const template = document.createElement('template');
    template.innerHTML = String(html || '').slice(0, 250000);

    template.content.querySelectorAll('script, iframe, object, embed, style, link, meta').forEach(function (node) {
      node.remove();
    });
    removeTransientMessages(template.content);
    template.content.querySelectorAll('.gb-empty-shopper-state').forEach(function (node) {
      node.remove();
    });
    template.content.querySelectorAll('.gb-message__user-avatar').forEach(function (node) {
      node.remove();
    });
    template.content.querySelectorAll('*').forEach(function (node) {
      Array.prototype.slice.call(node.attributes || []).forEach(function (attribute) {
        const name = attribute.name.toLowerCase();
        const value = String(attribute.value || '').trim().toLowerCase();
        if (name.indexOf('on') === 0 || name === 'srcdoc' || name === 'style') {
          node.removeAttribute(attribute.name);
          return;
        }
        if ((name === 'href' || name === 'src') && value.indexOf('javascript:') === 0) {
          node.removeAttribute(attribute.name);
        }
      });
    });

    return template.innerHTML;
  }

  function observeConversationChanges() {
    if (!window.MutationObserver || !historyEnabled()) {
      return;
    }
    const observer = new MutationObserver(function () {
      scheduleConversationSave();
    });
    observer.observe(messagesEl, { childList: true, subtree: true, attributes: true, characterData: true });
  }

  function scheduleConversationSave() {
    if (isRestoringHistory || !historyEnabled()) {
      return;
    }
    if (historySaveTimer) {
      clearTimeout(historySaveTimer);
    }
    historySaveTimer = setTimeout(saveConversationState, 150);
  }

  function saveConversationState() {
    if (!window.localStorage || !historyEnabled()) {
      return;
    }
    try {
      const clone = messagesEl.cloneNode(true);
      removeTransientMessages(clone);
      clone.querySelectorAll('.gb-empty-shopper-state').forEach(function (node) {
        node.remove();
      });
      const html = sanitizeStoredConversationHtml(clone.innerHTML || '');
      if (!html || !html.trim()) {
        clearStoredConversation();
        return;
      }
      localStorage.setItem(storageKeys.history, html.slice(0, 250000));
      localStorage.setItem(storageKeys.timestamp, String(Date.now()));
    } catch (error) {}
  }

  function clearStoredConversation() {
    if (!window.localStorage) {
      return;
    }
    try {
      localStorage.removeItem(storageKeys.history);
      localStorage.removeItem(storageKeys.timestamp);
    } catch (error) {}
  }

  function saveWidgetOpenState(isOpen) {
    if (!window.localStorage) {
      return;
    }
    try {
      localStorage.setItem(storageKeys.open, isOpen ? '1' : '0');
    } catch (error) {}
  }

  function restoreWidgetOpenState() {
    if (!window.localStorage) {
      return;
    }
    try {
      if (localStorage.getItem(storageKeys.open) === '1') {
        root.classList.add('gb-widget--open');
        windowEl.setAttribute('aria-hidden', 'false');
      }
    } catch (error) {}
  }

  function bindStorageSync() {
    if (!window.addEventListener || !window.localStorage) {
      return;
    }
    window.addEventListener('storage', function (event) {
      if (event.key === storageKeys.open) {
        const shouldOpen = event.newValue === '1';
        if (shouldOpen) {
          hideShopperInvitation(true);
        }
        root.classList.toggle('gb-widget--open', shouldOpen);
        windowEl.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
      }
      if (event.key === storageKeys.history && event.newValue === null) {
        isRestoringHistory = true;
        document.dispatchEvent(new CustomEvent('geekybot:conversationCleared', {
          detail: { root: root, messagesEl: messagesEl }
        }));
        initializeFreshConversation();
        isRestoringHistory = false;
      }
    });
  }

  function setSending(value) {
    isSending = value;
    input.disabled = value;
    root.querySelector('.gb-send').disabled = value;
  }

  function addUserMessage(text) {
    const item = document.createElement('div');
    item.className = 'gb-message gb-message--user';
    item.appendChild(document.createTextNode(text));
    addUserAvatar(item, text);
    messagesEl.appendChild(item);
    scrollToEnd();
    return item;
  }

  function enhanceRestoredUserMessages() {
    messagesEl.querySelectorAll('.gb-message--user').forEach(function (item) {
      const text = String(item.textContent || '').trim();
      addUserAvatar(item, text);
    });
  }

  function addUserAvatar(item, text) {
    if (!item || item.querySelector('.gb-message__user-avatar')) {
      return;
    }
    const avatar = document.createElement('span');
    avatar.className = 'gb-message__user-avatar';
    avatar.setAttribute('aria-hidden', 'true');
    avatar.innerHTML = userAvatarSvg();
    item.setAttribute('aria-label', (i18n.you || 'You') + ': ' + String(text || '').trim());
    item.appendChild(avatar);
  }

  function addBotMessage(text, loading) {
    const item = document.createElement('div');
    const messageText = loading
      ? String(text || '').replace(/(?:\u2026|\.{3})\s*$/, '').trim()
      : String(text || '');
    item.className = 'gb-message gb-message--bot' + (loading ? ' gb-message--loading' : '');

    if (loading) {
      const label = document.createElement('span');
      const dots = document.createElement('span');

      item.setAttribute('data-gb-transient', '1');
      item.setAttribute('role', 'status');
      item.setAttribute('aria-live', 'polite');
      item.setAttribute('aria-atomic', 'true');
      item.setAttribute('aria-label', messageText);

      label.className = 'gb-message__loading-label';
      label.textContent = messageText;

      dots.className = 'gb-message__typing-dots';
      dots.setAttribute('aria-hidden', 'true');
      for (let index = 0; index < 3; index += 1) {
        const dot = document.createElement('span');
        dot.className = 'gb-message__typing-dot';
        dots.appendChild(dot);
      }

      item.appendChild(label);
      item.appendChild(dots);
    } else {
      item.textContent = messageText;
    }

    messagesEl.appendChild(item);
    scrollToEnd();
    return item;
  }

  function replaceBotMessage(item, text, products, knowledge, productExpert) {
    item.removeAttribute('data-gb-transient');
    item.removeAttribute('role');
    item.removeAttribute('aria-live');
    item.removeAttribute('aria-atomic');
    item.removeAttribute('aria-label');
    item.className = 'gb-message gb-message--bot' + (products && products.length ? ' gb-message--with-products' : '');
    item.innerHTML = '<div class="gb-message__text">' + escapeHtml(text) + '</div>';

    if (productExpert && productExpert.verified) {
      const sourceProductId = String(productExpert.productId || '');
      const sourceWasSeen = sourceProductId && productExpertSourcesSeen.has(sourceProductId);
      const shouldShowSource = productExpert.alwaysShowSource === true || !sourceWasSeen;

      if (shouldShowSource) {
        const verified = document.createElement('div');
        verified.className = 'gb-product-expert-verified';
        verified.textContent = productExpert.verifiedLabel || 'From store details';
        item.appendChild(verified);
      }

      if (sourceProductId) {
        productExpertSourcesSeen.add(sourceProductId);
      }
    }

    if (products && products.length) {
      const list = document.createElement('div');
      list.className = 'gb-products';
      const initialLimit = 3;
      products.forEach(function (product, index) {
        const card = productCard(product);
        if (index >= initialLimit) {
          card.classList.add('gb-product-card--hidden');
        }
        list.appendChild(card);
      });
      item.appendChild(list);

      if (products.length > initialLimit) {
        const moreWrap = document.createElement('div');
        moreWrap.className = 'gb-products-show-more';
        const moreButton = document.createElement('button');
        moreButton.type = 'button';
        moreButton.className = 'gb-products-show-more__button';
        moreButton.textContent = 'Show ' + String(products.length - initialLimit) + ' more products';
        moreButton.addEventListener('click', function () {
          list.querySelectorAll('.gb-product-card--hidden').forEach(function (card) {
            card.classList.remove('gb-product-card--hidden');
          });
          moreWrap.remove();
        });
        moreWrap.appendChild(moreButton);
        item.appendChild(moreWrap);
      }

      dispatchProductsRendered(list, products);
    }

    if (knowledge && knowledge.length) {
      const sources = document.createElement('div');
      sources.className = 'gb-sources';
      sources.innerHTML = '<span>Source:</span> ';
      knowledge.slice(0, 2).forEach(function (source, index) {
        if (index) {
          sources.appendChild(document.createTextNode(', '));
        }
        const link = document.createElement('a');
        link.href = source.url || '#';
        link.textContent = source.title || 'Store page';
        link.addEventListener('click', function () {
          trackAnalyticsEvent('policy_source_click', source.id || 0, source.title || 'Store page', {
            surface: 'policy_source',
            url: source.url || ''
          });
        });
        sources.appendChild(link);
      });
      item.appendChild(sources);
    }

    scrollToMessageStart(item);
    document.dispatchEvent(new CustomEvent('geekybot:messageRendered', {
      detail: { root: root, messageEl: item, products: products || [], knowledge: knowledge || [], productExpert: productExpert || null }
    }));
  }

  function handleTrackedProductInteraction(event) {
    const target = event.target && event.target.closest ? event.target.closest('[data-gb-product-click="1"]') : null;
    if (!target || !root.contains(target)) {
      return;
    }
    if (event.type === 'pointerdown') {
      if (event.pointerType === 'touch' || (typeof event.button === 'number' && event.button !== 0)) {
        return;
      }
    }

    const now = Date.now();
    const lastPointerTrack = parseInt(target.getAttribute('data-gb-product-pointer-time') || '0', 10) || 0;
    if (event.type === 'click' && lastPointerTrack > 0 && now - lastPointerTrack < 1500) {
      return;
    }
    if (event.type === 'pointerdown') {
      target.setAttribute('data-gb-product-pointer-time', String(now));
    }

    trackAnalyticsEvent(
      'product_click',
      target.getAttribute('data-gb-product-id') || 0,
      target.getAttribute('data-gb-product-name') || '',
      {
        surface: target.getAttribute('data-gb-product-surface') || 'product_card',
        url: target.getAttribute('href') || ''
      }
    );
  }

  function trackAnalyticsEvent(eventType, objectId, objectLabel, context) {
    if (settings.chatHistoryEnabled === 'no' || !eventType || !sessionKey) {
      return Promise.resolve(false);
    }

    const payload = {
      eventType: String(eventType || ''),
      sessionKey: String(sessionKey || ''),
      objectId: parseInt(objectId || '0', 10) || 0,
      objectLabel: String(objectLabel || '').slice(0, 190),
      context: context || {}
    };

    return fetch(config.restUrl.replace(/\/$/, '') + '/events', {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce
      },
      body: JSON.stringify(payload)
    }).then(function (response) {
      if (!response.ok) {
        return false;
      }
      document.dispatchEvent(new CustomEvent('geekybot:analyticsRecorded', {
        detail: { eventType: payload.eventType, objectId: payload.objectId }
      }));
      return true;
    }).catch(function () {
      return false;
    });
  }

  function productCard(product) {
    const card = document.createElement('article');
    card.className = 'gb-product-card';
    card.setAttribute('data-product-id', String(product.id || ''));
    card.setAttribute('data-product-type', String(product.type || ''));
    card.setAttribute('data-product-purchasable', product.isPurchasable ? '1' : '0');
    card.setAttribute('data-product-in-stock', product.isInStock ? '1' : '0');
    card.setAttribute('data-product-requires-options', product.requiresOptions ? '1' : '0');

    const image = product.image ? '<img src="' + escapeAttr(product.image) + '" alt="' + escapeAttr(product.name || '') + '" loading="lazy" />' : '';
    const price = product.priceHtml ? '<div class="gb-product-card__price">' + product.priceHtml + '</div>' : '';
    const desc = product.shortDescription ? '<p>' + escapeHtml(product.shortDescription) + '</p>' : '';
    const stock = product.stockStatus ? '<span class="gb-product-card__stock gb-stock--' + escapeAttr(product.stockStatus) + '">' + escapeHtml(product.stockLabel || formatStock(product.stockStatus)) + '</span>' : '';
    const match = productMatchBlock(product.searchMatch || null);

    const trackingAttributes = ' data-gb-product-click="1" data-gb-product-id="' + escapeAttr(product.id || '') + '" data-gb-product-name="' + escapeAttr(product.name || '') + '"';
    card.innerHTML = '' +
      '<a class="gb-product-card__image" href="' + escapeAttr(product.url || '#') + '"' + trackingAttributes + ' data-gb-product-surface="product_image">' + image + '</a>' +
      '<div class="gb-product-card__body">' +
        '<a class="gb-product-card__title" href="' + escapeAttr(product.url || '#') + '"' + trackingAttributes + ' data-gb-product-surface="product_title">' + escapeHtml(product.name || '') + '</a>' +
        price + desc + stock + match +
        '<div class="gb-product-card__actions">' +
          '<a class="gb-product-card__button" href="' + escapeAttr(product.url || '#') + '"' + trackingAttributes + ' data-gb-product-surface="view_product">' + escapeHtml(i18n.viewProduct || 'View product') + '</a>' +
        '</div>' +
      '</div>';

    return card;
  }

  function productMatchBlock(match) {
    if (!match || (!hasListItems(match.matched) && !hasListItems(match.notConfirmed))) {
      return '';
    }

    let html = '<div class="gb-product-card__match gb-product-card__match--' + escapeAttr(match.type || 'match') + '">';
    html += '<div class="gb-product-card__match-title">' + escapeHtml(match.label || 'Match details') + '</div>';

    if (hasListItems(match.matched)) {
      html += productMatchRow(match.matchedLabel || 'Matched', match.matched, 'matched');
    }
    if (hasListItems(match.notConfirmed)) {
      html += productMatchRow(match.notConfirmedLabel || 'Not confirmed', match.notConfirmed, 'missing');
    }

    html += '</div>';
    return html;
  }

  function productMatchRow(label, items, type) {
    let html = '<div class="gb-product-card__match-row gb-product-card__match-row--' + escapeAttr(type || 'matched') + '">';
    html += '<strong>' + escapeHtml(label) + '</strong>';
    html += '<span class="gb-product-card__match-list">';
    (items || []).slice(0, 5).forEach(function (item) {
      const text = String(item || '').trim();
      if (text) {
        html += '<span class="gb-product-card__match-chip gb-product-card__match-chip--' + escapeAttr(type || 'matched') + '">' + escapeHtml(text) + '</span>';
      }
    });
    html += '</span></div>';
    return html;
  }

  function hasListItems(items) {
    return Array.isArray(items) && items.filter(function (item) { return String(item || '').trim() !== ''; }).length > 0;
  }

  function dispatchProductsRendered(container, products) {
    document.dispatchEvent(new CustomEvent('geekybot:productsRendered', {
      detail: { root: root, container: container, products: products || [] }
    }));
  }

  function formatStock(stock) {
    if (stock === 'instock') return 'In stock';
    if (stock === 'outofstock') return 'Out of stock';
    if (stock === 'onbackorder') return 'On backorder';
    return stock;
  }

  function scrollToEnd() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function scrollToMessageStart(item) {
    if (!item) {
      scrollToEnd();
      return;
    }

    const top = Math.max(0, item.offsetTop - 12);
    messagesEl.scrollTop = top;
  }

  function brandMarkSvg() {
    brandMarkSvg._n = (brandMarkSvg._n || 0) + 1;
    var g = 'gbSiteMark' + brandMarkSvg._n;
    var u = 'url(#' + g + ')';
    return '<svg class="gb-brand-mark-svg" viewBox="0 0 100 100" role="img" focusable="false" aria-hidden="true">' +
      '<defs><linearGradient id="' + g + '" x1="8" y1="16" x2="92" y2="88" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#2f6bed" /><stop offset="1" stop-color="#7c3aed" /></linearGradient></defs>' +
      '<g transform="translate(0,8)">' +
      '<path d="M20 15 C21 22 23 24 30 25 C23 26 21 28 20 35 C19 28 17 26 10 25 C17 24 19 22 20 15 Z" fill="' + u + '" />' +
      '<path d="M55 20 H73 Q88 20 88 35 V45 Q88 60 73 60 L70 60 L73 72 L62 60 H55 Q40 60 40 45 V35 Q40 20 55 20 Z" fill="none" stroke="' + u + '" stroke-width="5.5" stroke-linejoin="round" />' +
      '<line x1="66" y1="20" x2="66" y2="13" stroke="' + u + '" stroke-width="3.5" stroke-linecap="round" /><circle cx="66" cy="10" r="3" fill="' + u + '" />' +
      '<rect x="51" y="31" width="26" height="16" rx="7" fill="' + u + '" />' +
      '<ellipse cx="59" cy="39" rx="2.3" ry="3" fill="#fff" /><ellipse cx="69" cy="39" rx="2.3" ry="3" fill="#fff" />' +
      '<circle cx="58" cy="53" r="1.7" fill="' + u + '" /><circle cx="64" cy="53" r="1.7" fill="' + u + '" /><circle cx="70" cy="53" r="1.7" fill="' + u + '" />' +
      '<path d="M20 46 L48 46 L44 60 L27 60 Z" fill="none" stroke="' + u + '" stroke-width="4.5" stroke-linejoin="round" />' +
      '<path d="M20 46 L15 40 L11 40" fill="none" stroke="' + u + '" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round" />' +
      '<line x1="29" y1="60" x2="29" y2="63" stroke="' + u + '" stroke-width="4" /><line x1="42" y1="60" x2="42" y2="63" stroke="' + u + '" stroke-width="4" />' +
      '<circle cx="29" cy="66" r="3" fill="' + u + '" /><circle cx="42" cy="66" r="3" fill="' + u + '" />' +
      '</g>' +
    '</svg>';
  }

  function userAvatarSvg() {
    return '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">' +
      '<circle cx="12" cy="8" r="3.4" fill="none" stroke="currentColor" stroke-width="1.8" />' +
      '<path d="M5.2 20c.5-4 3-6.1 6.8-6.1s6.3 2.1 6.8 6.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />' +
    '</svg>';
  }

  function escapeHtml(value) {
    return String(value || '').replace(/[&<>"']/g, function (char) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[char];
    });
  }

  function escapeAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#096;');
  }

  window.GeekyBotFrontend = {
    root: root,
    open: openWidget,
    close: closeWidget,
    addUserMessage: addUserMessage,
    addBotMessage: addBotMessage,
    replaceBotMessage: replaceBotMessage,
    scrollToEnd: scrollToEnd,
    scrollToMessageStart: scrollToMessageStart,
    addSuggestionChips: addSuggestionChips,
    saveConversationState: saveConversationState,
    clearConversation: clearConversation,
    clearStoredConversation: clearStoredConversation,
    escapeHtml: escapeHtml,
    escapeAttr: escapeAttr
  };
})();
