(($) => {
  const input = $('#duola-site-avatar-id');
  const preview = $('#duola-avatar-preview');
  const removeButton = $('#duola-remove-avatar');

  if (!input.length) return;

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
})(jQuery);
