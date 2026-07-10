(($) => {
  const list = $('#duola-album-photo-list');
  const hiddenIds = $('#duola-album-photo-ids');
  const coverId = $('#duola-album-cover-id');
  const coverPreview = $('#duola-cover-preview');

  const getPhotoCount = () => list.children('[data-id]').length;

  const notifyPhotosChanged = () => {
    document.dispatchEvent(new CustomEvent('duola-albums:photos-changed', {
      detail: { count: getPhotoCount() },
    }));
  };

  const updateIds = () => {
    const ids = list.children('[data-id]').map((_, item) => Number(item.dataset.id)).get();
    hiddenIds.val(JSON.stringify(ids));
    notifyPhotosChanged();
  };

  const appendPhoto = (attachment) => {
    if (list.children(`[data-id="${attachment.id}"]`).length) return;
    const source = attachment.sizes?.thumbnail?.url || attachment.url;
    list.append(`<li data-id="${attachment.id}"><img src="${source}" alt=""><button type="button" class="duola-remove-photo" aria-label="移除照片">×</button></li>`);
  };

  list.sortable({ items: '> li', update: updateIds });
  list.on('click', '.duola-remove-photo', function () {
    $(this).closest('li').remove();
    updateIds();
  });

  const openPhotoSelector = () => {
    const frame = wp.media({
      title: duolaAlbums.title,
      button: { text: duolaAlbums.add },
      multiple: true,
      library: { type: 'image' },
    });
    frame.on('select', () => {
      frame.state().get('selection').each((attachment) => appendPhoto(attachment.toJSON()));
      updateIds();
    });
    frame.open();
  };

  window.duolaAlbumsEditor = {
    getPhotoCount,
    openPhotoSelector,
  };

  $('#duola-add-photos').on('click', openPhotoSelector);

  $('#duola-select-cover').on('click', () => {
    const frame = wp.media({
      title: duolaAlbums.coverTitle,
      button: { text: duolaAlbums.coverButton },
      multiple: false,
      library: { type: 'image' },
    });
    frame.on('select', () => {
      const attachment = frame.state().get('selection').first().toJSON();
      const source = attachment.sizes?.thumbnail?.url || attachment.url;
      coverId.val(attachment.id);
      coverPreview.html(`<img src="${source}" alt="">`);
    });
    frame.open();
  });
})(jQuery);
