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

  if (collage) {
    const carouselNotes = Array.from(collage.querySelectorAll('[data-collage-note]'));
    const carouselRoot = collage.closest('.memory-board');

    if (carouselNotes.length > 4 && carouselRoot) {
      const slotClasses = ['photo-note-1', 'photo-note-2', 'photo-note-3', 'photo-note-4'];
      const stackCount = collage.querySelector('.photo-stack-count');
      let startIndex = 0;
      let autoPlayTimer = 0;
      let transitionTimer = 0;
      let isInteracting = false;
      let isTransitioning = false;

      const visibleNotes = () => carouselNotes.filter((note) => !note.hidden);

      const renderCarousel = (direction = 0) => {
        const visibleIndexes = Array.from({ length: 4 }, (_, slot) => (
          (startIndex + slot) % carouselNotes.length
        ));

        carouselNotes.forEach((note) => {
          note.hidden = true;
          note.tabIndex = -1;
          note.setAttribute('aria-hidden', 'true');
          note.classList.remove(...slotClasses, 'has-photo-stack', 'is-carousel-entering-next', 'is-carousel-entering-previous');
        });

        visibleIndexes.forEach((noteIndex, slot) => {
          const note = carouselNotes[noteIndex];
          note.hidden = false;
          note.tabIndex = 0;
          note.removeAttribute('aria-hidden');
          note.classList.add(slotClasses[slot], 'is-visible');
          if (direction) note.classList.add(direction > 0 ? 'is-carousel-entering-next' : 'is-carousel-entering-previous');
        });

        const lastVisibleNote = carouselNotes[visibleIndexes[3]];
        lastVisibleNote.classList.add('has-photo-stack');
        if (stackCount) {
          stackCount.querySelector('strong').textContent = `+${carouselNotes.length - 4}`;
          lastVisibleNote.appendChild(stackCount);
        }

        if (direction) {
          window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
              visibleNotes().forEach((note) => note.classList.remove('is-carousel-entering-next', 'is-carousel-entering-previous'));
            });
          });
        }
      };

      const scheduleAutoPlay = () => {
        window.clearTimeout(autoPlayTimer);
        if (reducedMotion || isInteracting || document.hidden || document.body.classList.contains('is-lightbox-open')) return;
        autoPlayTimer = window.setTimeout(() => {
          if (isInteracting || document.hidden || document.body.classList.contains('is-lightbox-open')) return;
          moveCarousel(1);
        }, 5600);
      };

      const moveCarousel = (direction) => {
        if (isTransitioning) return;
        isTransitioning = true;
        window.clearTimeout(autoPlayTimer);

        if (reducedMotion) {
          startIndex = (startIndex + direction + carouselNotes.length) % carouselNotes.length;
          renderCarousel();
          isTransitioning = false;
          return;
        }

        const exitingClass = direction > 0 ? 'is-carousel-exiting-next' : 'is-carousel-exiting-previous';
        visibleNotes().forEach((note) => note.classList.add(exitingClass));
        transitionTimer = window.setTimeout(() => {
          visibleNotes().forEach((note) => note.classList.remove(exitingClass));
          startIndex = (startIndex + direction + carouselNotes.length) % carouselNotes.length;
          renderCarousel(direction);
          isTransitioning = false;
          scheduleAutoPlay();
        }, 280);
      };

      carouselRoot.addEventListener('pointerenter', () => {
        isInteracting = true;
        window.clearTimeout(autoPlayTimer);
      });
      carouselRoot.addEventListener('pointerleave', () => {
        isInteracting = false;
        scheduleAutoPlay();
      });
      carouselRoot.addEventListener('focusin', () => {
        isInteracting = true;
        window.clearTimeout(autoPlayTimer);
      });
      carouselRoot.addEventListener('focusout', () => {
        window.setTimeout(() => {
          isInteracting = carouselRoot.contains(document.activeElement);
          scheduleAutoPlay();
        });
      });
      document.addEventListener('visibilitychange', scheduleAutoPlay);
      document.addEventListener('duola:lightbox-open', () => window.clearTimeout(autoPlayTimer));
      document.addEventListener('duola:lightbox-close', scheduleAutoPlay);
      window.addEventListener('pagehide', () => {
        window.clearTimeout(autoPlayTimer);
        window.clearTimeout(transitionTimer);
      });

      renderCarousel();
      scheduleAutoPlay();
    }
  }

  const triggers = Array.from(document.querySelectorAll('[data-lightbox-image]'));
  if (!triggers.length) return;

  let currentIndex = 0;
  let previousFocus = null;
  let touchStartX = 0;
  let touchStartY = 0;
  let wheelLocked = false;
  let imageRequest = 0;
  let zoom = 1;
  let panX = 0;
  let panY = 0;
  let activePointerId = null;
  let pointerStartX = 0;
  let pointerStartY = 0;
  let pointerStartPanX = 0;
  let pointerStartPanY = 0;
  let pointerMoved = false;
  let lastTouchTap = 0;

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
      <div class="lightbox-tools">
        <button class="lightbox-zoom" type="button" aria-label="放大照片"><span data-lightbox-zoom-label>1×</span></button>
        <button class="lightbox-close" type="button" aria-label="关闭查看器"><span></span><span></span></button>
      </div>
    </header>
    <div class="lightbox-stage">
      <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张照片">←</button>
      <figure class="lightbox-media">
        <div class="lightbox-loader" aria-hidden="true"><span></span></div>
        <div class="lightbox-viewport"><img src="" alt="" draggable="false"></div>
        <figcaption></figcaption>
      </figure>
      <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张照片">→</button>
    </div>
    <div class="lightbox-progress"><span></span><input type="range" min="0" value="0" step="1" aria-label="滑动预览照片"></div>`;
  document.body.appendChild(lightbox);

  const image = lightbox.querySelector('.lightbox-media img');
  const media = lightbox.querySelector('.lightbox-media');
  const viewport = lightbox.querySelector('.lightbox-viewport');
  const caption = lightbox.querySelector('figcaption');
  const title = lightbox.querySelector('.lightbox-title');
  const current = lightbox.querySelector('[data-lightbox-current]');
  const total = lightbox.querySelector('[data-lightbox-total]');
  const scrubber = lightbox.querySelector('input[type="range"]');
  const closeButton = lightbox.querySelector('.lightbox-close');
  const zoomButton = lightbox.querySelector('.lightbox-zoom');
  const zoomLabel = lightbox.querySelector('[data-lightbox-zoom-label]');
  const progress = lightbox.querySelector('.lightbox-progress span');

  const formatIndex = (index) => String(index + 1).padStart(2, '0');
  const clamp = (value, minimum, maximum) => Math.min(Math.max(value, minimum), maximum);

  const applyZoom = () => {
    if (zoom <= 1) {
      panX = 0;
      panY = 0;
    } else {
      const bounds = viewport.getBoundingClientRect();
      panX = clamp(panX, -bounds.width * (zoom - 1) / 2, bounds.width * (zoom - 1) / 2);
      panY = clamp(panY, -bounds.height * (zoom - 1) / 2, bounds.height * (zoom - 1) / 2);
    }

    image.style.setProperty('--lightbox-zoom', String(zoom));
    image.style.setProperty('--lightbox-pan-x', `${panX}px`);
    image.style.setProperty('--lightbox-pan-y', `${panY}px`);
    lightbox.classList.toggle('is-zoomed', zoom > 1);
    zoomLabel.textContent = Number.isInteger(zoom) ? `${zoom}×` : `${zoom.toFixed(1)}×`;
    zoomButton.setAttribute('aria-label', zoom > 1 ? '恢复原始大小' : '放大照片');
  };

  const setZoom = (nextZoom) => {
    zoom = clamp(Math.round(nextZoom * 4) / 4, 1, 3);
    applyZoom();
  };

  const resetZoom = () => {
    zoom = 1;
    panX = 0;
    panY = 0;
    applyZoom();
  };

  const preloadAdjacent = () => {
    if (triggers.length < 2) return;
    const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    if (connection?.saveData || /(^|-)2g$/.test(connection?.effectiveType || '')) return;

    [-1, 1].forEach((offset) => {
      const item = triggers[(currentIndex + offset + triggers.length) % triggers.length];
      const preloader = new Image();
      preloader.decoding = 'async';
      if (item.dataset.lightboxSrcset) preloader.srcset = item.dataset.lightboxSrcset;
      preloader.sizes = item.dataset.lightboxSizes || '82vw';
      preloader.src = item.dataset.lightboxImage;
    });
  };

  const loadImage = (item, direction) => {
    const request = ++imageRequest;
    const sourceSet = item.dataset.lightboxSrcset || '';
    media.classList.remove('is-forward', 'is-backward');
    lightbox.classList.add('is-loading');
    media.setAttribute('aria-busy', 'true');
    image.classList.remove('is-ready');
    if (sourceSet) image.srcset = sourceSet;
    else image.removeAttribute('srcset');
    image.sizes = item.dataset.lightboxSizes || '82vw';
    image.src = item.dataset.lightboxImage;

    const finish = () => {
      if (request !== imageRequest) return;
      lightbox.classList.remove('is-loading');
      media.setAttribute('aria-busy', 'false');
      image.classList.add('is-ready');
      void media.offsetWidth;
      media.classList.add(direction >= 0 ? 'is-forward' : 'is-backward');
    };

    if (typeof image.decode === 'function') image.decode().catch(() => {}).then(finish);
    else image.addEventListener('load', finish, { once: true });
  };

  const show = (index, direction = 1, opening = false) => {
    currentIndex = (index + triggers.length) % triggers.length;
    const item = triggers[currentIndex];
    const gallery = item.closest('[data-lightbox-gallery]');
    const itemTitle = item.dataset.lightboxTitle || gallery?.dataset.galleryTitle || '照片';
    const itemCaption = item.dataset.lightboxCaption || '';

    resetZoom();
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
    document.dispatchEvent(new CustomEvent('duola:lightbox-open'));
    loadImage(item, direction);
    preloadAdjacent();
    if (opening) window.requestAnimationFrame(() => closeButton.focus());
  };

  const close = () => {
    lightbox.classList.remove('is-open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('is-lightbox-open');
    document.dispatchEvent(new CustomEvent('duola:lightbox-close'));
    imageRequest += 1;
    resetZoom();
    previousFocus?.focus();
  };

  if (triggers.length < 2) lightbox.classList.add('is-single');

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
  zoomButton.addEventListener('click', () => setZoom(zoom >= 3 ? 1 : zoom + 1));
  image.addEventListener('dblclick', () => setZoom(zoom > 1 ? 1 : 2));
  scrubber.addEventListener('input', () => {
    const nextIndex = Number(scrubber.value);
    show(nextIndex, nextIndex >= currentIndex ? 1 : -1);
  });

  lightbox.addEventListener('wheel', (event) => {
    if (event.target === scrubber || Math.abs(event.deltaY) < 18) return;
    if (zoom > 1 || event.ctrlKey) {
      event.preventDefault();
      setZoom(zoom + (event.deltaY < 0 ? 0.25 : -0.25));
      return;
    }
    if (wheelLocked) return;
    event.preventDefault();
    wheelLocked = true;
    const direction = event.deltaY > 0 ? 1 : -1;
    show(currentIndex + direction, direction);
    window.setTimeout(() => { wheelLocked = false; }, 450);
  }, { passive: false });

  lightbox.addEventListener('touchstart', (event) => {
    touchStartX = event.changedTouches[0].clientX;
    touchStartY = event.changedTouches[0].clientY;
  }, { passive: true });
  lightbox.addEventListener('touchend', (event) => {
    if (zoom > 1) return;
    const distance = event.changedTouches[0].clientX - touchStartX;
    const verticalDistance = event.changedTouches[0].clientY - touchStartY;
    if (Math.abs(distance) < 48 || Math.abs(distance) <= Math.abs(verticalDistance)) return;
    const direction = distance < 0 ? 1 : -1;
    show(currentIndex + direction, direction);
  }, { passive: true });

  viewport.addEventListener('pointerdown', (event) => {
    activePointerId = event.pointerId;
    pointerStartX = event.clientX;
    pointerStartY = event.clientY;
    pointerStartPanX = panX;
    pointerStartPanY = panY;
    pointerMoved = false;
    if (zoom > 1) {
      event.preventDefault();
      viewport.setPointerCapture(event.pointerId);
      viewport.classList.add('is-dragging');
    }
  });

  viewport.addEventListener('pointermove', (event) => {
    if (event.pointerId !== activePointerId || zoom <= 1) return;
    const distanceX = event.clientX - pointerStartX;
    const distanceY = event.clientY - pointerStartY;
    pointerMoved = pointerMoved || Math.abs(distanceX) > 4 || Math.abs(distanceY) > 4;
    panX = pointerStartPanX + distanceX;
    panY = pointerStartPanY + distanceY;
    applyZoom();
  });

  const releasePointer = (event) => {
    if (event.pointerId !== activePointerId) return;
    if (viewport.hasPointerCapture(event.pointerId)) viewport.releasePointerCapture(event.pointerId);
    viewport.classList.remove('is-dragging');

    if (event.pointerType === 'touch' && !pointerMoved) {
      const now = Date.now();
      if (now - lastTouchTap < 320) {
        setZoom(zoom > 1 ? 1 : 2);
        lastTouchTap = 0;
      } else {
        lastTouchTap = now;
      }
    }

    activePointerId = null;
  };

  viewport.addEventListener('pointerup', releasePointer);
  viewport.addEventListener('pointercancel', releasePointer);

  document.addEventListener('keydown', (event) => {
    if (!lightbox.classList.contains('is-open')) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft' && zoom <= 1) show(currentIndex - 1, -1);
    if (event.key === 'ArrowRight' && zoom <= 1) show(currentIndex + 1, 1);
    if (event.key === '+' || event.key === '=') setZoom(zoom + 1);
    if (event.key === '-') setZoom(zoom - 1);
    if (event.key === '0') resetZoom();
  });

  const requestedPhoto = new URLSearchParams(window.location.search).get('duola_photo');
  if (requestedPhoto) {
    const requestedIndex = triggers.findIndex((trigger) => trigger.dataset.lightboxKey === requestedPhoto);
    if (requestedIndex >= 0) window.setTimeout(() => show(requestedIndex, 1, true), 180);
  }
})();
