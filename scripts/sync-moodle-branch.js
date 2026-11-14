'use strict';
/* Keep the Moodle plugin branch in sync with main.
 *
 * Merges main into moodle-plugin and, when the bundled editor sources changed,
 * regenerates moodle/scratch/assets/vendor via scripts/build-moodle-plugin.js.
 * Used both by .github/workflows/sync-moodle.yml and by `npm run sync:moodle`.
 *
 * The merge happens in a temporary git worktree, so the current working tree
 * (and any uncommitted work in it) is never touched.
 *
 * Usage:
 *   node scripts/sync-moodle-branch.js [options]
 *
 * Options:
 *   --source <branch>    Source branch (default: main)
 *   --target <branch>    Target branch (default: moodle-plugin)
 *   --source-ref <ref>   Override source ref (e.g. local "main" instead of origin/main)
 *   --target-ref <ref>   Override target ref
 *   --no-push            Do everything except the final push
 *   --dry-run            Implies --no-push; also skips the asset rebuild commit
 *   --force-build        Rebuild bundled assets even if sources did not change
 *   --help
 */
const fs = require('fs');
const os = require('os');
const path = require('path');
const { spawnSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

const BOT_NAME = process.env.MOODLE_SYNC_BOT_NAME || 'github-actions[bot]';
const BOT_EMAIL = process.env.MOODLE_SYNC_BOT_EMAIL || '41898282+github-actions[bot]@users.noreply.github.com';

function parseArgs(argv) {
  const out = {
    source: 'main',
    target: 'moodle-plugin',
    sourceRef: null,
    targetRef: null,
    push: true,
    dryRun: false,
    forceBuild: false,
    help: false
  };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (a === '--source') out.source = argv[++i];
    else if (a === '--target') out.target = argv[++i];
    else if (a === '--source-ref') out.sourceRef = argv[++i];
    else if (a === '--target-ref') out.targetRef = argv[++i];
    else if (a === '--no-push') out.push = false;
    else if (a === '--dry-run') { out.push = false; out.dryRun = true; }
    else if (a === '--force-build') out.forceBuild = true;
    else if (a === '--help' || a === '-h') out.help = true;
    else throw new Error('Unknown option: ' + a);
  }
  return out;
}

function readJson(p) {
  try { return JSON.parse(fs.readFileSync(p, 'utf8')); } catch (e) { return null; }
}

const defaults = readJson(path.join(ROOT, 'sync.config.json')) || {};
const localPath = process.env.SIM_SCRATCH_CONFIG || path.join(ROOT, 'sync.local.json');
const cfg = Object.assign({}, defaults, readJson(localPath) || {});
const remoteName = cfg.remoteName || 'origin';

function sshPath(p) { return String(p).replace(/\\/g, '/'); }

function gitEnv() {
  const env = Object.assign({}, process.env, {
    GIT_TERMINAL_PROMPT: '0',
    GCM_INTERACTIVE: 'never',
    GIT_EDITOR: 'true',
    GIT_MERGE_AUTOEDIT: 'no'
  });
  if (cfg.sshKey) {
    const known = cfg.knownHosts || path.join(os.homedir(), '.ssh', 'known_hosts');
    env.GIT_SSH_COMMAND = 'ssh -i "' + sshPath(cfg.sshKey) + '" -o IdentitiesOnly=yes' +
      ' -o UserKnownHostsFile="' + sshPath(known) + '" -o StrictHostKeyChecking=yes';
  }
  return env;
}

const ENV = gitEnv();

function log(msg) {
  console.log('[sync-moodle] ' + msg);
}

function git(args, opts) {
  opts = opts || {};
  const res = spawnSync('git', args, {
    cwd: opts.cwd || ROOT,
    env: ENV,
    encoding: 'utf8',
    maxBuffer: 64 * 1024 * 1024
  });
  if (res.error) throw res.error;
  const out = (res.stdout || '').trim();
  const err = (res.stderr || '').trim();
  if (res.status !== 0 && !opts.allowFail) {
    throw new Error('git ' + args.join(' ') + ' failed (' + res.status + '): ' + (err || out));
  }
  return { code: res.status, out, err };
}

function refExists(ref, cwd) {
  return git(['rev-parse', '--verify', '--quiet', ref + '^{commit}'], { cwd, allowFail: true }).code === 0;
}

function cleanupWorktree(dir) {
  if (!dir) return;
  git(['worktree', 'remove', '--force', dir], { allowFail: true });
  try { fs.rmSync(dir, { recursive: true, force: true }); } catch (e) {}
  git(['worktree', 'prune'], { allowFail: true });
}

