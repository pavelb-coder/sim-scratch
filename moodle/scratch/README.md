# mod_scratch — Scratch project activity for Moodle

A Moodle 5.x activity module that lets students create Scratch projects in an embedded
Scratch editor, save drafts to Moodle, submit `.sb3` files, and receive grades/feedback.
The Scratch editor is bundled inside the plugin; it does not depend on GitHub Pages.

## Features

- Activity type: **Scratch project** (`mod_scratch`).
- Bundled Scratch GUI 5.3.0 editor (Russian/English UI, themes).
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
