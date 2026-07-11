(($) => {
  const list = $('#duola-album-photo-list');
  const hiddenIds = $('#duola-album-photo-ids');
  const hiddenSettings = $('#duola-album-photo-settings');
  const coverId = $('#duola-album-cover-id');
  const photoCount = $('#duola-photo-count');
  const editor = $('#duola-photo-editor');

  if (!list.length) return;

  const defaults = {
    headline: '',
    description: '',
    date: '',
    layout: 'standard',
    text_position: 'spread',
    focus_x: 50,
    focus_y: 50,
    accent: '#009fe8',
    background: '#f3f3f0',
    home_width: 'standard',
    show_home: true,
  };

  const fields = {
    headline: $('#duola-photo-headline'),
    description: $('#duola-photo-description'),
    date: $('#duola-photo-date'),
    layout: $('#duola-photo-layout'),
    text_position: $('#duola-photo-text-position'),
    focus_x: $('#duola-photo-focus-x'),
    focus_y: $('#duola-photo-focus-y'),
    accent: $('#duola-photo-accent'),
    background: $('#duola-photo-background'),
    home_width: $('#duola-photo-home-width'),
    show_home: $('#duola-photo-show-home'),
  };

  const preview = $('#duola-editor-preview');
  const previewImage = $('#duola-editor-preview-image');
  const previewHeadline = $('#duola-editor-preview-headline');
  const previewDate = $('#duola-editor-preview-date');
  const previewDescription = $('#duola-editor-preview-description');
  let activeId = 0;
  let settings = {};

  try {
    settings = JSON.parse(hiddenSettings.val() || '{}') || {};
  } catch (error) {
    settings = {};
  }

  const getIds = () => list.children('[data-id]').map((_, item) => Number(item.dataset.id)).get();
  const getSetting = (id) => ({ ...defaults, ...(settings[String(id)] || {}) });

  const hasCustomSetting = (setting) => Object.keys(defaults).some((key) => setting[key] !== defaults[key]);

  const writeSettings = () => {
    const validIds = new Set(getIds().map(String));
    settings = Object.fromEntries(Object.entries(settings).filter(([id]) => validIds.has(id)));
    hiddenSettings.val(JSON.stringify(settings));
  };

  const updateCount = (count) => {
    photoCount.text(duolaAlbums.count.replace('%d', count));
  };

  const setCover = (id) => {
    const nextCoverId = Number(id) || 0;
    coverId.val(nextCoverId);
    list.children('[data-id]').removeClass('is-cover');
    if (nextCoverId) {
      list.children(`[data-id="${nextCoverId}"]`).addClass('is-cover');
    }
  };

  const updateIds = () => {
    const ids = getIds();
    hiddenIds.val(JSON.stringify(ids));
    updateCount(ids.length);
    writeSettings();

    if (!ids.includes(Number(coverId.val()))) {
      setCover(ids[0] || 0);
    }
  };

  const updatePreview = (setting) => {
    const headline = setting.headline || '装饰文字预览';
    preview.css({
      '--preview-accent': setting.accent,
      '--preview-background': setting.background,
      '--preview-focus-x': `${setting.focus_x}%`,
      '--preview-focus-y': `${setting.focus_y}%`,
    });
    preview.attr('data-text-position', setting.text_position);
    previewHeadline.text(headline);
    previewDate.text(setting.date || '日期可选').toggleClass('is-placeholder', !setting.date);
    previewDescription.text(setting.description || '图片描述会显示在这里').toggleClass('is-placeholder', !setting.description);
    $('#duola-photo-focus-x-value').text(`${setting.focus_x}%`);
    $('#duola-photo-focus-y-value').text(`${setting.focus_y}%`);
  };

  const readFields = () => ({
    headline: fields.headline.val().trim(),
    description: fields.description.val().trim(),
    date: fields.date.val(),
    layout: fields.layout.val(),
    text_position: fields.text_position.val(),
    focus_x: Number(fields.focus_x.val()),
    focus_y: Number(fields.focus_y.val()),
    accent: fields.accent.val(),
    background: fields.background.val(),
    home_width: fields.home_width.val(),
    show_home: fields.show_home.is(':checked'),
  });

  const writeFields = (setting) => {
    Object.entries(fields).forEach(([key, field]) => {
      if ('show_home' === key) {
        field.prop('checked', setting[key]);
      } else {
        field.val(setting[key]);
      }
    });
    updatePreview(setting);
  };

  const saveActiveSetting = () => {
    if (!activeId) return;
    const setting = readFields();
    const item = list.children(`[data-id="${activeId}"]`);
    if (hasCustomSetting(setting)) {
      settings[String(activeId)] = setting;
      item.addClass('has-settings');
    } else {
      delete settings[String(activeId)];
      item.removeClass('has-settings');
    }
    writeSettings();
    updatePreview(setting);
  };

  const openEditor = (item) => {
    activeId = Number(item.data('id'));
    list.children('[data-id]').removeClass('is-editing');
    item.addClass('is-editing');
    $('#duola-photo-editor-title').text(`照片 #${activeId}`);
    previewImage.attr('src', item.children('img').attr('src'));
    writeFields(getSetting(activeId));
    editor.prop('hidden', false);
    editor[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  };

  const closeEditor = () => {
    activeId = 0;
    list.children('[data-id]').removeClass('is-editing');
    editor.prop('hidden', true);
  };

  const appendPhoto = (attachment) => {
    if (list.children(`[data-id="${attachment.id}"]`).length) return;

    const source = attachment.sizes?.thumbnail?.url || attachment.url;
    const item = $('<li>').attr('data-id', attachment.id);
    const actions = $('<div>').addClass('duola-photo-actions');
    item.append($('<img>').attr({ src: source, alt: '' }));
    actions.append($('<button>').attr('type', 'button').addClass('button-link duola-edit-photo').text('编辑信息与排版'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link duola-set-cover').text('设为封面'));
    actions.append($('<button>').attr('type', 'button').addClass('button-link-delete duola-remove-photo').text('移除'));
    item.append(actions);
    list.append(item);
  };

  list.sortable({ items: '> li', tolerance: 'pointer', update: updateIds });

  list.on('click', '.duola-remove-photo', function () {
    const item = $(this).closest('li');
    const id = String(item.data('id'));
    if (Number(id) === activeId) closeEditor();
    delete settings[id];
    item.remove();
    updateIds();
  });

  list.on('click', '.duola-set-cover', function () {
    setCover($(this).closest('li').data('id'));
  });

  list.on('click', '.duola-edit-photo, li > img', function () {
    openEditor($(this).closest('li'));
  });

  Object.values(fields).forEach((field) => {
    field.on('input change', saveActiveSetting);
  });

  $('#duola-close-photo-editor').on('click', closeEditor);
  $('#duola-reset-photo-settings').on('click', () => {
    if (!activeId) return;
    delete settings[String(activeId)];
    list.children(`[data-id="${activeId}"]`).removeClass('has-settings');
    writeFields({ ...defaults });
    writeSettings();
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
