// Live check of the SVG Frieren shell on a running WordPress site.
// usage: node live_check.js [url] [outdir]
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const url = process.argv[2] || 'http://127.0.0.1/wordpress/';
const out = process.argv[3] || path.join(__dirname, 'preview', 'live');
fs.mkdirSync(out, { recursive: true });

(async () => {
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  const errors = [];
  const failed = [];
  pg.on('console', m => { if (m.type() === 'error' || m.type() === 'warning') errors.push(m.type() + ': ' + m.text()); });
  pg.on('pageerror', e => errors.push('pageerror: ' + e.message));
  pg.on('requestfailed', r => failed.push(r.url()));
  pg.on('response', r => { if (r.status() >= 400) failed.push(r.status() + ' ' + r.url()); });
  await pg.goto(url, { waitUntil: 'networkidle' });
  await pg.waitForFunction(() => {
    const m = window.mpuFrierenManager;
    return m && m.isFrierenMode && m.frierenIdleImgElement && m.frierenIdleImgElement.style.display === 'block';
  }, null, { timeout: 20000 });
  await pg.waitForTimeout(800);

  const state = await pg.evaluate(() => {
    const m = window.mpuFrierenManager;
    const box = document.getElementById('ukagaka_img');
    const img = m.frierenIdleImgElement;
    const r = el => { const q = el.getBoundingClientRect(); return [Math.round(q.left), Math.round(q.top), Math.round(q.width), Math.round(q.height)]; };
    const body = window.mpuGetCharacterRect(img);
    return {
      container: r(box),
      img: r(img),
      bodyRect: [Math.round(body.left), Math.round(body.top), Math.round(body.width), Math.round(body.height)],
      src: img.getAttribute('src'),
      states: m.frierenSequenceState,
      decorations: m.frierenDecorations.map(d => [d.getAttribute('src').split('/').pop(), r(d), d.naturalWidth, d.naturalHeight]),
      hitCanvases: Array.from(m.decorationHitCanvases.keys()),
      pngRequests: performance.getEntriesByType('resource').map(e => e.name).filter(n => /shell\/Frieren|decorations/.test(n) && /\.png/.test(n)),
    };
  });
  console.log(JSON.stringify(state, null, 1));
  const box = await pg.$('#ukagaka_img');
  const shot = async name => { const bb = await box.boundingBox(); await pg.screenshot({ path: path.join(out, name + '.png'), clip: { x: bb.x - 140, y: bb.y - 120, width: bb.width + 280, height: bb.height + 160 } }); };
  await shot('idle');

  // idle loop: sample src over time
  const srcs = await pg.evaluate(async () => {
    const img = window.mpuFrierenManager.frierenIdleImgElement; const seen = [];
    for (let i = 0; i < 60; i++) { seen.push(img.getAttribute('src').split('/').pop()); await new Promise(r => setTimeout(r, 200)); }
    return Array.from(new Set(seen));
  });
  console.log('idle frames seen in 12s:', srcs.join(' '));

  // book flip
  await pg.evaluate(() => window.mpuFrierenManager.playFrierenBookFlipAnimation());
  await pg.waitForTimeout(700);
  const flip = await pg.evaluate(() => {
    const c = window.mpuCanvasManager.canvas; const q = c.getBoundingClientRect();
    return { canvasShown: c.style.display, backing: [c.width, c.height], css: [Math.round(q.width), Math.round(q.height)], timer: !!window.mpuFrierenManager.frierenAnimationTimer };
  });
  console.log('during flip:', JSON.stringify(flip));
  await shot('flip');
  await pg.waitForTimeout(2500);
  const after = await pg.evaluate(() => ({ imgShown: window.mpuFrierenManager.frierenIdleImgElement.style.display, canvas: window.mpuCanvasManager.canvas.style.display, speaking: window.mpuFrierenManager.frierenIsSpeaking }));
  console.log('after flip:', JSON.stringify(after));

  // wake
  const woke = await pg.evaluate(() => new Promise(res => { const t0 = performance.now(); window.mpuFrierenManager.playWakeUpAnimation(() => res(Math.round(performance.now() - t0))); }));
  console.log('wake callback after ms:', woke);
  await pg.evaluate(() => window.mpuFrierenManager.showFrierenIdle());

  // touch zone at the character's head and legs
  const zones = await pg.evaluate(() => {
    const m = window.mpuFrierenManager; const img = m.frierenIdleImgElement; const b = window.mpuGetCharacterRect(img);
    const at = f => m.detectTouchZone({ clientY: b.top + b.height * f }, img);
    return { head: at(0.05), face: at(0.2), book: at(0.5), legs: at(0.8) };
  });
  console.log('touch zones:', JSON.stringify(zones));
  console.log('failed requests:', JSON.stringify(failed));
  console.log('console errors/warnings:', JSON.stringify(errors, null, 1));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
