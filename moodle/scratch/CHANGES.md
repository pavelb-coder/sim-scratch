# Changelog

## 1.1.0 (2026-11-14)

- LEGO hardware support: LEGO Education WeDo 2.0 and LEGO MINDSTORMS EV3 connect through
  Scratch Link (`ws://127.0.0.1:20111`), installed on the student computer.
- The hosted Scratch relay (`wss://device-manager.scratch.mit.edu`) is no longer contacted, so
  device data stays on the local computer. `scripts/build-moodle-plugin.js` rewrites the relay
  URL when building the plugin, and `assets/editor.js` refuses relay sockets at runtime.
- The editor now follows the Moodle language (`scratch_editor_locale()`), so the LEGO blocks and
  the whole connection UI are shown in Russian with the Russian language pack.
- New "LEGO" help panel in the editor with installation and connection steps, plus a privacy
  entry documenting the local Scratch Link connection.

## 1.0.0 (2026-11-14)

- Initial release for Moodle 5.0–5.3.
- Scratch project activity with bundled Scratch GUI editor.
- Student draft autosave and `.sb3` submission to Moodle.
- Teacher submission report, read-only preview, download, grading and feedback.
- Moodle gradebook integration.
- Due date, cut-off date, maximum file size and resubmission settings.
- Backup/restore, privacy provider, events and capabilities.
- English and Russian language packs.
