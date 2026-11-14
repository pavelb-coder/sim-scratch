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
 * Local library tests for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

namespace mod_scratch;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/scratch/locallib.php');

/**
 * Tests for locallib.php.
 */
final class locallib_test extends \advanced_testcase {

    /**
     * Status strings are returned for null/draft/submitted.
     */
    public function test_status_string(): void {
        $this->resetAfterTest();
        $this->assertSame(get_string('statusnotsubmitted', 'mod_scratch'), scratch_status_string(null));
        $draft = (object)['status' => 'draft'];
        $submitted = (object)['status' => 'submitted'];
        $this->assertSame(get_string('statusdraft', 'mod_scratch'), scratch_status_string($draft));
        $this->assertSame(get_string('statussubmitted', 'mod_scratch'), scratch_status_string($submitted));
    }

    /**
     * Cut-off date blocks editing.
     */
    public function test_cutoff_blocks_edit(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $scratch = $this->getDataGenerator()->create_module('scratch', ['course' => $course->id, 'cutoffdate' => time() - 100]);
        $cm = get_coursemodule_from_instance('scratch', $scratch->id);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->assertFalse(scratch_can_edit_submission($scratch, null, $context));
    }
}
