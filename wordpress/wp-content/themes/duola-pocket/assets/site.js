(() => {
  const menuButton = document.querySelector('.menu-toggle');
  const navigation = document.querySelector('.site-nav');

  if (menuButton && navigation) {
    menuButton.addEventListener('click', () => {
      const isOpen = navigation.classList.toggle('is-open');
      menuButton.setAttribute('aria-expanded', String(isOpen));
    });
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
