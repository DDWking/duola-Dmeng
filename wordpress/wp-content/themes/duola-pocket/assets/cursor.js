(() => {
  'use strict';

  const root = document.documentElement;
  const cursorQuery = window.matchMedia('(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)');
  const interactiveSelector = [
    'a[href]',
    'button',
    'summary',
    'select',
    'label[for]',
    '[role=button]',
    '[role=link]',
    '[data-lightbox]',
    'input[type=button]',
    'input[type=submit]',
    'input[type=reset]',
    'input[type=checkbox]',
    'input[type=radio]',
    'input[type=range]',
    'input[type=file]',
  ].join(',');
  const textSelector = 'textarea, [contenteditable=true], input:not([type]), input[type=text], input[type=search], input[type=email], input[type=url], input[type=tel], input[type=password], input[type=number]';
  const modeByCursor = new Map([
    ['pointer', 'pointer'],
    ['text', 'text'],
    ['vertical-text', 'text'],
    ['wait', 'wait'],
    ['progress', 'progress'],
    ['crosshair', 'crosshair'],
    ['not-allowed', 'unavailable'],
    ['no-drop', 'unavailable'],
    ['move', 'move'],
    ['all-scroll', 'move'],
    ['grab', 'move'],
    ['grabbing', 'move'],
    ['n-resize', 'resize-vertical'],
    ['s-resize', 'resize-vertical'],
    ['ns-resize', 'resize-vertical'],
    ['row-resize', 'resize-vertical'],
    ['e-resize', 'resize-horizontal'],
    ['w-resize', 'resize-horizontal'],
    ['ew-resize', 'resize-horizontal'],
    ['col-resize', 'resize-horizontal'],
    ['ne-resize', 'resize-diagonal-1'],
    ['sw-resize', 'resize-diagonal-1'],
    ['nesw-resize', 'resize-diagonal-1'],
    ['nw-resize', 'resize-diagonal-2'],
    ['se-resize', 'resize-diagonal-2'],
    ['nwse-resize', 'resize-diagonal-2'],
    ['help', 'help'],
    ['zoom-in', 'crosshair'],
    ['zoom-out', 'crosshair'],
  ]);

  let cursor = null;
  let lastTarget = null;
  let pointerVisible = false;
  let frameRequested = false;
  let pointerX = -200;
  let pointerY = -200;

  const render = () => {
    frameRequested = false;
    if (!cursor) return;
    cursor.style.transform = `translate3d(${pointerX}px, ${pointerY}px, 0) scale(var(--bocchi-cursor-scale, 1))`;
  };

  const requestRender = () => {
    if (frameRequested) return;
    frameRequested = true;
    window.requestAnimationFrame(render);
  };

  const setVisible = (visible) => {
    pointerVisible = visible;
    if (cursor) cursor.classList.toggle('is-visible', visible && !root.classList.contains('bocchi-cursor-suspended'));
  };

  const readNativeCursor = (target) => {
    root.classList.add('bocchi-cursor-probe');
    root.classList.remove('bocchi-cursor-suspended');
    const nativeCursor = window.getComputedStyle(target).cursor;
    root.classList.remove('bocchi-cursor-probe');
    return nativeCursor;
  };

  const resolveMode = (target, nativeCursor) => {
    if (modeByCursor.has(nativeCursor)) return modeByCursor.get(nativeCursor);
    if (target.closest(textSelector)) return 'text';
    if (target.closest(interactiveSelector)) return 'pointer';
    if (nativeCursor === 'auto' || nativeCursor === 'default') return 'default';
    if (nativeCursor === 'copy' || nativeCursor === 'alias' || nativeCursor === 'context-menu') return 'pointer';
    return null;
  };

  const updateMode = (target) => {
    if (!cursor || !(target instanceof Element)) return;
    const mode = resolveMode(target, readNativeCursor(target));
    if (!mode) {
      root.classList.add('bocchi-cursor-suspended');
      cursor.classList.remove('is-visible');
      return;
    }
    root.classList.remove('bocchi-cursor-suspended');
    cursor.dataset.mode = mode;
    cursor.classList.toggle('is-visible', pointerVisible);
  };

  const handlePointerMove = (event) => {
    pointerX = event.clientX;
    pointerY = event.clientY;
    if (event.target !== lastTarget) {
      lastTarget = event.target;
      updateMode(lastTarget);
    }
    setVisible(true);
    requestRender();
  };

  const enable = () => {
    if (cursor || !document.body) return;
    cursor = document.createElement('span');
    cursor.className = 'bocchi-cursor';
    cursor.dataset.mode = 'default';
    cursor.setAttribute('aria-hidden', 'true');
    document.body.append(cursor);
    root.classList.add('has-bocchi-cursor');
  };

  const disable = () => {
    root.classList.remove('has-bocchi-cursor', 'bocchi-cursor-probe', 'bocchi-cursor-suspended');
    cursor?.remove();
    cursor = null;
    lastTarget = null;
    pointerVisible = false;
  };

  const sync = () => {
    if (cursorQuery.matches) enable();
    else disable();
  };

  document.addEventListener('pointermove', handlePointerMove, { passive: true });
  document.addEventListener('pointerdown', () => cursor?.classList.add('is-pressed'), { passive: true });
  document.addEventListener('pointerup', (event) => {
    cursor?.classList.remove('is-pressed');
    window.setTimeout(() => updateMode(event.target), 0);
  }, { passive: true });
  document.documentElement.addEventListener('pointerleave', () => setVisible(false), { passive: true });
  window.addEventListener('blur', () => setVisible(false));
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) setVisible(false);
  });

  document.addEventListener('turbo:before-render', () => setVisible(false));
  document.addEventListener('turbo:render', () => {
    if (cursor && !cursor.isConnected) {
      cursor = null;
    }
    lastTarget = null;
    sync();
  });

  if (typeof cursorQuery.addEventListener === 'function') cursorQuery.addEventListener('change', sync);
  else cursorQuery.addListener(sync);
  sync();
})();
