(() => {
  document.documentElement.classList.add('motion-ready');
  const revealItems = Array.from(document.querySelectorAll('.section, .page-intro, .album-header, .album-card, .post-row, .photo-button, .article, .page-content'));
  revealItems.forEach((item, index) => {
    item.classList.add('reveal-item');
    item.style.setProperty('--reveal-delay', `${(index % 4) * 70}ms`);
  });

  if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -4% 0px' });
    revealItems.forEach((item) => revealObserver.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  const gallery = document.querySelector('[data-lightbox-gallery]');
  if (!gallery) return;

  const triggers = Array.from(gallery.querySelectorAll('[data-lightbox-image]'));
  const itemIndexByKey = new Map();
  const items = [];
  triggers.forEach((trigger) => {
    const key = trigger.dataset.lightboxKey || trigger.dataset.lightboxImage;
    if (!itemIndexByKey.has(key)) {
      itemIndexByKey.set(key, items.length);
      items.push(trigger);
    }
    trigger.dataset.lightboxIndex = String(itemIndexByKey.get(key));
  });

  const formatIndex = (index) => String(index + 1).padStart(2, '0');
  const homeRail = document.querySelector('[data-home-photo-rail]');
  const homePreview = document.querySelector('[data-home-preview-control]');
  const homePreviewInput = homePreview?.querySelector('input[type="range"]');
  const homePreviewCurrent = homePreview?.querySelector('[data-home-preview-current]');

  const syncHomePreview = (index, shouldScroll = false) => {
    if (!homePreviewInput || !homePreviewCurrent) return;
    const normalizedIndex = Math.max(0, Math.min(items.length - 1, Number(index)));
    homePreviewInput.value = String(normalizedIndex);
    homePreviewCurrent.textContent = formatIndex(normalizedIndex);
    triggers.forEach((trigger) => trigger.classList.remove('is-previewed'));
    const previewTrigger = triggers.find((trigger) => Number(trigger.dataset.lightboxIndex) === normalizedIndex);
    previewTrigger?.classList.add('is-previewed');
    if (shouldScroll) {
      previewTrigger?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
    }
  };

  homePreviewInput?.addEventListener('input', () => syncHomePreview(homePreviewInput.value, true));
  triggers.forEach((trigger) => {
    trigger.addEventListener('pointerenter', () => syncHomePreview(trigger.dataset.lightboxIndex));
    trigger.addEventListener('focus', () => syncHomePreview(trigger.dataset.lightboxIndex));
  });
  syncHomePreview(0);

  const galleryTitle = gallery.dataset.galleryTitle || '照片';
  let currentIndex = 0;
  let previousFocus = null;
  let wheelLocked = false;
  let touchStartX = 0;
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', `${galleryTitle}照片查看器`);
  lightbox.setAttribute('aria-hidden', 'true');
  lightbox.innerHTML = `
    <div class="lightbox-display-title" aria-hidden="true"></div>
    <header class="lightbox-header">
      <span class="lightbox-album"></span>
      <div class="lightbox-progress">
        <div class="lightbox-counter" aria-live="polite"><span data-lightbox-current>01</span><span>/</span><span data-lightbox-total>01</span></div>
        <div class="lightbox-scrubber-track"><span aria-hidden="true"></span><input class="lightbox-scrubber" type="range" min="0" value="0" step="1" aria-label="滑动预览照片"></div>
      </div>
      <button class="lightbox-close" type="button" aria-label="关闭查看器">×</button>
    </header>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张照片">←</button>
    <div class="lightbox-stage">
      <div class="lightbox-frame"><img class="lightbox-image" alt=""></div>
    </div>
    <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张照片">→</button>
    <div class="lightbox-details">
      <dl>
        <div data-lightbox-date-row><dt>A</dt><dd data-lightbox-date></dd></div>
        <div><dt>B</dt><dd data-lightbox-detail-album></dd></div>
      </dl>
      <p data-lightbox-description></p>
    </div>
    <div class="lightbox-thumbnails" role="tablist" aria-label="照片缩略图"></div>`;
  document.body.appendChild(lightbox);

  const image = lightbox.querySelector('.lightbox-image');
  const displayTitle = lightbox.querySelector('.lightbox-display-title');
  const albumLabel = lightbox.querySelector('.lightbox-album');
  const currentLabel = lightbox.querySelector('[data-lightbox-current]');
  const totalLabel = lightbox.querySelector('[data-lightbox-total]');
  const detailAlbum = lightbox.querySelector('[data-lightbox-detail-album]');
  const detailDate = lightbox.querySelector('[data-lightbox-date]');
  const detailDateRow = lightbox.querySelector('[data-lightbox-date-row]');
  const detailDescription = lightbox.querySelector('[data-lightbox-description]');
  const scrubber = lightbox.querySelector('.lightbox-scrubber');
  const closeButton = lightbox.querySelector('.lightbox-close');
  const thumbnails = lightbox.querySelector('.lightbox-thumbnails');
  const thumbnailButtons = [];

  const renderDisplayTitle = (text) => {
    const characters = Array.from(text.trim()).slice(0, 14);
    displayTitle.style.setProperty('--title-count', String(Math.max(1, characters.filter((character) => !/\s/.test(character)).length)));
    displayTitle.replaceChildren(...characters.map((character) => {
      const span = document.createElement('span');
      span.textContent = character;
      if (/\s/.test(character)) span.className = 'is-space';
      return span;
    }));
  };

  const getContrastColor = (hex) => {
    const normalized = hex.replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(normalized)) return '#16191b';
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    return (red * 299 + green * 587 + blue * 114) / 1000 > 142 ? '#16191b' : '#f4f6f7';
  };

  albumLabel.textContent = galleryTitle;
  detailAlbum.textContent = galleryTitle;
  totalLabel.textContent = String(items.length).padStart(2, '0');
  scrubber.max = String(Math.max(0, items.length - 1));

  items.forEach((item, index) => {
    const sourceImage = item.querySelector('img');
    const button = document.createElement('button');
    const thumbnail = document.createElement('img');
    button.className = 'lightbox-thumbnail';
    button.type = 'button';
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-label', `查看第 ${index + 1} 张照片`);
    button.dataset.lightboxThumbnail = String(index);
    thumbnail.src = sourceImage?.currentSrc || sourceImage?.src || item.dataset.lightboxImage;
    thumbnail.alt = '';
    button.appendChild(thumbnail);
    button.addEventListener('click', () => show(index, index >= currentIndex ? 1 : -1));
    thumbnails.appendChild(button);
    thumbnailButtons.push(button);
  });

  const preloadAdjacent = () => {
    [-1, 1].forEach((offset) => {
      const item = items[(currentIndex + offset + items.length) % items.length];
      const preloader = new Image();
      preloader.src = item.dataset.lightboxImage;
    });
  };

  const show = (index, direction = 1, opening = false) => {
    currentIndex = (index + items.length) % items.length;
    const item = items[currentIndex];
    const itemTitle = item.dataset.lightboxTitle || galleryTitle;
    const headline = item.dataset.lightboxHeadline || itemTitle;
    const description = item.dataset.lightboxDescription || item.dataset.lightboxCaption || '';
    const date = item.dataset.lightboxDate || '';
    image.classList.remove('is-entering-forward', 'is-entering-backward');
    displayTitle.classList.remove('is-shifting-forward', 'is-shifting-backward');
    void image.offsetWidth;

    lightbox.dataset.layout = item.dataset.lightboxLayout || 'standard';
    lightbox.dataset.textPosition = item.dataset.lightboxTextPosition || 'spread';
    const background = item.dataset.lightboxBackground || '#f3f3f0';
    lightbox.style.setProperty('--lightbox-accent', item.dataset.lightboxAccent || '#009fe8');
    lightbox.style.setProperty('--lightbox-background', background);
    lightbox.style.setProperty('--lightbox-ink', getContrastColor(background));
    lightbox.style.setProperty('--lightbox-focus-x', `${item.dataset.lightboxFocusX || 50}%`);
    lightbox.style.setProperty('--lightbox-focus-y', `${item.dataset.lightboxFocusY || 50}%`);

    renderDisplayTitle(headline);
    albumLabel.textContent = itemTitle;
    detailAlbum.textContent = itemTitle;
    detailDate.textContent = date.replaceAll('-', '.');
    detailDateRow.hidden = !date;
    detailDescription.textContent = description;
    detailDescription.hidden = !description;
    image.src = item.dataset.lightboxImage;
    image.alt = description;
    currentLabel.textContent = formatIndex(currentIndex);
    scrubber.value = String(currentIndex);
    image.classList.add(direction >= 0 ? 'is-entering-forward' : 'is-entering-backward');
    displayTitle.classList.add(direction >= 0 ? 'is-shifting-forward' : 'is-shifting-backward');
    thumbnailButtons.forEach((button, buttonIndex) => {
      const isActive = buttonIndex === currentIndex;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', String(isActive));
      button.tabIndex = isActive ? 0 : -1;
    });
    thumbnailButtons[currentIndex]?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: opening ? 'auto' : 'smooth' });
    syncHomePreview(currentIndex);
    lightbox.classList.add('is-open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    preloadAdjacent();
    if (opening) window.requestAnimationFrame(() => closeButton.focus());
  };

  const close = () => {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    previousFocus?.focus();
  };

  triggers.forEach((item) => item.addEventListener('click', () => {
    previousFocus = item;
    show(Number(item.dataset.lightboxIndex), 1, true);
  }));
  scrubber.addEventListener('input', () => {
    const nextIndex = Number(scrubber.value);
    show(nextIndex, nextIndex >= currentIndex ? 1 : -1);
  });
  closeButton.addEventListener('click', close);
  lightbox.querySelector('.lightbox-prev').addEventListener('click', () => show(currentIndex - 1, -1));
  lightbox.querySelector('.lightbox-next').addEventListener('click', () => show(currentIndex + 1, 1));
  lightbox.addEventListener('click', (event) => { if (event.target === lightbox) close(); });
  lightbox.addEventListener('wheel', (event) => {
    if (event.target.matches('input[type="range"]')) return;
    event.preventDefault();
    if (wheelLocked || Math.abs(event.deltaY) < 18) return;
    wheelLocked = true;
    const direction = event.deltaY > 0 ? 1 : -1;
    show(currentIndex + direction, direction);
    window.setTimeout(() => { wheelLocked = false; }, 650);
  }, { passive: false });
  lightbox.addEventListener('touchstart', (event) => {
    touchStartX = event.changedTouches[0].clientX;
  }, { passive: true });
  lightbox.addEventListener('touchend', (event) => {
    const distance = event.changedTouches[0].clientX - touchStartX;
    if (Math.abs(distance) < 48) return;
    const direction = distance < 0 ? 1 : -1;
    show(currentIndex + direction, direction);
  }, { passive: true });
  document.addEventListener('keydown', (event) => {
    if (!lightbox.classList.contains('is-open')) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft') show(currentIndex - 1, -1);
    if (event.key === 'ArrowRight') show(currentIndex + 1, 1);
  });

  if (homeRail && window.matchMedia('(hover: hover) and (prefers-reduced-motion: no-preference)').matches) {
    const home = homeRail.closest('.kinetic-home');
    let railFrame = 0;
    home?.addEventListener('pointermove', (event) => {
      if (railFrame) return;
      railFrame = window.requestAnimationFrame(() => {
        const progress = event.clientX / window.innerWidth - 0.5;
        homeRail.style.setProperty('--rail-drift', `${progress * -22}px`);
        railFrame = 0;
      });
    });
    home?.addEventListener('pointerleave', () => {
      homeRail.style.setProperty('--rail-drift', '0px');
    });
  }
})();
