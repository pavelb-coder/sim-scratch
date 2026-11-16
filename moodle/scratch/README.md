# mod_scratch — Scratch project activity for Moodle

A Moodle 5.x activity module that lets students create Scratch projects in an embedded
Scratch editor, save drafts to Moodle, submit `.sb3` files, and receive grades/feedback.
The Scratch editor is bundled inside the plugin; it does not depend on GitHub Pages.

## Features

- Activity type: **Scratch project** (`mod_scratch`).
- Bundled Scratch GUI 5.3.0 editor (Russian/English UI, themes).
- **LEGO hardware support**: LEGO Education WeDo 2.0 and LEGO MINDSTORMS EV3 work through
  [Scratch Link](https://scratch.mit.edu/download/scratch-link) installed on the student computer.
  The connection is local (`ws://127.0.0.1:20111`); the hosted Scratch relay is disabled, so no
  device data is sent anywhere.
- The editor follows the Moodle language, including the LEGO blocks and connection dialogs.
- Student workflow: open editor → autosave draft → submit project.
- Teacher workflow: view submissions, preview projects read-only, download `.sb3`, grade and give feedback.
- Moodle gradebook integration.
- Due date, cut-off date, maximum file size, resubmission settings.
- Backup/restore, privacy provider, events, capabilities.
- `.sb3` validation (ZIP archive containing `project.json`).

## Requirements

- Moodle **5.0–5.3** (`$plugin->requires = 2025041400`).
- PHP 8.2+ with `zip`, `mbstring`, `xml`, `curl`.
- For Moodle **5.1+**, install into `public/mod/scratch` (the new web root).
- For Moodle **5.0**, install into `mod/scratch`.

## Installation

1. Copy the `scratch` folder into Moodle's `mod/` directory (or `public/mod/` on Moodle 5.1+).
   Alternatively, install `mod_scratch.zip` via **Site administration → Plugins → Install plugins**.
2. Visit **Site administration → Notifications** to complete installation.
3. Add a **Scratch project** activity to a course.

## Editor behaviour

- The editor opens on a blank project. If the student already has a saved `.sb3`, a small panel
  asks whether to continue it or start fresh; nothing is loaded or overwritten silently.
- The Scratch tutorial library button is hidden — course activities do not use it.
- The GUI texts that ship untranslated (the debug window) are translated by
  `scripts/scratch-locale-ru.json`, applied to the bundle when the plugin is built. Add a key
  there to translate another message.
- The language list in **Settings → Language** is capped to the available height, so the menu no
  longer pulls the editor page up and down while it is open.

## Permissions

- `mod/scratch:addinstance` — add activity (editing teacher, manager).
- `mod/scratch:view` — view activity.
- `mod/scratch:submit` — submit projects (student).
- `mod/scratch:grade` — grade submissions (teacher, editing teacher, manager).
- `mod/scratch:manage` — manage projects.

## Notes

- The editor core is bundled. Scratch library assets (sprites/costumes/sounds) are loaded
  from the Scratch CDN (`assets.scratch.mit.edu`) by default, so an internet connection is
  needed for the built-in libraries. Uploaded `.sb3` projects are stored in Moodle.
- Projects are stored in the Moodle file API under `mod_scratch/submission`.
- The plugin is licensed AGPL-3.0-or-later because it bundles Scratch GUI (AGPL-3.0).

## Building the bundled assets

From the repository root (branch `moodle-plugin`):

```bash
node scripts/build-moodle-plugin.js
```

This copies the vendored Scratch GUI/React assets from `public/vendor/` into
`moodle/scratch/assets/vendor/` and patches the webpack public path for Moodle.

It also rewrites the hosted Scratch Link relay URL
(`wss://device-manager.scratch.mit.edu:20110/`) to the local port, so the editor only ever talks
to Scratch Link on the student computer. `assets/editor.js` enforces the same rule at runtime by
turning relay sockets into inert objects; set `hardware.allowCloudRelay` to `true` in
`editor.php` (together with an unpatched bundle) if the hosted relay is ever wanted again.

## LEGO hardware (WeDo 2.0 / EV3)

1. Install and start [Scratch Link](https://scratch.mit.edu/download/scratch-link) on the
   student computer (Windows 10 1709+ or macOS 10.15+, Bluetooth required).
2. In the editor press **LEGO** in the top bar for the short instructions.
3. Open **Add extension** and pick *LEGO Education WeDo 2.0* or *LEGO MINDSTORMS EV3*,
   then **Start searching**. EV3 additionally needs the connection PIN `1234`.

The editor talks to Scratch Link over `ws://127.0.0.1:20111` directly from the browser; this
works from both HTTP and HTTPS pages. Chrome and Edge ask for local network permission the first
time — students need to allow it. Only one Scratch tab can use Scratch Link at a time.
