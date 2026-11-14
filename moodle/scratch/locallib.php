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
 * Internal helpers for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Get a submission record for a user.
 *
 * @param int $scratchid
 * @param int $userid
 * @return stdClass|null
 */
function scratch_get_submission($scratchid, $userid) {
    global $DB;
    return $DB->get_record('scratch_submissions', ['scratchid' => $scratchid, 'userid' => $userid]) ?: null;
}

/**
 * Whether the current user may edit a submission in the Scratch editor.
 *
 * @param stdClass $scratch
 * @param stdClass|null $submission
 * @param context_module $context
 * @return bool
 */
function scratch_can_edit_submission($scratch, $submission, $context) {
    if (!has_capability('mod/scratch:submit', $context)) {
        return false;
    }
    if (has_capability('mod/scratch:grade', $context)) {
        // Teachers preview submissions read-only.
        return false;
    }
    if (!empty($scratch->cutoffdate) && time() > $scratch->cutoffdate) {
        return false;
    }
    if ($submission && $submission->status === 'submitted' && empty($scratch->allowresubmit)) {
        return false;
    }
    return true;
}

/**
 * Whether the current user may submit a project.
 *
 * @param stdClass $scratch
 * @param stdClass|null $submission
 * @param context_module $context
 * @return bool
 */
function scratch_can_submit_submission($scratch, $submission, $context) {
    return scratch_can_edit_submission($scratch, $submission, $context);
}

/**
 * Build the pluginfile URL for a submission's .sb3.
 *
 * @param stdClass $submission
 * @param context_module $context
 * @return moodle_url|null
 */
function scratch_submission_file_url($submission, $context) {
    if (!$submission || empty($submission->filename)) {
        return null;
    }
    return moodle_url::make_pluginfile_url(
        $context->id,
        'mod_scratch',
        'submission',
        $submission->id,
        '/',
        $submission->filename,
        false
    );
}

/**
 * Human-readable submission status.
 *
 * @param stdClass|null $submission
 * @return string
 */
function scratch_status_string($submission) {
    if (!$submission) {
        return get_string('statusnotsubmitted', 'mod_scratch');
    }
    if ($submission->status === 'submitted') {
        return get_string('statussubmitted', 'mod_scratch');
    }
    return get_string('statusdraft', 'mod_scratch');
}

/**
 * Validate an uploaded .sb3 file.
 *
 * @param string $pathname
 * @param int $maxbytes
 * @return string error key or empty string
 */
function scratch_validate_sb3($pathname, $maxbytes) {
    if (!is_uploaded_file($pathname)) {
        return 'errorinvalidfile';
    }
    if (filesize($pathname) > $maxbytes) {
        return 'errorfiletoolarge';
    }
    if (!class_exists('ZipArchive')) {
        return '';
    }
    $zip = new ZipArchive();
    if ($zip->open($pathname) !== true) {
        return 'errorinvalidfile';
    }
    $hasproject = false;
    $total = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) {
            continue;
        }
        $total += isset($stat['size']) ? $stat['size'] : 0;
        if ($stat['name'] === 'project.json') {
            $hasproject = true;
        }
        if ($total > $maxbytes * 10) {
            $zip->close();
            return 'errorfiletoolarge';
        }
    }
    $zip->close();
    if (!$hasproject) {
        return 'errorinvalidfile';
    }
    return '';
}

/**
 * Store an uploaded .sb3 and update/create the submission record.
 *
 * @param stdClass $scratch
 * @param stdClass $cm
 * @param context_module $context
 * @param int $userid
 * @param array $file $_FILES entry
 * @param string $title
 * @param bool $submit
 * @return stdClass updated submission
 * @throws moodle_exception
 */
function scratch_store_submission($scratch, $cm, $context, $userid, array $file, $title, $submit = false) {
    global $DB, $USER;

    $error = scratch_validate_sb3($file['tmp_name'], $scratch->maxbytes);
    if ($error !== '') {
        throw new moodle_exception($error, 'mod_scratch');
    }

    $submission = scratch_get_submission($scratch->id, $userid);
    $now = time();
    if (!$submission) {
        $submission = new stdClass();
        $submission->scratchid = $scratch->id;
        $submission->userid = $userid;
        $submission->status = 'draft';
        $submission->title = '';
        $submission->filename = '';
        $submission->filesize = 0;
        $submission->filehash = '';
        $submission->timemodified = $now;
        $submission->timesubmitted = 0;
        $submission->feedbackformat = FORMAT_HTML;
        $submission->grader = 0;
        $submission->timegraded = 0;
        $submission->id = $DB->insert_record('scratch_submissions', $submission);
    }

    $title = trim($title) !== '' ? trim($title) : ($submission->title ?: $scratch->name);
    $filename = clean_param($title, PARAM_FILE);
    if ($filename === '') {
        $filename = 'project';
    }
    if (strtolower(substr($filename, -4)) !== '.sb3') {
        $filename .= '.sb3';
    }

    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_scratch', 'submission', $submission->id);
    $filerecord = [
        'contextid' => $context->id,
        'component' => 'mod_scratch',
        'filearea' => 'submission',
        'itemid' => $submission->id,
        'filepath' => '/',
        'filename' => $filename,
        'userid' => $userid,
        'author' => fullname($USER),
        'license' => 'allrightsreserved',
        'source' => $filename,
    ];
    $fs->create_file_from_pathname($filerecord, $file['tmp_name']);

    $submission->title = $title;
    $submission->filename = $filename;
    $submission->filesize = filesize($file['tmp_name']);
    $submission->filehash = sha1_file($file['tmp_name']);
    $submission->timemodified = $now;
    if ($submit) {
        $submission->status = 'submitted';
        $submission->timesubmitted = $now;
    } else if ($submission->status !== 'submitted') {
        $submission->status = 'draft';
    }
    $DB->update_record('scratch_submissions', $submission);

    if ($submit) {
        $event = \mod_scratch\event\submission_submitted::create([
            'context' => $context,
            'objectid' => $submission->id,
            'relateduserid' => $userid,
            'other' => ['scratchid' => $scratch->id],
        ]);
        $event->trigger();
    }

    return $submission;
}
