const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: false });
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  await pg.goto('http://127.0.0.1/wordpress/', { waitUntil: 'load', timeout: 60000 });
  await pg.waitForFunction(() => window.mpuFrierenManager && window.mpuFrierenManager.isFrierenSequenceReady && window.mpuFrierenManager.isFrierenSequenceReady('book_flip'), null, { timeout: 30000 });
  await pg.evaluate(() => window.mpuFrierenManager.playFrierenBookFlipAnimation());
  await pg.waitForTimeout(700);
  const st = await pg.evaluate(() => {
    const c = window.mpuCanvasManager.canvas, img = window.mpuFrierenManager.frierenIdleImgElement;
    const cs = getComputedStyle(c), is = getComputedStyle(img); window.__td = [cs.transitionDuration, cs.transitionProperty, cs.imageRendering, cs.willChange, cs.backfaceVisibility];
    const box = document.getElementById('ukagaka_img');
    const all = Array.from(box.querySelectorAll('canvas,img')).filter(e => getComputedStyle(e).display !== 'none' && !e.classList.contains('frieren-decoration')).map(e => e.tagName + '#' + e.id + ' op=' + getComputedStyle(e).opacity + ' filter=' + getComputedStyle(e).filter + ' trans=' + getComputedStyle(e).transition);
    return { canvas: [cs.display, cs.opacity, cs.filter, cs.mixBlendMode, cs.transition, c.width, c.height], img: [is.display, img.style.display], visible: all,
      td: window.__td, ctxAlpha: window.mpuCanvasManager.ctx.globalAlpha, op: window.mpuCanvasManager.ctx.globalCompositeOperation, scripts: Array.from(document.scripts).map(s => s.src).filter(s => /frieren|ukagaka-bundle/.test(s)) };
  });
  console.log(JSON.stringify(st, null, 1));
  const el = await pg.$('#cur_ukagaka');
  await el.screenshot({ path: path.join(__dirname, 'preview', 'edge_canvas_only.png') });
  await b.close();
})();
