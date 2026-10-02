const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: false });
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  await pg.goto('http://127.0.0.1/wordpress/', { waitUntil: 'load', timeout: 60000 });
  await pg.waitForFunction(() => window.mpuFrierenManager && window.mpuFrierenManager.isFrierenSequenceReady && window.mpuFrierenManager.isFrierenSequenceReady('book_flip'), null, { timeout: 30000 });
  await pg.evaluate(k => { window.__k = k; }, 3);
  await pg.evaluate(k => { window.__k = k; }, 2);
  await pg.evaluate(k => { window.__k = k; }, 1);
  const r = await pg.evaluate(async () => {
    const m = window.mpuFrierenManager; const c = window.mpuCanvasManager.canvas; const ctx = window.mpuCanvasManager.ctx;
    m.applyFrierenBodyLayout(c);
    const f = m.frierenSequences.book_flip[4]; const k = Number(window.__k || 0); if (k) { c.width = 208 * k; c.height = 328 * k; }
    m.drawFrierenFrame(f.img);
    const d = ctx.getImageData(0, 0, c.width, c.height).data;
    const hist = {}; let opaque = 0, semi = 0;
    for (let i = 3; i < d.length; i += 4) { if (d[i] === 255) opaque++; else if (d[i] > 0) semi++; }
    // same image in a fresh 2d canvas
    const t = document.createElement('canvas'); t.width = c.width; t.height = c.height; const tc = t.getContext('2d');
    tc.drawImage(f.img, 0, 0, t.width, t.height); const e = tc.getImageData(0, 0, t.width, t.height).data;
    let o2 = 0, s2 = 0; for (let i = 3; i < e.length; i += 4) { if (e[i] === 255) o2++; else if (e[i] > 0) s2++; }
    const ctxAttrs = ctx.getContextAttributes ? ctx.getContextAttributes() : null;
    return { live: { opaque, semi }, fresh: { opaque: o2, semi: s2 }, attrs: ctxAttrs, nat: [f.img.naturalWidth, f.img.naturalHeight], url: f.img.src.split('/').pop() };
  });
  console.log(JSON.stringify(r));
  await b.close();
})();
