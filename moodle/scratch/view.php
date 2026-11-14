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
 * View a Scratch activity.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/completionlib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('scratch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$scratch = $DB->get_record('scratch', ['id' => $cm->instance], '*', MUST_EXIST);

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/scratch:view', $context);

$PAGE->set_url('/mod/scratch/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . format_string($scratch->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_activity_record($scratch);

$event = \mod_scratch\event\course_module_viewed::create([
    'context' => $context,
    'objectid' => $scratch->id,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('scratch', $scratch);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$submission = scratch_get_submission($scratch->id, $USER->id);
$canedit = scratch_can_edit_submission($scratch, $submission, $context);
$cangrade = has_capability('mod/scratch:grade', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($scratch->name));
echo format_module_intro('scratch', $scratch, $cm->id);

echo html_writer::start_div('mod-scratch-meta');
if ($scratch->duedate) {
    echo html_writer::div(get_string('duedate', 'mod_scratch') . ': ' . userdate($scratch->duedate));
}
if ($scratch->cutoffdate) {
    echo html_writer::div(get_string('cutoffdate', 'mod_scratch') . ': ' . userdate($scratch->cutoffdate));
}
echo html_writer::div(get_string('status', 'mod_scratch') . ': ' . scratch_status_string($submission));
echo html_writer::end_div();

if ($submission && $submission->grade !== null && !empty($scratch->grade)) {
    echo html_writer::div(get_string('grade') . ': ' . format_float($submission->grade, 2) . ' / ' . $scratch->grade, 'mod-scratch-grade');
    if (!empty($submission->feedback)) {
        echo html_writer::div(format_text($submission->feedback, $submission->feedbackformat), 'mod-scratch-feedback');
    }
}

echo html_writer::start_div('mod-scratch-actions');
if ($canedit) {
    echo html_writer::link(
        new moodle_url('/mod/scratch/editor.php', ['cmid' => $cm->id]),
        get_string('openeditor', 'mod_scratch'),
        ['class' => 'btn btn-primary']
    );
} else if (has_capability('mod/scratch:submit', $context) && $submission) {
    echo html_writer::link(
        new moodle_url('/mod/scratch/editor.php', ['cmid' => $cm->id]),
        get_string('viewproject', 'mod_scratch'),
        ['class' => 'btn btn-secondary']
    );
}
if ($submission && $submission->filename) {
    echo html_writer::link(
        scratch_submission_file_url($submission, $context),
        get_string('downloadproject', 'mod_scratch'),
        ['class' => 'btn btn-secondary']
    );
}
if ($cangrade) {
    echo html_writer::link(
        new moodle_url('/mod/scratch/report.php', ['id' => $cm->id]),
        get_string('submissions', 'mod_scratch'),
        ['class' => 'btn btn-secondary']
    );
}
echo html_writer::end_div();

echo $OUTPUT->footer();
