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
 * AJAX endpoints for saving and submitting Scratch projects.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);

$cm = get_coursemodule_from_id('scratch', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$scratch = $DB->get_record('scratch', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($action === 'save' || $action === 'submit') {
        require_capability('mod/scratch:submit', $context);
        $submission = scratch_get_submission($scratch->id, $USER->id);
        if (!scratch_can_edit_submission($scratch, $submission, $context)) {
            throw new moodle_exception('errornotallowed', 'mod_scratch');
        }
        if (empty($_FILES['project']) || !is_array($_FILES['project'])) {
            throw new moodle_exception('errorinvalidfile', 'mod_scratch');
        }
        $file = $_FILES['project'];
        if (!empty($file['error'])) {
            throw new moodle_exception('errorinvalidfile', 'mod_scratch');
        }
        $title = optional_param('title', '', PARAM_TEXT);
        $submission = scratch_store_submission($scratch, $cm, $context, $USER->id, $file, $title, $action === 'submit');
        echo json_encode([
            'ok' => true,
            'status' => $submission->status,
            'statusstring' => scratch_status_string($submission),
            'fileurl' => scratch_submission_file_url($submission, $context)->out(false),
            'timemodified' => $submission->timemodified,
        ]);
        exit;
    }

    throw new moodle_exception('invalidaction', 'mod_scratch');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