function main() {
  const args = parseArgs(process.argv.slice(2));
  if (args.help) {
    console.log(fs.readFileSync(__filename, 'utf8').split('*/')[0].replace(/^'use strict';\s*\/\*/, ''));
    return 0;
  }

  const sourceRef = args.sourceRef || (remoteName + '/' + args.source);
  const targetRef = args.targetRef || (remoteName + '/' + args.target);

  log('repo=' + (cfg.repo || '(local)') + ' ' + sourceRef + ' -> ' + targetRef);

  if (!args.sourceRef || !args.targetRef) {
    git(['fetch', remoteName, '+refs/heads/*:refs/remotes/' + remoteName + '/*']);
  }

  if (!refExists(sourceRef)) throw new Error('Source ref not found: ' + sourceRef);
  if (!refExists(targetRef)) {
    throw new Error('Target ref not found: ' + targetRef + '. Create the plugin branch first.');
  }

  const sourceSha = git(['rev-parse', sourceRef]).out;
  const targetSha = git(['rev-parse', targetRef]).out;
  log('source ' + sourceSha.slice(0, 7) + ', target ' + targetSha.slice(0, 7));

  const upToDate = git(['merge-base', '--is-ancestor', sourceSha, targetSha], { allowFail: true }).code === 0;
  if (upToDate && !args.forceBuild) {
    log('already up to date: ' + args.target + ' contains ' + args.source + '. Nothing to do.');
    return 0;
  }

  const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'moodle-sync-'));
  let pushed = false;
  let mergeCommit = null;
  let buildCommit = null;

  try {
    git(['worktree', 'add', '--detach', tmp, targetSha]);
    log('temporary worktree: ' + tmp);

    const identity = ['-c', 'user.name=' + BOT_NAME, '-c', 'user.email=' + BOT_EMAIL];

    if (!upToDate) {
      const merge = git(identity.concat([
        'merge', '--no-ff', '--no-edit',
        '-m', 'Merge ' + args.source + ' into ' + args.target + '\n\nAutomatic sync of ' + sourceSha + '.',
        sourceSha
      ]), { cwd: tmp, allowFail: true });

      if (merge.code !== 0) {
        const conflicts = git(['diff', '--name-only', '--diff-filter=U'], { cwd: tmp, allowFail: true }).out;
        git(['merge', '--abort'], { cwd: tmp, allowFail: true });
        throw new Error(
          'Merge conflict; automatic sync stopped.\n' +
          (conflicts ? 'Conflicting files:\n  ' + conflicts.split('\n').join('\n  ') + '\n' : '') +
          (merge.err || merge.out) + '\n' +
          'Resolve manually: git checkout ' + args.target + ' && git merge ' + args.source
        );
      }
      mergeCommit = git(['rev-parse', 'HEAD'], { cwd: tmp }).out;
      log('merged: ' + mergeCommit.slice(0, 7));
    } else {
      log('branch already contains ' + args.source + '; rebuild requested explicitly.');
    }

    // Rebuild the bundled editor only when its sources actually moved.
    const buildScript = path.join(tmp, 'scripts', 'build-moodle-plugin.js');
    const vendorSrc = path.join(tmp, 'public', 'vendor');
    let needBuild = args.forceBuild;
    if (!needBuild && !upToDate) {
      const changed = git(['diff', '--name-only', targetSha, 'HEAD', '--',
        'public/vendor', 'scripts/build-moodle-plugin.js'], { cwd: tmp }).out;
      needBuild = changed.length > 0;
      if (needBuild) log('bundled editor sources changed:\n  ' + changed.split('\n').slice(0, 10).join('\n  '));
    }

    if (needBuild && fs.existsSync(buildScript) && fs.existsSync(vendorSrc)) {
      log('rebuilding bundled Moodle assets…');
      const build = spawnSync(process.execPath, [buildScript], { cwd: tmp, env: ENV, encoding: 'utf8' });
      if (build.status !== 0) {
        throw new Error('build-moodle-plugin.js failed: ' + ((build.stderr || '') + (build.stdout || '')).trim());
      }
      process.stdout.write(build.stdout || '');
      git(['add', '-A'], { cwd: tmp });
      const staged = git(['diff', '--cached', '--quiet'], { cwd: tmp, allowFail: true }).code !== 0;
      if (staged && !args.dryRun) {
        git(identity.concat(['commit', '-m', 'Rebuild bundled Moodle editor assets from ' + args.source]), { cwd: tmp });
        buildCommit = git(['rev-parse', 'HEAD'], { cwd: tmp }).out;
        log('assets rebuilt: ' + buildCommit.slice(0, 7));
      } else if (staged) {
        log('dry-run: assets would be rebuilt and committed.');
      } else {
        log('assets already in sync.');
      }
    } else if (needBuild) {
      log('WARNING: rebuild needed but scripts/build-moodle-plugin.js or public/vendor is missing.');
    }

    const head = git(['rev-parse', 'HEAD'], { cwd: tmp }).out;
    if (head === targetSha) {
      log('no changes to push.');
    } else if (!args.push) {
      log('push skipped (--no-push/--dry-run). Result would be ' + head.slice(0, 7) + '.');
    } else {
      const push = git(['push', remoteName, 'HEAD:refs/heads/' + args.target], { cwd: tmp, allowFail: true });
      if (push.code !== 0) {
        // The remote branch may have moved while we were merging; retry once.
        log('push rejected, re-fetching and retrying once…');
        git(['fetch', remoteName, '+refs/heads/' + args.target + ':refs/remotes/' + remoteName + '/' + args.target]);
        const retry = git(identity.concat([
          'merge', '--no-ff', '--no-edit', '-m', 'Merge remote ' + args.target + ' during sync',
          remoteName + '/' + args.target
        ]), { cwd: tmp, allowFail: true });
        if (retry.code !== 0) {
          git(['merge', '--abort'], { cwd: tmp, allowFail: true });
          throw new Error('Push rejected and retry merge conflicted: ' + (push.err || push.out));
        }
        git(['push', remoteName, 'HEAD:refs/heads/' + args.target], { cwd: tmp });
      }
      pushed = true;
      log('pushed ' + args.target + ' -> ' + git(['rev-parse', 'HEAD'], { cwd: tmp }).out.slice(0, 7));
    }

    console.log(JSON.stringify({
      source: args.source,
      target: args.target,
      sourceSha,
      targetShaBefore: targetSha,
      mergeCommit,
      buildCommit,
      pushed,
      dryRun: args.dryRun
    }, null, 2));
    return 0;
  } finally {
    cleanupWorktree(tmp);
  }
}

try {
  process.exit(main());
} catch (e) {
  console.error('[sync-moodle] ERROR: ' + e.message);
  process.exit(1);
}
