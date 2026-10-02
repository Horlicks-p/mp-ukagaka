// Decorations at their real CSS size: PNG vs SVG on light and dark grounds.
const fs = require('fs'), os = require('os'), path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const dir = path.join(__dirname, '..', '..', 'ghost', 'Frieren', 'decorations');
const names = ['books', 'staff', 'suitcase', 'evil_horns', 'dark_dragon_horn', 'potion'];
const out = process.argv[2];
const cell = (n, ext) => `<img src="${pathToFileURL(path.join(dir, n + '.' + ext)).href}" style="display:block">`;
const row = bg => `<div style="display:flex;gap:14px;align-items:flex-end;background:${bg};padding:12px">` +
  names.map(n => `<div style="display:flex;gap:4px">${cell(n, 'png')}${cell(n, 'svg')}</div>`).join('') + '</div>';
(async () => {
  const html = path.join(os.tmpdir(), 'frieren-decor.html');
  fs.writeFileSync(html, `<body style="margin:0;display:inline-block">${row('#ffffff')}${row('#18181c')}</body>`);
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 1600, height: 600 }, deviceScaleFactor: Number(process.env.DPR || 1) });
  await pg.goto(pathToFileURL(html).href);
  await pg.waitForLoadState('networkidle');
  await (await pg.$('body')).screenshot({ path: out });
  await b.close();
})();
