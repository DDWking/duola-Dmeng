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

  const parseJsonScript = (selector, fallback = {}) => {
    const script = document.querySelector(selector);
    if (!script) return fallback;
    try {
      return JSON.parse(script.textContent || '') || fallback;
    } catch (error) {
      return fallback;
    }
  };

  const homeLayout = parseJsonScript('#duola-home-visual-layout', {});
  const photoScenes = parseJsonScript('#duola-photo-scenes', {});
  const mobileMedia = window.matchMedia('(max-width: 760px)');
  const applyStyle = (element, style = {}) => {
    element.removeAttribute('style');
    Object.entries(style).forEach(([property, value]) => element.style.setProperty(property, value));
  };

  const autoMobileStyle = (element, settings) => {
    const map = {
      'home-articles': { position: 'absolute', left: '4%', top: '13%', width: '92%', height: '22%', 'z-index': '3' },
      'home-preview': { position: 'absolute', left: '4%', top: '7%', width: '92%', height: '3%', 'z-index': '5' },
      'home-rail': { position: 'absolute', left: '4%', top: '38%', width: '104%', height: '26%', 'z-index': '2' },
      'home-link': { position: 'absolute', left: '4%', top: '68%', width: '7rem', height: '1.5rem', 'z-index': '3', 'text-align': 'left' },
      photo: { position: 'absolute', left: '3%', top: '20%', width: '94%', height: '58%', 'z-index': '2', 'object-fit': 'cover', 'object-position': `${settings.focus_x || 50}% ${settings.focus_y || 50}%` },
      headline: { position: 'absolute', left: '3%', top: '40%', width: '94%', height: '24%', 'z-index': '4', color: settings.accent || '#009fe8', 'font-size': '18vw', 'font-weight': '900', 'line-height': '.8', 'text-align': 'center' },
      date: { position: 'absolute', left: '4%', bottom: '5%', width: '12rem', height: '1.5rem', 'z-index': '5', 'font-size': '.62rem', 'font-weight': '700' },
      description: { position: 'absolute', left: '4%', bottom: '2%', width: '82%', 'min-height': '2rem', 'z-index': '5', 'font-size': '.62rem', 'font-weight': '700', 'text-align': 'left' },
    };
    if (map[element.type]) return map[element.type];
    return { ...(element.desktop || {}), width: element.desktop?.width || ('image' === element.type ? '38%' : '70%'), 'font-size': 'text' === element.type ? '8vw' : element.desktop?.['font-size'] };
  };

  const styleFor = (element, layout) => mobileMedia.matches
    ? { ...autoMobileStyle(element, layout.settings || {}), ...(element.mobile || {}) }
    : { ...(element.desktop || {}) };

  const applyHomeLayout = () => {
    if (!homeLayout.elements) return;
    const home = document.querySelector('.kinetic-home');
    if (!home) return;
    home.querySelectorAll('.home-visual-decoration').forEach((element) => element.remove());
    const fixedTargets = {
      'home-articles': document.querySelector('#duola-home-articles'),
      'home-preview': document.querySelector('#duola-home-preview'),
      'home-rail': document.querySelector('#duola-home-rail'),
      'home-link': document.querySelector('#duola-home-link'),
    };
    home.style.background = homeLayout.settings?.background || '#111315';
    homeLayout.elements.forEach((element) => {
      let target = fixedTargets[element.type];
      if (!target && ['text', 'image'].includes(element.type)) {
        target = document.createElement('image' === element.type ? 'img' : 'div');
        target.className = `home-visual-decoration is-${element.type}`;
        target.dataset.visualId = element.id;
        if ('image' === element.type) {
          target.src = element.src;
          target.alt = '';
        } else {
          target.textContent = element.content;
        }
        home.appendChild(target);
      }
      if (target) applyStyle(target, styleFor(element, homeLayout));
    });
  };
  applyHomeLayout();
  mobileMedia.addEventListener?.('change', applyHomeLayout);

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
  const homeTrack = document.querySelector('[data-home-photo-track]');
  const homePreview = document.querySelector('[data-home-preview-control]');
  const homePreviewInput = homePreview?.querySelector('input[type="range"]');
  const homePreviewCurrent = homePreview?.querySelector('[data-home-preview-current]');
  const homePreviewTicks = homePreview?.querySelector('.home-preview-track > span');
  let waveTarget = 0;
  let waveCurrent = 0;
  let waveLatency = 0;
  let sliderDragging = false;
  let railDragging = false;
  let railDragged = false;
  let dragStartX = 0;
  let dragStartTarget = 0;
  let wheelSnapTimer = 0;

  const clampWave = (value) => Math.max(0, Math.min(items.length - 1, value));
  const setWaveTarget = (value) => { waveTarget = clampWave(Number(value) || 0); };
  const setActiveSlice = (index) => {
    triggers.forEach((trigger) => trigger.classList.remove('is-previewed'));
    triggers.find((trigger) => Number(trigger.dataset.lightboxIndex) === index)?.classList.add('is-previewed');
    if (homePreviewCurrent) homePreviewCurrent.textContent = formatIndex(index);
  };

  if (homeRail && homeTrack && homePreviewInput) {
    const wave = homeLayout.settings || {};
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    homeRail.classList.add('is-physics-ready');
    triggers.forEach((trigger, index) => {
      trigger.dataset.wavePosition = String(index);
      trigger.addEventListener('pointerenter', () => {
        if (!railDragging && !sliderDragging && index < items.length) setWaveTarget(trigger.dataset.lightboxIndex);
      });
      trigger.addEventListener('click', (event) => {
        if (!railDragged) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        railDragged = false;
      }, true);
    });
    homePreviewInput.addEventListener('pointerdown', () => { sliderDragging = true; });
    homePreviewInput.addEventListener('input', () => setWaveTarget(homePreviewInput.value));
    homePreviewInput.addEventListener('pointerup', () => {
      sliderDragging = false;
      setWaveTarget(Math.round(waveTarget));
    });
    homeRail.addEventListener('pointerdown', (event) => {
      if (event.button !== 0) return;
      railDragging = true;
      railDragged = false;
      dragStartX = event.clientX;
      dragStartTarget = waveTarget;
      homeRail.setPointerCapture(event.pointerId);
    });
    homeRail.addEventListener('pointermove', (event) => {
      if (!railDragging) return;
      const distance = event.clientX - dragStartX;
      if (Math.abs(distance) > 5) railDragged = true;
      setWaveTarget(dragStartTarget - distance / Math.max(90, homeRail.clientWidth / Math.max(4, items.length + 1)));
    });
    const endRailDrag = (event) => {
      if (!railDragging) return;
      railDragging = false;
      if (homeRail.hasPointerCapture(event.pointerId)) homeRail.releasePointerCapture(event.pointerId);
      setWaveTarget(Math.round(waveTarget));
      window.setTimeout(() => { railDragged = false; }, 80);
    };
    homeRail.addEventListener('pointerup', endRailDrag);
    homeRail.addEventListener('pointercancel', endRailDrag);
    homeRail.addEventListener('wheel', (event) => {
      if (mobileMedia.matches) return;
      event.preventDefault();
      const delta = Math.abs(event.deltaX) > Math.abs(event.deltaY) ? event.deltaX : event.deltaY;
      setWaveTarget(waveTarget + delta / 260);
      window.clearTimeout(wheelSnapTimer);
      wheelSnapTimer = window.setTimeout(() => setWaveTarget(Math.round(waveTarget)), 180);
    }, { passive: false });

    const animateWave = () => {
      const damping = reducedMotion ? 1 : (Number(wave.wave_damping || 12) / 100);
      const latency = reducedMotion ? 1 : (Number(wave.wave_latency || 7) / 100);
      waveCurrent += (waveTarget - waveCurrent) * damping;
      waveLatency += (waveCurrent - waveLatency) * latency;
      const velocity = waveCurrent - waveLatency;
      const activeIndex = Math.round(waveCurrent);
      const amplitude = reducedMotion ? 0 : Number(wave.wave_amplitude || 28);
      const expansion = Number(wave.wave_expansion || 96);
      const rotation = reducedMotion ? 0 : Number(wave.wave_rotation || 4);

      if (!sliderDragging) homePreviewInput.value = String(waveCurrent);
      setActiveSlice(activeIndex);
      if (homePreviewTicks) {
        homePreviewTicks.style.backgroundPositionX = `${waveCurrent * -11}px`;
        homePreviewTicks.style.transform = `translate3d(${velocity * -18}px, -50%, 0) skewX(${velocity * -8}deg)`;
      }
      triggers.forEach((trigger) => {
        const position = Number(trigger.dataset.wavePosition || 0);
        const distance = position - waveCurrent;
        const proximity = Math.exp(-(distance * distance) * 1.15);
        const base = trigger.classList.contains('is-width-wide') ? 112 : trigger.classList.contains('is-width-narrow') ? 58 : 76;
        const width = base + proximity * expansion;
        const energy = Math.min(1, Math.abs(velocity) * 8 + proximity * .15);
        const lift = Math.sin(distance * 1.12 - velocity * 3.4) * amplitude * energy;
        const tilt = Math.max(-12, Math.min(12, -velocity * rotation * 5 + Math.sin(distance) * rotation * (1 - proximity)));
        trigger.style.flexBasis = `${width}px`;
        trigger.style.opacity = String(.34 + proximity * .66);
        trigger.style.filter = `grayscale(${1 - proximity}) brightness(${.48 + proximity * .52})`;
        trigger.style.transform = `translate3d(0, ${lift}px, 0) rotate(${tilt}deg) scaleY(${1 + Math.abs(velocity) * .06})`;
      });
      if (mobileMedia.matches) {
        const offset = window.innerWidth * .34 - waveCurrent * 82;
        homeTrack.style.transform = `translate3d(${offset}px, 0, 0)`;
      } else {
        homeTrack.style.transform = 'translate3d(0, 0, 0)';
      }
      window.requestAnimationFrame(animateWave);
    };
    animateWave();
  }

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
    <div class="lightbox-scene" data-lightbox-scene></div>
    <header class="lightbox-header">
      <span class="lightbox-album"></span>
      <div class="lightbox-progress">
        <div class="lightbox-counter" aria-live="polite"><span data-lightbox-current>01</span><span>/</span><span data-lightbox-total>01</span></div>
        <div class="lightbox-scrubber-track"><span aria-hidden="true"></span><input class="lightbox-scrubber" type="range" min="0" value="0" step="1" aria-label="滑动预览照片"></div>
      </div>
      <button class="lightbox-close" type="button" aria-label="关闭查看器">×</button>
    </header>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张照片">←</button>
    <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张照片">→</button>`;
  document.body.appendChild(lightbox);

  const sceneRoot = lightbox.querySelector('[data-lightbox-scene]');
  const albumLabel = lightbox.querySelector('.lightbox-album');
  const currentLabel = lightbox.querySelector('[data-lightbox-current]');
  const totalLabel = lightbox.querySelector('[data-lightbox-total]');
  const scrubber = lightbox.querySelector('.lightbox-scrubber');
  const closeButton = lightbox.querySelector('.lightbox-close');

  const getContrastColor = (hex) => {
    const normalized = hex.replace('#', '');
    if (!/^[0-9a-f]{6}$/i.test(normalized)) return '#16191b';
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    return (red * 299 + green * 587 + blue * 114) / 1000 > 142 ? '#16191b' : '#f4f6f7';
  };

  const renderScene = (scene, item, title) => {
    const fallback = {
      settings: { background: '#f3f3f0', accent: '#009fe8', focus_x: 50, focus_y: 50 },
      elements: [
        { id: 'photo', type: 'photo', src: item.dataset.lightboxImage, content: '', desktop: { position: 'absolute', left: '16%', top: '17%', width: '68%', height: '66%', 'z-index': '2', 'object-fit': 'cover' }, mobile: {} },
        { id: 'headline', type: 'headline', content: item.dataset.lightboxHeadline || title, desktop: { position: 'absolute', left: '4%', top: '34%', width: '92%', height: '32%', 'z-index': '4', color: '#009fe8', 'font-size': '9vw', 'font-weight': '900', 'line-height': '.78', 'text-align': 'center' }, mobile: {} },
      ],
    };
    const activeScene = scene?.elements?.length ? scene : fallback;
    const settings = activeScene.settings || fallback.settings;
    const background = settings.background || '#f3f3f0';
    lightbox.style.setProperty('--lightbox-background', background);
    lightbox.style.setProperty('--lightbox-accent', settings.accent || '#009fe8');
    lightbox.style.setProperty('--lightbox-ink', getContrastColor(background));
    sceneRoot.replaceChildren();
    activeScene.elements.forEach((element) => {
      let node;
      if (['photo', 'image'].includes(element.type)) {
        node = document.createElement('img');
        node.src = 'photo' === element.type ? item.dataset.lightboxImage : element.src;
        node.alt = '';
      } else {
        node = document.createElement('div');
        let content = element.content || '';
        if ('headline' === element.type && !content) content = item.dataset.lightboxHeadline || title;
        if ('date' === element.type && !content) content = item.dataset.lightboxDate || '';
        if ('description' === element.type && !content) content = item.dataset.lightboxDescription || item.dataset.lightboxCaption || '';
        node.textContent = 'date' === element.type ? content.replaceAll('-', '.') : content;
      }
      node.className = `lightbox-scene-element is-${element.type}`;
      node.dataset.sceneId = element.id;
      applyStyle(node, styleFor(element, activeScene));
      sceneRoot.appendChild(node);
    });
    sceneRoot.classList.remove('is-entering-forward', 'is-entering-backward');
    void sceneRoot.offsetWidth;
  };

  albumLabel.textContent = galleryTitle;
  totalLabel.textContent = String(items.length).padStart(2, '0');
  scrubber.max = String(Math.max(0, items.length - 1));

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
    const key = item.dataset.lightboxKey || item.dataset.lightboxImage;
    renderScene(photoScenes[key], item, itemTitle);
    albumLabel.textContent = itemTitle;
    currentLabel.textContent = formatIndex(currentIndex);
    scrubber.value = String(currentIndex);
    sceneRoot.classList.add(direction >= 0 ? 'is-entering-forward' : 'is-entering-backward');
    setWaveTarget(currentIndex);
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
    if (event.target instanceof Element && event.target.matches('input[type="range"]')) return;
    event.preventDefault();
    if (wheelLocked || Math.abs(event.deltaY) < 18) return;
    wheelLocked = true;
    const direction = event.deltaY > 0 ? 1 : -1;
    show(currentIndex + direction, direction);
    window.setTimeout(() => { wheelLocked = false; }, 650);
  }, { passive: false });
  lightbox.addEventListener('touchstart', (event) => { touchStartX = event.changedTouches[0].clientX; }, { passive: true });
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
  if (requestedPhoto && itemIndexByKey.has(requestedPhoto)) {
    window.setTimeout(() => show(itemIndexByKey.get(requestedPhoto), 1, true), 250);
  }
})();
