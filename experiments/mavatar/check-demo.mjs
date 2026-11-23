import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { createDemoServer } from './server.mjs';
import { createRig, PARAMS } from './studio/packages/runtime/src/engine/rig.js';

const root = path.dirname(fileURLToPath(import.meta.url));
const requireStudio = createRequire(new URL('./studio/package.json', import.meta.url));
const { chromium } = requireStudio('@playwright/test');
const { unzipSync } = requireStudio('fflate');
const project = path.join(root, 'studio/projects/frieren-chest');
const output = path.join(project, 'review-demo');
await mkdir(output, { recursive: true });
const rig = JSON.parse(await readFile(path.join(project, 'rig.json'), 'utf8'));
const defaults = Object.fromEntries(PARAMS.map(p => [p.id, p.def]));
const roi = { leftHand: [170, 785, 325, 850], rightHand: [750, 780, 880, 850],
  chest: [15, 750, 1018, 1370], legs: [375, 850, 715, 1540] };
const poses = { breath: { breath: 1 }, left: { bodyAngleX: -10, bodyAngleZ: -8, angleX: -30, angleZ: -15, breath: 1 },
  right: { bodyAngleX: 10, bodyAngleZ: 8, angleX: 30, angleZ: 15, breath: 1 } };
const result = { upstream: 'fb823c05c9963d241fec42e772959be562c927eb', checks: [], numerical: {}, pixels: {} };
const archiveBytes = new Uint8Array(await readFile(path.join(project, 'frieren-runtime.mavatar')));
result.avatarBytes = archiveBytes.length;
result.avatarSha256 = createHash('sha256').update(archiveBytes).digest('hex');
const archive = unzipSync(archiveBytes);
assert(!Object.keys(archive).some(name => /(^|\/)source\.png$/.test(name)));
result.checks.push('archive omits source.png');

// Evaluate the actual upstream deformer at densely sampled points, not a second implementation.
const model = createRig(rig);
const physics = { gain: 0, strands: [], bunL: [0, 0], bunR: [0, 0] };
for (const [name, box] of Object.entries(roi)) {
  let max = 0;
  for (let y = box[1]; y <= box[3]; y += 5) for (let x = box[0]; x <= box[2]; x += 5) {
    const weights = model.baseWeights(x, y);
    for (const values of Object.values(poses)) {
      const transformed = model.deformBase(x, y, weights, { ...defaults, ...values }, physics, [0, 0]);
      max = Math.max(max, Math.hypot(transformed[0] - x, transformed[1] - y));
    }
  }
  result.numerical[name] = { maxSourcePixelDisplacement: max };
  assert(max < 0.001, `${name} drifted ${max} source pixels`);
}
result.checks.push('anchored ROI source displacement < 0.001px under extreme poses');

