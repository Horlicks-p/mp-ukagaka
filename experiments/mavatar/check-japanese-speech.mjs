import assert from 'node:assert/strict';
import { prepareJapaneseLine, sampleJapaneseLine } from './japanese-speech.mjs';

const vowels = prepareJapaneseLine('あいうえお', { moraSeconds: 0.2 });
assert.deepEqual(vowels.units.map(unit => unit.vowel), ['a', 'i', 'u', 'e', 'o']);
for (let i = 0; i < 5; i++) {
  const sample = sampleJapaneseLine(vowels, i * 0.2 + 0.1);
  assert.equal(sample.vowel, vowels.units[i].vowel);
  assert.equal(sample.text, 'あいうえお'.slice(0, i + 1));
}
assert.equal(sampleJapaneseLine(vowels, 1).done, true);
const compounds = prepareJapaneseLine('きゃシュちょティファフェヴォ', { moraSeconds: 0.2 });
assert.deepEqual(compounds.units.map(unit => unit.text), ['きゃ', 'シュ', 'ちょ', 'ティ', 'ファ', 'フェ', 'ヴォ']);
assert.deepEqual(compounds.units.map(unit => unit.vowel), ['a', 'u', 'o', 'i', 'a', 'e', 'o']);
assert(Math.abs(compounds.duration - 1.4) < 1e-9, 'small kana must not add a beat');
const special = prepareJapaneseLine('コーんっ、ぱ。', { moraSeconds: 0.2 });
assert.equal(special.units[0].end, 0.4);
assert.deepEqual(special.units.map(unit => unit.vowel), ['o', 'n', 'n', 'n', 'a', 'n']);
const lips = special.units[4];
assert.equal(sampleJapaneseLine(special, lips.start + 0.01).vowel, 'n');
assert.equal(sampleJapaneseLine(special, lips.start + 0.1).vowel, 'a');
assert.equal(prepareJapaneseLine('ﾌｪﾙﾝ\nまほう').text, 'フェルン まほう');
for (const text of ['魔法', '你好', 'hello', '', 'ゃ', 'ー', '。', 'んー']) {
  assert.throws(() => prepareJapaneseLine(text), undefined, `must explain unsupported input: ${text}`);
}
assert.throws(() => prepareJapaneseLine('あ', { moraSeconds: NaN }));
const repeated = prepareJapaneseLine('にちすこし', { moraSeconds: 0.2 });
assert.equal(sampleJapaneseLine(repeated, 0.1).vowel, 'i');
assert.equal(sampleJapaneseLine(repeated, 0.18).vowel, 'n');
assert.equal(sampleJapaneseLine(repeated, 0.21).vowel, 'i');
assert.equal(sampleJapaneseLine(repeated, 0.38).vowel, 'n');
assert.equal(sampleJapaneseLine(repeated, 0.41).vowel, 'u');
assert.equal(sampleJapaneseLine(repeated, 0.58).vowel, 'n');
assert.equal(sampleJapaneseLine(repeated, 0.78).vowel, 'n');
assert.equal(sampleJapaneseLine(repeated, 0.98).vowel, 'i');
const glide = prepareJapaneseLine('やすもう', { moraSeconds: 0.2 });
assert.equal(sampleJapaneseLine(glide, 0.58).vowel, 'o');
console.log('Japanese timeline: vowels, synchronized text, compound kana, long vowels, closures, pauses, articulation dips, normalization and unsupported-input checks passed.');
