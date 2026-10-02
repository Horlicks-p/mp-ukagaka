// Render runtime SVGs one by one in Chromium at display height (default 249px)
// so an animated preview can be assembled from what the browser really draws.
// usage: node render_seq.js outdir rel/a.svg rel/b.svg ...   (env H, DPR, BG)
const fs = require('fs'), os = require('os'), path = require('path');
const { pathToFileURL } = require('url');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const shell = path.join(__dirname, '..', '..', 'ghost', 'Frieren', 'shell', 'Frieren');
const [outdir, ...files] = process.argv.slice(2);
const H = Number(process.env.H || 249), DPR = Number(process.env.DPR || 1), BG = process.env.BG || '#ffffff';
(async () => {
  fs.mkdirSync(outdir, { recursive: true });
  const b = await chromium.launch();
  const pg = await b.newPage({ viewport: { width: 400, height: H + 20 }, deviceScaleFactor: DPR });
  for (const [i, f] of files.entries()) {
    const html = path.join(os.tmpdir(), 'frieren-seq.html');
    fs.writeFileSync(html, `<body style="margin:0;background:${BG}"><img id=i src="${pathToFileURL(path.join(shell, f)).href}" style="display:block;height:${H}px;width:auto"></body>`);
    await pg.goto(pathToFileURL(html).href);
    await pg.waitForLoadState('networkidle');
    await (await pg.$('#i')).screenshot({ path: path.join(outdir, String(i).padStart(2, '0') + '.png') });
  }
  await b.close();
})();
