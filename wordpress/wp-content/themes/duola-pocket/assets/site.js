(() => {
  const systemClock = document.querySelector('[data-system-clock]');
  if (systemClock) {
    const updateClock = () => {
      const now = new Date();
      systemClock.dateTime = now.toISOString();
      systemClock.textContent = new Intl.DateTimeFormat('zh-CN', {
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
      }).format(now).replace('/', '月').replace(' ', '日 ');
    };
    updateClock();
    window.setInterval(updateClock, 30000);
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