const server = createDemoServer();
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = `http://127.0.0.1:${server.address().port}`;
let browser;
try {
  browser = await chromium.launch();
  result.browser = browser.version();
  const context = await browser.newContext({ viewport: { width: 1280, height: 940 }, deviceScaleFactor: 1,
    recordVideo: { dir: path.join(output, 'video'), size: { width: 1280, height: 940 } } });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const external = [];
  await page.route('**/*', route => {
    const url = new URL(route.request().url());
    if (url.origin !== origin) { external.push(url.origin); return route.abort(); }
    return route.continue();
  });
  const loadStarted = Date.now();
  await page.goto(origin);
  await page.waitForFunction(() => window.mavatarDemo?.state.status === 'ready');
  result.initialLoadMs = Date.now() - loadStarted;
  result.checks.push('real .mavatar loads in WebGL2 with only local assets');
  await page.screenshot({ path: path.join(output, 'desktop.png'), fullPage: true });
  result.pixels = await page.evaluate(({ defaults, roi, poses, rig }) => {
    const el = window.mavatarDemo.element, av = el.avatar;
    el.paused = true;
    av.setAutoIdle(false); av.setAutoMotion(false); av.setEmotion(null); av.setExpression('neutral');
    const canvas = el.shadowRoot.querySelector('canvas');
    const copy = document.createElement('canvas'); copy.width = canvas.width; copy.height = canvas.height;
    const ctx = copy.getContext('2d', { willReadFrequently: true });
    const snapshot = () => { ctx.clearRect(0, 0, copy.width, copy.height); ctx.drawImage(canvas, 0, 0); return ctx.getImageData(0, 0, copy.width, copy.height).data; };
    av.setParameters(defaults); av.advance(1, 60);
    const base = snapshot();
    const scale = Math.min(canvas.width / (rig.image.width * (1 + 2 * rig.view.padSide)), canvas.height / (rig.image.height * (1 + rig.view.padTop)));
    const ox = (canvas.width - rig.image.width * scale) / 2, oy = canvas.height - rig.image.height * scale;
    const stats = {};
    for (const [pose, values] of Object.entries(poses)) {
      av.setParameters({ ...defaults, ...values }); av.advance(1, 60);
      const data = snapshot(); stats[pose] = {};
      for (const [name, [x0, y0, x1, y1]] of Object.entries(roi)) {
        let maximum = 0, changed = 0, sum = 0, pixels = 0;
        for (let y = Math.ceil(oy + y0 * scale); y < Math.floor(oy + y1 * scale); y++) {
          for (let x = Math.ceil(ox + x0 * scale); x < Math.floor(ox + x1 * scale); x++) {
            const index = (y * canvas.width + x) * 4;
            let delta = 0;
            for (let k = 0; k < 4; k++) delta = Math.max(delta, Math.abs(data[index + k] - base[index + k]));
            maximum = Math.max(maximum, delta); changed += delta > 8 ? 1 : 0; sum += delta; pixels++;
          }
        }
        stats[pose][name] = { maxChannelDelta: maximum, pixelsOver8: changed, meanDelta: sum / pixels, pixels };
      }
    }
    av.setParameters({}); av.setAutoIdle(true); el.paused = false;
    return { canvas: [canvas.width, canvas.height], poses: stats };
  }, { defaults, roi, poses, rig });
  for (const [pose, values] of Object.entries(result.pixels.poses)) for (const [name, stats] of Object.entries(values)) {
    assert.equal(stats.pixelsOver8, 0, `${pose}/${name} visibly changed`);
  }
  result.checks.push('350px rendered hand/chest/leg ROI: no pixels differ by >8/255');
  const expressions = await page.evaluate(async ({ defaults }) => {
    const bridge = window.mavatarDemo, el = bridge.element, av = el.avatar;
    el.paused = true;
    av.setAutoIdle(false);
    const result = {};
    const canvas = el.shadowRoot.querySelector('canvas');
    const sheet = document.createElement('canvas'); sheet.width = 1404; sheet.height = 570;
    const ctx = sheet.getContext('2d'); ctx.fillStyle = '#192421'; ctx.fillRect(0, 0, sheet.width, sheet.height);
    const scale = Math.min(canvas.width / (1034 * 1.05), canvas.height / (1551 * 1.015));
    const ox = (canvas.width - 1034 * scale) / 2, oy = canvas.height - 1551 * scale;
    let column = 0;
    for (const emotion of ['neutral', 'happy', 'angry', 'sad', 'surprised', 'relaxed']) {
      bridge.setEmotion(emotion);
      av.setParameters(defaults);
      av.advance(0.8, 60);
      result[emotion] = { expression: av.getExpression().name, parameters: av.getParameters() };
      const x = column++ * 234;
      ctx.drawImage(canvas, x, 0, 234, 350);
      ctx.fillStyle = '#e4e9df'; ctx.font = '16px sans-serif'; ctx.fillText(emotion, x + 12, 375);
      ctx.drawImage(canvas, ox + 325 * scale, oy + 155 * scale, 435 * scale, 205 * scale, x + 8, 405, 218, 103);
      if (emotion === 'happy') {
        bridge.startSpeaking(); bridge.stopSpeaking(); av.advance(2, 60);
        result.afterSpeech = av.getExpression().name;
      }
    }
    // Restore the normal animation path for the following lifecycle checks.
    bridge.setEmotion('neutral'); av.setParameters({}); av.setAutoIdle(true); el.paused = false;
    result.comparison = sheet.toDataURL('image/png');
    return result;
  }, { defaults });
  await writeFile(path.join(output, 'emotions.png'), Buffer.from(expressions.comparison.split(',')[1], 'base64'));
  delete expressions.comparison;
  assert(expressions.happy.parameters.eyeLOpen < 0.1);
  assert(expressions.happy.parameters.eyeSmile > 0.9);
  assert(expressions.surprised.parameters.mouthOpen > 0.5);
  assert(expressions.angry.parameters.browAngle > 0.7);
  assert(expressions.sad.parameters.browAngle < -0.7);
  assert.equal(expressions.afterSpeech, 'smile');
  result.expressions = expressions;
  result.checks.push('picker holds distinct expressions; happy uses smiling eyes, surprise opens mouth, speech end preserves selection');
  await page.selectOption('#emotion', 'happy');
  await page.click('#say');
  await page.waitForFunction(() => window.mavatarDemo.state.speaking);
  await page.waitForFunction(() => document.getElementById('dialog').textContent.length >= 4);
  await page.click('#cancel');
  assert.equal(await page.evaluate(() => window.mavatarDemo.state.speaking), false);
  await page.click('#kana');
  await page.waitForFunction(() => window.mavatarDemo.element.avatar.getLipSyncState().active);
  await page.click('#cancel');
  assert.equal(await page.evaluate(() => window.mavatarDemo.element.avatar.getLipSyncState().active), false);
  result.checks.push('Chinese mock stream / kana lip sync start and cancel');
  await page.click('#sleep');
  await page.waitForFunction(() => window.mavatarDemo.state.sleeping);
  await page.screenshot({ path: path.join(output, 'sleep.png'), fullPage: true });
  await page.click('#sleep');
  assert.equal(await page.evaluate(() => window.mavatarDemo.element.avatar.getExpression().name), 'smile');
  await page.click('#visibility');
  assert.equal(await page.locator('#avatar-host').isVisible(), false);
  await page.click('#visibility');
  assert.equal(await page.locator('#avatar-host').isVisible(), true);
  result.checks.push('sleep / wake / hide / show');
  const rapid = await page.evaluate(async () => {
    const b = window.mavatarDemo;
    return Promise.all([b.load('/avatar.mavatar?generation=old'), b.load('/avatar.mavatar?generation=new')]);
  });
  assert.deepEqual(rapid, [false, true]);
  assert.equal(await page.locator('mesh-avatar').count(), 1);
  result.checks.push('overlapping loads settle [false,true], one current avatar');
  await page.click('#fallback');
  await page.waitForFunction(() => window.mavatarDemo.state.status === 'error');
  assert.equal(await page.locator('#poster').isVisible(), true);
  assert.equal(await page.locator('mesh-avatar').count(), 0);
  await page.click('#reload');
  await page.waitForFunction(() => window.mavatarDemo.state.status === 'ready');
  result.checks.push('404 fallback and recovery');
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: path.join(output, 'mobile.png'), fullPage: true });
  assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
  result.checks.push('390px viewport without horizontal overflow');
  const destroyed = await page.evaluate(async () => {
    const b = window.mavatarDemo;
    const pending = b.load('/avatar.mavatar'); b.destroy(); return pending;
  });
  assert.equal(destroyed, false);
  assert.equal(await page.locator('mesh-avatar').count(), 0);
  result.checks.push('destroy cancels in-flight load');
  await context.close();

  const noGL = await browser.newContext();
  await noGL.addInitScript(() => {
    const getContext = HTMLCanvasElement.prototype.getContext;
    HTMLCanvasElement.prototype.getContext = function(type, ...args) {
      if (type === 'webgl2') return null;
      return getContext.call(this, type, ...args);
    };
  });
  const fallbackPage = await noGL.newPage();
  await fallbackPage.goto(origin);
  await fallbackPage.waitForFunction(() => window.mavatarDemo?.state.status === 'error');
  assert.equal(await fallbackPage.locator('#poster').isVisible(), true);
  result.checks.push('no WebGL2 shows complete poster');
  await noGL.close();
  const reduced = await browser.newContext({ reducedMotion: 'reduce' });
  const reducedPage = await reduced.newPage();
  await reducedPage.goto(origin);
  await reducedPage.waitForFunction(() => window.mavatarDemo?.state.status === 'ready');
  assert(await reducedPage.evaluate(() => matchMedia('(prefers-reduced-motion: reduce)').matches));
  result.checks.push('prefers-reduced-motion loads successfully (upstream reduces hair sway)');
  await reduced.close();
  assert.deepEqual(external, [], 'avatar contacted an external host');
  assert.deepEqual(errors, [], 'browser script errors');
  result.checks.push('no page errors or external requests');
  result.passed = true;
} finally {
  await browser?.close();
  await new Promise(resolve => server.close(resolve));
  await writeFile(path.join(output, 'results.json'), JSON.stringify(result, null, 2));
}
console.log(JSON.stringify(result, null, 2));
