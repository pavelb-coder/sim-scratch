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
 * Data generator for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Scratch activity data generator.
 */
class mod_scratch_generator extends testing_module_generator {

    /**
     * Create a Scratch activity instance.
     *
     * @param array|stdClass $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;
        if (!isset($record->name)) {
            $record->name = 'Scratch project';
        }
        if (!isset($record->intro)) {
            $record->intro = '';
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_HTML;
        }
        if (!isset($record->duedate)) {
            $record->duedate = 0;
        }
        if (!isset($record->cutoffdate)) {
            $record->cutoffdate = 0;
        }
        if (!isset($record->maxbytes)) {
            $record->maxbytes = 10485760;
        }
        if (!isset($record->grade)) {
            $record->grade = 100;
        }
        if (!isset($record->allowresubmit)) {
            $record->allowresubmit = 1;
        }
        return parent::create_instance($record, (array)$options);
    }
}
