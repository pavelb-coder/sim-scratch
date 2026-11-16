'use strict';
/* Shared patches applied to the vendored scratch-gui bundle by the build scripts.
 *
 * The GUI ships as a minified UMD bundle, so changes to its behaviour or its translations have
 * to be made on the built artefact. Both targets (the GitHub Pages site and the Moodle plugin)
 * need the same treatment, hence this module instead of a copy in each build script.
 */
const fs = require('fs');
const path = require('path');

const TRANSLATIONS = path.join(__dirname, 'scratch-locale-ru.json');

/**
 * Rewrite the hosted Scratch Link relay URL to the local Scratch Link port.
 *
 * scratch-vm opens ws://127.0.0.1:20111/<channel> and, in parallel, a relay socket to the hosted
 * Scratch service; whichever answers first wins. Pointing the relay at the local port as well
 * keeps hardware support independent of any third-party service and stops device data from
 * leaving the computer.
 *
 * @param {string} code bundle source
 * @returns {{code: string, count: number}} patched source and number of replacements
 */
function applyRelayPatch(code) {
  const needle = 'wss://device-manager.scratch.mit.edu:20110/';
  const replacement = 'ws://127.0.0.1:20111/';
  const count = code.split(needle).length - 1;
  return { code: count ? code.split(needle).join(replacement) : code, count };
}

/**
 * Find the object literal that starts at `start` (a "{" offset) and return its end offset.
 *
 * @param {string} code source
 * @param {number} start offset of the opening brace
 * @returns {number} offset just past the matching closing brace
 */
function findObjectEnd(code, start) {
  let depth = 0;
  let inString = false;
  let escaped = false;
  for (let i = start; i < code.length; i++) {
    const ch = code[i];
    if (inString) {
      if (escaped) escaped = false;
      else if (ch === '\\') escaped = true;
      else if (ch === '"') inString = false;
    } else if (ch === '"') {
      inString = true;
    } else if (ch === '{') {
      depth++;
    } else if (ch === '}') {
      depth--;
      if (depth === 0) return i + 1;
    }
  }
  throw new Error('unbalanced object literal at offset ' + start);
}

/**
 * Replace Russian strings inside the bundle's ru locale map only.
 *
 * The English text of an untranslated message also exists in the en map, so a naive replacement
 * would turn the English UI Russian. The ru map is therefore located first and the replacements
 * are confined to it.
 *
 * @param {string} code bundle source
 * @param {Object<string,string>} translations message id -> Russian text
 * @returns {{code: string, replaced: number, missing: string[]}} result
 */
function applyLocalePatch(code, translations) {
  const anchor = 'ru:{"gui.alerts.lostPeripheralConnection"';
  const anchorAt = code.indexOf(anchor);
  if (anchorAt < 0) return { code, replaced: 0, missing: Object.keys(translations) };

  const start = code.indexOf('{', anchorAt);
  const end = findObjectEnd(code, start);
  let ru = code.slice(start, end);

  let replaced = 0;
  const missing = [];
  for (const [id, text] of Object.entries(translations)) {
    // Match "id":"<anything>" inside the ru map and swap in the translation.
    const pattern = new RegExp('"' + id.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '":"(?:[^"\\\\]|\\\\.)*"');
    if (!pattern.test(ru)) {
      missing.push(id);
      continue;
    }
    ru = ru.replace(pattern, () => '"' + id + '":' + JSON.stringify(text));
    replaced++;
  }
  return { code: code.slice(0, start) + ru + code.slice(end), replaced, missing };
}

/**
 * Apply the Russian translation pack shipped next to this module.
 *
 * @param {string} code bundle source
 * @returns {{code: string, replaced: number, missing: string[]}} result
 */
function applyShippedTranslations(code) {
  const translations = JSON.parse(fs.readFileSync(TRANSLATIONS, 'utf8'));
  return applyLocalePatch(code, translations);
}

module.exports = { applyRelayPatch, applyLocalePatch, applyShippedTranslations, findObjectEnd, TRANSLATIONS };
