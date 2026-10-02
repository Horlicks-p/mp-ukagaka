// Click map: real mouse clicks on a grid around the shell; records whether
// each click became a touch zone (and which) or a decoration click, with the
// dialog handlers stubbed. Writes preview/click_map.png next to a screenshot.
// usage: node click_map.js [url] [step=4]
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const { PNG } = require(path.join(__dirname, '..', 'node', 'node_modules', 'pngjs'));
const url = process.argv[2] || 'http://127.0.0.1/wordpress/';
const step = Number(process.argv[3] || 4);
const COLORS = {
  none: [0, 0, 0, 0], head: [255, 80, 80, 255], face: [255, 170, 0, 255], chest: [255, 255, 0, 255],
  book: [0, 200, 0, 255], legs: [0, 160, 255, 255], body: [150, 150, 150, 255],
  books: [120, 0, 200, 255], staff: [255, 0, 255, 255], suitcase: [0, 90, 60, 255], evil_horns: [140, 90, 40, 255],
  dark_dragon_horn: [40, 40, 120, 255], potion: [0, 255, 200, 255],
};
(async () => {
  const b = await chromium.launch(process.env.EDGE ? { channel: 'msedge', headless: false } : {});
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  await pg.goto(url, { waitUntil: 'load', timeout: 60000 });
  await pg.waitForFunction(() => {
    const m = window.mpuFrierenManager;
    return m && m.frierenIdleImgElement && m.frierenIdleImgElement.style.display === 'block' && m.decorationHitCanvases.size >= 6;
  }, null, { timeout: 30000 });
  await pg.evaluate(() => {
    const m = window.mpuFrierenManager;
    m.stopFrierenAnimation();
    window.__hits = [];
    m.handleTouchZone = z => window.__hits.push('zone:' + z);
    m.handleDecorationClick = t => window.__hits.push('deco:' + t);
  });
  const box = await (await pg.$('#ukagaka_img')).boundingBox();
  const x0 = Math.floor(box.x - 100), y0 = Math.floor(box.y - 110), x1 = Math.ceil(box.x + box.width + 100), y1 = Math.ceil(box.y + box.height + 60);
  const W = Math.floor((x1 - x0) / step), H = Math.floor((y1 - y0) / step);
  const png = new PNG({ width: W, height: H });
  const counts = {};
  for (let j = 0; j < H; j++) {
    for (let i = 0; i < W; i++) {
      await pg.evaluate(() => { window.__hits = []; });
      await pg.mouse.click(x0 + i * step + step / 2, y0 + j * step + step / 2);
      const hits = await pg.evaluate(() => window.__hits);
      const key = hits.length ? hits.map(h => h.split(':')[1]).join('+') : 'none';
      counts[key] = (counts[key] || 0) + 1;
      const c = hits.length > 1 ? [255, 255, 255, 255] : COLORS[hits.length ? hits[0].split(':')[1] : 'none'] || [255, 255, 255, 255];
      const k = (j * W + i) * 4;
      png.data[k] = c[0]; png.data[k + 1] = c[1]; png.data[k + 2] = c[2]; png.data[k + 3] = c[3];
    }
  }
  fs.writeFileSync(path.join(__dirname, 'preview', 'click_map_raw.png'), PNG.sync.write(png));
  await pg.screenshot({ path: path.join(__dirname, 'preview', 'click_map_shot.png'), clip: { x: x0, y: y0, width: W * step, height: H * step } });
  console.log(JSON.stringify(counts));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
