(() => {
  const menuButton = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.site-nav');

  if (menuButton && navigation) {
    const closeMenu = () => {
      navigation.classList.remove('is-open');
      menuButton.setAttribute('aria-expanded', 'false');
    };

    menuButton.addEventListener('click', () => {
      const isOpen = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(isOpen));
    });
    navigation.addEventListener('click', (event) => {
      if (event.target.closest('a')) closeMenu();
    });
    document.addEventListener('click', (event) => {
      if (!navigation.contains(event.target) && !menuButton.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeMenu();
    });
  }

  const carousel = document.querySelector('[data-hero-carousel]');
  if (carousel) {
    const slides = Array.from(carousel.querySelectorAll('[data-hero-slide]'));
    const previousButton = carousel.querySelector('[data-carousel-previous]');
    const nextButton = carousel.querySelector('[data-carousel-next]');
    const toggleButton = carousel.querySelector('[data-carousel-toggle]');
    const status = carousel.querySelector('[data-carousel-status]');
    const interval = Number(carousel.dataset.interval) || 6500;
    let currentIndex = 0;
    let timer = null;
    let pausedByUser = false;
    let pausedByInteraction = false;
    let touchStartX = 0;

    const showSlide = (nextIndex, announce = true) => {
      currentIndex = (nextIndex + slides.length) % slides.length;
      slides.forEach((slide, index) => {
        const isActive = index === currentIndex;
        slide.classList.toggle('is-active', isActive);
        slide.setAttribute('aria-hidden', String(!isActive));
      });
      if (announce && status) {
        status.textContent = `已切换到第 ${currentIndex + 1} 张照片，共 ${slides.length} 张`;
      }
    };

    const stop = () => {
      window.clearInterval(timer);
      timer = null;
    };

    const start = () => {
      stop();
      if (pausedByUser || pausedByInteraction || document.hidden) return;
      timer = window.setInterval(() => showSlide(currentIndex + 1, false), interval);
    };

    const showAndRestart = (nextIndex) => {
      showSlide(nextIndex);
      start();
    };

    previousButton?.addEventListener('click', () => showAndRestart(currentIndex - 1));
    nextButton?.addEventListener('click', () => showAndRestart(currentIndex + 1));
    toggleButton?.addEventListener('click', () => {
      pausedByUser = !pausedByUser;
      toggleButton.textContent = pausedByUser ? '继续' : '暂停';
      toggleButton.setAttribute('aria-label', pausedByUser ? '继续轮播' : '暂停轮播');
      pausedByUser ? stop() : start();
    });

    carousel.addEventListener('mouseenter', () => {
      pausedByInteraction = true;
      stop();
    });
    carousel.addEventListener('mouseleave', () => {
      pausedByInteraction = false;
      start();
    });
    carousel.addEventListener('focusin', () => {
      pausedByInteraction = true;
      stop();
    });
    carousel.addEventListener('focusout', () => {
      pausedByInteraction = false;
      start();
    });
    carousel.addEventListener('touchstart', (event) => {
      touchStartX = event.changedTouches[0].clientX;
    }, { passive: true });
    carousel.addEventListener('touchend', (event) => {
      const distance = event.changedTouches[0].clientX - touchStartX;
      if (Math.abs(distance) > 48) showAndRestart(currentIndex + (distance < 0 ? 1 : -1));
    }, { passive: true });
    document.addEventListener('visibilitychange', start);
    start();
  }

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
  let currentIndex = 0;
  const lightbox = document.createElement('div');
  lightbox.className = 'lightbox';
  lightbox.innerHTML = `
    <button class="lightbox-close" type="button" aria-label="关闭">×</button>
    <button class="lightbox-nav lightbox-prev" type="button" aria-label="上一张">‹</button>
    <img alt="">
    <button class="lightbox-nav lightbox-next" type="button" aria-label="下一张">›</button>
    <div class="lightbox-caption"></div>`;
  document.body.appendChild(lightbox);

  const image = lightbox.querySelector('img');
  const caption = lightbox.querySelector('.lightbox-caption');
  const show = (index) => {
    currentIndex = (index + items.length) % items.length;
    const item = items[currentIndex];
    image.src = item.dataset.lightboxImage;
    image.alt = item.dataset.lightboxCaption || '';
    caption.textContent = item.dataset.lightboxCaption || '';
    lightbox.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    lightbox.classList.remove('is-open');
    document.body.style.overflow = '';
  };

  items.forEach((item, index) => item.addEventListener('click', () => show(index)));
  lightbox.querySelector('.lightbox-close').addEventListener('click', close);
  lightbox.querySelector('.lightbox-prev').addEventListener('click', () => show(currentIndex - 1));
  lightbox.querySelector('.lightbox-next').addEventListener('click', () => show(currentIndex + 1));
  lightbox.addEventListener('click', (event) => { if (event.target === lightbox) close(); });
  document.addEventListener('keydown', (event) => {
    if (!lightbox.classList.contains('is-open')) return;
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowLeft') show(currentIndex - 1);
    if (event.key === 'ArrowRight') show(currentIndex + 1);
  });
})();
