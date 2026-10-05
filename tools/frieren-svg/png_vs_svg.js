// Old PNG shell (as production shows it: 134x249 at natural size) next to the
// SVG shell with the shipped layout (or SX times its width), in the same
// 134x249 box, rendered with a transparent ground for measuring.
// work/old-frieren0.png: git show "ac81047^:ghost/Frieren/shell/Frieren/frieren[0].png"
// usage: node png_vs_svg.js [SX=1]
const fs = require('fs'), os = require('os'), path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const g = path.join(__dirname, '..', '..', 'ghost', 'Frieren');
const layout = JSON.parse(fs.readFileSync(path.join(g, 'shell', 'Frieren', 'assets.json'), 'utf8')).layout;
const sx = Number(process.argv[2] || 1);
const [fl, top, fw, fh] = layout.frame;
const W = fw * sx, left = layout.box[0] / 2 - (layout.box[0] / 2 - fl) * sx;
const u = p => pathToFileURL(p).href;
(async () => {
  const html = path.join(os.tmpdir(), 'png-vs-svg.html');
  const cell = inner => `<div style="position:relative;width:134px;height:249px;margin:30px 40px">${inner}</div>`;
  fs.writeFileSync(html, `<body style="margin:0;display:flex;background:transparent">
    ${cell(`<img id=a src="${u(path.join(__dirname, 'work', 'old-frieren0.png'))}" style="position:absolute;left:0;top:0;width:134px;height:249px">`)}
    ${cell(`<img id=b src="${u(path.join(g, 'shell', 'Frieren', 'idle', 'frieren-idle-00.svg'))}" style="position:absolute;left:${left}px;top:${top}px;width:${W}px;height:${fh}px">`)}
  </body>`);
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 500, height: 330 }, deviceScaleFactor: 4 });
  await pg.goto(u(html));
  await pg.waitForLoadState('networkidle');
  for (const id of ['a', 'b']) {
    const box = await pg.evaluate(id => { const r = document.getElementById(id).parentNode.getBoundingClientRect(); return { x: r.left, y: r.top, width: r.width, height: r.height }; }, id);
    await pg.screenshot({ path: path.join(__dirname, 'preview', `cmp_${id}.png`), clip: { x: box.x - 30, y: box.y - 25, width: box.width + 60, height: box.height + 30 }, omitBackground: true });
  }
  await b.close();
})();
