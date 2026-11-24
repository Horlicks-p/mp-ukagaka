// Text-only Japanese preview. Reuse the pinned runtime's vowel/onset tables.
import { kanaToMoras, skippedKanaCharacters } from './studio/packages/runtime/src/engine/kana.js';

const hiragana = c => c.replace(/[ァ-ヶ]/g, k => String.fromCharCode(k.charCodeAt(0) - 0x60));
const smallKana = new Set([...'ゃゅょぁぃぅぇぉ']);

export function prepareJapaneseLine(input, { moraSeconds = 0.19 } = {}) {
  if (!Number.isFinite(moraSeconds) || moraSeconds < 0.1 || moraSeconds > 1) {
    throw new Error('假名速度必須介於每拍 0.1～1 秒。');
  }
  const text = String(input).normalize('NFKC').replace(/[\r\n\t]/g, ' ').trim();
  if (!text) throw new Error('請先輸入日文平假名或片假名。');
  const unsupported = skippedKanaCharacters(text);
  if (unsupported.length) throw new Error(`請把「${unsupported.join('')}」改成日文假名讀音；目前不自動轉換漢字。`);
  const groups = [];
  for (const char of text) {
    const kana = hiragana(char), previous = groups.at(-1);
    if (smallKana.has(kana) || char === 'ー') {
      if (!previous || !/[あ-ゖア-ヶー]$/.test(previous) || /[っんッン]$/.test(previous)) {
        throw new Error(`「${char}」前面需要有可接續的假名。`);
      }
      groups[groups.length - 1] += char;
    } else groups.push(char);
  }
  let duration = 0, revealed = '';
  const units = groups.map(group => {
    const moras = kanaToMoras(group, moraSeconds);
    const first = moras[0], last = moras.at(-1);
    if (!last) throw new Error(`無法解析「${group}」的讀音。`);
    // ティ / ファ etc. have one beat, using the final small vowel. きゃ is
    // already combined by upstream; each ー extends the same vowel one beat.
    const seconds = /ー/.test(group) ? moraSeconds * (1 + [...group].filter(c => c === 'ー').length) : first.dur;
    const start = duration;
    duration += seconds;
    revealed += group;
    return { text: group, revealed, vowel: last.vowel, onset: first.onset, start, end: duration };
  });
  if (!units.some(unit => unit.vowel !== 'n')) throw new Error('請輸入包含母音的日文假名。');
  return { text, units, duration };
}

export function sampleJapaneseLine(line, seconds) {
  const index = line.units.findIndex(unit => seconds < unit.end - 1e-9);
  if (index === -1) return { done: true, text: line.text, unit: '', vowel: null, index };
  const unit = line.units[index];
  // A short lip closure for m/b/p, then let the native engine smooth the vowel.
  const closing = unit.onset === 'lips' && seconds - unit.start < Math.min(0.04, (unit.end - unit.start) * 0.2);
  return { done: false, text: unit.revealed, unit: unit.text, vowel: closing ? 'n' : unit.vowel, index };
}
