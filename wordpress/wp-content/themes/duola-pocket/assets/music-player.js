(() => {
  const root = document.querySelector('[data-music-player]');
  const config = window.duolaMusicPlayer || {};
  const tracks = Array.isArray(config.tracks) ? config.tracks.filter((track) => track && track.src) : [];
  const luminous = window.DuolaLuminousLyrics;
  if (!root || !tracks.length || !luminous) {
    return;
  }
  if (root.dataset.playerInitialized === 'true') {
    return;
  }
  root.dataset.playerInitialized = 'true';
  let showLyrics = Boolean(config.showLyrics) && root.dataset.showLyrics === 'true';

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
  const lyricStage = root.querySelector('[data-lyric-stage]');
  const lyricLineEl = root.querySelector('[data-lyric-line]');
  const spectrumBars = Array.from(root.querySelectorAll('[data-music-wave] i'));
  const volumeButton = root.querySelector('[data-mute]');
  const volumeIcon = root.querySelector('[data-volume-icon]');
  const muteIcon = root.querySelector('[data-mute-icon]');
  const volumeInput = root.querySelector('[data-volume]');
  const modeButton = root.querySelector('[data-play-mode]');
  const playlistButton = root.querySelector('[data-playlist-toggle]');
  const playlist = root.querySelector('[data-playlist]');
  const playlistListEl = root.querySelector('[data-playlist-list]');
  const playlistCountEl = root.querySelector('[data-playlist-count]');
  const playlistClose = root.querySelector('[data-close-playlist]');
  const toastEl = root.querySelector('[data-player-toast]');
  let lyricPageLayer = null;

  const storageKey = config.storageKey || 'duolaMusicPlayer:v3';
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lyricCache = new Map();

  let index = 0;
  let isSeeking = false;
  let lyricLines = [];
  let activeLineIndex = -1;
  let masterRaf = 0;
  let masterFrame = 0;
  let lineTransitionTimer = 0;
  let currentLyricSeed = config.seed || 'duola-pocket';
  let activeWordNodes = [];
  const measurementCache = new Map();
  const lyricGhostTimers = new Set();
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
  let isPageUnloading = false;
  let isRestoringPlayback = false;
  let resumeOnInteraction = null;
  let lastStateWrite = 0;
  let playMode = 'list';
  let playlistOpen = false;
  let playlistItems = [];
  let errorChain = 0;
  let toastTimer = 0;
  const PLAY_MODES = ['list', 'one', 'shuffle'];
  const PLAY_MODE_LABELS = { list: '列表循环', one: '单曲循环', shuffle: '随机播放' };

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
    const spectrumEnd = Math.min(104, frequencyData.length - 1);
    const visualTime = Number.isFinite(audio.currentTime)
      ? audio.currentTime
      : window.performance.now() / 1000;
    spectrumBars.forEach((bar, barIndex) => {
      const ratio = spectrumBars.length > 1 ? barIndex / (spectrumBars.length - 1) : 0;
      const center = Math.round(2 + Math.pow(ratio, 1.42) * Math.max(1, spectrumEnd - 2));
      const radius = barIndex < 8 ? 2 : 3;
      let total = 0;
      let samples = 0;
      for (let bin = Math.max(1, center - radius); bin <= Math.min(spectrumEnd, center + radius); bin += 1) {
        total += frequencyData[bin];
        samples += 1;
      }
      const average = samples ? total / samples : 0;
      const realLevel = Math.max(0, Math.min(1, Math.pow(average / 205, 0.82)));
      const primaryWave = Math.pow((Math.sin(barIndex * 0.73 + visualTime * 2.35) + 1) / 2, 1.3);
      const secondaryWave = (Math.sin(barIndex * 1.91 - visualTime * 1.45) + 1) / 2;
      const accentPulse = Math.pow(Math.max(0, Math.sin(visualTime * 3.1 + barIndex * 0.43)), 4);
      const seededLift = ((((barIndex * 29) % 17) / 16) - 0.5) * 0.22;
      const artisticLevel = Math.max(0, Math.min(1,
        0.08 + primaryWave * 0.56 + secondaryWave * 0.18 + accentPulse * 0.26 + seededLift
      ));
      const mixedLevel = realLevel * 0.52 + artisticLevel * 0.48;
      const contrastedLevel = 0.5 + (mixedLevel - 0.5) * 1.45;
      const level = Math.max(0.06, Math.min(1, contrastedLevel));
      bar.style.setProperty('--bar-level', level.toFixed(3));
    });
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
    setRootLevel('--music-energy', rhythmEnergy);
    setRootLevel('--music-pulse', rhythmPulse);
    setRootLevel('--music-high', rhythmHigh);
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
    const clamped = Math.max(0, Math.min(1, ratio));
    const value = clamped * 100;
    input.style.setProperty('--progress', `${value}%`);
    // The waveform bars read a unitless 0..1 value to color played bars.
    const field = input.closest('[data-music-wave]');
    if (field && field !== input) {
      field.style.setProperty('--progress-n', clamped.toFixed(4));
    }
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

  function showToast(message) {
    if (!toastEl || !message) {
      return;
    }
    toastEl.textContent = message;
    toastEl.classList.add('is-visible');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
      toastEl.classList.remove('is-visible');
    }, 2400);
  }

  function pickIndex(step) {
    if (playMode === 'shuffle' && tracks.length > 1) {
      let next = index;
      while (next === index) {
        next = Math.floor(Math.random() * tracks.length);
      }
      return next;
    }
    return index + step;
  }

  function handlePrevious() {
    if (audio.currentTime > 3) {
      audio.currentTime = 0;
      return;
    }
    loadTrack(pickIndex(-1), { autoplay: !audio.paused });
  }

  function handleNext(options = {}) {
    loadTrack(pickIndex(1), options);
  }

  function applyPlayModeUi() {
    if (!modeButton) {
      return;
    }
    modeButton.querySelectorAll('[data-mode-icon]').forEach((icon) => {
      icon.hidden = icon.dataset.modeIcon !== playMode;
    });
    const label = PLAY_MODE_LABELS[playMode] || PLAY_MODE_LABELS.list;
    modeButton.setAttribute('aria-label', `播放模式：${label}`);
    modeButton.setAttribute('title', label);
  }

  function cyclePlayMode() {
    const nextSlot = (PLAY_MODES.indexOf(playMode) + 1) % PLAY_MODES.length;
    playMode = PLAY_MODES[nextSlot];
    applyPlayModeUi();
    writeState({ mode: playMode });
    showToast(`播放模式：${PLAY_MODE_LABELS[playMode]}`);
  }

  function applyVolumeUi() {
    if (volumeInput) {
      volumeInput.value = String(Math.round(audio.volume * 100));
      setProgressCss(volumeInput, audio.muted ? 0 : audio.volume);
    }
    if (volumeIcon && muteIcon) {
      volumeIcon.hidden = audio.muted;
      muteIcon.hidden = !audio.muted;
    }
    volumeButton?.setAttribute('aria-label', audio.muted ? '取消静音' : '静音');
    root.classList.toggle('is-muted', audio.muted);
  }

  function setVolume(value, persist = true) {
    const next = Math.max(0, Math.min(1, Number(value)));
    audio.volume = next;
    if (next > 0 && audio.muted) {
      audio.muted = false;
    }
    applyVolumeUi();
    if (persist) {
      writeState({ volume: audio.volume, muted: audio.muted });
    }
  }

  function toggleMute() {
    audio.muted = !audio.muted;
    applyVolumeUi();
    writeState({ volume: audio.volume, muted: audio.muted });
  }

  function buildPlaylist() {
    if (!playlistListEl) {
      return;
    }
    playlistListEl.textContent = '';
    tracks.forEach((track, trackIndex) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'home-music-playlist-item';
      button.dataset.trackIndex = String(trackIndex);

      const num = document.createElement('span');
      num.className = 'home-music-playlist-num';
      num.textContent = String(trackIndex + 1).padStart(2, '0');

      const text = document.createElement('span');
      text.className = 'home-music-playlist-text';
      const title = document.createElement('strong');
      title.textContent = track.title || '未命名';
      const artist = document.createElement('small');
      artist.textContent = track.artist || '个人歌单';
      text.appendChild(title);
      text.appendChild(artist);

      const meter = document.createElement('span');
      meter.className = 'home-music-playlist-meter';
      meter.setAttribute('aria-hidden', 'true');
      meter.innerHTML = '<i></i><i></i><i></i>';

      button.appendChild(num);
      button.appendChild(text);
      button.appendChild(meter);
      button.addEventListener('click', () => {
        togglePlaylist(false);
        if (trackIndex === index) {
          if (audio.paused) {
            audio.play().catch(() => {});
          }
          return;
        }
        loadTrack(trackIndex, { autoplay: true });
      });
      item.appendChild(button);
      playlistListEl.appendChild(item);
    });
    if (playlistCountEl) {
      playlistCountEl.textContent = `${tracks.length} 首`;
    }
    playlistItems = Array.from(playlistListEl.querySelectorAll('.home-music-playlist-item'));
    refreshPlaylistUi();
  }

  function refreshPlaylistUi() {
    playlistItems.forEach((item, itemIndex) => {
      const isCurrent = itemIndex === index;
      item.classList.toggle('is-current', isCurrent);
      if (isCurrent) {
        item.setAttribute('aria-current', 'true');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  }

  function togglePlaylist(force) {
    if (!playlist) {
      return;
    }
    const open = typeof force === 'boolean' ? force : playlist.hidden;
    playlist.hidden = !open;
    playlistOpen = open;
    playlistButton?.setAttribute('aria-expanded', String(open));
    root.classList.toggle('is-playlist-open', open);
  }

  function seekBy(delta) {
    if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
      return;
    }
    audio.currentTime = Math.max(0, Math.min(audio.duration, (audio.currentTime || 0) + delta));
    writeState({ currentTime: audio.currentTime });
  }

  function handleTrackError() {
    setPlayingUi(false);
    stopMasterLoop();
    if (tracks.length > 1 && errorChain < tracks.length) {
      errorChain += 1;
      showToast('音频加载失败，正在切换下一首');
      window.setTimeout(() => {
        loadTrack(pickIndex(1), { autoplay: hasPlaybackStarted });
      }, 1200);
    } else {
      showToast('音频加载失败');
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

  function parkLyricStage() {
    if (!lyricStage) {
      return;
    }
    lyricStage.hidden = true;
    lyricStage.setAttribute('aria-hidden', 'true');
    if (!root.contains(lyricStage)) {
      root.insertBefore(lyricStage, panel || root.firstChild);
    }
    lyricPageLayer?.remove();
    lyricPageLayer = null;
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
    const ghostVariants = lyricLineEl.classList.contains('is-chorus') ? 'is-chorus' : '';
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
    lyricLineEl.className = 'home-music-lyric-line is-enter mode-' + mode
      + (line.isChorus ? ' is-chorus' : '');
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

  const rootVarCache = {};

  function setRootLevel(name, value) {
    const quantized = Math.round(Math.max(0, Math.min(1, value)) * 50) / 50;
    if (rootVarCache[name] === quantized) {
      return;
    }
    rootVarCache[name] = quantized;
    root.style.setProperty(name, quantized.toFixed(2));
  }

  // One master rAF loop drives both the spectrum sampling and the lyric
  // sync so the player never schedules two animation frames per tick.
  function tickMaster() {
    masterRaf = 0;
    if (audio.paused || audio.ended) {
      return;
    }
    masterFrame += 1;
    // Spectrum bars animate with a 92ms CSS transition, so ~30fps sampling
    // is visually identical to 60fps and halves the per-frame style writes.
    if (!reducedMotion && masterFrame % 2 === 0) {
      sampleAudioRhythm();
    }
    // Keep the waveform playhead and played-bar coloring buttery smooth;
    // the timeupdate event only fires a few times per second.
    if (!isSeeking && seekInput && Number.isFinite(audio.duration) && audio.duration > 0) {
      setProgressCss(seekInput, (audio.currentTime || 0) / audio.duration);
    }
    if (lyricLines.length) {
      syncLyrics(audio.currentTime || 0);
    }
    masterRaf = window.requestAnimationFrame(tickMaster);
  }

  function startMasterLoop() {
    if (masterRaf || document.hidden) {
      return;
    }
    masterRaf = window.requestAnimationFrame(tickMaster);
  }

  function stopMasterLoop() {
    window.cancelAnimationFrame(masterRaf);
    masterRaf = 0;
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
          startMasterLoop();
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
          startMasterLoop();
        }
      }
    } catch (error) {
      lyricCache.set(url, []);
      lyricLines = [];
    }
  }

  function guessCoverType(url) {
    const ext = String(url || '').split('?')[0].split('.').pop().toLowerCase();
    const map = { png: 'image/png', webp: 'image/webp', gif: 'image/gif', avif: 'image/avif', svg: 'image/svg+xml' };
    return map[ext] || 'image/jpeg';
  }

  function updateMediaSession(track) {
    if (!('mediaSession' in navigator) || !track) {
      return;
    }
    const artwork = track.cover
      ? [{ src: track.cover, sizes: '512x512', type: guessCoverType(track.cover) }]
      : [];
    navigator.mediaSession.metadata = new window.MediaMetadata({
      title: track.title || '未命名',
      artist: track.artist || '个人歌单',
      album: '口袋音乐',
      artwork,
    });
    navigator.mediaSession.setActionHandler('play', () => audio.play().catch(() => {}));
    navigator.mediaSession.setActionHandler('pause', () => audio.pause());
    navigator.mediaSession.setActionHandler('previoustrack', () => handlePrevious());
    navigator.mediaSession.setActionHandler('nexttrack', () => handleNext({ autoplay: true }));
    try {
      navigator.mediaSession.setActionHandler('seekto', (details) => {
        if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
          return;
        }
        if (details.fastSeek && Number.isFinite(details.seekTime)) {
          audio.currentTime = details.seekTime;
        } else if (Number.isFinite(details.seekOffset)) {
          audio.currentTime = Math.max(0, Math.min(audio.duration, audio.currentTime + details.seekOffset));
        }
      });
    } catch (error) {
      // seekto is not supported everywhere; ignore.
    }
    updateMediaPosition();
  }

  function updateMediaPosition() {
    if (!('mediaSession' in navigator) || typeof navigator.mediaSession.setPositionState !== 'function') {
      return;
    }
    if (!Number.isFinite(audio.duration) || audio.duration <= 0) {
      return;
    }
    try {
      navigator.mediaSession.setPositionState({
        duration: audio.duration,
        playbackRate: audio.playbackRate || 1,
        position: Math.max(0, Math.min(audio.currentTime || 0, audio.duration)),
      });
    } catch (error) {
      // Some browsers reject position state while metadata is loading.
    }
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
    refreshPlaylistUi();
    writeState({ index, currentTime: 0, volume: audio.volume, muted: audio.muted, mode: playMode });
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
    playMode = PLAY_MODES.includes(saved.mode) ? saved.mode : 'list';
    applyVolumeUi();
    applyPlayModeUi();
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

  previousButton?.addEventListener('click', () => handlePrevious());
  nextButton?.addEventListener('click', () => handleNext({ autoplay: !audio.paused }));

  modeButton?.addEventListener('click', (event) => {
    event.preventDefault();
    cyclePlayMode();
  });

  playlistButton?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    togglePlaylist();
  });

  playlistClose?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    togglePlaylist(false);
  });

  volumeButton?.addEventListener('click', (event) => {
    event.preventDefault();
    toggleMute();
  });

  volumeInput?.addEventListener('input', () => {
    setVolume(Number(volumeInput.value) / 100, false);
  });

  volumeInput?.addEventListener('change', () => {
    writeState({ volume: audio.volume, muted: audio.muted });
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
    initAudioAnalysis().catch(() => {});
    startMasterLoop();
  });
  audio.addEventListener('playing', () => {
    errorChain = 0;
    updateMediaPosition();
  });
  audio.addEventListener('pause', () => {
    if (!isPageUnloading && !isRestoringPlayback) {
      writeState({ playing: false, index, currentTime: audio.currentTime || 0 });
    }
    setPlayingUi(false);
    stopMasterLoop();
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
      startMasterLoop();
    }
    const now = Date.now();
    if (now - lastStateWrite >= 1000) {
      lastStateWrite = now;
      writeState({ currentTime: audio.currentTime, index, volume: audio.volume, muted: audio.muted, playing: !audio.paused, mode: playMode });
      updateMediaPosition();
    }
  });
  audio.addEventListener('seeked', () => {
    if (lyricLines.length > 0 && !audio.paused) {
      syncLyrics(audio.currentTime, { force: true });
      startMasterLoop();
    }
    updateMediaPosition();
  });
  audio.addEventListener('loadedmetadata', () => {
    if (durationEl) {
      durationEl.textContent = formatTime(audio.duration);
    }
    updateMediaPosition();
  });
  audio.addEventListener('error', () => {
    if (!audio.getAttribute('src') || isPageUnloading) {
      return;
    }
    handleTrackError();
  });
  audio.addEventListener('ended', () => {
    if (playMode === 'one') {
      audio.currentTime = 0;
      audio.play().catch(() => setPlayingUi(false));
      return;
    }
    handleNext({ autoplay: true });
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
    if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) {
      return;
    }
    if (event.key === 'Escape') {
      if (playlistOpen) {
        togglePlaylist(false);
      }
      return;
    }
    const target = event.target;
    if (target && typeof target.matches === 'function'
      && target.matches('input, textarea, select, button, [role="button"], summary, a[href], [contenteditable="true"]')) {
      return;
    }
    switch (event.key) {
      case ' ':
        event.preventDefault();
        togglePlay();
        break;
      case 'ArrowLeft':
        event.preventDefault();
        seekBy(-5);
        break;
      case 'ArrowRight':
        event.preventDefault();
        seekBy(5);
        break;
      case 'ArrowUp':
        event.preventDefault();
        setVolume(audio.volume + 0.1);
        break;
      case 'ArrowDown':
        event.preventDefault();
        setVolume(audio.volume - 0.1);
        break;
      case 'm':
      case 'M':
        toggleMute();
        break;
      case 'n':
      case 'N':
        handleNext({ autoplay: !audio.paused });
        break;
      case 'p':
      case 'P':
        handlePrevious();
        break;
      default:
        break;
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

  document.addEventListener('turbo:before-render', () => {
    persistPlaybackState();
    parkLyricStage();
  });
  document.addEventListener('turbo:render', () => {
    isPageUnloading = false;
    const onFront = window.location.pathname === '/' || window.location.pathname === '';
    showLyrics = onFront;
    root.dataset.showLyrics = onFront ? 'true' : 'false';
    if (showLyrics) {
      mountLyricPageLayer();
    } else {
      parkLyricStage();
    }
    if (lyricStage) {
      const canShow = showLyrics && showText && hasPlaybackStarted;
      lyricStage.hidden = !canShow;
      lyricStage.setAttribute('aria-hidden', String(!canShow));
    }
    updateLyricViewportVisibility();
  });

  document.addEventListener('pointerdown', (event) => {
    if (playlistOpen && playlist && !playlist.contains(event.target)
      && !(playlistButton && playlistButton.contains(event.target))) {
      togglePlaylist(false);
    }
  }, { passive: true });

  // Freeze the animation loop entirely while the tab is hidden; the audio
  // keeps playing and the visuals resync on return.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopMasterLoop();
      persistPlaybackState();
      return;
    }
    if (!audio.paused) {
      if (lyricLines.length) {
        syncLyrics(audio.currentTime || 0, { force: true });
      }
      startMasterLoop();
    }
  });
  if (reducedMotion) {
    root.classList.add('is-reduced-motion');
  }

  mountLyricPageLayer();
  buildPlaylist();
  restoreState();
  updateLyricViewportVisibility();
})();
