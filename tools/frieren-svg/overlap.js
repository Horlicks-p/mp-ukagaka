// How much each decoration overlaps the character (opaque pixels), in the
// live layout (134x249 box, assets.json layout, decorations.json), and the
// smallest outward shift (px, along its left/right anchor) that clears it.
// usage: node overlap.js   (BODY=png: the old PNG body from work/old-frieren0.png)
const fs = require('fs'), path = require('path');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const g = path.join(__dirname, '..', '..', 'ghost', 'Frieren');
const cfg = JSON.parse(fs.readFileSync(path.join(g, 'decorations.json'), 'utf8'));
const layout = JSON.parse(fs.readFileSync(path.join(g, 'shell', 'Frieren', 'assets.json'), 'utf8')).layout;
// served by the local Apache: same-origin, so the canvas can be read
const ROOT = path.join(__dirname, '..', '..');
const BASE = process.env.BASE || 'http://127.0.0.1/wordpress/wp-content/plugins/mp-ukagaka/';
const u = p => BASE + path.relative(ROOT, p).split(path.sep).map(encodeURIComponent).join('/');
const [fl, ft, fw, fh] = layout.frame;
(async () => {
  const html = path.join(__dirname, 'preview', 'fr-overlap.html');
  const decos = cfg.items.map(it => {
    const p = it.position;
    return `<img data-type="${it.type}" src="${u(path.join(g, 'decorations', it.image))}" style="position:absolute;top:${p.top};left:${p.left};right:${p.right};width:${it.size.width};transform:${it.transform || ''}">`;
  }).join('');
  fs.writeFileSync(html, `<body style="margin:0;background:transparent"><div id=box style="position:relative;width:134px;height:249px;margin:200px">
    ${process.env.BODY === 'png'
      ? `<img id=body src="${u(path.join(__dirname, 'work', 'old-frieren0.png'))}" style="position:absolute;left:0;top:0;width:134px;height:249px">`
      : `<img id=body src="${u(path.join(g, 'shell', 'Frieren', 'idle', 'frieren-idle-00.svg'))}" style="position:absolute;left:${fl}px;top:${ft}px;width:${fw}px;height:${fh}px">`}${decos}</div></body>`);
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 600, height: 700 }, deviceScaleFactor: 1 });
  await pg.goto(u(html));
  await pg.waitForLoadState('networkidle');
  const res = await pg.evaluate(async (items) => {
    const mask = (img) => {
      const r = img.getBoundingClientRect();
      const c = document.createElement('canvas');
      c.width = 600; c.height = 700;
      const ctx = c.getContext('2d');
      ctx.drawImage(img, r.left, r.top, r.width, r.height);
      const d = ctx.getImageData(0, 0, 600, 700).data;
      const m = new Uint8Array(600 * 700);
      for (let i = 0; i < m.length; i++) m[i] = d[i * 4 + 3] >= 128 ? 1 : 0;
      return m;
    };
    const body = mask(document.getElementById('body'));
    const out = {};
    for (const it of items) {
      const img = document.querySelector(`[data-type="${it.type}"]`);
      const anchor = it.position.left !== 'auto' ? 'left' : 'right';
      const dir = anchor === 'left' ? -1 : 1;           // outward = away from the box
      const base = img.getBoundingClientRect();
      const dm = mask(img);
      const count = (dx) => {
        let n = 0;
        for (let y = 0; y < 700; y++) for (let x = 0; x < 600; x++) {
          if (!dm[y * 600 + x]) continue;
          const bx = x + dx;
          if (bx >= 0 && bx < 600 && body[y * 600 + bx]) n++;
        }
        return n;
      };
      const now = count(0);
      let clear = null;
      for (let s = 0; s <= 40; s++) { if (count(dir * s) === 0) { clear = s; break; } }
      out[it.type] = { anchor, offset: it.position[anchor], z: it.z_index, overlapPx: now, clearAfterPx: clear };
    }
    return out;
  }, cfg.items);
  console.log(JSON.stringify(res, null, 1));
  await b.close();
})();
