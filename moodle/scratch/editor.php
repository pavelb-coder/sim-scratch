<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Full-screen Scratch editor embedded in Moodle.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$PAGE->set_url('/mod/scratch/editor.php', ['cmid' => $cmid, 'userid' => $userid]);

$cm = get_coursemodule_from_id('scratch', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$scratch = $DB->get_record('scratch', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/scratch:view', $context);
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');

if ($userid && $userid != $USER->id) {
    require_capability('mod/scratch:grade', $context);
    $targetuserid = $userid;
} else {
    $targetuserid = $USER->id;
}

$submission = scratch_get_submission($scratch->id, $targetuserid);
$canedit = ($targetuserid == $USER->id) && scratch_can_edit_submission($scratch, $submission, $context);
$cansubmit = $canedit;
$fileurl = scratch_submission_file_url($submission, $context);
$assetsbase = $CFG->wwwroot . '/mod/scratch/assets/';

$config = [
    'cmid' => (int)$cm->id,
    'userid' => (int)$targetuserid,
    'sesskey' => sesskey(),
    'ajaxUrl' => (new moodle_url('/mod/scratch/ajax.php'))->out(false),
    'returnUrl' => (new moodle_url('/mod/scratch/view.php', ['id' => $cm->id]))->out(false),
    'fileUrl' => $fileurl ? $fileurl->out(false) : null,
    'canEdit' => $canedit,
    'canSubmit' => $cansubmit,
    'maxBytes' => (int)$scratch->maxbytes,
    'title' => $submission ? $submission->title : $scratch->name,
    'status' => $submission ? $submission->status : 'none',
    'activityName' => format_string($scratch->name),
    'basePath' => $assetsbase . 'vendor/scratch-gui/dist/',
    'assetHost' => 'https://assets.scratch.mit.edu',
    // Language of the bundled editor UI and its extension blocks (LEGO WeDo 2.0 / EV3 included).
    'locale' => scratch_editor_locale(),
    // Hardware support: the editor talks to a locally installed Scratch Link (WebSocket on
    // 127.0.0.1:20111). The hosted Scratch relay is disabled, so no device data leaves the computer.
    'hardware' => [
        'linkUrl' => 'https://scratch.mit.edu/download/scratch-link',
        'installUrl' => 'https://scratch.mit.edu/download/scratch-link',
        'allowCloudRelay' => false,
    ],
    'strings' => [
        'loading' => get_string('loading', 'mod_scratch'),
        'saving' => get_string('saving', 'mod_scratch'),
        'saved' => get_string('saved', 'mod_scratch'),
        'saveerror' => get_string('saveerror', 'mod_scratch'),
        'submit' => get_string('submitproject', 'mod_scratch'),
        'submitted' => get_string('statussubmitted', 'mod_scratch'),
        'draft' => get_string('statusdraft', 'mod_scratch'),
        'download' => get_string('downloadproject', 'mod_scratch'),
        'back' => get_string('backtoactivity', 'mod_scratch'),
        'readonly' => get_string('readonlyproject', 'mod_scratch'),
        'toolarge' => get_string('errorfiletoolarge', 'mod_scratch'),
        'confirmSubmit' => get_string('confirmsubmit', 'mod_scratch'),
        'hardware' => get_string('hardwarehelp', 'mod_scratch'),
        'hardwaretitle' => get_string('hardwaretitle', 'mod_scratch'),
        'hardwaresteps' => [
            get_string('hardwarestep1', 'mod_scratch'),
            get_string('hardwarestep2', 'mod_scratch'),
            get_string('hardwarestep3', 'mod_scratch'),
        ],
        'hardwareinstall' => get_string('hardwareinstalllink', 'mod_scratch'),
        'hardwareclose' => get_string('hardwareclose', 'mod_scratch'),
        'hardwarenoedit' => get_string('hardwarenoedit', 'mod_scratch'),
    ],
];

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
?>
<!DOCTYPE html>
<html lang="<?php echo current_language(); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo format_string($scratch->name); ?> — Scratch</title>
<link rel="stylesheet" href="<?php echo $assetsbase; ?>editor.css">
</head>
<body>
<header class="topbar">
  <a class="btn btn-secondary" href="<?php echo $config['returnUrl']; ?>"><?php echo $config['strings']['back']; ?></a>
  <span class="activity-name"><?php echo format_string($scratch->name); ?></span>
  <span class="spacer"></span>
  <span id="save-status" class="save-status"><?php echo $config['strings']['loading']; ?></span>
  <button id="hardware-btn" class="btn btn-secondary" type="button" title="<?php echo s($config['strings']['hardwaretitle']); ?>">LEGO</button>
  <?php if ($canedit) : ?>
  <button id="save-btn" class="btn btn-secondary" type="button"><?php echo get_string('savedraft', 'mod_scratch'); ?></button>
  <button id="submit-btn" class="btn btn-primary" type="button"><?php echo $config['strings']['submit']; ?></button>
  <?php endif; ?>
  <button id="download-btn" class="btn btn-secondary" type="button"><?php echo $config['strings']['download']; ?></button>
</header>
<div id="hardware-panel" class="hardware-panel" role="dialog" aria-modal="true" aria-labelledby="hardware-panel-title" hidden>
  <div class="hardware-panel__box">
    <h2 id="hardware-panel-title"><?php echo s($config['strings']['hardwaretitle']); ?></h2>
    <ol class="hardware-panel__steps">
      <?php foreach ($config['strings']['hardwaresteps'] as $step) : ?>
      <li><?php echo s($step); ?></li>
      <?php endforeach; ?>
    </ol>
    <p class="hardware-panel__note"><?php echo s($config['strings']['hardware']); ?></p>
    <p class="hardware-panel__actions">
      <a class="btn btn-primary" href="<?php echo s($config['hardware']['installUrl']); ?>" target="_blank" rel="noopener noreferrer"><?php echo s($config['strings']['hardwareinstall']); ?></a>
      <button id="hardware-close" class="btn btn-secondary" type="button"><?php echo s($config['strings']['hardwareclose']); ?></button>
    </p>
  </div>
</div>
<main id="app" class="app" translate="no">
  <div class="loading-screen"><p><?php echo $config['strings']['loading']; ?></p></div>
</main>
<script>
window.__scratchGuiPublicPath = <?php echo json_encode($config['basePath']); ?>;
window.SCRATCH_MOODLE = <?php echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script src="<?php echo $assetsbase; ?>vendor/react.production.min.js"></script>
<script src="<?php echo $assetsbase; ?>vendor/react-dom.production.min.js"></script>
<script>window.react = window.React; window['react-dom'] = window.ReactDOM;</script>
<script src="<?php echo $assetsbase; ?>vendor/scratch-gui/dist/scratch-gui.js"></script>
<script src="<?php echo $assetsbase; ?>editor.js"></script>
</body>
</html>
