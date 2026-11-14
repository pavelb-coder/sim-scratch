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
 * Grade a student's Scratch submission.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$cm = get_coursemodule_from_id('scratch', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$scratch = $DB->get_record('scratch', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/scratch:grade', $context);

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
$submission = scratch_get_submission($scratch->id, $userid);

if (empty($scratch->grade)) {
    redirect(
        new moodle_url('/mod/scratch/report.php', ['id' => $cm->id]),
        get_string('gradingdisabled', 'mod_scratch')
    );
}

$PAGE->set_url('/mod/scratch/grade.php', ['cmid' => $cm->id, 'userid' => $userid]);
$PAGE->set_title(format_string($scratch->name) . ': ' . get_string('gradeverb', 'mod_scratch'));
$PAGE->set_heading(format_string($course->fullname));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    $graderaw = optional_param('grade', '', PARAM_RAW);
    $grade = ($graderaw === '' || $graderaw === null) ? null : (float)$graderaw;
    $feedback = optional_param('feedback', '', PARAM_CLEANHTML);

    if (!$submission) {
        $submission = new stdClass();
        $submission->scratchid = $scratch->id;
        $submission->userid = $userid;
        $submission->status = 'draft';
        $submission->title = '';
        $submission->filename = '';
        $submission->filesize = 0;
        $submission->filehash = '';
        $submission->timemodified = time();
        $submission->timesubmitted = 0;
        $submission->feedbackformat = FORMAT_HTML;
        $submission->grader = 0;
        $submission->timegraded = 0;
        $submission->id = $DB->insert_record('scratch_submissions', $submission);
    }

    if ($grade !== null) {
        $grade = max(0, min((float)$scratch->grade, (float)$grade));
        $submission->grade = $grade;
    } else {
        $submission->grade = null;
    }
    $submission->feedback = $feedback;
    $submission->feedbackformat = FORMAT_HTML;
    $submission->grader = $USER->id;
    $submission->timegraded = time();
    $DB->update_record('scratch_submissions', $submission);

    scratch_update_grades($scratch, $userid);

    $event = \mod_scratch\event\submission_graded::create([
        'context' => $context,
        'objectid' => $submission->id,
        'relateduserid' => $userid,
        'other' => ['scratchid' => $scratch->id, 'grade' => $submission->grade],
    ]);
    $event->trigger();

    redirect(new moodle_url('/mod/scratch/report.php', ['id' => $cm->id]), get_string('gradesaved', 'mod_scratch'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradeverb', 'mod_scratch') . ': ' . fullname($user));

if ($submission && $submission->filename) {
    echo html_writer::div(
        html_writer::link(
            new moodle_url('/mod/scratch/editor.php', ['cmid' => $cm->id, 'userid' => $userid]),
            get_string('viewproject', 'mod_scratch'),
            ['class' => 'btn btn-secondary']
        ) . ' ' .
        html_writer::link(
            scratch_submission_file_url($submission, $context),
            get_string('downloadproject', 'mod_scratch'),
            ['class' => 'btn btn-secondary']
        )
    );
} else {
    echo $OUTPUT->notification(get_string('nosubmissionyet', 'mod_scratch'), 'info');
}

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'class' => 'mod-scratch-grade-form']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::div(
    html_writer::label(get_string('grade') . ' (0–' . $scratch->grade . ')', 'id_grade') .
    html_writer::empty_tag('input', [
        'type' => 'number',
        'id' => 'id_grade',
        'name' => 'grade',
        'min' => 0,
        'max' => $scratch->grade,
        'step' => '0.01',
        'value' => $submission && $submission->grade !== null ? $submission->grade : '',
    ])
);
echo html_writer::div(
    html_writer::label(get_string('feedback', 'mod_scratch'), 'id_feedback') .
    html_writer::tag('textarea', $submission ? s($submission->feedback) : '', ['id' => 'id_feedback', 'name' => 'feedback', 'rows' => 6, 'cols' => 60])
);
echo html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('savechanges'), 'class' => 'btn btn-primary']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();
