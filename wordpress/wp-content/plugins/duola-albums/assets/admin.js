(($) => {
  const list = $('#duola-album-photo-list');
  const hiddenIds = $('#duola-album-photo-ids');
  const coverId = $('#duola-album-cover-id');
  const photoCount = $('#duola-photo-count');
  const uploadButton = $('#duola-upload-files');
  const fileInput = $('#duola-photo-files');
  const uploadStatus = $('#duola-upload-status');

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
    if (list.children(`[data-id="${attachment.id}"]`).length) return false;
    const source = attachment.sizes?.thumbnail?.url
      || attachment.media_details?.sizes?.thumbnail?.source_url
      || attachment.source_url
      || attachment.url;
    const item = $('<li>').attr('data-id', attachment.id);
    const actions = $('<div>').addClass('duola-photo-actions');
    item.append($('<img>').attr({ src: source, alt: '' }));
    actions.append($('<span>').addClass('duola-visual-pending').text('保存后可编辑图片信息'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link duola-set-cover').text('设为封面'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link-delete duola-remove-photo').text('移除'));
    item.append(actions);
    list.append(item);
    return true;
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
    const selection = new wp.media.model.Selection([], { multiple: true });
    const frame = wp.media({
      title: duolaAlbums.title,
      button: { text: duolaAlbums.add },
      multiple: true,
      selection,
      library: { type: 'image' },
    });

    const updateMediaButton = () => {
      const count = selection.length;
      frame.$el.find('.media-button-select').text(count
        ? duolaAlbums.addSelected.replace('%d', count)
        : duolaAlbums.add);
    };

    selection.on('add remove reset', updateMediaButton);
    frame.on('open', updateMediaButton);
    frame.on('select', () => {
      const selected = new Map();
      selection.each((attachment) => selected.set(Number(attachment.id), attachment));
      frame.$el.find('.attachment[aria-checked="true"]').each((_, element) => {
        const attachmentId = Number(element.dataset.id);
        if (attachmentId) selected.set(attachmentId, wp.media.attachment(attachmentId));
      });

      if (!selected.size) {
        uploadStatus.text(duolaAlbums.libraryEmpty);
        return;
      }

      let added = 0;
      selected.forEach((attachment) => {
        if (appendPhoto(attachment.toJSON())) added += 1;
      });
      updateIds();
      uploadStatus.text(formatUploadText(duolaAlbums.libraryAdded, [added, selected.size - added]));
    });
    frame.open();
  });

  const formatUploadText = (template, values) => values.reduce(
    (text, value, index) => text.replace(`%${index + 1}$d`, value).replace(`%${index + 1}$s`, value),
    template,
  );

  const uploadFile = async (file) => {
    const body = new FormData();
    body.append('file', file, file.name);
    const response = await window.fetch(duolaAlbums.restUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-WP-Nonce': duolaAlbums.restNonce },
      body,
    });
    const attachment = await response.json();
    if (!response.ok) throw new Error(attachment.message || duolaAlbums.uploadFailed);
    return attachment;
  };

  uploadButton.on('click', () => fileInput.trigger('click'));
  fileInput.on('change', async (event) => {
    const files = Array.from(event.target.files || []).filter((file) => file.type.startsWith('image/'));
    if (!files.length) return;

    uploadButton.prop('disabled', true);
    $('#duola-add-photos').prop('disabled', true);
    let uploaded = 0;
    let failed = 0;

    for (let index = 0; index < files.length; index += 1) {
      const file = files[index];
      uploadStatus.text(formatUploadText(duolaAlbums.uploading, [index + 1, files.length, file.name]));
      try {
        appendPhoto(await uploadFile(file));
        uploaded += 1;
      } catch (error) {
        failed += 1;
      }
      updateIds();
    }

    uploadStatus.text(failed
      ? formatUploadText(duolaAlbums.uploadPartial, [uploaded, failed])
      : duolaAlbums.uploadComplete.replace('%d', uploaded));
    uploadButton.prop('disabled', false);
    $('#duola-add-photos').prop('disabled', false);
    fileInput.val('');
  });

  updateIds();
})(jQuery);
