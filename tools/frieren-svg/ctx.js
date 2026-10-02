// Frieren + decorations in the live layout. The container keeps the PNG
// shell's box (134x249, which decorations.json is written against); the SVG
// is scaled so the character matches the PNG character's height (master
// rows 29..318 -> PNG rows 1..248) and centred on it.
// usage: node ctx.js out.png [body.svg]
const fs = require('fs'), os = require('os'), path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const g = path.join(__dirname, '..', '..', 'ghost', 'Frieren');
const cfg = JSON.parse(fs.readFileSync(path.join(g, 'decorations.json'), 'utf8'));
const body = process.argv[3] || 'shell/Frieren/idle/frieren-idle-00.svg';
const u = p => pathToFileURL(p).href;
const s = 248 / 290, H = 328 * s, W = 208 * s;
const left = -30 * s + (134 - 147 * s) / 2, top = 1 - 29 * s;
const box = () => `<div style="position:relative;width:134px;height:249px;margin:60px 130px 40px;">` +
  `<img src="${u(path.join(g, body))}" style="position:absolute;left:${left}px;top:${top}px;width:${W}px;height:${H}px;z-index:50">` +
  cfg.items.map(it => {
    const p = it.position;
    const st = `position:absolute;top:${p.top};left:${p.left};right:${p.right};width:${it.size.width};transform:${it.transform || ''};z-index:${it.z_index}`;
    return `<img src="${u(path.join(g, 'decorations', it.image.replace('.png', '.svg')))}" style="${st}">`;
  }).join('') + '</div>';
(async () => {
  const h = path.join(os.tmpdir(), 'fr-ctx.html');
  fs.writeFileSync(h, `<body style="margin:0;display:flex"><div style="background:#faf8f5">${box()}</div><div style="background:#18181c">${box()}</div></body>`);
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 800, height: 360 }, deviceScaleFactor: 2 });
  await pg.goto(u(h));
  await pg.waitForLoadState('networkidle');
  await pg.screenshot({ path: process.argv[2] });
  await b.close();
})();
