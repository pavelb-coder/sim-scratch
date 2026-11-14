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
 * Restore activity task for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scratch/backup/moodle2/restore_scratch_stepslib.php');

/**
 * Provides the steps to perform one complete restore of a Scratch activity.
 */
class restore_scratch_activity_task extends restore_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a restore step to restore the instance data from the scratch.xml file.
     */
    protected function define_my_steps() {
        $this->add_step(new restore_scratch_activity_structure_step('scratch_structure', 'scratch.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return array
     */
    public static function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('scratch', ['intro'], 'scratch');
        return $contents;
    }

    /**
     * Define the decoding rules for links belonging to the activity.
     *
     * @return array
     */
    public static function define_decode_rules() {
        $rules = [];
        $rules[] = new restore_decode_rule('SCRATCHVIEWBYID', '/mod/scratch/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('SCRATCHINDEX', '/mod/scratch/index.php?id=$1', 'course');
        return $rules;
    }

    /**
     * Define the restore log rules.
     *
     * @return array
     */
    public static function define_restore_log_rules() {
        return [];
    }

    /**
     * Rebuild the gradebook after restore.
     */
    protected function after_restore() {
        global $DB;
        require_once(__DIR__ . '/../../lib.php');
        $scratch = $DB->get_record('scratch', ['id' => $this->get_activityid()]);
        if ($scratch) {
            scratch_update_grades($scratch);
        }
    }
}
