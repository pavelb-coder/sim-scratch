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
 * Teacher report of Scratch submissions.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('scratch', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$scratch = $DB->get_record('scratch', ['id' => $cm->instance], '*', MUST_EXIST);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/scratch:grade', $context);

$PAGE->set_url('/mod/scratch/report.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($scratch->name) . ': ' . get_string('submissions', 'mod_scratch'));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('submissions', 'mod_scratch'));
echo html_writer::link(new moodle_url('/mod/scratch/view.php', ['id' => $cm->id]), get_string('backtoactivity', 'mod_scratch'));

$userfields = \core_user\fields::for_name()->get_sql('u');
$sql = "SELECT s.*{$userfields->selects}
          FROM {scratch_submissions} s
          JOIN {user} u ON u.id = s.userid {$userfields->joins}
         WHERE s.scratchid = :scratchid
      ORDER BY u.lastname, u.firstname";
$submissions = $DB->get_records_sql($sql, array_merge(['scratchid' => $scratch->id], $userfields->params));

if (!$submissions) {
    echo $OUTPUT->notification(get_string('nosubmissions', 'mod_scratch'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('status', 'mod_scratch'),
    get_string('lastmodified'),
    get_string('grade'),
    get_string('actions'),
];
foreach ($submissions as $submission) {
    $user = new stdClass();
    $user->id = $submission->userid;
    foreach (\core_user\fields::get_name_fields() as $field) {
        $user->$field = $submission->$field;
    }
    $name = html_writer::link(new moodle_url('/user/view.php', ['id' => $submission->userid, 'course' => $course->id]), fullname($user));
    $actions = [];
    if ($submission->filename) {
        $actions[] = html_writer::link(
            new moodle_url('/mod/scratch/editor.php', ['cmid' => $cm->id, 'userid' => $submission->userid]),
            get_string('viewproject', 'mod_scratch')
        );
        $actions[] = html_writer::link(
            scratch_submission_file_url($submission, $context),
            get_string('downloadproject', 'mod_scratch')
        );
    }
    $actions[] = html_writer::link(
        new moodle_url('/mod/scratch/grade.php', ['cmid' => $cm->id, 'userid' => $submission->userid]),
        get_string('gradeverb', 'mod_scratch')
    );
    $table->data[] = [
        $name,
        scratch_status_string($submission),
        $submission->timemodified ? userdate($submission->timemodified) : '—',
        $submission->grade !== null ? format_float($submission->grade, 2) : '—',
        implode(' | ', $actions),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
