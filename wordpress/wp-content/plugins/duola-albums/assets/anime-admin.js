(() => {
  const posterButton = document.querySelector('#duola-anime-select-poster');
  const removePosterButton = document.querySelector('#duola-anime-remove-poster');
  const posterInput = document.querySelector('#duola-anime-poster-id');
  const posterPreview = document.querySelector('#duola-anime-poster-preview');

  if (posterButton && posterInput && posterPreview && window.wp?.media) {
    let frame;
    posterButton.addEventListener('click', () => {
      if (!frame) {
        frame = window.wp.media({
          title: window.duolaAnimeAdmin?.posterTitle || '选择动画海报',
          button: { text: window.duolaAnimeAdmin?.posterButton || '使用这张海报' },
          library: { type: 'image' },
          multiple: false,
        });
        frame.on('select', () => {
          const attachment = frame.state().get('selection').first()?.toJSON();
          if (!attachment) return;
          const previewUrl = attachment.sizes?.medium?.url || attachment.sizes?.thumbnail?.url || attachment.url;
          const previewImage = document.createElement('img');
          previewImage.src = previewUrl;
          previewImage.alt = '';
          posterInput.value = String(attachment.id);
          posterPreview.replaceChildren(previewImage);
          posterPreview.classList.add('has-image');
          if (removePosterButton) removePosterButton.hidden = false;
        });
      }
      frame.open();
    });

    removePosterButton?.addEventListener('click', () => {
      const placeholder = document.createElement('span');
      placeholder.className = 'dashicons dashicons-format-image';
      placeholder.setAttribute('aria-hidden', 'true');
      posterInput.value = '';
      posterPreview.replaceChildren(placeholder);
      posterPreview.classList.remove('has-image');
      removePosterButton.hidden = true;
    });
  }

  const relationSearch = document.querySelector('#duola-anime-relation-search');
  const relationOptions = Array.from(document.querySelectorAll('[data-anime-option]'));
  const relationEmpty = document.querySelector('.duola-anime-relation-empty');

  relationSearch?.addEventListener('input', () => {
    const query = relationSearch.value.trim().toLocaleLowerCase();
    let visibleCount = 0;
    relationOptions.forEach((option) => {
      const isVisible = !query || option.textContent.toLocaleLowerCase().includes(query);
      option.hidden = !isVisible;
      if (isVisible) visibleCount += 1;
    });
    if (relationEmpty) relationEmpty.hidden = visibleCount > 0;
  });
})();
