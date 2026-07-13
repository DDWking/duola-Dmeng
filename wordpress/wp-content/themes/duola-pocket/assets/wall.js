(() => {
  const config = window.duolaWall;
  const form = document.querySelector('[data-wall-form]');
  const messages = document.querySelector('[data-wall-messages]');
  if (!config || !form || !messages) return;

  const textarea = form.elements.message;
  const count = form.querySelector('[data-wall-count]');
  const status = form.querySelector('[data-wall-status]');
  const submit = form.querySelector('button[type="submit"]');

  const setText = (element, value) => {
    element.textContent = value;
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

  textarea.addEventListener('input', () => setText(count, textarea.value.length));

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    submit.disabled = true;
    setText(status, 'sending...');
    const data = new FormData(form);
    try {
      const response = await fetch(config.messagesUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Duola-Wall-Nonce': config.nonce },
        body: JSON.stringify(Object.fromEntries(data.entries())),
      });
      const result = await response.json();
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
      const response = await fetch(`${config.messagesUrl}/${message.dataset.wallMessage}/like`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Duola-Wall-Nonce': config.nonce },
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || config.networkError);
      setText(button.querySelector('span'), result.likes);
      button.classList.toggle('is-liked', result.liked);
    } catch (error) {
      setText(status, error.message || config.networkError);
    } finally {
      button.disabled = false;
    }
  });
})();
