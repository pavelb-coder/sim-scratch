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
 * Library functions for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * List of features supported in mod_scratch.
 *
 * @param string $feature FEATURE_xx constant
 * @return bool|null
 */
function scratch_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Add a new Scratch activity instance.
 *
 * @param stdClass $data
 * @param mod_scratch_mod_form|null $mform
 * @return int instance id
 */
function scratch_add_instance($data, $mform = null) {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = time();
    if (!isset($data->allowresubmit)) {
        $data->allowresubmit = 0;
    }
    $data->id = $DB->insert_record('scratch', $data);
    scratch_grade_item_update($data);
    return $data->id;
}

/**
 * Update an existing Scratch activity instance.
 *
 * @param stdClass $data
 * @param mod_scratch_mod_form|null $mform
 * @return bool
 */
function scratch_update_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;
    if (!isset($data->allowresubmit)) {
        $data->allowresubmit = 0;
    }
    $result = $DB->update_record('scratch', $data);
    scratch_grade_item_update($data);
    return $result;
}

/**
 * Delete a Scratch activity instance.
 *
 * @param int $id
 * @return bool
 */
function scratch_delete_instance($id) {
    global $DB;

    if (!$scratch = $DB->get_record('scratch', ['id' => $id])) {
        return false;
    }

    $cm = get_coursemodule_from_instance('scratch', $scratch->id, $scratch->course);
    if ($cm) {
        $context = context_module::instance($cm->id);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_scratch', 'intro');
        $submissions = $DB->get_records('scratch_submissions', ['scratchid' => $scratch->id]);
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'mod_scratch', 'submission', $submission->id);
        }
    }

    $DB->delete_records('scratch_submissions', ['scratchid' => $scratch->id]);
    $DB->delete_records('scratch', ['id' => $scratch->id]);
    scratch_grade_item_delete($scratch);
    return true;
}

/**
 * Create or update the grade item for a Scratch activity.
 *
 * @param stdClass $scratch
 * @param array|null $grades
 * @return int
 */
function scratch_grade_item_update($scratch, $grades = null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = ['itemname' => $scratch->name];
    if (!empty($scratch->cmidnumber)) {
        $params['idnumber'] = $scratch->cmidnumber;
    }
    if (empty($scratch->grade)) {
        // Grade 0 means "no grading" for this activity.
        $params['gradetype'] = GRADE_TYPE_NONE;
    } else {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax'] = $scratch->grade;
        $params['grademin'] = 0;
    }

    return grade_update('mod/scratch', $scratch->course, 'mod', 'scratch', $scratch->id, 0, $grades, $params);
}

/**
 * Delete the grade item for a Scratch activity.
 *
 * @param stdClass $scratch
 * @return int
 */
function scratch_grade_item_delete($scratch) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    $params = ['deleted' => 1];
    return grade_update('mod/scratch', $scratch->course, 'mod', 'scratch', $scratch->id, 0, null, $params);
}

/**
 * Push submission grades into the Moodle gradebook.
 *
 * @param stdClass $scratch
 * @param int $userid
 * @param bool $nullifnone
 * @return void
 */
function scratch_update_grades($scratch, $userid = 0, $nullifnone = true) {
    global $DB;

    scratch_grade_item_update($scratch);
    if (empty($scratch->grade)) {
        return;
    }

    $conditions = ['scratchid' => $scratch->id];
    if ($userid) {
        $conditions['userid'] = $userid;
    }
    $submissions = $DB->get_records('scratch_submissions', $conditions);
    $grades = [];
    foreach ($submissions as $submission) {
        if ($submission->grade === null) {
            continue;
        }
        $grade = new stdClass();
        $grade->userid = $submission->userid;
        $grade->rawgrade = $submission->grade;
        $grade->feedback = $submission->feedback;
        $grade->feedbackformat = $submission->feedbackformat;
        $grade->usermodified = $submission->grader;
        $grade->datesubmitted = $submission->timesubmitted;
        $grade->dategraded = $submission->timegraded;
        $grades[$submission->userid] = $grade;
    }

    if (!empty($grades)) {
        scratch_grade_item_update($scratch, $grades);
    } else if ($nullifnone) {
        scratch_grade_item_update($scratch, null);
    }
}

/**
 * Serve files associated with mod_scratch.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function scratch_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }
    require_login($course, false, $cm);

    $fs = get_file_storage();

    if ($filearea === 'intro') {
        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $file = $fs->get_file($context->id, 'mod_scratch', 'intro', $itemid, $filepath, $filename);
        if (!$file) {
            return false;
        }
        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    if ($filearea === 'submission') {
        $itemid = (int)array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        $submission = $DB->get_record('scratch_submissions', ['id' => $itemid, 'scratchid' => $cm->instance]);
        if (!$submission) {
            return false;
        }
        if ($submission->userid != $USER->id && !has_capability('mod/scratch:grade', $context)) {
            return false;
        }
        $file = $fs->get_file($context->id, 'mod_scratch', 'submission', $submission->id, $filepath, $filename);
        if (!$file) {
            return false;
        }
        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    return false;
}

/**
 * Display a user's grade outline.
 *
 * @param stdClass $user
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $scratch
 * @return stdClass|null
 */
function scratch_user_outline($user, $course, $cm, $scratch) {
    global $DB;
    $submission = $DB->get_record('scratch_submissions', ['scratchid' => $scratch->id, 'userid' => $user->id]);
    if (!$submission || $submission->grade === null) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('grade') . ': ' . $submission->grade;
    $result->time = $submission->timegraded;
    return $result;
}

/**
 * Print a user's grade information.
 *
 * @param stdClass $user
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $scratch
 * @return void
 */
function scratch_user_complete($user, $course, $cm, $scratch) {
    global $DB;
    $submission = $DB->get_record('scratch_submissions', ['scratchid' => $scratch->id, 'userid' => $user->id]);
    if ($submission && $submission->grade !== null) {
        echo get_string('grade') . ': ' . $submission->grade;
    }
}
