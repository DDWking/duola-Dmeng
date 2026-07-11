(() => {
  document.documentElement.classList.add('motion-ready');

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const revealItems = Array.from(document.querySelectorAll(
    '.home-note-card, .photo-note, .page-intro, .album-card, .post-row, .photo-button, .article, .page-content',
  ));

  revealItems.forEach((item, index) => {
    item.classList.add('reveal-item');
    item.style.setProperty('--reveal-delay', `${(index % 6) * 55}ms`);
  });

  if (!reducedMotion && 'IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-visible');
        revealObserver.unobserve(entry.target);
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -3% 0px' });
    revealItems.forEach((item) => revealObserver.observe(item));
  } else {
    revealItems.forEach((item) => item.classList.add('is-visible'));
  }

  const collage = document.querySelector('[data-memory-collage]');
  if (collage && !reducedMotion && window.matchMedia('(pointer: fine)').matches) {
    const notes = Array.from(collage.querySelectorAll('[data-collage-note]'));
    collage.addEventListener('pointermove', (event) => {
      const bounds = collage.getBoundingClientRect();
      const offsetX = (event.clientX - bounds.left) / bounds.width - 0.5;
      const offsetY = (event.clientY - bounds.top) / bounds.height - 0.5;
      notes.forEach((note) => {
        const depth = Number(note.dataset.depth || 0.5);
        note.style.setProperty('--parallax-x', `${offsetX * depth * 22}px`);
        note.style.setProperty('--parallax-y', `${offsetY * depth * 16}px`);
      });
    });
    collage.addEventListener('pointerleave', () => {
      notes.forEach((note) => {
        note.style.setProperty('--parallax-x', '0px');
        note.style.setProperty('--parallax-y', '0px');
      });
    });
  }

  const triggers = Array.from(document.querySelectorAll('[data-lightbox-image]'));
  if (!triggers.length) return;

  let currentIndex = 0;
  let previousFocus = null;
  let touchStartX = 0;
  let wheelLocked = false;

  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.setAttribute('role', 'dialog');
  lightbox.setAttribute('aria-modal', 'true');
  lightbox.setAttribute('aria-label', '照片查看器');
  lightbox.setAttribute('aria-hidden', 'true');
  lightbox.innerHTML = `
    <div class="lightbox-backdrop" data-lightbox-close></div>
    <header class="lightbox-header">
      <div class="lightbox-title"></div>
      <div class="lightbox-counter" aria-live="polite"><span data-lightbox-current>01</span><i>/</i><span data-lightbox-total>01</span></div>
      <button class="lightbox-close" type="button" aria-label="关闭查看器"><span></span><span></span></button>
    </header>
    <div class="lightbox-stage">
      <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张照片">←</button>
      <figure class="lightbox-media">
        <img src="" alt="">
        <figcaption></figcaption>
      </figure>
      <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张照片">→</button>
    </div>
    <div class="lightbox-progress"><span></span><input type="range" min="0" value="0" step="1" aria-label="滑动预览照片"></div>`;
  document.body.appendChild(lightbox);

  const image = lightbox.querySelector('.lightbox-media img');
  const media = lightbox.querySelector('.lightbox-media');
  const caption = lightbox.querySelector('figcaption');
  const title = lightbox.querySelector('.lightbox-title');
  const current = lightbox.querySelector('[data-lightbox-current]');
  const total = lightbox.querySelector('[data-lightbox-total]');
  const scrubber = lightbox.querySelector('input[type="range"]');
  const closeButton = lightbox.querySelector('.lightbox-close');
  const progress = lightbox.querySelector('.lightbox-progress span');

  const formatIndex = (index) => String(index + 1).padStart(2, '0');

  const preloadAdjacent = () => {
    [-1, 1].forEach((offset) => {
      const item = triggers[(currentIndex + offset + triggers.length) % triggers.length];
      const preloader = new Image();
      preloader.src = item.dataset.lightboxImage;
    });
  };

  const show = (index, direction = 1, opening = false) => {
    currentIndex = (index + triggers.length) % triggers.length;
    const item = triggers[currentIndex];
    const gallery = item.closest('[data-lightbox-gallery]');
    const itemTitle = item.dataset.lightboxTitle || gallery?.dataset.galleryTitle || '照片';
    const itemCaption = item.dataset.lightboxCaption || '';

    media.classList.remove('is-forward', 'is-backward');
    void media.offsetWidth;
    image.src = item.dataset.lightboxImage;
    image.alt = itemTitle;
    title.textContent = itemTitle;
    caption.textContent = itemCaption;
    caption.hidden = !itemCaption;
    current.textContent = formatIndex(currentIndex);
    total.textContent = formatIndex(triggers.length - 1);
    scrubber.max = String(Math.max(0, triggers.length - 1));
    scrubber.value = String(currentIndex);
    progress.style.setProperty('--progress', `${((currentIndex + 1) / triggers.length) * 100}%`);
    media.classList.add(direction >= 0 ? 'is-forward' : 'is-backward');
    lightbox.classList.add('is-open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.classList.add('is-lightbox-open');
    preloadAdjacent();
    if (opening) window.requestAnimationFrame(() => closeButton.focus());
  };

  const close = () => {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('is-lightbox-open');
    previousFocus?.focus();
  };

  triggers.forEach((trigger, index) => {
    trigger.addEventListener('click', () => {
      previousFocus = trigger;
      show(index, 1, true);
    });
  });

  lightbox.querySelector('.lightbox-prev').addEventListener('click', () => show(currentIndex - 1, -1));
  lightbox.querySelector('.lightbox-next').addEventListener('click', () => show(currentIndex + 1, 1));
  lightbox.querySelector('[data-lightbox-close]').addEventListener('click', close);
  closeButton.addEventListener('click', close);
  scrubber.addEventListener('input', () => {
    const nextIndex = Number(scrubber.value);
    show(nextIndex, nextIndex >= currentIndex ? 1 : -1);
  });

  lightbox.addEventListener('wheel', (event) => {
    if (event.target === scrubber || Math.abs(event.deltaY) < 18 || wheelLocked) return;
    event.preventDefault();
    wheelLocked = true;
    const direction = event.deltaY > 0 ? 1 : -1;
    show(currentIndex + direction, direction);
    window.setTimeout(() => { wheelLocked = false; }, 450);
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

  const requestedPhoto = new URLSearchParams(window.location.search).get('duola_photo');
  if (requestedPhoto) {
    const requestedIndex = triggers.findIndex((trigger) => trigger.dataset.lightboxKey === requestedPhoto);
    if (requestedIndex >= 0) window.setTimeout(() => show(requestedIndex, 1, true), 180);
  }
})();
