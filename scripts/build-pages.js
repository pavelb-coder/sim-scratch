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
// Force webpack publicPath to the vendored dist directory. The stock UMD build
// can otherwise request /chunks/... from the domain root on project Pages.
const guiPath = path.join(OUT, 'vendor', 'scratch-gui', 'dist', 'scratch-gui.js');
if (fs.existsSync(guiPath)) {
  let gui = fs.readFileSync(guiPath, 'utf8');
  let patched = 0;
  const needle = '__webpack_require__.p=e';
  const replacement = '__webpack_require__.p=(self.__scratchGuiPublicPath||e)';
  if (gui.includes(needle)) {
    gui = gui.replace(needle, replacement);
    patched++;
  }
  // scratch-storage has a nested webpack runtime with publicPath hardcoded to "/".
  gui = gui.replace(/(__nested_webpack_require_\d+__\.p)="\/"/g, (m, req) => {
    patched++;
    return req + '=(self.__scratchGuiPublicPath||"/")';
  });
  if (patched) {
    fs.writeFileSync(guiPath, gui);
    console.log('patched scratch-gui publicPath for static hosting: ' + patched);
  } else {
    console.warn('publicPath needle not found; static chunks may need attention');
  }
}
// scratch-vm may create extension workers from ./extension-worker.js.
const extensionWorker = path.join(SRC, 'vendor', 'scratch-gui', 'dist', 'extension-worker.js');
if (fs.existsSync(extensionWorker)) {
  fs.copyFileSync(extensionWorker, path.join(OUT, 'extension-worker.js'));
}
// Keep LEGO hardware traffic local and translate the strings the GUI ships untranslated.
// Shared with the Moodle plugin build, see scripts/scratch-bundle-patches.js.
const patches = require('./scratch-bundle-patches');
if (fs.existsSync(guiPath)) {
  let gui = fs.readFileSync(guiPath, 'utf8');
  const relay = patches.applyRelayPatch(gui);
  gui = relay.code;
  console.log('patched hosted Scratch Link relay -> local: ' + relay.count);
  const ru = patches.applyShippedTranslations(gui);
  gui = ru.code;
  console.log('applied Russian UI translations: ' + ru.replaced +
    (ru.missing.length ? ' (missing: ' + ru.missing.join(', ') + ')' : ''));
  fs.writeFileSync(guiPath, gui);
}
// Scratch GUI should also follow the language of the surrounding site.
const indexHtml = path.join(OUT, 'index.html');
if (fs.existsSync(indexHtml)) {
  let html = fs.readFileSync(indexHtml, 'utf8');
  const localeScript = '<script>try{var p=new URL(location.href);if(!p.searchParams.has("locale")&&!p.searchParams.has("lang")){var l=(navigator.language||"en").toLowerCase();if(l.slice(0,2)!=="en"){p.searchParams.set("locale",l);history.replaceState(null,"",p.href);}}}catch(e){}</script>\n';
  if (!html.includes('searchParams.has("locale")')) {
    html = html.replace('</head>', localeScript + '</head>');
    fs.writeFileSync(indexHtml, html);
    console.log('index.html: locale bootstrap added');
  }
}
fs.writeFileSync(path.join(OUT, '.nojekyll'), '');
console.log('GitHub Pages site built at ' + OUT);
