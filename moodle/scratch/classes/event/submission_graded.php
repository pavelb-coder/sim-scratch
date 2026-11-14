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
 * Submission graded event.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

namespace mod_scratch\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_scratch submission graded event.
 */
class submission_graded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'scratch_submissions';
    }

    /**
     * Event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventsubmissiongraded', 'mod_scratch');
    }

    /**
     * Event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' graded a Scratch submission with id '$this->objectid'.";
    }

    /**
     * Event URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/scratch/report.php', ['id' => $this->contextinstanceid]);
    }
}
