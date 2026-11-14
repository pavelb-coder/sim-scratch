'use strict';
/* Auto-sync with GitHub. Works on Windows, Ubuntu and the Arena workspace.
 * Modes: node scripts/sync.js once | watch
 * Local non-secret override: sync.local.json (gitignored).
 * For SSH, set sshKey/knownHosts/remote in sync.local.json.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawn } = require('child_process');

const ROOT = path.resolve(__dirname, '..');
const MODE = process.argv[2] || 'once';

function readJson(p) {
  try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch (e) { return null; }
}
const defaults = readJson(path.join(ROOT, 'sync.config.json')) || {};
const localPath = process.env.SIM_SCRATCH_CONFIG || path.join(ROOT, 'sync.local.json');
const local = readJson(localPath) || {};
const cfg = Object.assign({}, defaults, local);
const remoteName = cfg.remoteName || 'origin';
const branch = cfg.branch || 'main';
const remote = cfg.remote || ('https://github.com/' + cfg.repo + '.git');
const debounceMs = Number(cfg.debounceMs || 4000);
const pullIntervalMs = Number(cfg.pullIntervalMs || 60000);
const commitPrefix = cfg.commitPrefix || 'auto-sync';
const logFile = cfg.logFile ? path.join(ROOT, cfg.logFile) : null;
if (logFile) fs.mkdirSync(path.dirname(logFile), { recursive: true });

function log(msg) {
  const line = '[' + new Date().toISOString() + '] ' + msg;
  console.log(line);
  if (logFile) {
    try { fs.appendFileSync(logFile, line + '\n'); } catch (e) {}
  }
}

function sshPath(p) { return String(p).replace(/\\/g, '/'); }

function gitEnv() {
  const env = Object.assign({}, process.env, {
    GIT_TERMINAL_PROMPT: '0',
    GCM_INTERACTIVE: 'never',
    GIT_EDITOR: 'true'
  });
  if (cfg.sshKey) {
    const known = cfg.knownHosts || path.join(os.homedir(), '.ssh', 'known_hosts');
    env.GIT_SSH_COMMAND = 'ssh -i "' + sshPath(cfg.sshKey) + '" -o IdentitiesOnly=yes -o UserKnownHostsFile="' + sshPath(known) + '" -o StrictHostKeyChecking=yes';
  }
  return env;
}

function git(args, opts) {
  opts = opts || {};
  return new Promise((resolve, reject) => {
    const child = spawn('git', args, { cwd: ROOT, env: gitEnv(), stdio: ['ignore', 'pipe', 'pipe'] });
    let out = '', err = '';
    child.stdout.on('data', d => { out += d; });
    child.stderr.on('data', d => { err += d; });
    child.on('error', reject);
    child.on('close', code => {
      if (code === 0 || opts.allowFail) resolve({ code, out: out.trim(), err: err.trim() });
      else reject(new Error('git ' + args.join(' ') + ' failed (' + code + '): ' + (err || out).trim()));
    });
  });
}

async function ensureRemote() {
  const r = await git(['remote', 'get-url', remoteName], { allowFail: true });
  if (r.code !== 0) await git(['remote', 'add', remoteName, remote]);
  else if (r.out !== remote) await git(['remote', 'set-url', remoteName, remote]);
}

async function hasStagedChanges() {
  const r = await git(['diff', '--cached', '--quiet'], { allowFail: true });
  if (r.code === 0) return false;
  if (r.code === 1) return true;
  throw new Error('git diff --cached failed: ' + r.err);
}

async function remoteRefExists() {
  const r = await git(['rev-parse', '--verify', remoteName + '/' + branch], { allowFail: true });
  return r.code === 0;
}

async function syncOnce(reason) {
  await ensureRemote();
  await git(['fetch', remoteName, branch]);
  await git(['add', '-A']);
  const changed = await hasStagedChanges();
  if (changed) {
    const stamp = new Date().toISOString().replace('T', ' ').slice(0, 19) + ' UTC';
    const msg = commitPrefix + ': ' + stamp + ' (' + (reason || 'manual') + ')';
    await git(['commit', '-m', msg]);
    log('committed: ' + msg);
  }
  if (await remoteRefExists()) {
    const behind = Number((await git(['rev-list', '--count', 'HEAD..' + remoteName + '/' + branch])).out || 0);
    if (behind > 0) {
      try {
        await git(['rebase', remoteName + '/' + branch]);
        log('rebased onto ' + remoteName + '/' + branch);
      } catch (e) {
        await git(['rebase', '--abort'], { allowFail: true });
        throw new Error('rebase conflict; auto-sync needs manual resolution');
      }
    }
    const ahead = Number((await git(['rev-list', '--count', remoteName + '/' + branch + '..HEAD'])).out || 0);
    if (ahead > 0) {
      try {
        await git(['push', remoteName, 'HEAD:' + branch]);
        log('pushed ' + ahead + ' commit(s) to ' + cfg.repo + '#' + branch);
      } catch (e) {
        // Retry once after a fresh fetch/rebase in case the remote moved.
        await git(['fetch', remoteName, branch]);
        await git(['rebase', remoteName + '/' + branch]);
        await git(['push', remoteName, 'HEAD:' + branch]);
        log('pushed after retry to ' + cfg.repo + '#' + branch);
      }
    }
  } else {
    await git(['push', '-u', remoteName, 'HEAD:' + branch]);
    log('created remote branch ' + branch);
  }
  if (!changed) log('up-to-date (' + (reason || 'manual') + ')');
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

async function watch() {
  log('watch started; repo=' + cfg.repo + ' branch=' + branch + ' interval=' + debounceMs + 'ms');
  let stopped = false;
  let errors = 0;
  let lastPull = 0;
  process.on('SIGINT', () => { stopped = true; });
  process.on('SIGTERM', () => { stopped = true; });
  try { await syncOnce('startup'); lastPull = Date.now(); } catch (e) { errors++; log('ERROR ' + e.message); }
  while (!stopped) {
    const delay = errors ? Math.min(60000, debounceMs * Math.pow(2, Math.min(errors, 4))) : debounceMs;
    await sleep(delay);
    if (stopped) break;
    try {
      const status = await git(['status', '--porcelain']);
      const dirty = status.out.length > 0;
      const duePull = Date.now() - lastPull >= pullIntervalMs;
      if (dirty || duePull) {
        await syncOnce(dirty ? 'change' : 'periodic-pull');
        lastPull = Date.now();
      }
      errors = 0;
    } catch (e) {
      errors++;
      log('ERROR ' + e.message);
    }
  }
  log('watch stopped');
}

(async () => {
  if (MODE === 'watch') return watch();
  try { await syncOnce('manual'); } catch (e) { log('ERROR ' + e.message); process.exit(1); }
})();
