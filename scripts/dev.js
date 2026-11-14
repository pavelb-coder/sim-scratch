'use strict';
/* Run editor + auto-sync together. Cross-platform: Windows / Ubuntu. */
const path = require('path');
const { spawn } = require('child_process');
const ROOT = path.resolve(__dirname, '..');
const PORT = process.env.PORT || '8083';
let shuttingDown = false;
const children = [];

function run(args) {
  const child = spawn(process.execPath, args, {
    cwd: ROOT,
    env: Object.assign({}, process.env, { PORT }),
    stdio: 'inherit'
  });
  children.push(child);
  child.on('exit', (code, signal) => {
    if (shuttingDown) return;
    if (args[0] === 'server.js') {
      console.error('Server exited; stopping dev process.');
      shutdown(code || 1);
    } else {
      console.error('Sync watcher exited (' + (signal || code) + '). Editor keeps running; restart npm run dev to restore sync.');
    }
  });
  return child;
}

function shutdown(code) {
  if (shuttingDown) return;
  shuttingDown = true;
  for (const c of children) {
    try { c.kill('SIGTERM'); } catch (e) {}
  }
  setTimeout(() => process.exit(code || 0), 500);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));
console.log('Starting Scratch Code on port ' + PORT + ' with GitHub auto-sync.');
run(['server.js']);
run(['scripts/sync.js', 'watch']);
