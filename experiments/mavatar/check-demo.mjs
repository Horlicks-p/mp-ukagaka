import assert from 'node:assert/strict';
import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { createDemoServer } from './server.mjs';
import { relaxedPose } from './mesh-bridge.mjs';
import { createRig, PARAMS } from './studio/packages/runtime/src/engine/rig.js';

const root = path.dirname(fileURLToPath(import.meta.url));
const requireStudio = createRequire(new URL('./studio/package.json', import.meta.url));
const { chromium } = requireStudio('@playwright/test');
const { unzipSync } = requireStudio('fflate');
const projectName = process.argv[2] || 'frieren-chest';
assert(/^[A-Za-z0-9][A-Za-z0-9_-]*$/.test(projectName), 'invalid local project name');
const project = path.join(root, 'studio/projects', projectName);
let study = {};
try { study = JSON.parse(await readFile(path.join(project, 'study.json'), 'utf8')); }
catch (error) { if (error.code !== 'ENOENT') throw error; }
let facePresets = {};
try { facePresets = JSON.parse(await readFile(path.join(project, 'expressions/bridge.json'), 'utf8')); }
catch (error) { if (error.code !== 'ENOENT') throw error; }
const output = path.join(project, 'review-demo');
await mkdir(output, { recursive: true });
const rig = JSON.parse(await readFile(path.join(project, 'rig.json'), 'utf8'));
const defaults = Object.fromEntries(PARAMS.map(p => [p.id, p.def]));
const roi = study.roi || { leftHand: [170, 785, 325, 850], rightHand: [750, 780, 880, 850],
  chest: [15, 750, 1018, 1370], legs: [375, 850, 715, 1540] };
const poses = { breath: { breath: 1 }, left: { bodyAngleX: -10, bodyAngleZ: -8, angleX: -30, angleZ: -15, breath: 1 },
  right: { bodyAngleX: 10, bodyAngleZ: 8, angleX: 30, angleZ: 15, breath: 1 } };
const result = { project: projectName, upstream: 'fb823c05c9963d241fec42e772959be562c927eb', checks: [], numerical: {}, pixels: {} };
const archiveBytes = new Uint8Array(await readFile(path.join(project, 'frieren-runtime.mavatar')));
result.avatarBytes = archiveBytes.length;
result.avatarSha256 = createHash('sha256').update(archiveBytes).digest('hex');
const archive = unzipSync(archiveBytes);
assert(!Object.keys(archive).some(name => /(^|\/)source\.png$/.test(name)));
result.checks.push('archive omits source.png');
const spriteManifest = JSON.parse(Buffer.from(archive['built/sprites/sprites.json']).toString('utf8'));
for (const name of ['eyes_sad_0', 'eyes_sad_1', 'mouth_angry']) {
  assert(spriteManifest.layers[name] && archive[`built/sprites/${name}.png`], `${name} absent from exported archive`);
}
assert(!spriteManifest.layers.mouth_sad && !archive['built/sprites/mouth_sad.png'], 'sad face must retain the original closed mouth');
result.checks.push('exported archive retains sad eyes and angry mouth, omits the exaggerated sad mouth');
const overlayManifest = JSON.parse(Buffer.from(archive['built/overlays/overlays.json']).toString('utf8'));
for (const name of ['awkward-eyes', 'awkward-mouth']) {
  assert(overlayManifest.overlays.some(item => item.name === name && archive[`built/overlays/${item.file}`]), `${name} overlay missing`);
}
for (const face of Object.values(facePresets)) for (const name of [face.eyeOverlay, face.mouthOverlay].filter(Boolean)) {
  assert(overlayManifest.overlays.some(item => item.name === name && archive[`built/overlays/${item.file}`]), `${name} overlay missing`);
}
result.checks.push('native archive includes independent awkward eye and mouth overlays');
const samples = Array.from({ length: 860 }, (_, i) => relaxedPose(i / 100).eyeLOpen);
result.relaxedTiming = { openFraction: samples.filter(value => value >= 0.75).length / samples.length,
  halfFraction: samples.filter(value => value >= 0.3 && value < 0.75).length / samples.length,
  closedFraction: samples.filter(value => value < 0.3).length / samples.length };
assert(result.relaxedTiming.openFraction > 0.8 && result.relaxedTiming.halfFraction < 0.1);
assert(result.relaxedTiming.closedFraction > 0);
result.checks.push('relaxed timing spends >80% open and <10% half-open, with a brief closed rest');

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

