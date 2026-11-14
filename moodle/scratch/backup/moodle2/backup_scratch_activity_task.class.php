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
 * Backup activity task for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/scratch/backup/moodle2/backup_scratch_stepslib.php');

/**
 * Provides the steps to perform one complete backup of a Scratch activity.
 */
class backup_scratch_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings() {
    }

    /**
     * Defines a backup step to store the instance data in the scratch.xml file.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_scratch_activity_structure_step('scratch_structure', 'scratch.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');
        $search = '/(' . $base . '\/mod\/scratch\/index.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@SCRATCHINDEX*$2@$', $content);
        $search = '/(' . $base . '\/mod\/scratch\/view.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@SCRATCHVIEWBYID*$2@$', $content);
        return $content;
    }
}
