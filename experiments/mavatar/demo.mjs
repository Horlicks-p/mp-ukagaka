import '/runtime.js';
import { MeshBridge } from '/mesh-bridge.mjs';

const $ = id => document.getElementById(id);
const bridge = new MeshBridge($('avatar-host'), $('poster'));
window.mavatarDemo = bridge;
let timer = 0;
let lineGeneration = 0;
function cancelLine() {
  ++lineGeneration;
  clearTimeout(timer);
  bridge.stopSpeaking();
}
function status() {
  const s = bridge.state;
  $('status').textContent = s.status === 'loading' ? '正在載入模型…' : s.status === 'error'
    ? '模型載入失敗，已顯示完整靜態圖。' : s.sleeping ? '休息中' : s.speaking ? '正在說話…' : s.status === 'ready' ? '角色已就緒' : '角色已卸載';
  $('sleep').textContent = s.sleeping ? '叫醒她' : '睡一下';
  $('visibility').textContent = s.visible ? '隱藏角色' : '顯示角色';
}
bridge.addEventListener('statechange', status);
$('emotion').onchange = event => bridge.setEmotion(event.target.value);
$('say').onclick = () => {
  cancelLine();
  if (!bridge.startSpeaking()) return;
  const generation = lineGeneration;
  const characters = Array.from($('line').value.trim());
  $('dialog').textContent = '';
  let index = 0;
  function delta() {
    if (generation !== lineGeneration) return;
    if (index === characters.length) { bridge.stopSpeaking(); return; }
    $('dialog').textContent += characters[index++];
    timer = setTimeout(delta, 85);
  }
  delta();
};
$('cancel').onclick = cancelLine;
$('sleep').onclick = () => { cancelLine(); bridge.sleep(!bridge.state.sleeping); };
$('visibility').onclick = () => { cancelLine(); bridge.setVisible(!bridge.state.visible); };
$('reload').onclick = () => { cancelLine(); void bridge.load('/avatar.mavatar'); };
$('fallback').onclick = () => { cancelLine(); void bridge.load('/missing-avatar.mavatar'); };
$('kana').onclick = () => {
  cancelLine();
  if (!bridge.element?.avatar || bridge.state.sleeping) return;
  bridge.element.avatar.speakKana('こんにちは。すこし、やすもう。');
  $('dialog').textContent = 'こんにちは。すこし、やすもう。';
};
document.addEventListener('visibilitychange', () => { if (document.hidden) cancelLine(); });
window.addEventListener('pagehide', () => { cancelLine(); bridge.destroy(); });
await bridge.load('/avatar.mavatar');
