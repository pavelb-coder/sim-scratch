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
 * List Scratch activities in a course.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$PAGE->set_url('/mod/scratch/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname) . ': ' . get_string('modulenameplural', 'mod_scratch'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_scratch'));

if (!$instances = get_all_instances_in_course('scratch', $course)) {
    echo $OUTPUT->notification(get_string('noinstances', 'mod_scratch'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('name'), get_string('description'), get_string('duedate', 'mod_scratch')];
foreach ($instances as $instance) {
    $link = html_writer::link(
        new moodle_url('/mod/scratch/view.php', ['id' => $instance->coursemodule]),
        format_string($instance->name)
    );
    $table->data[] = [$link, format_module_intro('scratch', $instance, $instance->coursemodule), $instance->duedate ? userdate($instance->duedate) : '—'];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
