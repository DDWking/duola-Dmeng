(() => {
  const config = window.duolaWall;
  const form = document.querySelector('[data-wall-form]');
  const messages = document.querySelector('[data-wall-messages]');
  if (!config || !form || !messages) return;

  const textarea = form.elements.message;
  const count = form.querySelector('[data-wall-count]');
  const status = form.querySelector('[data-wall-status]');
  const submit = form.querySelector('button[type="submit"]');
  const search = document.querySelector('[data-wall-search]');
  const searchInput = document.querySelector('[data-wall-search-input]');
  const searchStatus = document.querySelector('[data-wall-search-status]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let nonce = config.nonce;
  let lastQuery = '';
  let searchIndex = -1;

  const setText = (element, value) => {
    element.textContent = value;
  };

  const refreshNonce = async () => {
    const response = await fetch(config.tokenUrl, {
      credentials: 'same-origin',
      cache: 'no-store',
    });
    const result = await response.json();
    if (!response.ok || !result.nonce) throw new Error(config.networkError);
    nonce = result.nonce;
  };

  const requestWithNonce = async (url, options = {}) => {
    try {
      await refreshNonce();
    } catch (error) {
      // The nonce embedded in the page remains a valid fallback.
    }

    for (let attempt = 0; attempt < 2; attempt += 1) {
      const response = await fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: { ...options.headers, 'X-Duola-Wall-Nonce': nonce },
      });
      const result = await response.json();
      if (403 !== response.status || 'duola_wall_invalid_request' !== result.code || 1 === attempt) {
        return { response, result };
      }
      await refreshNonce();
    }

    throw new Error(config.networkError);
  };

  const messageElement = (message, isReply = false) => {
    const article = document.createElement('article');
    article.className = `wall-message${message.pinned ? ' is-pinned' : ''}${isReply ? ' is-reply' : ''}`;
    article.dataset.wallMessage = message.id;

    const header = document.createElement('header');
    const number = document.createElement('span');
    setText(number, `[${message.number}]`);
    const time = document.createElement('time');
    time.dateTime = message.date.replace(' ', 'T');
    setText(time, message.date);
    const nickname = document.createElement('strong');
    setText(nickname, message.nickname || config.anonymous);
    header.append(number, time, nickname);

    if (message.pinned) {
      const pinned = document.createElement('i');
      setText(pinned, 'PINNED');
      header.appendChild(pinned);
    }
    if (!isReply) {
      const like = document.createElement('button');
      like.type = 'button';
      like.dataset.wallLike = '';
      like.setAttribute('aria-label', 'Give this message +1');
      like.append('+1 ');
      const likes = document.createElement('span');
      setText(likes, message.likes);
      like.appendChild(likes);
      header.appendChild(like);
    }

    const body = document.createElement('pre');
    setText(body, message.message);
    article.append(header, body);
    if (!isReply && message.replies?.length) {
      const replies = document.createElement('div');
      replies.className = 'wall-replies';
      message.replies.forEach((reply) => replies.appendChild(messageElement(reply, true)));
      article.appendChild(replies);
    }
    return article;
  };

  const clearSearchHit = () => {
    messages.querySelector('.is-search-hit')?.classList.remove('is-search-hit');
  };

  const closeSearch = () => {
    if (!search) return;
    search.hidden = true;
    setText(searchStatus, '');
    clearSearchHit();
  };

  const openSearch = () => {
    if (!search || !searchInput) return;
    search.hidden = false;
    searchInput.focus();
    searchInput.select();
  };

  const findNext = () => {
    const query = searchInput?.value.trim().toLocaleLowerCase() || '';
    if (!query) {
      setText(searchStatus, 'empty pattern');
      return;
    }

    const matches = Array.from(messages.querySelectorAll('[data-wall-message]'))
      .filter((message) => message.textContent.toLocaleLowerCase().includes(query));
    if (!matches.length) {
      clearSearchHit();
      setText(searchStatus, 'pattern not found');
      return;
    }

    if (query !== lastQuery) searchIndex = -1;
    searchIndex = (searchIndex + 1) % matches.length;
    lastQuery = query;
    clearSearchHit();
    matches[searchIndex].classList.add('is-search-hit');
    matches[searchIndex].scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
    setText(searchStatus, `${searchIndex + 1}/${matches.length}`);
  };

  const runCommand = (command) => {
    const distance = Math.max(72, window.innerHeight * 0.22);
    if ('down' === command) window.scrollBy({ top: distance, behavior: reducedMotion ? 'auto' : 'smooth' });
    if ('up' === command) window.scrollBy({ top: -distance, behavior: reducedMotion ? 'auto' : 'smooth' });
    if ('search' === command) openSearch();
    if ('quit' === command) window.location.href = config.homeUrl;
  };

  const isTyping = (target) => target instanceof HTMLElement
    && (target.matches('input, textarea, select') || target.isContentEditable);

  textarea.addEventListener('input', () => setText(count, textarea.value.length));

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    submit.disabled = true;
    setText(status, 'transmitting...');
    const data = new FormData(form);
    try {
      const { response, result } = await requestWithNonce(config.messagesUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.fromEntries(data.entries())),
      });
      if (!response.ok) throw new Error(result.message || config.networkError);
      setText(status, result.notice);
      if (result.message) {
        document.querySelector('[data-wall-empty]')?.remove();
        messages.prepend(messageElement(result.message));
      }
      form.reset();
      form.elements.started_at.value = Math.floor(Date.now() / 1000);
      setText(count, '0');
    } catch (error) {
      setText(status, error.message || config.networkError);
    } finally {
      submit.disabled = false;
    }
  });

  messages.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-wall-like]');
    if (!button || button.disabled) return;
    const message = button.closest('[data-wall-message]');
    button.disabled = true;
    try {
      const { response, result } = await requestWithNonce(`${config.messagesUrl}/${message.dataset.wallMessage}/like`, {
        method: 'POST',
      });
      if (!response.ok) throw new Error(result.message || config.networkError);
      setText(button.querySelector('span'), result.likes);
      button.classList.toggle('is-liked', result.liked);
    } catch (error) {
      setText(status, error.message || config.networkError);
    } finally {
      button.disabled = false;
    }
  });

  document.querySelectorAll('[data-wall-command]').forEach((button) => {
    button.addEventListener('click', () => runCommand(button.dataset.wallCommand));
  });
  document.querySelector('[data-wall-search-close]')?.addEventListener('click', closeSearch);
  search?.addEventListener('submit', (event) => {
    event.preventDefault();
    findNext();
  });

  document.addEventListener('keydown', (event) => {
    if (event.target === searchInput) {
      if ('Escape' === event.key) {
        event.preventDefault();
        closeSearch();
      }
      return;
    }
    if (isTyping(event.target)) return;

    if ('j' === event.key) runCommand('down');
    if ('k' === event.key) runCommand('up');
    if ('q' === event.key) runCommand('quit');
    if ('/' === event.key) {
      event.preventDefault();
      runCommand('search');
    }
    if ('g' === event.key) window.scrollTo({ top: 0, behavior: reducedMotion ? 'auto' : 'smooth' });
    if ('G' === event.key) window.scrollTo({ top: document.documentElement.scrollHeight, behavior: reducedMotion ? 'auto' : 'smooth' });
  });
})();
