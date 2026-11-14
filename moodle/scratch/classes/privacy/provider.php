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
 * Privacy provider for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

namespace mod_scratch\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy subsystem implementation for mod_scratch.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe stored data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('scratch_submissions', [
            'userid' => 'privacy:metadata:scratch_submissions:userid',
            'status' => 'privacy:metadata:scratch_submissions:status',
            'title' => 'privacy:metadata:scratch_submissions:title',
            'filename' => 'privacy:metadata:scratch_submissions:filename',
            'filesize' => 'privacy:metadata:scratch_submissions:filesize',
            'timemodified' => 'privacy:metadata:scratch_submissions:timemodified',
            'timesubmitted' => 'privacy:metadata:scratch_submissions:timesubmitted',
            'grade' => 'privacy:metadata:scratch_submissions:grade',
            'feedback' => 'privacy:metadata:scratch_submissions:feedback',
            'grader' => 'privacy:metadata:scratch_submissions:grader',
            'timegraded' => 'privacy:metadata:scratch_submissions:timegraded',
        ], 'privacy:metadata:scratch_submissions');

        $collection->add_file_area('submission', 'privacy:metadata:files:submission');
        return $collection;
    }

    /**
     * Get contexts for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {scratch} s ON s.id = cm.instance
                  JOIN {scratch_submissions} ss ON ss.scratchid = s.id
                 WHERE ss.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'scratch',
            'userid' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('scratch', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $submission = $DB->get_record('scratch_submissions', ['scratchid' => $cm->instance, 'userid' => $user->id]);
            if (!$submission) {
                continue;
            }
            $data = (object)[
                'status' => $submission->status,
                'title' => $submission->title,
                'filename' => $submission->filename,
                'filesize' => $submission->filesize,
                'timemodified' => $submission->timemodified,
                'timesubmitted' => $submission->timesubmitted,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'timegraded' => $submission->timegraded,
            ];
            writer::with_context($context)->export_data([get_string('pluginname', 'mod_scratch')], $data);
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id, 'mod_scratch', 'submission', $submission->id, 'filename', false);
            foreach ($files as $file) {
                writer::with_context($context)->export_file([get_string('pluginname', 'mod_scratch')], $file);
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $cm = get_coursemodule_from_id('scratch', $context->instanceid);
        if (!$cm) {
            return;
        }
        $fs = get_file_storage();
        $submissions = $DB->get_records('scratch_submissions', ['scratchid' => $cm->instance]);
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'mod_scratch', 'submission', $submission->id);
        }
        $DB->delete_records('scratch_submissions', ['scratchid' => $cm->instance]);
    }

    /**
     * Get users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('scratch', $context->instanceid);
        if (!$cm) {
            return;
        }
        $sql = "SELECT userid FROM {scratch_submissions} WHERE scratchid = :scratchid";
        $userlist->add_from_sql('userid', $sql, ['scratchid' => $cm->instance]);
    }

    /**
     * Delete data for multiple users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $cm = get_coursemodule_from_id('scratch', $context->instanceid);
        if (!$cm) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['scratchid'] = $cm->instance;
        $submissions = $DB->get_records_select('scratch_submissions', "scratchid = :scratchid AND userid $insql", $params);
        $fs = get_file_storage();
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'mod_scratch', 'submission', $submission->id);
            $DB->delete_records('scratch_submissions', ['id' => $submission->id]);
        }
    }

    /**
     * Delete user data in contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cm = get_coursemodule_from_id('scratch', $context->instanceid);
            if (!$cm) {
                continue;
            }
            $submission = $DB->get_record('scratch_submissions', ['scratchid' => $cm->instance, 'userid' => $userid]);
            if ($submission) {
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_scratch', 'submission', $submission->id);
                $DB->delete_records('scratch_submissions', ['id' => $submission->id]);
            }
        }
    }
}
