// Cursor map: real mouse moves on a grid around the shell; records the
// cursor the page shows (computed cursor of the element under the pointer).
// usage: node cursor_map.js [url] [step=4]
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const { PNG } = require(path.join(__dirname, '..', 'node', 'node_modules', 'pngjs'));
const url = process.argv[2] || 'http://127.0.0.1/wordpress/';
const step = Number(process.argv[3] || 4);
const COLORS = { pointer: [255, 0, 255, 255], grab: [255, 80, 80, 255], help: [0, 200, 0, 255], 'not-allowed': [255, 255, 0, 255] };
(async () => {
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  await pg.goto(url, { waitUntil: 'load', timeout: 60000 });
  await pg.waitForFunction(() => {
    const m = window.mpuFrierenManager;
    return m && m.frierenIdleImgElement && m.frierenIdleImgElement.style.display === 'block' && m.decorationHitCanvases.size >= 6;
  }, null, { timeout: 30000 });
  await pg.evaluate(() => window.mpuFrierenManager.stopFrierenAnimation());
  const box = await (await pg.$('#ukagaka_img')).boundingBox();
  const x0 = Math.floor(box.x - 100), y0 = Math.floor(box.y - 110);
  const W = Math.floor((box.width + 200) / step), H = Math.floor((box.height + 170) / step);
  await pg.screenshot({ path: path.join(__dirname, 'preview', 'cursor_map_shot.png'), clip: { x: x0, y: y0, width: W * step, height: H * step } });
  const png = new PNG({ width: W, height: H });
  const counts = {};
  for (let j = 0; j < H; j++) {
    for (let i = 0; i < W; i++) {
      const x = x0 + i * step + step / 2, y = y0 + j * step + step / 2;
      await pg.mouse.move(x, y);
      const cur = await pg.evaluate(([x, y]) => {
        const el = document.elementFromPoint(x, y);
        return el ? getComputedStyle(el).cursor : 'none';
      }, [x, y]);
      counts[cur] = (counts[cur] || 0) + 1;
      const c = COLORS[cur] || [0, 0, 0, 0];
      const k = (j * W + i) * 4;
      png.data[k] = c[0]; png.data[k + 1] = c[1]; png.data[k + 2] = c[2]; png.data[k + 3] = c[3];
    }
  }
  fs.writeFileSync(path.join(__dirname, 'preview', 'cursor_map_raw.png'), PNG.sync.write(png));
  console.log(JSON.stringify(counts));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
