const test = require('node:test');
const assert = require('node:assert/strict');
const core = require('../assets/luminous-lyrics-core.js');

test('segments emoji and combining marks as graphemes', () => {
  assert.deepEqual(core.segmentGraphemes('A👨‍👩‍👧‍👦é中文'), ['A', '👨‍👩‍👧‍👦', 'é', '中', '文']);
});

test('word state moves waiting to active to passed', () => {
  const word = { text: 'light', startTime: 1, endTime: 2 };
  assert.equal(core.getWordState(0.7, word, 'normal'), 'waiting');
  assert.equal(core.getWordState(0.9, word, 'normal'), 'active');
  assert.equal(core.getWordState(1.5, word, 'normal'), 'active');
  assert.equal(core.getWordState(2.01, word, 'normal'), 'passed');
});

test('seeking backwards restores waiting state', () => {
  const word = { text: 'return', startTime: 4, endTime: 5 };
  assert.equal(core.getWordState(5.5, word, 'normal'), 'passed');
  assert.equal(core.getWordState(2, word, 'normal'), 'waiting');
});

test('extremely short lines use instant timing caps', () => {
  const line = { startTime: 1, endTime: 1.18 };
  assert.equal(core.getLineMode(line), 'instant');
  assert.equal(core.getModeConfig('instant').glowTailMax, 0.12);
});

test('grapheme timing respects syllables before word timing', () => {
  const word = {
    text: 'hello',
    startTime: 1,
    endTime: 3,
    syllables: [
      { text: 'hel', startTime: 1, endTime: 1.5 },
      { text: 'lo', startTime: 2, endTime: 3 },
    ],
  };
  const timings = core.buildGraphemeTimings(word);
  assert.equal(timings.length, 5);
  assert.equal(timings[0].startTime, 1);
  assert.equal(timings[2].endTime, 1.5);
  assert.equal(timings[3].startTime, 2);
  assert.equal(timings[4].endTime, 3);
});

test('seeded layout remains deterministic', () => {
  const first = core.createWordLayout('song', 'same line', 2, 'normal');
  const second = core.createWordLayout('song', 'same line', 2, 'normal');
  assert.deepEqual(first, second);
});

test('keyword colors override accent color', () => {
  const theme = {
    accentColor: '#ff00aa',
    wordColors: [{ word: 'moon', color: '#f5c451' }],
  };
  assert.equal(core.resolveActiveColor('moon', theme), '#f5c451');
  assert.equal(core.resolveActiveColor('night', theme), '#ff00aa');
});

test('invalid word ranges are normalized for state and glow', () => {
  const word = { text: 'now', startTime: 3, endTime: 2 };
  assert.equal(core.getWordState(3.02, word, 'instant'), 'active');
  const timings = core.buildGraphemeTimings(word);
  assert.ok(timings.every((timing) => timing.endTime > timing.startTime));
});

test('instant glow always expires within its short tail cap', () => {
  const timing = { text: '闪', startTime: 1, endTime: 1.04 };
  assert.ok(core.getGlowIntensity(1.05, timing, 'instant') > 0);
  assert.equal(core.getGlowIntensity(1.17, timing, 'instant'), 0);
});
