'use strict';
const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');
const { URL } = require('url');

const PORT = Number(process.env.PORT || 8083);
const HOST = process.env.HOST || '0.0.0.0';
const ROOT = path.join(__dirname, 'public');
const VENDOR = path.join(ROOT, 'vendor', 'scratch-gui');
const CACHE = path.join(__dirname, '.asset-cache');
fs.mkdirSync(CACHE, { recursive: true });

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif': 'image/gif',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.wav': 'audio/wav',
  '.mp3': 'audio/mpeg',
  '.ogg': 'audio/ogg',
  '.woff': 'font/woff',
  '.woff2': 'font/woff2',
  '.hex': 'application/octet-stream',
  '.map': 'application/json; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8'
};

function send(res, code, body, headers) {
  res.writeHead(code, Object.assign({
    'X-Content-Type-Options': 'nosniff',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Permissions-Policy': 'camera=(), microphone=(), geolocation=()',
    'Cross-Origin-Opener-Policy': 'same-origin'
  }, headers || {}));
  res.end(body);
}

function notFound(res) { send(res, 404, 'Not found', { 'Content-Type': 'text/plain; charset=utf-8' }); }

function safeJoin(root, urlPath) {
  const p = path.normalize(path.join(root, urlPath));
  if (!p.startsWith(root)) return null;
  return p;
}

function serveFile(req, res, filePath, cacheControl) {
  fs.stat(filePath, (err, st) => {
    if (err || !st.isFile()) return notFound(res);
    const ext = path.extname(filePath).toLowerCase();
    const type = MIME[ext] || 'application/octet-stream';
    const headers = {
      'Content-Type': type,
      'Cache-Control': cacheControl || 'no-cache',
      'Last-Modified': st.mtime.toUTCString()
    };
    const accept = String(req.headers['accept-encoding'] || '');
    const gzPath = filePath + '.gz';
    if (accept.includes('gzip') && fs.existsSync(gzPath)) {
      const gzSt = fs.statSync(gzPath);
      headers['Content-Encoding'] = 'gzip';
      headers['Content-Length'] = gzSt.size;
      headers['Vary'] = 'Accept-Encoding';
      res.writeHead(200, headers);
      fs.createReadStream(gzPath).pipe(res);
      return;
    }
    headers['Content-Length'] = st.size;
    headers['Vary'] = 'Accept-Encoding';
    res.writeHead(200, headers);
    fs.createReadStream(filePath).pipe(res);
  });
}

function cacheKey(name) {
  return path.join(CACHE, name.replace(/[^a-zA-Z0-9._-]/g, '_'));
}

function proxyAsset(req, res, assetName) {
  if (!/^[a-f0-9]{1,64}\.[a-z0-9]{1,8}$/i.test(assetName)) return notFound(res);
  const cached = cacheKey(assetName);
  if (fs.existsSync(cached)) {
    return serveFile(req, res, cached, 'public, max-age=31536000, immutable');
  }
  const upstream = 'https://assets.scratch.mit.edu/internalapi/asset/' + assetName + '/get/';
  const r = https.get(upstream, { headers: { 'User-Agent': 'scratch-only-local-editor' } }, up => {
    if (up.statusCode !== 200) {
      up.resume();
      return send(res, up.statusCode || 502, 'Asset upstream error', { 'Content-Type': 'text/plain' });
    }
    const chunks = [];
    up.on('data', c => chunks.push(c));
    up.on('end', () => {
      const buf = Buffer.concat(chunks);
      fs.writeFile(cached, buf, () => {});
      send(res, 200, buf, {
        'Content-Type': up.headers['content-type'] || MIME[path.extname(assetName).toLowerCase()] || 'application/octet-stream',
        'Cache-Control': 'public, max-age=31536000, immutable'
      });
    });
  });
  r.on('error', () => send(res, 502, 'Asset proxy error', { 'Content-Type': 'text/plain' }));
  r.setTimeout(20000, () => { r.destroy(); });
}

let localProjectJson = null;

