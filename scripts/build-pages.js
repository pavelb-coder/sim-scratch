'use strict';
/* Build a static GitHub Pages site from public/. No Node server required. */
const fs = require('fs');
const path = require('path');
const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'public');
const OUT = path.join(ROOT, 'dist-pages');
const CHUNKS = path.join(SRC, 'vendor', 'scratch-gui', 'dist', 'chunks');

function skipGzip(src) {
  return !src.endsWith('.gz');
}

fs.rmSync(OUT, { recursive: true, force: true });
fs.mkdirSync(OUT, { recursive: true });
fs.cpSync(SRC, OUT, { recursive: true, filter: skipGzip });
// The UMD build may request webpack chunks from /chunks/ relative to the site.
if (fs.existsSync(CHUNKS)) {
  fs.cpSync(CHUNKS, path.join(OUT, 'chunks'), { recursive: true, filter: skipGzip });
}
fs.writeFileSync(path.join(OUT, '.nojekyll'), '');
console.log('GitHub Pages site built at ' + OUT);
