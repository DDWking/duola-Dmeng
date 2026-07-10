(() => {
  const { createElement, useEffect, useState } = wp.element;
  const { Button } = wp.components;
  const { registerPlugin } = wp.plugins;
  const PluginDocumentSettingPanel = wp.editor?.PluginDocumentSettingPanel || wp.editPost?.PluginDocumentSettingPanel;

  if (!PluginDocumentSettingPanel) {
    return;
  }

  const getPhotoCount = () => window.duolaAlbumsEditor?.getPhotoCount?.() || 0;

  const AlbumPhotosPanel = () => {
    const [photoCount, setPhotoCount] = useState(getPhotoCount);

    useEffect(() => {
      const updatePhotoCount = (event) => setPhotoCount(event.detail?.count ?? getPhotoCount());
      document.addEventListener('duola-albums:photos-changed', updatePhotoCount);
      setPhotoCount(getPhotoCount());

      return () => document.removeEventListener('duola-albums:photos-changed', updatePhotoCount);
    }, []);

    return createElement(
      PluginDocumentSettingPanel,
      {
        className: 'duola-albums-sidebar',
        icon: 'format-gallery',
        name: 'duola-album-photos',
        title: '相册照片',
      },
      createElement('p', { className: 'duola-albums-sidebar__intro' }, '无需填写照片信息，先把照片批量传上来即可。'),
      createElement(
        Button,
        {
          className: 'duola-albums-sidebar__upload',
          isPrimary: true,
          onClick: () => window.duolaAlbumsEditor?.openPhotoSelector?.(),
        },
        '批量上传或选择照片'
      ),
      createElement('p', { className: 'duola-albums-sidebar__count' }, `已添加 ${photoCount} 张照片`),
      createElement('p', { className: 'duola-albums-sidebar__hint' }, '上传完成后点击“发布”。封面、地点和排序可在页面底部的“相册信息与照片”中以后再补。')
    );
  };

  registerPlugin('duola-albums-editor-sidebar', { render: AlbumPhotosPanel });
})();
