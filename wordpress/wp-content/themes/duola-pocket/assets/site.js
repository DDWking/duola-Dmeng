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

  const items = Array.from(gallery.querySelectorAll('[data-lightbox-image]'));
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
    <div class="lightbox-backdrop-title" aria-hidden="true"></div>
    <header class="lightbox-header">
      <span class="lightbox-album"></span>
      <div class="lightbox-counter" aria-live="polite"><span data-lightbox-current>01</span><span>/</span><span data-lightbox-total>01</span></div>
      <button class="lightbox-close" type="button" aria-label="关闭查看器">×</button>
    </header>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张照片">←</button>
    <div class="lightbox-stage">
      <div class="lightbox-frame"><img class="lightbox-image" alt=""></div>
      <div class="lightbox-caption"></div>
    </div>
    <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张照片">→</button>
    <div class="lightbox-thumbnails" role="tablist" aria-label="照片缩略图"></div>`;
  document.body.appendChild(lightbox);

  const image = lightbox.querySelector('.lightbox-image');
  const caption = lightbox.querySelector('.lightbox-caption');
  const backdropTitle = lightbox.querySelector('.lightbox-backdrop-title');
  const albumLabel = lightbox.querySelector('.lightbox-album');
  const currentLabel = lightbox.querySelector('[data-lightbox-current]');
  const totalLabel = lightbox.querySelector('[data-lightbox-total]');
  const closeButton = lightbox.querySelector('.lightbox-close');
  const thumbnails = lightbox.querySelector('.lightbox-thumbnails');
  const thumbnailButtons = [];
  const formatIndex = (index) => String(index + 1).padStart(2, '0');

  backdropTitle.textContent = galleryTitle;
  albumLabel.textContent = galleryTitle;
  totalLabel.textContent = String(items.length).padStart(2, '0');

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
    image.classList.remove('is-entering-forward', 'is-entering-backward');
    backdropTitle.classList.remove('is-shifting-forward', 'is-shifting-backward');
    void image.offsetWidth;
    image.src = item.dataset.lightboxImage;
    image.alt = item.dataset.lightboxCaption || '';
    caption.textContent = item.dataset.lightboxCaption || '';
    caption.hidden = !item.dataset.lightboxCaption;
    currentLabel.textContent = formatIndex(currentIndex);
    image.classList.add(direction >= 0 ? 'is-entering-forward' : 'is-entering-backward');
    backdropTitle.classList.add(direction >= 0 ? 'is-shifting-forward' : 'is-shifting-backward');
    thumbnailButtons.forEach((button, buttonIndex) => {
      const isActive = buttonIndex === currentIndex;
      button.classList.toggle('is-active', isActive);
      button.setAttribute('aria-selected', String(isActive));
      button.tabIndex = isActive ? 0 : -1;
    });
    thumbnailButtons[currentIndex]?.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: opening ? 'auto' : 'smooth' });
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

  items.forEach((item, index) => item.addEventListener('click', () => {
    previousFocus = item;
    show(index, 1, true);
  }));
  closeButton.addEventListener('click', close);
  lightbox.querySelector('.lightbox-prev').addEventListener('click', () => show(currentIndex - 1, -1));
  lightbox.querySelector('.lightbox-next').addEventListener('click', () => show(currentIndex + 1, 1));
  lightbox.addEventListener('click', (event) => { if (event.target === lightbox) close(); });
  lightbox.addEventListener('wheel', (event) => {
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
})();
