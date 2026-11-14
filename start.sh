#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"
if ! command -v node >/dev/null 2>&1; then
  echo "Node.js 18+ is required. Install it from https://nodejs.org/" >&2
  exit 1
fi
export PORT="${PORT:-8083}"
echo "Starting Scratch Code on http://localhost:${PORT}"
if [ ! -f public/vendor/scratch-gui/dist/scratch-gui.js.gz ]; then
  node scripts/compress.js
fi
exec node server.js
