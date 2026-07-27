(function (globalScope, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  } else {
    globalScope.DuolaLuminousLyrics = api;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  const MODE_CONFIG = {
    instant: {
      lookahead: 0.03,
      lineEnter: 0.1,
      lineExit: 0.1,
      glowTailMultiplier: 1.5,
      glowTailMax: 0.12,
    },
    fast: {
      lookahead: 0.08,
      lineEnter: 0.2,
      lineExit: 0.16,
      glowTailMultiplier: 3,
      glowTailMax: 0.2,
    },
    normal: {
      lookahead: 0.15,
      lineEnter: 0.34,
      lineExit: 0.24,
      glowTailMultiplier: 5,
      glowTailMax: 0.7,
    },
  };

  const INTENSITY_CONFIG = {
    calm: { x: 3, y: 2, rotate: 1.5, activeScale: 1.22, passedDrift: 1 },
    normal: { x: 8, y: 5, rotate: 5, activeScale: 1.34, passedDrift: 2.5 },
    chaotic: { x: 18, y: 12, rotate: 24, activeScale: 1.46, passedDrift: 6 },
  };

  function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  function segmentGraphemes(text, locale) {
    const source = String(text || '');
    if (!source) {
      return [];
    }
    if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
      const segmenter = new Intl.Segmenter(locale, { granularity: 'grapheme' });
      return Array.from(segmenter.segment(source), (entry) => entry.segment);
    }
    return Array.from(source);
  }

  function normalizeRange(startTime, endTime, fallbackDuration) {
    const start = Number.isFinite(Number(startTime)) ? Number(startTime) : 0;
    const proposedEnd = Number.isFinite(Number(endTime)) ? Number(endTime) : start;
    const duration = Math.max(proposedEnd - start, fallbackDuration || 0.08);
    return { startTime: start, endTime: start + duration };
  }

  function distributeGraphemes(text, startTime, endTime, locale) {
    const graphemes = segmentGraphemes(text, locale);
    if (!graphemes.length) {
      return [];
    }
    const range = normalizeRange(startTime, endTime, 0.08);
    const slice = (range.endTime - range.startTime) / graphemes.length;
    return graphemes.map((grapheme, index) => ({
      text: grapheme,
      startTime: range.startTime + slice * index,
      endTime: range.startTime + slice * (index + 1),
    }));
  }

  function buildGraphemeTimings(word, locale) {
    if (!word) {
      return [];
    }
    if (Array.isArray(word.syllables) && word.syllables.length) {
      return word.syllables.flatMap((syllable) => distributeGraphemes(
        syllable.text,
        syllable.startTime,
        syllable.endTime,
        locale
      ));
    }
    return distributeGraphemes(word.text, word.startTime, word.endTime, locale);
  }

  function getLineMode(line) {
    const duration = Math.max(0, Number(line?.endTime) - Number(line?.startTime));
    if (duration < 0.25) {
      return 'instant';
    }
    if (duration < 0.8) {
      return 'fast';
    }
    return 'normal';
  }

  function getModeConfig(mode) {
    return MODE_CONFIG[mode] || MODE_CONFIG.normal;
  }

  function getWordState(currentTime, word, mode) {
    if (!word) {
      return 'waiting';
    }
    const range = normalizeRange(word.startTime, word.endTime, 0.08);
    const lookahead = getModeConfig(mode).lookahead;
    if (currentTime >= range.startTime - lookahead && currentTime <= range.endTime) {
      return 'active';
    }
    if (currentTime > range.endTime) {
      return 'passed';
    }
    return 'waiting';
  }

  function getGlowIntensity(currentTime, timing, mode) {
    if (!timing || currentTime < timing.startTime) {
      return 0;
    }
    const config = getModeConfig(mode);
    const range = normalizeRange(timing.startTime, timing.endTime, 0.04);
    const duration = range.endTime - range.startTime;
    const peakDuration = Math.min(Math.max(duration * 0.3, 0.025), 0.08);
    if (currentTime <= range.startTime + peakDuration) {
      return clamp((currentTime - range.startTime) / peakDuration, 0, 1);
    }
    const tailDuration = Math.min(duration * config.glowTailMultiplier, config.glowTailMax);
    const tailEnd = range.endTime + tailDuration;
    if (currentTime >= tailEnd) {
      return 0;
    }
    return clamp(1 - (currentTime - (range.startTime + peakDuration)) / Math.max(tailEnd - (range.startTime + peakDuration), 0.01), 0, 1);
  }

  function hashSeed(value) {
    const source = String(value ?? '');
    let hash = 2166136261;
    for (let index = 0; index < source.length; index += 1) {
      hash ^= source.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }
    return hash >>> 0;
  }

  function createSeededRandom(seed) {
    let state = hashSeed(seed) || 1;
    return function random() {
      state += 0x6D2B79F5;
      let value = state;
      value = Math.imul(value ^ (value >>> 15), value | 1);
      value ^= value + Math.imul(value ^ (value >>> 7), value | 61);
      return ((value ^ (value >>> 14)) >>> 0) / 4294967296;
    };
  }

  function createWordLayout(seed, lineText, wordIndex, intensity) {
    const config = INTENSITY_CONFIG[intensity] || INTENSITY_CONFIG.normal;
    const random = createSeededRandom(`${seed}:${lineText}:${wordIndex}`);
    const centered = () => random() * 2 - 1;
    const rotate = centered() * config.rotate;
    return {
      x: centered() * config.x,
      y: centered() * config.y,
      rotate,
      scale: 0.96 + random() * 0.1,
      activeScale: config.activeScale + random() * 0.08,
      passedRotate: rotate + centered() * config.passedDrift,
      marginRight: 0.22 + random() * 0.18,
    };
  }

  function resolveActiveColor(wordText, theme) {
    const match = Array.isArray(theme?.wordColors)
      ? theme.wordColors.find((entry) => entry && entry.word === wordText)
      : null;
    return match?.color || theme?.accentColor || 'currentColor';
  }

  return {
    MODE_CONFIG,
    INTENSITY_CONFIG,
    buildGraphemeTimings,
    clamp,
    createSeededRandom,
    createWordLayout,
    distributeGraphemes,
    getGlowIntensity,
    getLineMode,
    getModeConfig,
    getWordState,
    hashSeed,
    normalizeRange,
    resolveActiveColor,
    segmentGraphemes,
  };
});
