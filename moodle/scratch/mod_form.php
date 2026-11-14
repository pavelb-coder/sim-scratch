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
 * Activity configuration form for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Scratch activity settings form.
 */
class mod_scratch_mod_form extends moodleform_mod {

    /**
     * Form definition.
     */
    public function definition() {
        global $CFG, $COURSE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('name'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $this->standard_intro_elements();

        $mform->addElement('header', 'scratchsettings', get_string('settings', 'mod_scratch'));

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'mod_scratch'), ['optional' => true]);
        $mform->addHelpButton('duedate', 'duedate', 'mod_scratch');

        $mform->addElement('date_time_selector', 'cutoffdate', get_string('cutoffdate', 'mod_scratch'), ['optional' => true]);
        $mform->addHelpButton('cutoffdate', 'cutoffdate', 'mod_scratch');

        $options = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes);
        $mform->addElement('select', 'maxbytes', get_string('maxbytes', 'mod_scratch'), $options);
        $mform->setDefault('maxbytes', 10485760);
        $mform->addHelpButton('maxbytes', 'maxbytes', 'mod_scratch');

        $mform->addElement('select', 'grade', get_string('grademax', 'grades'), range(0, 100));
        $mform->setDefault('grade', 100);

        $mform->addElement('advcheckbox', 'allowresubmit', get_string('allowresubmit', 'mod_scratch'));
        $mform->setDefault('allowresubmit', 1);
        $mform->addHelpButton('allowresubmit', 'allowresubmit', 'mod_scratch');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate dates.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['duedate']) && !empty($data['cutoffdate']) && $data['cutoffdate'] < $data['duedate']) {
            $errors['cutoffdate'] = get_string('cutoffdatebeforeduedate', 'mod_scratch');
        }
        return $errors;
    }
}
