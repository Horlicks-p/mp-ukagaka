// Text-only Japanese preview. Reuse the pinned runtime's vowel/onset tables.
import { kanaToMoras, skippedKanaCharacters } from './studio/packages/runtime/src/engine/kana.js';

const hiragana = c => c.replace(/[ァ-ヶ]/g, k => String.fromCharCode(k.charCodeAt(0) - 0x60));
const smallKana = new Set([...'ゃゅょぁぃぅぇぉ']);

export function prepareJapaneseLine(input, { moraSeconds = 0.19 } = {}) {
  if (!Number.isFinite(moraSeconds) || moraSeconds < 0.1 || moraSeconds > 1) {
    throw new Error('かなの速さは1拍あたり0.1〜1秒の範囲で指定してください。');
  }
  const text = String(input).normalize('NFKC').replace(/[\r\n\t]/g, ' ').trim();
  if (!text) throw new Error('ひらがなかカタカナを入力してください。');
  const unsupported = skippedKanaCharacters(text);
  if (unsupported.length) throw new Error(`「${unsupported.join('')}」は読みのかなに書き換えてください。漢字は自動で変換しません。`);
  const groups = [];
  for (const char of text) {
    const kana = hiragana(char), previous = groups.at(-1);
    if (smallKana.has(kana) || char === 'ー') {
      if (!previous || !/[あ-ゖア-ヶー]$/.test(previous) || /[っんッン]$/.test(previous)) {
        throw new Error(`「${char}」の前には、続けて読めるかなが必要です。`);
      }
      groups[groups.length - 1] += char;
    } else groups.push(char);
  }
  let duration = 0, revealed = '';
  const raw = groups.map(group => {
    const moras = kanaToMoras(group, moraSeconds);
    const first = moras[0], last = moras.at(-1);
    if (!last) throw new Error(`「${group}」の読みを解析できません。`);
    // ティ / ファ etc. have one beat, using the final small vowel. きゃ is
    // already combined by upstream; each ー extends the same vowel one beat.
    const seconds = /ー/.test(group) ? moraSeconds * (1 + [...group].filter(c => c === 'ー').length) : first.dur;
    const start = duration;
    duration += seconds;
    revealed += group;
    return { text: group, revealed, vowel: last.vowel, onset: first.onset, start, end: duration, dur: seconds };
  });
  const units = raw.map((unit, index) => {
    const next = raw[index + 1];
    const lipClose = unit.onset === 'lips' ? Math.min(0.03, unit.dur * 0.16) : 0;
    const needsDip = unit.vowel !== 'n' && next && next.vowel !== 'n'
      && (next.onset !== null || next.vowel === unit.vowel);
    const tailDip = needsDip ? Math.min(0.045, unit.dur * 0.24) : 0;
    return { ...unit, lipClose, tailDip };
  });
  if (!units.some(unit => unit.vowel !== 'n')) throw new Error('母音を含むかなを入力してください。');
  return { text, units, duration };
}

export function sampleJapaneseLine(line, seconds) {
  const index = line.units.findIndex(unit => seconds < unit.end - 1e-9);
  if (index === -1) return { done: true, text: line.text, unit: '', vowel: null, index };
  const unit = line.units[index];
  // Close briefly before a bilabial onset and dip at the boundary before consonant or
  // repeated-vowel moras so each spoken syllable articulates clearly on long lines.
  const closing = (unit.lipClose > 0 && seconds - unit.start < unit.lipClose)
    || (unit.tailDip > 0 && seconds >= unit.end - unit.tailDip);
  return { done: false, text: unit.revealed, unit: unit.text, vowel: closing ? 'n' : unit.vowel, index };
}
