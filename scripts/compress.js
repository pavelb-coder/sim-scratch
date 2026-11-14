'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const ROOT = path.join(__dirname, '..', 'public');
const exts = new Set(['.js', '.css', '.html', '.json', '.svg', '.txt']);
let count = 0, before = 0, after = 0;
function walk(dir) {
  for (const name of fs.readdirSync(dir)) {
    const p = path.join(dir, name);
    const st = fs.statSync(p);
    if (st.isDirectory()) walk(p);
    else if (exts.has(path.extname(name).toLowerCase()) && st.size > 1024 && !name.endsWith('.gz')) {
      const gz = zlib.gzipSync(fs.readFileSync(p), { level: 9 });
      fs.writeFileSync(p + '.gz', gz);
      count++; before += st.size; after += gz.length;
    }
  }
}
walk(ROOT);
console.log(`compressed ${count} files: ${(before/1048576).toFixed(2)} MiB -> ${(after/1048576).toFixed(2)} MiB gzip`);
