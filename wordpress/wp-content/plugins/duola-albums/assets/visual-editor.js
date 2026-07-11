(() => {
  const config = window.duolaVisualEditor;
  const status = document.querySelector('#duola-visual-status');
  if (!config || !window.grapesjs) return;

  const clone = (value) => JSON.parse(JSON.stringify(value));
  let layout = clone(config.layout);
  let currentDevice = 'desktop';
  let selectedComponent = null;
  let dirty = false;

  const fixedTypes = new Set(['home-articles', 'home-preview', 'home-rail', 'home-link', 'photo', 'headline', 'date', 'description']);
  const imageTypes = new Set(['photo', 'image']);
  const textTypes = new Set(['headline', 'date', 'description', 'text', 'home-link']);
  const setStatus = (message, type = '') => {
    status.textContent = message;
    status.className = `duola-visual-status ${type}`.trim();
  };
  const markDirty = () => {
    dirty = true;
    setStatus('有尚未保存的修改');
  };
  const escapeHtml = (value) => String(value || '').replace(/[&<>"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[character]));

  const autoMobileStyle = (element) => {
    const map = {
      'home-articles': { position: 'absolute', left: '4%', top: '13%', width: '92%', height: '22%', 'z-index': '3' },
      'home-preview': { position: 'absolute', left: '4%', top: '7%', width: '92%', height: '3%', 'z-index': '5' },
      'home-rail': { position: 'absolute', left: '4%', top: '38%', width: '104%', height: '26%', 'z-index': '2' },
      'home-link': { position: 'absolute', left: '4%', top: '68%', width: '7rem', height: '1.5rem', 'z-index': '3', 'text-align': 'left' },
      photo: { position: 'absolute', left: '3%', top: '20%', width: '94%', height: '58%', 'z-index': '2', 'object-fit': 'cover', 'object-position': `${layout.settings.focus_x}% ${layout.settings.focus_y}%` },
      headline: { position: 'absolute', left: '3%', top: '40%', width: '94%', height: '24%', 'z-index': '4', color: layout.settings.accent, 'font-size': '18vw', 'font-weight': '900', 'line-height': '.8', 'text-align': 'center' },
      date: { position: 'absolute', left: '4%', bottom: '5%', width: '12rem', height: '1.5rem', 'z-index': '5', 'font-size': '.62rem', 'font-weight': '700' },
      description: { position: 'absolute', left: '4%', bottom: '2%', width: '82%', 'min-height': '2rem', 'z-index': '5', 'font-size': '.62rem', 'font-weight': '700', 'text-align': 'left' },
    };
    if (map[element.type]) return map[element.type];
    const desktop = element.desktop || {};
    return {
      ...desktop,
      width: desktop.width || ('image' === element.type ? '38%' : '70%'),
      'font-size': 'text' === element.type ? '8vw' : desktop['font-size'],
    };
  };

  const displayedStyle = (element) => currentDevice === 'desktop'
    ? { ...(element.desktop || {}) }
    : { ...autoMobileStyle(element), ...(element.mobile || {}) };

  const previewMarkup = (element) => {
    if ('home-articles' === element.type) return '<div class="mock-heading"><span>NOTES</span><span>全部文章</span></div><div class="mock-post"><small>01&nbsp;&nbsp;2026.07.10</small><strong>写给路上的一段文字</strong></div><div class="mock-post"><small>02&nbsp;&nbsp;2026.06.28</small><strong>城市边缘的夜晚</strong></div>';
    if ('home-preview' === element.type) return '<div class="mock-preview"><b>01</b><i></i><span>09</span></div>';
    if ('home-rail' === element.type) return '<div class="mock-rail"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>';
    return escapeHtml(element.content);
  };

  const componentDefinition = (element) => {
    const attributes = { 'data-duola-id': element.id, 'data-duola-type': element.type };
    const common = {
      attributes,
      name: element.label,
      style: displayedStyle(element),
      draggable: true,
      droppable: false,
      selectable: true,
      hoverable: true,
      removable: !element.locked,
      copyable: !element.locked,
      resizable: { tl: 1, tc: 1, tr: 1, cl: 1, cr: 1, bl: 1, bc: 1, br: 1, keyWidth: 'width', keyHeight: 'height' },
    };
    if (imageTypes.has(element.type)) {
      return { ...common, type: 'image', tagName: 'img', attributes: { ...attributes, src: element.src || config.photoUrl, alt: '' } };
    }
    return {
      ...common,
      type: 'text',
      tagName: 'div',
      classes: ['duola-canvas-element', `is-${element.type}`],
      components: previewMarkup(element),
      editable: textTypes.has(element.type),
    };
  };

  const editor = grapesjs.init({
    container: '#duola-gjs',
    height: '100%',
    width: 'auto',
    fromElement: false,
    storageManager: false,
    selectorManager: { componentFirst: true },
    panels: { defaults: [] },
    blockManager: { appendTo: '#duola-blocks' },
    layerManager: { appendTo: '#duola-layers' },
    styleManager: {
      appendTo: '#duola-styles',
      sectors: [
        { name: '位置与尺寸', open: true, buildProps: ['position', 'top', 'right', 'bottom', 'left', 'width', 'height', 'z-index', 'display', 'overflow'] },
        { name: '排版', open: true, buildProps: ['font-size', 'font-weight', 'line-height', 'text-align', 'color', 'opacity'] },
        { name: '间距与边框', open: false, buildProps: ['padding', 'margin', 'border', 'border-radius'] },
        { name: '图片', open: false, buildProps: ['object-fit', 'object-position', 'background-color'] },
      ],
    },
    deviceManager: {
      devices: [
        { id: 'desktop', name: 'Desktop', width: '' },
        { id: 'mobile', name: 'Mobile', width: '390px', widthMedia: '760px' },
      ],
    },
    canvas: { styles: [config.canvasCssUrl] },
    noticeOnUnload: false,
  });

  const getElement = (component) => layout.elements.find((element) => element.id === component?.getAttributes()?.['data-duola-id']);
  const allComponents = () => editor.getWrapper().components().models;

  const loadLayout = () => {
    const undoManager = editor.UndoManager;
    undoManager.stop();
    editor.getWrapper().components().reset(layout.elements.map(componentDefinition));
    editor.getWrapper().setStyle({ position: 'relative', width: '100%', height: '100%', overflow: 'hidden', background: layout.settings.background });
    undoManager.clear();
    undoManager.start();
    selectedComponent = null;
    updateInspector();
  };

  const saveCurrentDevice = () => {
    allComponents().forEach((component) => {
      const element = getElement(component);
      if (!element) return;
      const style = { ...component.getStyle() };
      if ('desktop' === currentDevice) {
        element.desktop = style;
      } else {
        const automatic = autoMobileStyle(element);
        element.mobile = Object.fromEntries(Object.entries(style).filter(([property, value]) => automatic[property] !== value));
      }
      if (imageTypes.has(element.type)) {
        element.src = component.getAttributes().src || element.src;
      } else if (!['home-articles', 'home-preview', 'home-rail'].includes(element.type)) {
        element.content = component.view?.el?.textContent || element.content || '';
      }
      element.label = component.get('name') || element.label;
    });
  };

  const applyDevice = (device) => {
    saveCurrentDevice();
    currentDevice = device;
    editor.setDevice('mobile' === device ? 'Mobile' : 'Desktop');
    const undoManager = editor.UndoManager;
    undoManager.stop();
    allComponents().forEach((component) => {
      const element = getElement(component);
      if (element) component.setStyle(displayedStyle(element));
    });
    editor.getWrapper().setStyle({ position: 'relative', width: '100%', height: '100%', overflow: 'hidden', background: layout.settings.background });
    undoManager.start();
    document.querySelectorAll('[data-device]').forEach((button) => button.classList.toggle('is-active', button.dataset.device === device));
    updateInspector();
  };

  const addElement = (type, content = '', src = '') => {
    saveCurrentDevice();
    const id = `decoration-${Date.now().toString(36)}`;
    const element = {
      id,
      type,
      label: 'image' === type ? '装饰图片' : '装饰文字',
      content,
      src,
      locked: false,
      desktop: 'image' === type
        ? { position: 'absolute', left: '12%', top: '18%', width: '18%', height: '30%', 'z-index': '6', 'object-fit': 'contain' }
        : { position: 'absolute', left: '10%', top: '18%', width: '42%', height: '15%', 'z-index': '6', color: layout.settings.accent, 'font-size': '4vw', 'font-weight': '800', 'line-height': '1' },
      mobile: {},
    };
    layout.elements.push(element);
    const component = editor.addComponents(componentDefinition(element))[0];
    editor.select(component);
    markDirty();
  };

  const openMedia = (callback) => {
    const frame = wp.media({ title: '选择装饰图片', button: { text: '使用这张图片' }, multiple: false, library: { type: 'image' } });
    frame.on('select', () => callback(frame.state().get('selection').first().toJSON()));
    frame.open();
  };

  const inspector = {
    empty: document.querySelector('.duola-no-selection'),
    controls: document.querySelector('.duola-selected-controls'),
    label: document.querySelector('#duola-selected-label'),
    content: document.querySelector('#duola-selected-content'),
    image: document.querySelector('#duola-selected-image'),
    mobileVisible: document.querySelector('#duola-selected-mobile-visible'),
    resetMobile: document.querySelector('#duola-reset-selected-mobile'),
  };

  const updateInspector = () => {
    const element = getElement(selectedComponent);
    inspector.empty.hidden = !!element;
    inspector.controls.hidden = !element;
    if (!element) return;
    inspector.label.value = element.label || '';
    inspector.content.closest('label')?.classList.remove('is-hidden');
    inspector.content.value = element.content || '';
    inspector.content.style.display = textTypes.has(element.type) || 'text' === element.type ? '' : 'none';
    inspector.image.style.display = imageTypes.has(element.type) ? '' : 'none';
    inspector.mobileVisible.checked = 'none' !== (element.mobile?.display || autoMobileStyle(element).display);
    inspector.resetMobile.disabled = 'desktop' === currentDevice || !Object.keys(element.mobile || {}).length;
  };

  const buildSettings = () => {
    const root = document.querySelector('#duola-global-settings');
    if ('home' === config.mode) {
      root.innerHTML = `
        <label>页面背景<input type="color" data-setting="background"></label>
        <label>波浪阻尼<output data-output="wave_damping"></output><input type="range" min="4" max="30" data-setting="wave_damping"></label>
        <label>延迟跟随<output data-output="wave_latency"></output><input type="range" min="2" max="24" data-setting="wave_latency"></label>
        <label>波浪高度<output data-output="wave_amplitude"></output><input type="range" min="0" max="80" data-setting="wave_amplitude"></label>
        <label>展开幅度<output data-output="wave_expansion"></output><input type="range" min="20" max="220" data-setting="wave_expansion"></label>
        <label>旋转强度<output data-output="wave_rotation"></output><input type="range" min="0" max="12" data-setting="wave_rotation"></label>`;
    } else {
      root.innerHTML = `
        <label>场景背景<input type="color" data-setting="background"></label>
        <label>装饰颜色<input type="color" data-setting="accent"></label>
        <label>水平焦点<output data-output="focus_x"></output><input type="range" min="0" max="100" data-setting="focus_x"></label>
        <label>垂直焦点<output data-output="focus_y"></output><input type="range" min="0" max="100" data-setting="focus_y"></label>
        <label>首页切片宽度<select data-setting="home_width"><option value="narrow">窄</option><option value="standard">标准</option><option value="wide">宽</option></select></label>
        <label class="duola-setting-check"><input type="checkbox" data-setting="show_home">在首页显示</label>`;
    }
    root.querySelectorAll('[data-setting]').forEach((input) => {
      const key = input.dataset.setting;
      if ('checkbox' === input.type) input.checked = !!layout.settings[key];
      else input.value = layout.settings[key];
      const output = root.querySelector(`[data-output="${key}"]`);
      if (output) output.textContent = layout.settings[key];
      input.addEventListener('input', () => {
        const oldAccent = layout.settings.accent;
        layout.settings[key] = 'checkbox' === input.type ? input.checked : ('range' === input.type ? Number(input.value) : input.value);
        if (output) output.textContent = input.value;
        if ('background' === key) editor.getWrapper().addStyle({ background: input.value });
        if ('accent' === key) {
          layout.elements.filter((element) => ['headline', 'text'].includes(element.type)).forEach((element) => {
            if (!element.desktop.color || element.desktop.color === oldAccent) element.desktop.color = input.value;
          });
          applyDevice(currentDevice);
        }
        if (['focus_x', 'focus_y'].includes(key)) {
          const photo = layout.elements.find((element) => 'photo' === element.type);
          if (photo) photo.desktop['object-position'] = `${layout.settings.focus_x}% ${layout.settings.focus_y}%`;
          applyDevice(currentDevice);
        }
        markDirty();
      });
    });
  };

  editor.on('load', () => {
    loadLayout();
    editor.setDevice('Desktop');
  });
  editor.on('component:selected', (component) => {
    selectedComponent = component;
    updateInspector();
  });
  editor.on('component:deselected', () => {
    selectedComponent = null;
    updateInspector();
  });
  editor.on('component:styleUpdate component:drag:end component:resize', markDirty);
  editor.on('component:remove', (component) => {
    const element = getElement(component);
    if (element && !element.locked) {
      layout.elements = layout.elements.filter((candidate) => candidate.id !== element.id);
      markDirty();
    }
  });

  document.querySelectorAll('[data-device]').forEach((button) => button.addEventListener('click', () => applyDevice(button.dataset.device)));
  document.querySelector('[data-command="undo"]').addEventListener('click', () => editor.UndoManager.undo());
  document.querySelector('[data-command="redo"]').addEventListener('click', () => editor.UndoManager.redo());
  document.querySelector('#duola-add-text').addEventListener('click', () => addElement('text', '新的装饰文字'));
  document.querySelector('#duola-add-image').addEventListener('click', () => openMedia((attachment) => addElement('image', '', attachment.url)));
  document.querySelector('#duola-open-preview').addEventListener('click', () => window.open(config.previewUrl, '_blank', 'noopener'));

  inspector.label.addEventListener('input', () => {
    const element = getElement(selectedComponent);
    if (!element) return;
    element.label = inspector.label.value;
    selectedComponent.set('name', element.label);
    markDirty();
  });
  inspector.content.addEventListener('input', () => {
    const element = getElement(selectedComponent);
    if (!element) return;
    element.content = inspector.content.value;
    selectedComponent.components().reset([{ type: 'textnode', content: element.content }]);
    markDirty();
  });
  inspector.image.addEventListener('click', () => openMedia((attachment) => {
    const element = getElement(selectedComponent);
    if (!element) return;
    element.src = attachment.url;
    selectedComponent.addAttributes({ src: attachment.url });
    markDirty();
  }));
  inspector.mobileVisible.addEventListener('change', () => {
    const element = getElement(selectedComponent);
    if (!element) return;
    element.mobile = { ...(element.mobile || {}), display: inspector.mobileVisible.checked ? autoMobileStyle(element).display || 'block' : 'none' };
    if ('mobile' === currentDevice) selectedComponent.addStyle({ display: element.mobile.display });
    markDirty();
  });
  inspector.resetMobile.addEventListener('click', () => {
    const element = getElement(selectedComponent);
    if (!element) return;
    element.mobile = {};
    if ('mobile' === currentDevice) selectedComponent.setStyle(autoMobileStyle(element));
    updateInspector();
    markDirty();
  });

  document.querySelector('#duola-reset-mobile').addEventListener('click', () => {
    layout.elements.forEach((element) => { element.mobile = {}; });
    if ('mobile' === currentDevice) applyDevice('mobile');
    markDirty();
  });
  document.querySelector('#duola-reset-layout').addEventListener('click', () => {
    if (!window.confirm('恢复默认布局会丢失当前未保存的编排，是否继续？')) return;
    layout = clone(config.defaultLayout);
    currentDevice = 'desktop';
    loadLayout();
    buildSettings();
    markDirty();
  });
  document.querySelector('#duola-save-layout').addEventListener('click', async () => {
    saveCurrentDevice();
    setStatus('正在保存…');
    const body = new URLSearchParams({
      action: 'duola_visual_save', nonce: config.nonce, mode: config.mode,
      album_id: String(config.albumId || 0), photo_id: String(config.photoId || 0), layout: JSON.stringify(layout),
    });
    try {
      const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body });
      const result = await response.json();
      if (!result.success) throw new Error(result.data?.message || '保存失败');
      dirty = false;
      setStatus(result.data.message, 'is-success');
    } catch (error) {
      setStatus(error.message || '保存失败', 'is-error');
    }
  });
  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  buildSettings();
})();
