// Pixel hit maps of each decoration, PNG vs SVG, built the way
// frieren-decorations.js does it (naturalWidth canvas + drawImage + alpha
// above pixelHitThreshold). usage: node hittest.js [threshold=10]
const path = require('path');
const http = require('http');
const fs = require('fs');
const { chromium } = require(path.join(__dirname, '..', 'node', 'node_modules', 'playwright'));
const dir = path.join(__dirname, '..', '..', 'ghost', 'Frieren', 'decorations');
const thr = Number(process.argv[2] || 10);
// same-origin HTTP (file:// would taint the canvas, unlike the site)
const types = { '.png': 'image/png', '.svg': 'image/svg+xml', '.html': 'text/html' };
const server = http.createServer((req, res) => {
  const f = path.join(dir, decodeURIComponent(req.url.split('?')[0]));
  if (!f.startsWith(dir) || !fs.existsSync(f)) { res.writeHead(404); return res.end(); }
  res.writeHead(200, { 'Content-Type': types[path.extname(f)] || 'application/octet-stream' });
  fs.createReadStream(f).pipe(res);
});
(async () => {
  await new Promise(r => server.listen(0, '127.0.0.1', r));
  const b = await chromium.launch();
  const pg = await b.newPage();
  await pg.goto('http://127.0.0.1:' + server.address().port + '/books.png');
  for (const n of ['books', 'staff', 'suitcase', 'evil_horns', 'dark_dragon_horn', 'potion']) {
    const r = await pg.evaluate(async ([png, svg, thr]) => {
      const map = async src => {
        const img = new Image();
        img.src = src;
        await img.decode();
        const c = document.createElement('canvas');
        c.width = img.naturalWidth;
        c.height = img.naturalHeight;
        const ctx = c.getContext('2d', { willReadFrequently: true });
        ctx.drawImage(img, 0, 0);
        const d = ctx.getImageData(0, 0, c.width, c.height).data;
        const hit = [];
        for (let i = 3; i < d.length; i += 4) hit.push(d[i] > thr ? 1 : 0);
        return { w: c.width, h: c.height, hit };
      };
      const a = await map(png), s = await map(svg);
      let pngOnly = 0, svgOnly = 0, ha = 0, hs = 0;
      for (let i = 0; i < a.hit.length; i++) {
        ha += a.hit[i]; hs += s.hit[i];
        if (a.hit[i] && !s.hit[i]) pngOnly++;
        if (!a.hit[i] && s.hit[i]) svgOnly++;
      }
      return { png: [a.w, a.h, ha], svg: [s.w, s.h, hs], pngOnly, svgOnly };
    }, ['./' + n + '.png', './' + n + '.svg', thr]);
    console.log(n.padEnd(17), JSON.stringify(r));
  }
  await b.close();
  server.close();
})();
