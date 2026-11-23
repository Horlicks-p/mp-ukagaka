// Local-only, fixed-route server. Never exposes the project directory or source PNG.
import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.dirname(fileURLToPath(import.meta.url));
const project = path.join(root, 'studio/projects/frieren-chest');
const routes = new Map([
  ['/', ['demo.html', 'text/html; charset=utf-8']],
  ['/demo.mjs', ['demo.mjs', 'text/javascript; charset=utf-8']],
  ['/mesh-bridge.mjs', ['mesh-bridge.mjs', 'text/javascript; charset=utf-8']],
  ['/runtime.js', ['studio/packages/element/dist/standalone.js', 'text/javascript; charset=utf-8']],
  ['/avatar.mavatar', [path.join(project, 'frieren-runtime.mavatar'), 'application/octet-stream']],
  ['/poster.png', [path.join(project, 'poster.png'), 'image/png']],
]);

export function createDemoServer() {
  return http.createServer(async (req, res) => {
    const host = req.headers.host || '';
    if (!/^(127\.0\.0\.1|localhost)(:\d+)?$/.test(host)) {
      res.writeHead(403).end('Local access only');
      return;
    }
    const pathname = new URL(req.url, 'http://127.0.0.1').pathname;
    if (pathname === '/favicon.ico') { res.writeHead(204).end(); return; }
    const route = routes.get(pathname);
    if (!route || !['GET', 'HEAD'].includes(req.method)) { res.writeHead(404).end('Not found'); return; }
    try {
      const data = await readFile(path.resolve(root, route[0]));
      res.writeHead(200, { 'Content-Type': route[1], 'Cache-Control': 'no-store',
        'X-Content-Type-Options': 'nosniff',
        'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' blob: data:; connect-src 'self' blob:; object-src 'none'; base-uri 'none'" });
      res.end(req.method === 'HEAD' ? undefined : data);
    } catch (error) {
      res.writeHead(error.code === 'ENOENT' ? 404 : 500).end('Local asset unavailable');
    }
  });
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const port = Number(process.argv[2] || 9488);
  const server = createDemoServer();
  server.on('error', error => { console.error(error.message); process.exitCode = 1; });
  server.listen(port, '127.0.0.1', () => console.log(`Frieren mesh preview: http://127.0.0.1:${server.address().port}/`));
}
