// Servidor solo para probar en local (no se sube a Hostinger).
// Sirve los archivos estáticos y simula api/solicitud.php.
import http from 'node:http';
import { readFile } from 'node:fs/promises';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const port = Number(process.env.PORT || 5173);
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.svg': 'image/svg+xml', '.png': 'image/png' };
let n = 141;

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://x');
  if (url.pathname === '/api/solicitud.php' && req.method === 'POST') {
    req.resume();
    req.on('end', () => {
      setTimeout(() => {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ ok: true, ref: 'SOL-' + String(++n).padStart(4, '0') }));
      }, 900);
    });
    return;
  }
  let p = normalize(decodeURIComponent(url.pathname)).replace(/^([\\/])+/, '');
  if (!p || p.endsWith('/') || p.endsWith('\\')) p += 'index.html';
  try {
    const data = await readFile(join(root, p));
    res.writeHead(200, { 'Content-Type': types[extname(p)] || 'application/octet-stream' });
    res.end(data);
  } catch {
    res.writeHead(404); res.end('404');
  }
}).listen(port, () => console.log('Recambia en http://localhost:' + port));
