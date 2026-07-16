(function (wp, document) {
  'use strict';

  if (!wp || !wp.data || !wp.domReady || !wp.blocks) {
    return;
  }

  var labels = window.duolaMobileEditor || {};
  var mobileViewport = window.matchMedia('(max-width: 782px)');
  var statusTimer;

  function button(icon, text, ariaLabel, action) {
    var control = document.createElement('button');
    control.type = 'button';
    control.setAttribute('aria-label', ariaLabel || text);
    control.innerHTML = '<span class="dashicons dashicons-' + icon + '" aria-hidden="true"></span><span>' + text + '</span>';
    control.addEventListener('click', action);
    return control;
  }

  function showStatus(message) {
    var status = document.querySelector('.duola-mobile-editor-status');
    if (!status) {
      status = document.createElement('div');
      status.className = 'duola-mobile-editor-status';
      status.setAttribute('role', 'status');
      status.setAttribute('aria-live', 'polite');
      document.body.appendChild(status);
    }

    status.textContent = message;
    status.classList.add('is-visible');
    window.clearTimeout(statusTimer);
    statusTimer = window.setTimeout(function () {
      status.classList.remove('is-visible');
    }, 2200);
  }

  function insertionPoint() {
    var selector = wp.data.select('core/block-editor');
    var clientId = selector.getSelectedBlockClientId();
    if (!clientId) {
      return { index: undefined, rootClientId: undefined };
    }

    return {
      index: selector.getBlockIndex(clientId) + 1,
      rootClientId: selector.getBlockRootClientId(clientId),
    };
  }

  function openImagePicker() {
    if (!wp.media) {
      showStatus(labels.mediaUnavailable);
      return;
    }

    var point = insertionPoint();
    var frame = wp.media({
      title: labels.imagePickerTitle,
      button: { text: labels.imagePickerButton },
      library: { type: 'image' },
      multiple: 'add',
    });

    frame.on('select', function () {
      var imageBlocks = frame.state().get('selection').toJSON().map(function (attachment) {
        return wp.blocks.createBlock('core/image', {
          id: attachment.id,
          url: attachment.url,
          alt: attachment.alt || '',
          caption: attachment.caption || '',
        });
      });

      if (imageBlocks.length) {
        wp.data.dispatch('core/block-editor').insertBlocks(imageBlocks, point.index, point.rootClientId);
      }
    });

    frame.open();
  }

  function openFeaturedImagePicker() {
    if (!wp.media) {
      showStatus(labels.mediaUnavailable);
      return;
    }

    var frame = wp.media({
      title: labels.featuredPickerTitle,
      button: { text: labels.featuredPickerButton },
      library: { type: 'image' },
      multiple: false,
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first();
      if (attachment) {
        wp.data.dispatch('core/editor').editPost({ featured_media: attachment.get('id') });
        showStatus(labels.saved || '已设置封面');
      }
    });

    frame.open();
  }

  function openSettings() {
    wp.data.dispatch('core/edit-post').openGeneralSidebar('edit-post/document');
  }

  function savePost(event) {
    var control = event.currentTarget;
    control.disabled = true;
    control.lastElementChild.textContent = labels.saving;

    Promise.resolve(wp.data.dispatch('core/editor').savePost()).then(function () {
      showStatus(labels.saved);
    }).catch(function () {
      showStatus(labels.saveFailed);
    }).finally(function () {
      control.disabled = false;
      control.lastElementChild.textContent = labels.save;
    });
  }

  function mountToolbar() {
    if (document.querySelector('.duola-mobile-editor-bar')) {
      return;
    }

    var toolbar = document.createElement('div');
    toolbar.className = 'duola-mobile-editor-bar';
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', '手机写作工具');
    toolbar.appendChild(button('format-image', labels.insertImage, labels.insertImageLabel, openImagePicker));
    toolbar.appendChild(button('format-gallery', labels.featuredImage, labels.featuredImageLabel, openFeaturedImagePicker));
    toolbar.appendChild(button('admin-generic', labels.settings, labels.settingsLabel, openSettings));
    toolbar.appendChild(button('saved', labels.save, labels.save, savePost));
    document.body.appendChild(toolbar);
    document.body.classList.add('duola-mobile-editor-ready');
  }

  function ensureWritingBlock() {
    var attempts = 0;

    function initialize() {
      attempts += 1;
      var selector = wp.data.select('core/block-editor');
      var editor = wp.data.select('core/editor');
      if (!selector || !editor || !editor.getCurrentPostId()) {
        if (attempts < 12) {
          window.setTimeout(initialize, 250);
        }
        return;
      }

      if (selector.getBlocks().length) {
        return;
      }

      wp.data.dispatch('core/block-editor').insertDefaultBlock();
      if (attempts < 12) {
        window.setTimeout(initialize, 250);
      }
    }

    initialize();
  }

  wp.domReady(function () {
    var preferences = wp.data.dispatch('core/preferences');
    if (preferences && preferences.set) {
      preferences.set('core/edit-post', 'welcomeGuide', false);
    }

    if (!mobileViewport.matches) {
      return;
    }

    ensureWritingBlock();
    mountToolbar();
  });
})(window.wp, document);