const server = http.createServer((req, res) => {
  const u = new URL(req.url, 'http://localhost');
  let p = decodeURIComponent(u.pathname);

  if (p === '/health') {
    return send(res, 200, JSON.stringify({ ok: true, mode: 'scratch-only', port: PORT }), { 'Content-Type': 'application/json' });
  }

  // Minimal same-origin project shim for scratch-gui. It satisfies the GUI's
  // "create project" call without any external cloud account. Projects are
  // still saved by the user as .sb3 and autosaved in the browser.
  if (p === '/projects' || p === '/projects/') {
    if (req.method === 'POST') {
      let body = '';
      req.on('data', c => { body += c; if (body.length > 50 * 1048576) req.destroy(); });
      req.on('end', () => {
        try { localProjectJson = JSON.parse(body); } catch (e) { localProjectJson = null; }
        // scratch-gui's save-project-to-server reads `content-name` for new projects.
        send(res, 200, JSON.stringify({ id: 'local', 'content-name': 'local' }), { 'Content-Type': 'application/json' });
      });
      return;
    }
    return send(res, 200, JSON.stringify({ id: 'local', 'content-name': 'local' }), { 'Content-Type': 'application/json' });
  }
  if (p.startsWith('/projects/')) {
    if (req.method === 'GET' && p === '/projects/local') {
      if (!localProjectJson) return notFound(res);
      return send(res, 200, JSON.stringify(localProjectJson), { 'Content-Type': 'application/json' });
    }
    if (req.method === 'PUT' || req.method === 'POST') {
      req.resume();
      req.on('end', () => send(res, 200, JSON.stringify({ id: 'local', 'content-name': 'local', status: 'ok' }), { 'Content-Type': 'application/json' }));
      return;
    }
  }
  // Asset upload shim: scratch-gui may POST dirty assets while "creating" a
  // local project. Accept and cache them same-origin; no external account.
  const assetUpload = p.match(/^\/assets\/([a-f0-9]{1,64}\.[a-z0-9]{1,8})$/i);
  if (assetUpload && req.method === 'POST') {
    const chunks = [];
    req.on('data', c => chunks.push(c));
    req.on('end', () => {
      const buf = Buffer.concat(chunks);
      fs.writeFile(cacheKey(assetUpload[1]), buf, () => {});
      send(res, 200, JSON.stringify({ status: 'ok' }), { 'Content-Type': 'application/json' });
    });
    return;
  }

  // Scratch asset proxy: keeps library costumes/sounds/backdrops same-origin.
  let m = p.match(/^\/assets\/internalapi\/asset\/([^/]+)\/get\/?$/);
  if (m) return proxyAsset(req, res, m[1]);
  m = p.match(/^\/static\/extension-assets\/scratch3_music\/([^/]+)$/);
  if (m) return proxyAsset(req, res, m[1]);

  // Webpack publicPath in the UMD build may request chunks from /chunks/.
  if (p.startsWith('/chunks/')) {
    const chunkPath = safeJoin(path.join(VENDOR, 'dist', 'chunks'), p.slice('/chunks'.length));
    if (chunkPath && fs.existsSync(chunkPath)) return serveFile(req, res, chunkPath, 'public, max-age=31536000, immutable');
  }

  if (p === '/') p = '/index.html';
  const filePath = safeJoin(ROOT, p);
  if (!filePath) return notFound(res);

  // Long cache for vendored, versioned editor assets.
  const cache = p.startsWith('/vendor/') ? 'public, max-age=31536000, immutable' : 'no-cache';
  serveFile(req, res, filePath, cache);
});

server.on('error', err => {
  if (err.code === 'EADDRINUSE') {
    console.error(`Port ${PORT} is already in use. Stop the other process or set PORT to another value.`);
  } else {
    console.error(err);
  }
  process.exit(1);
});

server.listen(PORT, HOST, () => {
  console.log(`Scratch-only editor listening on http://${HOST}:${PORT}`);
  console.log(`Local URL: http://localhost:${PORT}`);
});
