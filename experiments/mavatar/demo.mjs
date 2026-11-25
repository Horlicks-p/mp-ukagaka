import '/runtime.js';
import { MeshBridge } from '/mesh-bridge.mjs';

const $ = id => document.getElementById(id);
const mouthLabels = { a: 'あ', i: 'い', u: 'う', e: 'え', o: 'お', n: '閉じる／間' };
const emotionLabels = { neutral: 'ふつう', happy: '笑顔', relaxed: 'リラックス', sad: '悲しい',
  angry: 'むっ', surprised: '驚き', awkward: '気まずい／ごまかし', good_mood: 'ごきげん', smug: 'ドヤ顔' };
const faceResponse = await fetch('/face-presets.json');
const facePresets = faceResponse.ok ? await faceResponse.json() : {};
// good_mood and smug exist only for models whose bridge presets provide them.
const emotions = ['neutral', 'happy', 'good_mood', 'relaxed', 'sad', 'angry', 'surprised', 'awkward', 'smug']
  .filter(name => !['good_mood', 'smug'].includes(name) || facePresets[name]);
for (const name of emotions) {
  const chip = document.createElement('button');
  chip.type = 'button';
  chip.dataset.emotion = name;
  chip.textContent = emotionLabels[name];
  chip.onclick = () => bridge.setEmotion(name);
  $('emotion').append(chip);
}
const bridge = new MeshBridge($('avatar-host'), $('poster'), facePresets);
window.mavatarDemo = bridge;
function cancelLine() {
  bridge.stopSpeaking();
}
function status() {
  const s = bridge.state;
  $('status').textContent = s.status === 'loading' ? 'モデルを読み込んでいます…' : s.status === 'error'
    ? '読み込めなかったため、静止画を表示しています。' : s.sleeping ? 'おやすみ中' : s.speechError
      ? s.speechError : s.speaking ? `話しています：${s.speechUnit}（${mouthLabels[s.speechVowel] || '…'}）`
        : s.status === 'ready' ? '準備できました' : 'アバターを解放しました';
  if (s.speechText) $('dialog').textContent = s.speechText;
  for (const chip of $('emotion').children) chip.setAttribute('aria-pressed', String(chip.dataset.emotion === s.emotion));
  $('sleep').textContent = s.sleeping ? '起こす' : '眠らせる';
  $('visibility').textContent = s.visible ? 'アバターを隠す' : 'アバターを表示';
}
bridge.addEventListener('statechange', status);
$('say').onclick = () => {
  cancelLine();
  bridge.speakKana($('line').value);
};
$('cancel').onclick = cancelLine;
$('sleep').onclick = () => { cancelLine(); bridge.sleep(!bridge.state.sleeping); };
$('visibility').onclick = () => { cancelLine(); bridge.setVisible(!bridge.state.visible); };
$('reload').onclick = () => { cancelLine(); void bridge.load('/avatar.mavatar'); };
$('fallback').onclick = () => { cancelLine(); void bridge.load('/missing-avatar.mavatar'); };
$('kana').onclick = () => {
  cancelLine();
  bridge.speakKana('あ、い、う、え、お。', { moraSeconds: 0.4 });
};
for (const button of document.querySelectorAll('[data-vowel]')) button.onclick = () => {
  cancelLine();
  bridge.speakKana(`${button.dataset.vowel}ー。`, { moraSeconds: 0.4 });
};
document.addEventListener('visibilitychange', () => { if (document.hidden) cancelLine(); });
window.addEventListener('pagehide', () => { cancelLine(); bridge.destroy(); });
await bridge.load('/avatar.mavatar');
