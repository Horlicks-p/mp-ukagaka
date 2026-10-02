// Record what the page actually shows during the book flip and wake-up
// (CDP screencast frames of the shell area). usage: node record_flip.js [url]
const fs = require('fs');
const path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const url = process.argv[2] || 'http://127.0.0.1/wordpress/';
const out = path.join(__dirname, 'preview', 'rec');
fs.rmSync(out, { recursive: true, force: true });
fs.mkdirSync(out, { recursive: true });
(async () => {
  const b = await chromium.launch(process.env.EDGE ? { channel: 'msedge', headless: false } : {});
  const pg = await b.newPage({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 });
  await pg.goto(url, { waitUntil: 'load', timeout: 60000 });
  await pg.waitForFunction(() => {
    const m = window.mpuFrierenManager;
    return m && m.isFrierenSequenceReady && m.isFrierenSequenceReady('book_flip') && m.isFrierenSequenceReady('wake');
  }, null, { timeout: 30000 });
  const bb = await (await pg.$('#ukagaka_img')).boundingBox();
  const cdp = await pg.context().newCDPSession(pg);
  const frames = [];
  cdp.on('Page.screencastFrame', async e => {
    frames.push({ t: e.metadata.timestamp, data: e.data });
    await cdp.send('Page.screencastFrameAck', { sessionId: e.sessionId });
  });
  await cdp.send('Page.startScreencast', { format: 'png', everyNthFrame: 1 });
  await pg.waitForTimeout(300);
  await pg.evaluate(() => { window.__flipT = performance.now(); window.mpuFrierenManager.playFrierenBookFlipAnimation(); });
  await pg.waitForTimeout(2300);
  await pg.evaluate(() => new Promise(r => window.mpuFrierenManager.playWakeUpAnimation(r)));
  await pg.waitForTimeout(400);
  await cdp.send('Page.stopScreencast');
  const { PNG } = require(path.join(__dirname, '..', 'node', 'node_modules', 'pngjs'));
  frames.forEach((f, i) => fs.writeFileSync(path.join(out, String(i).padStart(3, '0') + '.png'), Buffer.from(f.data, 'base64')));
  fs.writeFileSync(path.join(out, 'box.json'), JSON.stringify({ bb, n: frames.length, t: frames.map(f => f.t) }));
  console.log('frames', frames.length, 'box', JSON.stringify(bb));
  await b.close();
})().catch(e => { console.error(e); process.exit(1); });