const server = createDemoServer(projectName);
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
  result.checks.push(`350px rendered ${Object.keys(roi).join('/')} ROI: no pixels differ by >8/255`);
  const comparisonEmotions = ['neutral', 'happy', ...(facePresets.good_mood ? ['good_mood'] : []),
    'angry', 'sad', 'surprised', 'relaxed', 'awkward', ...(facePresets.smug ? ['smug'] : [])];
  const expressions = await page.evaluate(async ({ defaults, rig, faceCrop, comparisonEmotions }) => {
    const bridge = window.mavatarDemo, el = bridge.element, av = el.avatar;
    el.paused = true;
    av.setAutoIdle(false);
    // Hold unrelated pose parameters fixed for the comparison, while keeping
    // the actual overrides supplied by the bridge (including relaxed brows).
    const setParameters = av.setParameters.bind(av);
    av.setParameters = (values, ...args) => setParameters({ ...defaults, ...values }, ...args);
    const result = {};
    const canvas = el.shadowRoot.querySelector('canvas');
    const sheet = document.createElement('canvas'); sheet.width = comparisonEmotions.length * 234; sheet.height = 570;
    const ctx = sheet.getContext('2d'); ctx.fillStyle = '#192421'; ctx.fillRect(0, 0, sheet.width, sheet.height);
    const scale = Math.min(canvas.width / (rig.image.width * (1 + 2 * rig.view.padSide)), canvas.height / (rig.image.height * (1 + rig.view.padTop)));
    const ox = (canvas.width - rig.image.width * scale) / 2, oy = canvas.height - rig.image.height * scale;
    let column = 0;
    for (const emotion of comparisonEmotions) {
      av.setParameters(defaults);
      bridge.setEmotion(emotion);
      av.advance(0.8, 60);
      result[emotion] = { expression: av.getExpression().name, parameters: av.getParameters() };
      const x = column++ * 234;
      ctx.drawImage(canvas, x, 0, 234, 350);
      ctx.fillStyle = '#e4e9df'; ctx.font = '16px sans-serif'; ctx.fillText(emotion, x + 12, 375);
      ctx.drawImage(canvas, ox + faceCrop[0] * scale, oy + faceCrop[1] * scale, faceCrop[2] * scale, faceCrop[3] * scale, x + 8, 405, 218, 103);
      if (emotion === 'happy') {
        bridge.startSpeaking(); bridge.stopSpeaking(); av.advance(2, 60);
        result.afterSpeech = av.getExpression().name;
      }
    }
    // Restore the normal animation path for the following lifecycle checks.
    av.setParameters = setParameters;
    bridge.setEmotion('neutral'); av.setParameters({}); av.setAutoIdle(true); el.paused = false;
    result.comparison = sheet.toDataURL('image/png');
    return result;
  }, { defaults, rig, faceCrop: study.faceCrop || [325, 155, 435, 205], comparisonEmotions });
  await writeFile(path.join(output, 'emotions.png'), Buffer.from(expressions.comparison.split(',')[1], 'base64'));
  delete expressions.comparison;
  assert(expressions.happy.parameters.eyeLOpen < 0.1);
  assert(expressions.happy.parameters.eyeSmile > 0.9);
  assert(expressions.surprised.parameters.mouthOpen > 0.5);
  if (facePresets.angry) {
    assert(expressions.angry.parameters.browAngle > 0 && expressions.angry.parameters.browAngle < 0.2);
    assert(expressions.angry.parameters.browY > -0.15);
    assert(expressions.angry.parameters.eyeLOpen > 0.9, 'restrained anger should keep the original open eyes');
    assert(expressions.good_mood.parameters.eyeLOpen > 0.9, 'good mood should be distinct from closed-eye happiness');
  } else {
    assert(expressions.angry.parameters.browAngle > 0.7);
    assert(expressions.angry.parameters.eyeLOpen > 0.71 && expressions.angry.parameters.eyeLOpen < 0.73);
  }
  assert(expressions.sad.parameters.browAngle < -0.7);
  assert(expressions.sad.parameters.mouthOpen < 0.01);
  assert(expressions.relaxed.parameters.eyeLOpen > 0.8);
  assert(Math.abs(expressions.relaxed.parameters.browAngle) < 0.01);
  assert(expressions.relaxed.parameters.browY > 0);
  assert(expressions.awkward.parameters.eyeLOpen < 0.1);
  assert.equal(expressions.afterSpeech, 'smile');
  result.expressions = expressions;
  result.checks.push('picker holds distinct expressions; happy uses smiling eyes, surprise opens mouth, speech end preserves selection');
  const relaxedCycle = await page.evaluate(() => {
    const bridge = window.mavatarDemo, el = bridge.element, av = el.avatar;
    el.paused = true; av.setAutoIdle(false); bridge.setEmotion('relaxed');
    av.advance(0.8, 60);
    const relaxed = av.getParameters();
    av.advance(4.1, 60);
    const closedRest = av.getParameters();
    av.advance(1, 60);
    const reopened = av.getParameters();
    bridge.sleep(true);
    const asleep = av.getParameters();
    bridge.sleep(false); el.paused = true; av.advance(0.8, 60);
    const awake = av.getParameters();
    bridge.setEmotion('neutral'); av.setAutoIdle(true); el.paused = false;
    return { relaxed, closedRest, reopened, asleep, awake };
  });
  assert(relaxedCycle.relaxed.eyeLOpen > 0.8);
  assert(relaxedCycle.closedRest.eyeLOpen < 0.1);
  assert(relaxedCycle.reopened.eyeLOpen > 0.8);
  assert(relaxedCycle.asleep.eyeLOpen < 0.1 && relaxedCycle.asleep.eyeROpen < 0.1);
  assert(Math.abs(relaxedCycle.awake.browAngle) < 0.01);
  result.checks.push('relaxed animation opens, slowly closes, reopens; sleep closes both eyes and wake restores relaxed face');
  const awkward = await page.evaluate(async () => {
    const b = window.mavatarDemo;
    b.setEmotion('awkward');
    const resting = b.element.overlays;
    b.startSpeaking(); const speaking = b.element.overlays;
    b.stopSpeaking(); const stopped = b.element.overlays;
    b.sleep(true); const sleeping = b.element.overlays;
    b.sleep(false); const awake = b.element.overlays;
    await b.load('/avatar.mavatar'); const reloaded = b.element.overlays;
    b.setEmotion('neutral'); const cleared = b.element.overlays;
    return { resting, speaking, stopped, sleeping, awake, reloaded, cleared };
  });
  for (const state of ['resting', 'stopped', 'awake', 'reloaded']) assert.deepEqual(awkward[state], ['awkward-eyes', 'awkward-mouth']);
  assert.deepEqual(awkward.speaking, ['awkward-eyes']);
  assert.deepEqual(awkward.sleeping, []); assert.deepEqual(awkward.cleared, []);
  result.checks.push('awkward overlays survive reload/wake, mouth yields to speech, and clear on emotion change');
  if (facePresets.smug) {
    const referenceStates = await page.evaluate(async () => {
      const b = window.mavatarDemo, results = {};
      for (const emotion of ['good_mood', 'smug']) {
        b.setEmotion(emotion);
        let el = b.element, av = el.avatar;
        el.paused = true; av.setAutoIdle(false); av.advance(0.2, 60);
        const record = results[emotion] = { resting: el.overlays };
        const parameters = av.getParameters();
        av.setParameters({ ...parameters, eyeLOpen: 0, eyeROpen: 0 }); av.advance(0);
        record.blinkClosed = el.overlays;
        av.setParameters({ ...parameters, eyeLOpen: 1, eyeROpen: 1 }); av.advance(0);
        record.blinkOpen = el.overlays;
        b.setEmotion(emotion);
        b.startSpeaking(); record.speaking = el.overlays;
        b.stopSpeaking(); record.stopped = el.overlays;
        b.speakKana('こんにちは。'); av.advance(0.1, 60); record.kana = el.overlays;
        b.stopSpeaking(); record.cancelled = el.overlays;
        b.speakKana('こんにちは。');
        for (let step = 0; step < 48 && av.getLipSyncState().active; step++) av.advance(0.25, 60);
        // Open-eye overlays intentionally yield to a native blink or an already
        // playing idle. Inspect restoration once that eye cycle has reopened.
        for (let step = 0; step < 40 && Math.min(av.getParameters().eyeLOpen, av.getParameters().eyeROpen) < 0.75; step++) av.advance(0.2, 60);
        record.kanaEnded = { overlays: el.overlays, speaking: b.state.speaking, active: av.getLipSyncState().active };
        b.sleep(true); record.sleeping = el.overlays;
        b.sleep(false); av.setAutoIdle(false); av.advance(0.5, 60);
        for (let step = 0; step < 40 && Math.min(av.getParameters().eyeLOpen, av.getParameters().eyeROpen) < 0.75; step++) av.advance(0.2, 60);
        record.awake = el.overlays;
        b.setVisible(false); record.hidden = { speaking: b.state.speaking, paused: el.paused };
        b.setVisible(true);
        await b.load('/avatar.mavatar'); el = b.element; av = el.avatar;
        el.paused = true; av.setAutoIdle(false); av.advance(0.2, 60); record.reloaded = el.overlays;
        b.setEmotion('neutral'); record.cleared = el.overlays;
        av.setParameters({}); av.setAutoIdle(true); el.paused = false;
      }
      return results;
    });
    result.referenceStates = referenceStates;
    for (const [emotion, states] of Object.entries(referenceStates)) {
      const mouth = emotion === 'smug' ? 'smug-mouth' : 'good-mood-mouth';
      const resting = emotion === 'smug' ? ['smug-eyes', mouth] : [mouth];
      for (const state of ['resting', 'blinkOpen', 'stopped', 'cancelled', 'awake', 'reloaded']) assert.deepEqual(states[state], resting, `${emotion}/${state}`);
      assert.deepEqual(states.blinkClosed, [mouth]);
      for (const state of ['speaking', 'kana']) assert(!states[state].includes(mouth), `${emotion}/${state} mouth must yield to lip sync`);
      assert.deepEqual(states.kanaEnded.overlays, resting);
      assert.equal(states.kanaEnded.speaking, false); assert.equal(states.kanaEnded.active, false);
      assert.deepEqual(states.sleeping, []); assert.deepEqual(states.cleared, []);
      assert.deepEqual(states.hidden, { speaking: false, paused: true });
    }
    result.checks.push('reference anger is restrained and good mood keeps open eyes');
    result.checks.push('smug eye drawing yields to closed blinks and restores on opening');
    result.checks.push('reference mouths yield to mock speech/kana, restore after natural kana end and cancellation');
    result.checks.push('good mood/smug survive sleep, visibility changes and reload without overlay leakage');
  }
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
