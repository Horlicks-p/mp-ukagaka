// Render runtime SVGs in Chromium at the planned display size (height 249px)
// on light and dark grounds, next to the original PNGs, for visual review.
// usage: node poc_render.js out.png "path|label" ...  (prefix png: for PNGs)
const fs = require('fs');
const os = require('os');
const path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const shell = path.join(__dirname, '..', '..', 'ghost', 'Frieren', 'shell', 'Frieren');
const out = process.argv[2];
const items = process.argv.slice(3).map(a => a.split('|'));
const height = Number(process.env.H || 249);
const row = (bg, fg) => `<div style="display:flex;gap:10px;background:${bg};padding:10px">` + items.map(([src, label]) => {
  const u = pathToFileURL(path.join(shell, src.replace(/^png:/, ''))).href;
  return `<figure style="margin:0;text-align:center;color:${fg};font:11px sans-serif"><img src="${u}" style="height:${height}px;width:auto"><figcaption>${label || src}</figcaption></figure>`;
}).join('') + '</div>';
(async () => {
  const html = path.join(os.tmpdir(), 'frieren-poc.html');
  fs.writeFileSync(html, `<body style="margin:0;display:inline-block">${row('#ffffff', '#333')}${row('#1e1f24', '#ddd')}</body>`);
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 1800, height: 700 }, deviceScaleFactor: Number(process.env.DPR || 1) });
  await pg.goto(pathToFileURL(html).href);
  await pg.waitForLoadState('networkidle');
  await (await pg.$('body')).screenshot({ path: out });
  await b.close();
})();
