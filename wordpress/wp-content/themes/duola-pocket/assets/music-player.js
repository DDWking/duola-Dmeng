(() => {
  const root = document.querySelector('[data-music-player]');
  const config = window.duolaMusicPlayer || {};
  const tracks = Array.isArray(config.tracks) ? config.tracks.filter((track) => track && track.src) : [];
  const luminous = window.DuolaLuminousLyrics;
  if (!root || !tracks.length || !luminous) {
    return;
  }
  const showLyrics = Boolean(config.showLyrics) && root.dataset.showLyrics === 'true';

  const rootStyles = window.getComputedStyle(document.documentElement);
  const themeColor = (name, fallback) => rootStyles.getPropertyValue(name).trim() || fallback;
  const lyricTheme = {
    backgroundColor: config.theme?.backgroundColor || themeColor('--paper', '#fbfaf7'),
    primaryColor: config.theme?.primaryColor || themeColor('--ink', '#1b2741'),
    secondaryColor: config.theme?.secondaryColor || themeColor('--ink-soft', '#536079'),
    accentColor: config.theme?.accentColor || themeColor('--lavender-deep', '#6579d8'),
    wordColors: Array.isArray(config.theme?.wordColors) ? config.theme.wordColors : [],
  };
  const animationIntensity = ['calm', 'normal', 'chaotic'].includes(config.animationIntensity)
    ? config.animationIntensity
    : 'normal';
  const fontScale = Number.isFinite(Number(config.fontScale)) ? Math.max(0.75, Math.min(1.4, Number(config.fontScale))) : 1;
  const showText = config.showText !== false;

  const audio = root.querySelector('[data-audio]');
  const coverImage = root.querySelector('[data-cover-image]');
  const noteFallback = root.querySelector('[data-note-fallback]');
  const titleEls = root.querySelectorAll('[data-panel-title]');
  const artistEls = root.querySelectorAll('[data-panel-artist]');
  const playButtons = root.querySelectorAll('[data-play], [data-play-panel]');
  const playIcons = root.querySelectorAll('[data-play-icon], [data-play-icon-panel]');
  const pauseIcons = root.querySelectorAll('[data-pause-icon], [data-pause-icon-panel]');
  const previousButton = root.querySelector('[data-previous]');
  const nextButton = root.querySelector('[data-next]');
  const seekInput = root.querySelector('[data-seek]');
  const currentTimeEl = root.querySelector('[data-current-time]');
  const durationEl = root.querySelector('[data-duration]');
  const panel = root.querySelector('[data-panel]');
  const panelClose = root.querySelector('[data-close-panel]');
  const lyricStage = root.querySelector('[data-lyric-stage]');
  const lyricLineEl = root.querySelector('[data-lyric-line]');
  const archiveIndexEl = root.querySelector('[data-archive-index]');
  let lyricPageLayer = null;

  const storageKey = config.storageKey || 'duolaMusicPlayer:v3';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lyricCache = new Map();

  let index = 0;
  let isSeeking = false;
  let lyricLines = [];
  let activeLineIndex = -1;
  let lyricRaf = 0;
  let lineTransitionTimer = 0;
  let currentLyricSeed = config.seed || 'duola-pocket';
  let activeWordNodes = [];
  const measurementCache = new Map();
  const lyricGhostTimers = new Set();
  let autoHideTimer = 0;
  let audioContext = null;
  let analyser = null;
  let audioSource = null;
  let frequencyData = null;
  let rhythmEnergy = 0.42;
  let rhythmPulse = 0;
  let rhythmHigh = 0.18;
  let hasPlaybackStarted = false;
  let lyricViewportActive = false;
  let lyricPosition = { left: 0, top: 0, width: 0 };
  let collapseHoverGuardUntil = 0;
  let isPageUnloading = false;
  let isRestoringPlayback = false;
  let resumeOnInteraction = null;
  let lastStateWrite = 0;

  function clearAutoHideTimer() {
    window.clearTimeout(autoHideTimer);
    autoHideTimer = 0;
  }

  function revealPlayer(options = {}) {
    if (options.fromHover && window.performance.now() < collapseHoverGuardUntil) {
      return;
    }
    clearAutoHideTimer();
    root.classList.remove('is-collapsed', 'is-dormant');
    panel.hidden = false;
    if (options.schedule !== false && !audio.paused) {
      scheduleAutoHide();
    }
  }

  function collapsePlayer() {
    clearAutoHideTimer();
    collapseHoverGuardUntil = window.performance.now() + 720;
    root.classList.add('is-collapsed');
    panel.hidden = false;
  }

  function scheduleAutoHide(delay) {
    clearAutoHideTimer();
    if (audio.paused && hasPlaybackStarted) {
      revealPlayer({ schedule: false });
      return;
    }
    const activeElement = document.activeElement;
    const hasKeyboardFocus = root.contains(activeElement)
      && typeof activeElement?.matches === 'function'
      && activeElement.matches(':focus-visible');
    if (panel.matches(':hover') || hasKeyboardFocus) {
      return;
    }
    const wait = Number.isFinite(delay) ? delay : 4200;
    autoHideTimer = window.setTimeout(collapsePlayer, wait);
  }
  async function initAudioAnalysis() {
    if (analyser) {
      if (audioContext?.state === 'suspended') {
        await audioContext.resume().catch(() => {});
      }
      return;
    }
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) {
      return;
    }
    try {
      audioContext = new AudioContextClass();
      analyser = audioContext.createAnalyser();
      analyser.fftSize = 256;
      analyser.smoothingTimeConstant = 0.78;
      frequencyData = new Uint8Array(analyser.frequencyBinCount);
      audioSource = audioContext.createMediaElementSource(audio);
      audioSource.connect(analyser);
      analyser.connect(audioContext.destination);
      await audioContext.resume().catch(() => {});
    } catch (error) {
      analyser = null;
      frequencyData = null;
    }
  }

  function sampleAudioRhythm() {
    if (!analyser || !frequencyData || audio.paused) {
      rhythmPulse *= 0.86;
      return;
    }
    analyser.getByteFrequencyData(frequencyData);
    let low = 0;
    let mid = 0;
    let high = 0;
    const lowEnd = Math.min(18, frequencyData.length);
    const midEnd = Math.min(52, frequencyData.length);
    const highEnd = Math.min(96, frequencyData.length);
    for (let bin = 1; bin < lowEnd; bin += 1) {
      low += frequencyData[bin];
    }
    for (let bin = lowEnd; bin < midEnd; bin += 1) {
      mid += frequencyData[bin];
    }
    for (let bin = midEnd; bin < highEnd; bin += 1) {
      high += frequencyData[bin];
    }
    low /= Math.max(1, lowEnd - 1);
    mid /= Math.max(1, midEnd - lowEnd);
    high /= Math.max(1, highEnd - midEnd);
    const nextEnergy = Math.min(1, (low * 0.68 + mid * 0.32) / 190);
    const delta = nextEnergy - rhythmEnergy;
    rhythmPulse = Math.max(delta * 3.2, rhythmPulse * 0.82);
    rhythmEnergy += (nextEnergy - rhythmEnergy) * 0.18;
    rhythmHigh += (Math.min(1, high / 178) - rhythmHigh) * 0.22;
    root.style.setProperty('--music-energy', rhythmEnergy.toFixed(3));
    root.style.setProperty('--music-pulse', Math.min(1, Math.max(0, rhythmPulse)).toFixed(3));
    root.style.setProperty('--music-high', rhythmHigh.toFixed(3));
  }

  function getLineCadence(line) {
    const seed = luminous.hashSeed(`${currentLyricSeed}:${line.fullText}:${line.startTime ?? line.start}`);
    const fallback = [0.12, 0.145, 0.175][seed % 3];
    if (!analyser) {
      return fallback;
    }
    const energyCadence = 0.19 - Math.min(1, Math.max(0, rhythmEnergy)) * 0.085;
    const pulseBoost = Math.min(0.018, Math.max(0, rhythmPulse) * 0.03);
    const variation = ((seed >>> 4) % 5 - 2) * 0.006;
    return luminous.clamp(energyCadence - pulseBoost + variation, 0.095, 0.19);
  }

  function formatTime(seconds) {
    if (!Number.isFinite(seconds) || seconds < 0) {
      return '0:00';
    }
    const total = Math.floor(seconds);
    const mins = Math.floor(total / 60);
    const secs = String(total % 60).padStart(2, '0');
    return `${mins}:${secs}`;
  }

  function readState() {
    try {
      return JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
    } catch (error) {
      return {};
    }
  }

  function writeState(partial) {
    try {
      const next = { ...readState(), ...partial };
      window.localStorage.setItem(storageKey, JSON.stringify(next));
    } catch (error) {
      // Ignore quota / private mode failures.
    }
  }

  function setProgressCss(input, ratio) {
    if (!input) {
      return;
    }
    const value = Math.max(0, Math.min(1, ratio)) * 100;
    input.style.setProperty('--progress', `${value}%`);
  }

  function setTexts(nodes, text) {
    nodes.forEach((node) => {
      if (node) {
        node.textContent = text;
      }
    });
  }

  function setPlayingUi(isPlaying) {
    root.classList.toggle('is-playing', isPlaying);
    lyricStage?.classList.toggle('is-playing', isPlaying);
    playIcons.forEach((icon) => {
      if (icon) {
        icon.hidden = isPlaying;
      }
    });
    pauseIcons.forEach((icon) => {
      if (icon) {
        icon.hidden = !isPlaying;
      }
    });
    playButtons.forEach((button) => {
      if (button) {
        button.setAttribute('aria-label', isPlaying ? '暂停' : '播放');
      }
    });
  }

  function setPanelOpen(open) {
    if (open) {
      revealPlayer();
    } else {
      scheduleAutoHide(0);
    }
  }
  function parseTimeTag(tag) {
    const match = String(tag).match(/(?:(\d+):)?(\d{1,2})(?:[.:](\d{1,3}))?/);
    if (!match) {
      return null;
    }
    const minutes = Number(match[1] || 0);
    const seconds = Number(match[2] || 0);
    let fraction = match[3] || '0';
    if (fraction.length === 1) {
      fraction = `${fraction}0`;
    }
    if (fraction.length === 2) {
      fraction = `${fraction}0`;
    }
    const millis = Number(fraction.slice(0, 3)) / 1000;
    return minutes * 60 + seconds + millis;
  }

 function splitPlainWords(text) {
   const source = String(text || '').trim();
   if (!source) {
     return [];
   }
   if (/\s/.test(source)) {
     return source.split(/\s+/).filter(Boolean).map((word) => ({ text: word, timed: false }));
   }
   if (typeof Intl.Segmenter === 'function') {
     const wordSegmenter = new Intl.Segmenter(undefined, { granularity: 'word' });
     const segments = Array.from(wordSegmenter.segment(source), (entry) => entry.segment)
       .filter((word) => word.trim());
     if (segments.length) {
       return segments.map((word) => ({ text: word, timed: false }));
     }
   }
   return luminous.segmentGraphemes(source).map((grapheme) => ({ text: grapheme, timed: false }));
 }

 function parseLrc(text) {
   const raw = String(text || '').replace(/^\uFEFF/, '');
   const lines = [];
   raw.split(/\r?\n/).forEach((row) => {
     const tags = [...row.matchAll(/\[(\d{1,2}:\d{1,2}(?:[.:]\d{1,3})?)\]/g)];
     if (!tags.length) {
       return;
     }
     let content = row.replace(/\[[^\]]*]/g, '').trim();
     if (!content) {
       return;
     }

     const times = tags
       .map((tag) => parseTimeTag(tag[1]))
       .filter((value) => Number.isFinite(value))
       .sort((a, b) => a - b);
     if (!times.length) {
       return;
     }

     const enhanced = [...content.matchAll(/<(\d{1,2}:\d{1,2}(?:[.:]\d{1,3})?)>([^<]*)/g)];
     let words = null;
     if (enhanced.length) {
       words = enhanced
         .map((item) => {
           const start = parseTimeTag(item[1]);
           const wordText = String(item[2] || '').replace(/\s+/g, ' ').trim();
           if (!Number.isFinite(start) || !wordText) {
             return null;
           }
           return { text: wordText, start, startTime: start, timed: true };
         })
         .filter(Boolean);
       if (words.length) {
         content = words.map((word) => word.text).join(' ');
       } else {
         words = null;
       }
     }

     times.forEach((start) => {
       lines.push({
         start,
         startTime: start,
         text: content,
         fullText: content,
         words: words
           ? words.map((word) => ({ ...word }))
           : splitPlainWords(content),
         wordTimed: Boolean(words && words.length),
       });
     });
   });

   lines.sort((a, b) => a.start - b.start);
   lines.forEach((line, lineIndex) => {
     const next = lines[lineIndex + 1];
     line.end = next ? Math.max(next.start, line.start + 0.2) : line.start + 8;
     line.endTime = line.end;
     if (line.wordTimed) {
       line.words.forEach((word, wordIndex) => {
         const nextWord = line.words[wordIndex + 1];
         word.end = nextWord ? nextWord.start : line.end;
         word.startTime = word.start;
         word.endTime = word.end;
       });
     } else if (line.words.length) {
       const span = Math.max(line.end - line.start, 0.08);
       const slice = span / line.words.length;
       line.words.forEach((word, wordIndex) => {
         word.start = line.start + slice * wordIndex;
         word.end = line.start + slice * (wordIndex + 1);
         word.startTime = word.start;
         word.endTime = word.end;
         word.timed = false;
       });
     }
   });
   const repetitions = new Map();
   lines.forEach((line) => {
     const key = line.fullText.replace(/\s+/g, ' ').trim().toLocaleLowerCase();
     repetitions.set(key, (repetitions.get(key) || 0) + 1);
   });
   lines.forEach((line) => {
     const key = line.fullText.replace(/\s+/g, ' ').trim().toLocaleLowerCase();
     line.isChorus = key.length >= 4 && repetitions.get(key) > 1;
   });

   return lines;
 }

  function clearLyricGhosts() {
    lyricGhostTimers.forEach((timer) => window.clearTimeout(timer));
    lyricGhostTimers.clear();
    lyricStage?.querySelectorAll('.home-music-lyric-ghost').forEach((ghost) => ghost.remove());
  }

  function clearLyricStage() {
    window.cancelAnimationFrame(lyricRaf);
    lyricRaf = 0;
    window.clearTimeout(lineTransitionTimer);
    lineTransitionTimer = 0;
    clearLyricGhosts();
    lyricLines = [];
    activeLineIndex = -1;
    activeWordNodes = [];
    if (lyricStage) {
      lyricStage.hidden = true;
      lyricStage.setAttribute('aria-hidden', 'true');
    }
    if (lyricLineEl) {
      lyricLineEl.textContent = '';
      lyricLineEl.className = 'home-music-lyric-line';
    }
  }

  function canShowLyricsInViewport() {
    return showLyrics && showText && hasPlaybackStarted;
  }

  function mountLyricPageLayer() {
    if (!showLyrics || !lyricStage) {
      return;
    }
    lyricPageLayer = document.querySelector('[data-lyric-page-layer]');
    if (!lyricPageLayer) {
      lyricPageLayer = document.createElement('div');
      lyricPageLayer.className = 'home-music-lyric-page-layer';
      lyricPageLayer.dataset.lyricPageLayer = '';
      document.body.appendChild(lyricPageLayer);
    }
    lyricPageLayer.appendChild(lyricStage);
  }

  function updateLyricViewportVisibility() {
    // The stage stays mounted at the page origin and naturally scrolls out of view.
    lyricViewportActive = true;
    root.classList.add('is-lyric-viewport-active');

    if (!lyricStage) {
      return;
    }
    if (!canShowLyricsInViewport() || activeLineIndex < 0) {
      lyricStage.hidden = true;
      lyricStage.setAttribute('aria-hidden', 'true');
      return;
    }
    lyricStage.hidden = false;
    lyricStage.setAttribute('aria-hidden', 'false');
    if (!audio.paused && lyricLines.length) {
      syncLyrics(audio.currentTime || 0);
    }
  }

  function shouldScatterLine(line) {
    const words = Array.isArray(line?.words)
      ? line.words.filter((word) => String(word?.text || '').trim())
      : [];
    const graphemeCount = luminous.segmentGraphemes(String(line?.fullText || '').replace(/\s+/g, '')).length;
    return words.length >= 4 || graphemeCount >= 20;
  }

  function intersectArea(first, second) {
    const width = Math.max(0, Math.min(first.right, second.right) - Math.max(first.left, second.left));
    const height = Math.max(0, Math.min(first.bottom, second.bottom) - Math.max(first.top, second.top));
    return width * height;
  }

  function getFirstScreenObstacles() {
    const screenWidth = window.innerWidth;
    const screenHeight = window.innerHeight;
    const selector = [
      '.site-header',
      '.site-brand',
      '.site-nav',
      'main h1',
      'main h2',
      'main h3',
      'main p',
      'main img',
      'main figure',
      '.home-character',
      '.hero-copy',
      '.entry-header',
      '.post-header',
      '.article-header',
      '[data-lightbox-image]',
    ].join(',');
    const seen = new Set();
    const obstacles = [];

    document.querySelectorAll(selector).forEach((element) => {
      if (seen.has(element) || element.closest('[data-music-player], [data-lyric-page-layer]')) {
        return;
      }
      seen.add(element);
      const rect = element.getBoundingClientRect();
      const documentTop = rect.top + window.scrollY;
      const documentLeft = rect.left + window.scrollX;
      if (rect.width < 18 || rect.height < 14 || documentTop >= screenHeight || documentTop + rect.height <= 0) {
        return;
      }
      const padding = element.matches('img, figure, .home-character, .home-character-scene') ? 18 : 12;
      obstacles.push({
        left: Math.max(0, documentLeft - padding),
        top: Math.max(0, documentTop - padding),
        right: Math.min(screenWidth, documentLeft + rect.width + padding),
        bottom: Math.min(screenHeight, documentTop + rect.height + padding),
      });
    });

    return obstacles;
  }

  function chooseOpenPosition(width, height, key, options = {}) {
    const compact = window.innerWidth <= 620;
    const horizontalPadding = compact ? 20 : 38;
    const topPadding = compact ? 78 : 96;
    const bottomPadding = compact ? 118 : 104;
    const safeWidth = Math.min(width, window.innerWidth - horizontalPadding * 2);
    const safeHeight = Math.min(height, window.innerHeight - topPadding - bottomPadding);
    const maxX = Math.max(horizontalPadding, window.innerWidth - horizontalPadding - safeWidth);
    const maxY = Math.max(topPadding, window.innerHeight - bottomPadding - safeHeight);
    const obstacles = options.obstacles || getFirstScreenObstacles();
    const reservations = options.reservations || [];
    let best = { left: horizontalPadding, top: topPadding, score: Number.POSITIVE_INFINITY };

    for (let index = 0; index < 84; index += 1) {
      const xRatio = luminous.hashSeed(`${key}:candidate:${index}:x`) / 0xffffffff;
      const yRatio = luminous.hashSeed(`${key}:candidate:${index}:y`) / 0xffffffff;
      const left = horizontalPadding + (maxX - horizontalPadding) * xRatio;
      const top = topPadding + (maxY - topPadding) * yRatio;
      const candidate = {
        left,
        top,
        right: left + safeWidth,
        bottom: top + safeHeight,
      };
      let score = obstacles.reduce((total, obstacle) => total + intersectArea(candidate, obstacle) * 24, 0);
      score += reservations.reduce((total, reservation) => total + intersectArea(candidate, reservation) * 5, 0);
      score += Math.abs((left + safeWidth / 2) - window.innerWidth / 2) * 0.025;
      if (score < best.score) {
        best = { left, top, score };
      }
    }

    return {
      left: Math.round(best.left),
      top: Math.round(best.top),
      width: Math.round(safeWidth),
      height: Math.round(safeHeight),
    };
  }

  function getLyricPosition(line) {
    if (!line) {
      return lyricPosition;
    }

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const compact = viewportWidth <= 620;
    const horizontalPadding = compact ? 16 : 36;
    const lineWidth = Math.round(Math.min(
      compact ? viewportWidth - horizontalPadding * 2 : 590,
      viewportWidth - horizontalPadding * 2
    ));
    return {
      left: Math.max(horizontalPadding, viewportWidth - horizontalPadding - lineWidth),
      top: compact ? 92 : 118,
      width: lineWidth,
      viewportWidth,
      viewportHeight,
      shortLine: true,
    };
  }

  function positionLyricLine(line) {
    if (!lyricStage || !line) {
      return;
    }
    lyricPosition = getLyricPosition(line);
    lyricStage.style.setProperty('--lyric-stage-left', '0px');
    lyricStage.style.setProperty('--lyric-stage-top', '0px');
    lyricStage.style.setProperty('--lyric-stage-width', `${lyricPosition.viewportWidth}px`);
  }

  function measureWordWidth(text) {
    const cacheKey = String(text) + ':' + fontScale;
    if (measurementCache.has(cacheKey)) {
      return measurementCache.get(cacheKey);
    }
    const canvas = measureWordWidth.canvas || (measureWordWidth.canvas = document.createElement('canvas'));
    const context = canvas.getContext('2d');
    if (!context) {
      return String(text).length * 12;
    }
    const lyricFont = window.getComputedStyle(lyricStage || document.body);
    const fontSize = Math.max(16, parseFloat(lyricFont.fontSize) || 16) * fontScale;
    context.font = '650 ' + fontSize + 'px ' + (lyricFont.fontFamily || 'sans-serif');
    const width = context.measureText(String(text)).width;
    measurementCache.set(cacheKey, width);
    return width;
  }

  function createLyricGhost(mode, offset = {}) {
    if (!lyricStage || !lyricLineEl?.textContent || mode === 'instant' || reducedMotion) {
      return;
    }
    const ghost = lyricLineEl.cloneNode(true);
    const ghostVariants = [
      lyricLineEl.classList.contains('is-scatter') ? 'is-scatter' : '',
      lyricLineEl.classList.contains('is-chorus') ? 'is-chorus' : '',
    ].filter(Boolean).join(' ');
    ghost.className = 'home-music-lyric-line home-music-lyric-ghost is-exit mode-' + mode
      + (ghostVariants ? ' ' + ghostVariants : '');
    ghost.style.setProperty('--line-exit-duration', reducedMotion ? '0s' : '0.9s');
    ghost.style.setProperty('--ghost-offset-x', `${offset.x || 0}px`);
    ghost.style.setProperty('--ghost-offset-y', `${offset.y || 0}px`);
    ghost.removeAttribute('data-lyric-line');
    ghost.setAttribute('aria-hidden', 'true');
    lyricStage.appendChild(ghost);
    const timeout = window.setTimeout(() => {
      ghost.remove();
      lyricGhostTimers.delete(timeout);
    }, reducedMotion ? 0 : 980);
    lyricGhostTimers.add(timeout);
  }

  function renderLyricWords(line) {
    if (!lyricLineEl || !line) {
      return 'normal';
    }
    lyricLineEl.innerHTML = '';
    activeWordNodes = [];

    const mode = luminous.getLineMode(line);
    const lineKey = line.startTime + ':' + line.fullText;
    const lineWords = Array.isArray(line.words) ? line.words : [];
    const isScatter = false;
    lyricLineEl.className = 'home-music-lyric-line is-enter mode-' + mode
      + (line.isChorus ? ' is-chorus' : '') + (isScatter ? ' is-scatter' : '');
    lyricLineEl.dataset.lineKey = lineKey;
    lyricLineEl.style.setProperty('--line-enter-duration', luminous.getModeConfig(mode).lineEnter + 's');
    lyricLineEl.style.setProperty('--line-exit-duration', luminous.getModeConfig(mode).lineExit + 's');
    lyricLineEl.style.setProperty('--lyric-primary', lyricTheme.primaryColor);
    lyricLineEl.style.setProperty('--lyric-secondary', lyricTheme.secondaryColor);
    lyricLineEl.style.setProperty('--lyric-accent', lyricTheme.accentColor);
    lyricLineEl.style.setProperty('--lyric-font-scale', fontScale);
    lyricLineEl.style.setProperty('--line-left', `${lyricPosition.left}px`);
    lyricLineEl.style.setProperty('--line-top', `${lyricPosition.top}px`);
    lyricLineEl.style.setProperty('--line-width', `${lyricPosition.width}px`);

    const lineDiv = document.createElement('div');
    lineDiv.className = 'home-music-lyric-subline';

    const lineStart = Number(line.startTime ?? line.start) || 0;
    const wordInterval = getLineCadence(line);
    const scatterObstacles = isScatter ? getFirstScreenObstacles() : [];
    const scatterReservations = [];
    let visibleWordIndex = 0;
    lineWords.forEach((word, wordIndex) => {
      const wordText = String(word.text || '').trim();
      if (!wordText) {
        return;
      }

      const layout = luminous.createWordLayout(currentLyricSeed, line.fullText, wordIndex, animationIntensity);
      const explicitColor = luminous.resolveActiveColor(wordText, { ...lyricTheme, accentColor: '' });
      const palette = [lyricTheme.accentColor, '#e88f72', '#67bdb3', '#b18be0', '#d4aa62', '#e878a5', '#6e9ee8'];
      const paletteSeed = `${currentLyricSeed}:${line.fullText}:${wordIndex}`;
      const paletteIndex = luminous.hashSeed(paletteSeed) % palette.length;
      const activeColor = explicitColor && explicitColor !== 'currentColor' ? explicitColor : palette[paletteIndex];
      const graphemeCount = Math.max(1, luminous.segmentGraphemes(wordText).length);
      const wordStart = lineStart + visibleWordIndex * wordInterval;
      const wordDuration = Math.min(0.42, Math.max(0.24, graphemeCount * 0.055));
      const wordEnd = wordStart + wordDuration;
      const animationWord = { ...word, startTime: wordStart, endTime: wordEnd };
      const graphemeTimings = luminous.distributeGraphemes(wordText, wordStart, wordEnd);
      const measuredWidth = measureWordWidth(wordText);
      const expansionGap = Math.max(4, measuredWidth * Math.max(layout.activeScale - 1, 0) * 0.36);
      const wordSpan = document.createElement('span');
      wordSpan.className = 'home-music-word is-waiting';
      wordSpan.setAttribute('aria-label', wordText);
      wordSpan.dataset.state = 'waiting';
      wordSpan.dataset.start = wordStart;
      wordSpan.dataset.end = wordEnd;
      wordSpan.style.setProperty('--word-x', layout.x + 'px');
      wordSpan.style.setProperty('--word-y', layout.y + 'px');
      wordSpan.style.setProperty('--word-rotate', layout.rotate + 'deg');
      wordSpan.style.setProperty('--word-passed-rotate', layout.passedRotate + 'deg');
      wordSpan.style.setProperty('--word-base-scale', layout.scale);
      wordSpan.style.setProperty('--word-active-scale', layout.activeScale);
      wordSpan.style.setProperty('--word-gap', layout.marginRight + 'em');
      wordSpan.style.setProperty('--word-expansion-gap', expansionGap + 'px');
      wordSpan.style.setProperty('--word-active-color', activeColor);
      if (isScatter) {
        const compactViewport = window.innerWidth <= 620;
        const conservativeWidth = Math.max(
          measuredWidth * 1.55,
          graphemeCount * (compactViewport ? 14 : 20) * fontScale
        );
        const scatterSeed = `${currentLyricSeed}:${line.fullText}:scatter:${wordIndex}`;
        const wordBox = chooseOpenPosition(
          conservativeWidth * layout.activeScale + Math.abs(layout.x) + 14,
          (compactViewport ? 48 : 58) * layout.activeScale,
          scatterSeed,
          { obstacles: scatterObstacles, reservations: scatterReservations }
        );
        wordSpan.style.setProperty('--scatter-x', `${wordBox.left}px`);
        wordSpan.style.setProperty('--scatter-y', `${wordBox.top}px`);
        scatterReservations.push({
          left: wordBox.left - 8,
          top: wordBox.top - 8,
          right: wordBox.left + wordBox.width + 8,
          bottom: wordBox.top + wordBox.height + 8,
        });
      }

      const bodyLayer = document.createElement('span');
      bodyLayer.className = 'home-music-word-body';
      bodyLayer.setAttribute('aria-hidden', 'true');
      bodyLayer.textContent = wordText;
      wordSpan.appendChild(bodyLayer);

      const glowLayer = document.createElement('span');
      glowLayer.className = 'home-music-word-glow';
      glowLayer.setAttribute('aria-hidden', 'true');
      const glowChars = graphemeTimings.map((timing) => {
        const charSpan = document.createElement('span');
        charSpan.className = 'home-music-glow-char';
        charSpan.textContent = timing.text;
        glowLayer.appendChild(charSpan);
        return { node: charSpan, timing, lit: false };
      });
      wordSpan.appendChild(glowLayer);

      if (line.isChorus) {
        const ripple = document.createElement('span');
        ripple.className = 'home-music-word-ripple';
        ripple.setAttribute('aria-hidden', 'true');
        wordSpan.appendChild(ripple);
      }

      activeWordNodes.push({ node: wordSpan, word: animationWord, mode, state: 'waiting', glowChars });
      visibleWordIndex += 1;
      lineDiv.appendChild(wordSpan);
    });

    lyricLineEl.appendChild(lineDiv);
    if (mode === 'instant' || reducedMotion) {
      lyricLineEl.classList.remove('is-enter');
      lyricLineEl.classList.add('is-active');
    } else {
      window.requestAnimationFrame(() => {
        if (lyricLineEl.dataset.lineKey === lineKey) {
          lyricLineEl.classList.remove('is-enter');
          lyricLineEl.classList.add('is-active');
        }
      });
    }
    return mode;
  }

  function setActiveLyricLine(nextIndex, options = {}) {
    if (!showText || !lyricLineEl || !lyricStage) {
      return;
    }
    if (nextIndex < 0 || nextIndex >= lyricLines.length) {
      if (options.forceHide) {
        clearLyricStage();
      }
      return;
    }
    if (activeLineIndex === nextIndex && !options.force) {
      return;
    }

    const previousLine = activeLineIndex >= 0 ? lyricLines[activeLineIndex] : null;
    const nextLine = lyricLines[nextIndex];
    if (previousLine && activeLineIndex !== nextIndex) {
      createLyricGhost(luminous.getLineMode(previousLine));
    }

    window.clearTimeout(lineTransitionTimer);
    lineTransitionTimer = 0;
    activeLineIndex = nextIndex;
    positionLyricLine(nextLine);
    if (canShowLyricsInViewport()) {
      lyricStage.hidden = false;
      lyricStage.setAttribute('aria-hidden', 'false');
    }
    renderLyricWords(nextLine);
  }

  function restartGlyphFlare(entry, currentTime) {
    const config = luminous.getModeConfig(entry.mode);
    const multiplier = config.glowTailMultiplier || 3;
    const maxTail = config.glowTailMax || 0.2;
    entry.glowChars.forEach((char) => {
      const start = Number(char.timing.startTime) || 0;
      const end = Math.max(start + 0.04, Number(char.timing.endTime) || start + 0.04);
      const duration = Math.min(Math.max((end - start) * multiplier, 0.08), maxTail + (end - start));
      const elapsed = (Number(currentTime) || 0) - start;
      const delay = elapsed > 0 ? -Math.min(elapsed, duration) : -elapsed;
      char.node.classList.remove('is-lit');
      char.node.style.setProperty('--flare-delay', `${delay}s`);
      char.node.style.setProperty('--flare-duration', `${duration}s`);
      void char.node.offsetWidth;
      char.node.classList.add('is-lit');
      char.lit = true;
    });
  }

  function updateWordStates(time, options = {}) {
    if (!lyricLineEl || activeLineIndex < 0) {
      return;
    }

    activeWordNodes.forEach((entry) => {
      const nextState = luminous.getWordState(time, entry.word, entry.mode);
      if (nextState !== entry.state) {
        entry.node.classList.remove('is-waiting', 'is-active', 'is-passed');
        entry.node.classList.add('is-' + nextState);
        entry.node.dataset.state = nextState;
        entry.state = nextState;
        if (nextState === 'active') {
          restartGlyphFlare(entry, time);
        } else if (nextState === 'waiting') {
          entry.glowChars.forEach((char) => {
            char.node.classList.remove('is-lit');
            char.lit = false;
          });
        }
      }
      if (options.force && nextState === 'active') {
        restartGlyphFlare(entry, time);
      }
    });
  }

  function findLineIndex(time) {
    if (!lyricLines.length) {
      return -1;
    }
    let low = 0;
    let high = lyricLines.length - 1;
    let found = -1;
    while (low <= high) {
      const mid = (low + high) >> 1;
      if (lyricLines[mid].start <= time + 0.05) {
        found = mid;
        low = mid + 1;
      } else {
        high = mid - 1;
      }
    }
    if (found >= 0 && time > lyricLines[found].end + 0.35) {
      return -1;
    }
    return found;
  }

  function syncLyrics(time, options = {}) {
    if (!showText || !lyricViewportActive) {
      if (lyricStage) {
        lyricStage.hidden = true;
        lyricStage.setAttribute('aria-hidden', 'true');
      }
      return;
    }
    const nextIndex = findLineIndex(time);
    if (nextIndex < 0) {
      activeLineIndex = -1;
      if (lyricStage) {
        lyricStage.hidden = true;
        lyricStage.setAttribute('aria-hidden', 'true');
      }
      return;
    }

    if (nextIndex !== activeLineIndex || options.force) {
      setActiveLyricLine(nextIndex, { force: true, instant: true });
    }
    updateWordStates(time, options);
  }

  function tickLyrics() {
    lyricRaf = 0;
    if (!lyricLines.length || audio.paused) {
      return;
    }
    sampleAudioRhythm();
    syncLyrics(audio.currentTime || 0);
    lyricRaf = window.requestAnimationFrame(tickLyrics);
  }

  function startLyricLoop() {
    if (!lyricLines.length) {
      return;
    }
    if (!lyricRaf) {
      lyricRaf = window.requestAnimationFrame(tickLyrics);
    }
  }

  async function loadLyricsForTrack(track) {
    clearLyricStage();
    if (!track || !track.lyrics) {
      return;
    }
    const url = track.lyrics;
    if (lyricCache.has(url)) {
      lyricLines = lyricCache.get(url);
      if (lyricLines.length) {
        if (!audio.paused) {
          setActiveLyricLine(findLineIndex(audio.currentTime || 0), { instant: true, force: true });
          startLyricLoop();
        }
      }
      return;
    }

    try {
      const response = await fetch(url, { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`lyrics ${response.status}`);
      }
      const text = await response.text();
      const parsed = parseLrc(text);
      lyricCache.set(url, parsed);
      lyricLines = parsed;
      if (lyricLines.length) {
        if (!audio.paused) {
          setActiveLyricLine(findLineIndex(audio.currentTime || 0), { instant: true, force: true });
          startLyricLoop();
        }
      }
    } catch (error) {
      lyricCache.set(url, []);
      lyricLines = [];
    }
  }

  function updateMediaSession(track) {
    if (!('mediaSession' in navigator) || !track) {
      return;
    }
    const artwork = track.cover
      ? [{ src: track.cover, sizes: '512x512', type: 'image/jpeg' }]
      : [];
    navigator.mediaSession.metadata = new window.MediaMetadata({
      title: track.title || '未命名',
      artist: track.artist || '个人歌单',
      album: '口袋音乐',
      artwork,
    });
    navigator.mediaSession.setActionHandler('play', () => audio.play().catch(() => {}));
    navigator.mediaSession.setActionHandler('pause', () => audio.pause());
    navigator.mediaSession.setActionHandler('previoustrack', () => loadTrack(index - 1, { autoplay: true }));
    navigator.mediaSession.setActionHandler('nexttrack', () => loadTrack(index + 1, { autoplay: true }));
  }

  function getTrackTheme(track) {
    const seed = luminous.hashSeed(`${track?.title || ''}:${track?.artist || ''}:${index}`);
    const hue = 168 + (seed % 148);
    return {
      primary: `hsl(${hue} 72% 62%)`,
      soft: `hsl(${(hue + 26) % 360} 68% 74%)`,
      hot: `hsl(${(hue + 318) % 360} 84% 69%)`,
    };
  }

  function applyTrackTheme(theme) {
    root.style.setProperty('--music-theme', theme.primary);
    root.style.setProperty('--music-theme-soft', theme.soft);
    root.style.setProperty('--music-theme-hot', theme.hot);
    lyricTheme.accentColor = theme.primary;
    lyricTheme.secondaryColor = theme.soft;
  }

  function sampleCoverTheme(image, fallback) {
    if (!image?.naturalWidth) {
      applyTrackTheme(fallback);
      return;
    }
    try {
      const canvas = document.createElement('canvas');
      canvas.width = 24;
      canvas.height = 24;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.drawImage(image, 0, 0, canvas.width, canvas.height);
      const pixels = context.getImageData(0, 0, canvas.width, canvas.height).data;
      let red = 0;
      let green = 0;
      let blue = 0;
      let count = 0;
      for (let offset = 0; offset < pixels.length; offset += 16) {
        const r = pixels[offset];
        const g = pixels[offset + 1];
        const b = pixels[offset + 2];
        const spread = Math.max(r, g, b) - Math.min(r, g, b);
        const light = (r + g + b) / 3;
        if (spread < 20 || light < 30 || light > 230) continue;
        red += r;
        green += g;
        blue += b;
        count += 1;
      }
      if (!count) throw new Error('no color sample');
      const color = `rgb(${Math.round(red / count)} ${Math.round(green / count)} ${Math.round(blue / count)})`;
      applyTrackTheme({
        primary: color,
        soft: `color-mix(in srgb, ${color} 58%, white)`,
        hot: `color-mix(in srgb, ${color} 55%, #ff6fae)`,
      });
    } catch (error) {
      applyTrackTheme(fallback);
    }
  }

  function loadTrack(nextIndex, options = {}) {
    const total = tracks.length;
    index = ((nextIndex % total) + total) % total;
    const track = tracks[index];
    const shouldAutoplay = Boolean(options.autoplay);
    currentLyricSeed = config.seed || track.src || track.title || index;
    const trackTheme = getTrackTheme(track);
    applyTrackTheme(trackTheme);
    if (archiveIndexEl) {
      archiveIndexEl.textContent = `ARCHIVE ${String(index + 1).padStart(3, '0')}`;
    }

    audio.src = track.src;
    if (track.type) {
      audio.setAttribute('type', track.type);
    }
    audio.load();

    setTexts(titleEls, track.title || '未命名');
    setTexts(artistEls, track.artist || '个人歌单');

    if (coverImage) {
      if (track.cover) {
        coverImage.onload = () => sampleCoverTheme(coverImage, trackTheme);
        coverImage.src = track.cover;
        coverImage.hidden = false;
        if (coverImage.complete) {
          sampleCoverTheme(coverImage, trackTheme);
        }
        if (noteFallback) {
          noteFallback.hidden = true;
        }
      } else {
        coverImage.removeAttribute('src');
        coverImage.hidden = true;
        if (noteFallback) {
          noteFallback.hidden = false;
        }
      }
    }

    updateMediaSession(track);
    writeState({ index, currentTime: 0, volume: audio.volume, muted: audio.muted });
    if (showLyrics) {
      loadLyricsForTrack(track);
    }

    if (shouldAutoplay) {
      audio.play().catch(() => setPlayingUi(false));
    } else {
      setPlayingUi(false);
    }
  }

  function clearResumeOnInteraction() {
    if (!resumeOnInteraction) {
      return;
    }
    document.removeEventListener('pointerdown', resumeOnInteraction, true);
    document.removeEventListener('keydown', resumeOnInteraction, true);
    resumeOnInteraction = null;
    root.classList.remove('is-resume-pending');
  }

  function requestPlaybackRestore() {
    isRestoringPlayback = true;
    audio.play().then(() => {
      isRestoringPlayback = false;
      clearResumeOnInteraction();
    }).catch(() => {
      isRestoringPlayback = false;
      writeState({ playing: true, index, currentTime: audio.currentTime || 0 });
      root.classList.add('is-resume-pending');
      if (resumeOnInteraction) {
        return;
      }
      resumeOnInteraction = () => {
        requestPlaybackRestore();
      };
      document.addEventListener('pointerdown', resumeOnInteraction, true);
      document.addEventListener('keydown', resumeOnInteraction, true);
      setPlayingUi(false);
    });
  }

  function restoreState() {
    const saved = readState();
    if (Number.isInteger(saved.index) && saved.index >= 0 && saved.index < tracks.length) {
      index = saved.index;
    }
    if (typeof saved.volume === 'number' && saved.volume >= 0 && saved.volume <= 1) {
      audio.volume = saved.volume;
    } else {
      audio.volume = 0.7;
    }
    audio.muted = Boolean(saved.muted);
    loadTrack(index, { autoplay: false });
    const shouldResume = saved.playing === true;
    const restorePlayback = () => {
      if (typeof saved.currentTime === 'number' && saved.currentTime > 0 && Number.isFinite(audio.duration) && audio.duration > 0) {
        audio.currentTime = Math.min(saved.currentTime, Math.max(0, audio.duration - 0.25));
      }
      if (shouldResume) {
        requestPlaybackRestore();
      }
    };
    if (audio.readyState >= 1) {
      restorePlayback();
    } else {
      audio.addEventListener('loadedmetadata', restorePlayback, { once: true });
    }
  }

  function persistPlaybackState() {
    writeState({
      index,
      currentTime: audio.currentTime || 0,
      volume: audio.volume,
      muted: audio.muted,
      playing: !audio.paused && !audio.ended,
    });
  }

  function togglePlay() {
    if (audio.paused) {
      audio.play().catch(() => {});
    } else {
      audio.pause();
    }
  }

  playButtons.forEach((button) => {
    button?.addEventListener('click', (event) => {
      event.preventDefault();
      togglePlay();
    });
  });

  previousButton?.addEventListener('click', () => {
    if (audio.currentTime > 3) {
      audio.currentTime = 0;
      return;
    }
    loadTrack(index - 1, { autoplay: !audio.paused });
  });

  nextButton?.addEventListener('click', () => {
    loadTrack(index + 1, { autoplay: !audio.paused });
  });

  panelClose?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    collapsePlayer();
  });

  seekInput?.addEventListener('pointerdown', () => {
    isSeeking = true;
  });
  seekInput?.addEventListener('pointerup', () => {
    isSeeking = false;
  });
  seekInput?.addEventListener('input', () => {
    if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
      return;
    }
    const ratio = Number(seekInput.value) / 1000;
    setProgressCss(seekInput, ratio);
    if (currentTimeEl) {
      currentTimeEl.textContent = formatTime(audio.duration * ratio);
    }
  });
  seekInput?.addEventListener('change', () => {
    if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
      return;
    }
    audio.currentTime = (Number(seekInput.value) / 1000) * audio.duration;
    isSeeking = false;
    writeState({ currentTime: audio.currentTime });
    if (lyricLines.length) {
      setActiveLyricLine(findLineIndex(audio.currentTime), { instant: true, force: true });
      updateWordStates(audio.currentTime);
    }
  });

  audio.addEventListener('play', () => {
    isRestoringPlayback = false;
    clearResumeOnInteraction();
    hasPlaybackStarted = true;
    writeState({ playing: true, index, currentTime: audio.currentTime || 0 });
    setPlayingUi(true);
    updateLyricViewportVisibility();
    revealPlayer();
    initAudioAnalysis().catch(() => {});
    startLyricLoop();
  });
  audio.addEventListener('pause', () => {
    if (!isPageUnloading && !isRestoringPlayback) {
      writeState({ playing: false, index, currentTime: audio.currentTime || 0 });
    }
    setPlayingUi(false);
    window.cancelAnimationFrame(lyricRaf);
    lyricRaf = 0;
    revealPlayer({ schedule: false });
    updateLyricViewportVisibility();
  });
  audio.addEventListener('timeupdate', () => {
    if (!isSeeking && seekInput && Number.isFinite(audio.duration) && audio.duration > 0) {
      const ratio = audio.currentTime / audio.duration;
      seekInput.value = String(Math.round(ratio * 1000));
      setProgressCss(seekInput, ratio);
    }
    if (currentTimeEl) {
      currentTimeEl.textContent = formatTime(audio.currentTime);
    }
    if (lyricLines.length > 0 && !audio.paused) {
      syncLyrics(audio.currentTime);
      startLyricLoop();
    }
    const now = Date.now();
    if (now - lastStateWrite >= 1000) {
      lastStateWrite = now;
      writeState({ currentTime: audio.currentTime, index, volume: audio.volume, muted: audio.muted, playing: !audio.paused });
    }
  });
  audio.addEventListener('seeked', () => {
    if (lyricLines.length > 0 && !audio.paused) {
      syncLyrics(audio.currentTime, { force: true });
      startLyricLoop();
    }
  });
  audio.addEventListener('loadedmetadata', () => {
    if (durationEl) {
      durationEl.textContent = formatTime(audio.duration);
    }
  });
  audio.addEventListener('ended', () => {
    loadTrack(index + 1, { autoplay: true });
  });

  let lyricResizeTimer = 0;
  window.addEventListener('resize', () => {
    window.clearTimeout(lyricResizeTimer);
    lyricResizeTimer = window.setTimeout(() => {
      measurementCache.clear();
      if (activeLineIndex >= 0 && lyricLines[activeLineIndex]) {
        positionLyricLine(lyricLines[activeLineIndex]);
        renderLyricWords(lyricLines[activeLineIndex]);
        updateWordStates(audio.currentTime || 0);
      }
    }, 140);
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setPanelOpen(false);
    }
  });

  window.addEventListener('beforeunload', () => {
    isPageUnloading = true;
    persistPlaybackState();
  });
  window.addEventListener('pagehide', () => {
    isPageUnloading = true;
    persistPlaybackState();
  });
  window.addEventListener('pageshow', () => {
    isPageUnloading = false;
  });

  panel?.addEventListener('pointerenter', () => revealPlayer({ schedule: false, fromHover: true }));
  panel?.addEventListener('pointerleave', () => scheduleAutoHide());
  panel?.addEventListener('focusin', () => revealPlayer({ schedule: false }));
  panel?.addEventListener('focusout', () => window.setTimeout(() => scheduleAutoHide(), 0));
  panel?.addEventListener('pointerdown', () => revealPlayer());
  panel?.addEventListener('click', () => {
    if (root.classList.contains('is-collapsed')) {
      revealPlayer();
    }
  });
  if (reducedMotion) {
    root.classList.add('is-reduced-motion');
  }

  mountLyricPageLayer();
  panel.hidden = false;
  restoreState();
  updateLyricViewportVisibility();
  scheduleAutoHide(2600);
})();
