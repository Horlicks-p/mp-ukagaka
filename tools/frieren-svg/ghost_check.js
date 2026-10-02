// Afterimage check: draw each book/wake frame the way the runtime does
// (mpuFrierenManager.drawFrierenFrame on the live canvas, right after the
// previous frame) and compare with the same frame drawn on a fresh canvas.
// usage: node ghost_check.js [url]
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const url = process.argv[2] || 'http://127.0.0.1/wordpress/';
(async () => {
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  await pg.goto(url, { waitUntil: 'networkidle' });
  await pg.waitForFunction(() => {
    const m = window.mpuFrierenManager;
    return m && m.isFrierenSequenceReady && m.isFrierenSequenceReady('book_flip') && m.isFrierenSequenceReady('wake');
  }, null, { timeout: 30000 });
  const res = await pg.evaluate(() => {
    const m = window.mpuFrierenManager;
    const c = window.mpuCanvasManager.canvas;
    m.applyFrierenBodyLayout(c);
    const ctx = window.mpuCanvasManager.ctx;
    const fresh = document.createElement('canvas');
    fresh.width = c.width; fresh.height = c.height;
    const fctx = fresh.getContext('2d');
    const out = {};
    for (const seq of ['book_flip', 'wake']) {
      out[seq] = m.frierenSequences[seq].map(f => {
        m.drawFrierenFrame(f.img);
        const a = ctx.getImageData(0, 0, c.width, c.height).data;
        fctx.clearRect(0, 0, fresh.width, fresh.height);
        fctx.drawImage(f.img, 0, 0, fresh.width, fresh.height);
        const z = fctx.getImageData(0, 0, fresh.width, fresh.height).data;
        let diff = 0;
        for (let i = 0; i < a.length; i += 4) {
          if (Math.abs(a[i] - z[i]) + Math.abs(a[i + 1] - z[i + 1]) + Math.abs(a[i + 2] - z[i + 2]) + Math.abs(a[i + 3] - z[i + 3]) > 24) diff++;
        }
        return diff;
      });
    }
    return out;
  });
  console.log(JSON.stringify(res));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
