'use strict';
/* Build bundled editor assets for the Moodle mod_scratch plugin.
 * Copies vendored Scratch GUI + React from public/vendor into moodle/scratch/assets/vendor
 * and patches webpack publicPath so chunks resolve under /mod/scratch/assets/.
 */
const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const SRC = path.join(ROOT, 'public', 'vendor');
const DEST = path.join(ROOT, 'moodle', 'scratch', 'assets', 'vendor');
const PLUGIN = path.join(ROOT, 'moodle', 'scratch');

function copyDir(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  for (const entry of fs.readdirSync(src, { withFileTypes: true })) {
    if (entry.name.endsWith('.gz')) continue;
    const s = path.join(src, entry.name);
    const d = path.join(dest, entry.name);
    if (entry.isDirectory()) copyDir(s, d);
    else if (entry.isFile()) fs.copyFileSync(s, d);
  }
}

fs.rmSync(DEST, { recursive: true, force: true });
fs.mkdirSync(DEST, { recursive: true });

// React UMD builds.
for (const f of ['react.production.min.js', 'react-dom.production.min.js']) {
  fs.copyFileSync(path.join(SRC, f), path.join(DEST, f));
}

// Scratch GUI dist only (no npm package source).
copyDir(path.join(SRC, 'scratch-gui', 'dist'), path.join(DEST, 'scratch-gui', 'dist'));
const license = path.join(SRC, 'scratch-gui', 'LICENSE');
if (fs.existsSync(license)) {
  fs.mkdirSync(path.join(DEST, 'scratch-gui'), { recursive: true });
  fs.copyFileSync(license, path.join(DEST, 'scratch-gui', 'LICENSE'));
}

// Patch webpack publicPath for Moodle subdirectory hosting.
const guiPath = path.join(DEST, 'scratch-gui', 'dist', 'scratch-gui.js');
let gui = fs.readFileSync(guiPath, 'utf8');
let patched = 0;
const needle = '__webpack_require__.p=e';
const replacement = '__webpack_require__.p=(self.__scratchGuiPublicPath||e)';
if (gui.includes(needle)) {
  gui = gui.replace(needle, replacement);
  patched++;
}
gui = gui.replace(/(__nested_webpack_require_\d+__\.p)="\/"/g, (m, req) => {
  patched++;
  return req + '=(self.__scratchGuiPublicPath||"/")';
});
if (patched) {
  fs.writeFileSync(guiPath, gui);
  console.log('patched scratch-gui publicPath: ' + patched);
} else {
  console.warn('publicPath needle not found');
}

// scratch-vm creates extension workers from ./extension-worker.js relative to editor.php.
const extWorker = path.join(DEST, 'scratch-gui', 'dist', 'extension-worker.js');
if (fs.existsSync(extWorker)) {
  fs.copyFileSync(extWorker, path.join(PLUGIN, 'extension-worker.js'));
}

console.log('Moodle plugin assets built at ' + DEST);
