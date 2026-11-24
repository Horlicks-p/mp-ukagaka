import '/runtime.js';
import { MeshBridge } from '/mesh-bridge.mjs';

const $ = id => document.getElementById(id);
const mouthLabels = { a: 'あ', i: 'い', u: 'う', e: 'え', o: 'お', n: '閉口／停頓' };
const faceResponse = await fetch('/face-presets.json');
const facePresets = faceResponse.ok ? await faceResponse.json() : {};
for (const name of ['angry', 'good_mood', 'smug']) {
  const preset = facePresets[name];
  if (!preset) continue;
  const existing = $('emotion').querySelector(`option[value="${name}"]`);
  if (existing) existing.textContent = preset.label;
  else $('emotion').add(new Option(preset.label, name));
}
const bridge = new MeshBridge($('avatar-host'), $('poster'), facePresets);
window.mavatarDemo = bridge;
function cancelLine() {
  bridge.stopSpeaking();
}
function status() {
  const s = bridge.state;
  $('status').textContent = s.status === 'loading' ? '正在載入模型…' : s.status === 'error'
    ? '模型載入失敗，已顯示完整靜態圖。' : s.sleeping ? '休息中' : s.speechError
      ? s.speechError : s.speaking ? `正在說話：${s.speechUnit}（${mouthLabels[s.speechVowel] || '…'}）`
        : s.status === 'ready' ? '角色已就緒' : '角色已卸載';
  if (s.speechText) $('dialog').textContent = s.speechText;
  $('sleep').textContent = s.sleeping ? '叫醒她' : '睡一下';
  $('visibility').textContent = s.visible ? '隱藏角色' : '顯示角色';
}
bridge.addEventListener('statechange', status);
$('emotion').onchange = event => bridge.setEmotion(event.target.value);
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
