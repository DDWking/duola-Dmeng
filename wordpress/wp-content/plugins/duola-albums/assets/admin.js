(($) => {
  const list = $('#duola-album-photo-list');
  const hiddenIds = $('#duola-album-photo-ids');
  const coverId = $('#duola-album-cover-id');
  const photoCount = $('#duola-photo-count');

  if (!list.length) return;

  const getIds = () => list.children('[data-id]').map((_, item) => Number(item.dataset.id)).get();
  const updateCount = (count) => photoCount.text(duolaAlbums.count.replace('%d', count));

  const setCover = (id) => {
    const nextCoverId = Number(id) || 0;
    coverId.val(nextCoverId);
    list.children('[data-id]').removeClass('is-cover');
    if (nextCoverId) list.children(`[data-id="${nextCoverId}"]`).addClass('is-cover');
  };

  const updateIds = () => {
    const ids = getIds();
    hiddenIds.val(JSON.stringify(ids));
    updateCount(ids.length);
    if (!ids.includes(Number(coverId.val()))) setCover(ids[0] || 0);
  };

  const appendPhoto = (attachment) => {
    if (list.children(`[data-id="${attachment.id}"]`).length) return;
    const source = attachment.sizes?.thumbnail?.url || attachment.url;
    const item = $('<li>').attr('data-id', attachment.id);
    const actions = $('<div>').addClass('duola-photo-actions');
    item.append($('<img>').attr({ src: source, alt: '' }));
    actions.append($('<span>').addClass('duola-visual-pending').text('保存后可编辑图片信息'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link duola-set-cover').text('设为封面'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link-delete duola-remove-photo').text('移除'));
    item.append(actions);
    list.append(item);
  };

  list.sortable({ items: '> li', tolerance: 'pointer', update: updateIds });
  list.on('click', '.duola-remove-photo', function () {
    $(this).closest('li').remove();
    updateIds();
  });
  list.on('click', '.duola-set-cover', function () {
    setCover($(this).closest('li').data('id'));
  });

  $('#duola-add-photos').on('click', () => {
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
  });

  updateIds();
})(jQuery);
