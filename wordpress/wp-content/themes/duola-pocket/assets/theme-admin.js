(($) => {
  const input = $('#duola-site-avatar-id');
  const preview = $('#duola-avatar-preview');
  const removeButton = $('#duola-remove-avatar');

  if (input.length) {
    $('#duola-select-avatar').on('click', () => {
      const frame = wp.media({
        title: duolaAppearance.title,
        button: { text: duolaAppearance.button },
        multiple: false,
        library: { type: 'image' },
      });
      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        input.val(attachment.id);
        preview.attr('src', attachment.sizes?.thumbnail?.url || attachment.url);
        removeButton.prop('hidden', false);
      });
      frame.open();
    });

    removeButton.on('click', () => {
      input.val('0');
      preview.attr('src', duolaAppearance.fallback);
      removeButton.prop('hidden', true);
    });
  }

  const trackList = document.querySelector('[data-music-track-list]');
  const trackTemplate = document.querySelector('#duola-music-track-template');
  const emptyState = document.querySelector('[data-music-empty]');
  const addTrackButton = document.querySelector('[data-add-music-track]');

  if (!trackList || !trackTemplate || !addTrackButton) return;

  const refreshTracks = () => {
    const rows = Array.from(trackList.querySelectorAll('[data-music-track]'));
    rows.forEach((row, index) => {
      row.querySelectorAll('[data-track-field]').forEach((field) => {
        field.name = `duola_music_tracks[${index}][${field.dataset.trackField}]`;
      });
      row.querySelector('[data-move-track="up"]').disabled = index === 0;
      row.querySelector('[data-move-track="down"]').disabled = index === rows.length - 1;
    });
    emptyState.hidden = rows.length > 0;
  };

  const selectMedia = (options, onSelect) => {
    const frame = wp.media({ ...options, multiple: false });
    frame.on('select', () => onSelect(frame.state().get('selection').first().toJSON()));
    frame.open();
  };

  const isLikelyLyrics = (attachment) => {
    const filename = String(attachment.filename || attachment.url || '').toLowerCase();
    const mime = String(attachment.mime || '').toLowerCase();
    return (
      filename.endsWith('.lrc') ||
      filename.endsWith('.txt') ||
      mime.startsWith('text/') ||
      mime === 'application/octet-stream'
    );
  };

  addTrackButton.addEventListener('click', () => {
    trackList.appendChild(trackTemplate.content.cloneNode(true));
    refreshTracks();
    const row = trackList.lastElementChild;
    row.querySelector('[data-select-audio]').focus();
  });

  trackList.addEventListener('click', (event) => {
    const row = event.target.closest('[data-music-track]');
    if (!row) return;

    if (event.target.closest('[data-select-audio]')) {
      selectMedia({
        title: duolaAppearance.audioTitle,
        button: { text: duolaAppearance.audioButton },
        library: { type: 'audio' },
      }, (attachment) => {
        row.querySelector('[data-track-field="audio_id"]').value = attachment.id;
        row.querySelector('[data-audio-filename]').textContent = attachment.filename || attachment.title;
        const titleField = row.querySelector('[data-track-field="title"]');
        if (!titleField.value) titleField.value = attachment.title || '';
      });
      return;
    }

    if (event.target.closest('[data-select-cover]')) {
      selectMedia({
        title: duolaAppearance.coverTitle,
        button: { text: duolaAppearance.coverButton },
        library: { type: 'image' },
      }, (attachment) => {
        row.querySelector('[data-track-field="cover_id"]').value = attachment.id;
        const cover = row.querySelector('[data-cover-preview]');
        const image = cover.querySelector('img');
        image.src = attachment.sizes?.thumbnail?.url || attachment.url;
        image.hidden = false;
        delete cover.dataset.empty;
        row.querySelector('[data-remove-cover]').hidden = false;
      });
      return;
    }

    if (event.target.closest('[data-remove-cover]')) {
      row.querySelector('[data-track-field="cover_id"]').value = '0';
      const cover = row.querySelector('[data-cover-preview]');
      const image = cover.querySelector('img');
      image.removeAttribute('src');
      image.hidden = true;
      cover.dataset.empty = 'true';
      event.target.closest('[data-remove-cover]').hidden = true;
      return;
    }

    if (event.target.closest('[data-select-lyrics]')) {
      selectMedia({
        title: duolaAppearance.lyricsTitle || '选择歌词文件',
        button: { text: duolaAppearance.lyricsButton || '使用此歌词' },
      }, (attachment) => {
        if (!isLikelyLyrics(attachment)) {
          window.alert(duolaAppearance.lyricsInvalid || '请选择 .lrc / .txt 歌词文件');
          return;
        }
        row.querySelector('[data-track-field="lyrics_id"]').value = attachment.id;
        row.querySelector('[data-lyrics-filename]').textContent = attachment.filename || attachment.title || 'lyrics.lrc';
        row.querySelector('[data-remove-lyrics]').hidden = false;
      });
      return;
    }

    if (event.target.closest('[data-remove-lyrics]')) {
      row.querySelector('[data-track-field="lyrics_id"]').value = '0';
      row.querySelector('[data-lyrics-filename]').textContent = duolaAppearance.lyricsEmpty || '未选择（可选）';
      event.target.closest('[data-remove-lyrics]').hidden = true;
      return;
    }

    if (event.target.closest('[data-remove-track]')) {
      row.remove();
      refreshTracks();
      addTrackButton.focus();
      return;
    }

    const moveButton = event.target.closest('[data-move-track]');
    if (!moveButton) return;
    const direction = moveButton.dataset.moveTrack;
    const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling) return;
    if (direction === 'up') trackList.insertBefore(row, sibling);
    else trackList.insertBefore(sibling, row);
    refreshTracks();
    moveButton.focus();
  });

  refreshTracks();
})(jQuery);
