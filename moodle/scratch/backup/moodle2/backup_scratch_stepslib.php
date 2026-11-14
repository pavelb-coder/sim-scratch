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
 * Backup structure step for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete scratch structure for backup.
 */
class backup_scratch_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        $scratch = new backup_nested_element('scratch', ['id'], [
            'course', 'name', 'intro', 'introformat', 'duedate', 'cutoffdate',
            'maxbytes', 'grade', 'allowresubmit', 'timecreated', 'timemodified',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission = new backup_nested_element('submission', ['id'], [
            'userid', 'status', 'title', 'filename', 'filesize', 'filehash',
            'timemodified', 'timesubmitted', 'grade', 'feedback', 'feedbackformat',
            'grader', 'timegraded',
        ]);

        $scratch->add_child($submissions);
        $submissions->add_child($submission);

        $scratch->set_source_table('scratch', ['id' => backup::VAR_ACTIVITYID]);
        $submission->set_source_table('scratch_submissions', ['scratchid' => backup::VAR_ACTIVITYID]);

        $submission->annotate_files('mod_scratch', 'submission', 'id');
        $scratch->annotate_files('mod_scratch', 'intro', null);

        return $this->prepare_activity_structure($scratch);
    }
}
