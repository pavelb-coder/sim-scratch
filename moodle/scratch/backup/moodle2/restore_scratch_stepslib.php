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
 * Restore structure step for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one Scratch activity.
 */
class restore_scratch_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define structure.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('scratch', '/activity/scratch');
        $paths[] = new restore_path_element('scratch_submission', '/activity/scratch/submissions/submission');
        return $paths;
    }

    /**
     * Process scratch instance.
     *
     * @param array $data
     */
    protected function process_scratch($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->duedate = $this->apply_date_offset($data->duedate);
        $data->cutoffdate = $this->apply_date_offset($data->cutoffdate);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('scratch', $data);
        $this->set_mapping('scratch', $oldid, $newitemid, true);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process submission.
     *
     * @param array $data
     */
    protected function process_scratch_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->scratchid = $this->get_new_parentid('scratch');
        $data->userid = $this->get_mappingid('user', $data->userid) ?: 0;
        $data->grader = $this->get_mappingid('user', $data->grader) ?: 0;
        $newitemid = $DB->insert_record('scratch_submissions', $data);
        $this->set_mapping('scratch_submission', $oldid, $newitemid, true);
    }

    /**
     * Post-execution actions.
     */
    protected function after_execute() {
        $this->add_related_files('mod_scratch', 'intro', 'scratch');
        $this->add_related_files('mod_scratch', 'submission', 'scratch_submission');
    }
}
