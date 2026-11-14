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
 * English strings for mod_scratch.
 *
 * @package    mod_scratch
 * @copyright  2026 Scratch Code
 * @license    http://www.gnu.org/licenses/agpl-3.0.html GNU AGPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Scratch project';
$string['modulename'] = 'Scratch project';
$string['modulenameplural'] = 'Scratch projects';
$string['scratch:addinstance'] = 'Add a new Scratch project activity';
$string['scratch:view'] = 'View Scratch project activity';
$string['scratch:submit'] = 'Submit Scratch projects';
$string['scratch:grade'] = 'Grade Scratch projects';
$string['scratch:manage'] = 'Manage Scratch projects';

$string['settings'] = 'Scratch settings';
$string['duedate'] = 'Due date';
$string['duedate_help'] = 'The recommended date for students to submit their Scratch project. Submissions after this date are marked as late but may still be accepted until the cut-off date.';
$string['cutoffdate'] = 'Cut-off date';
$string['cutoffdate_help'] = 'After this date students can no longer edit or submit the project.';
$string['cutoffdatebeforeduedate'] = 'The cut-off date cannot be before the due date.';
$string['maxbytes'] = 'Maximum project size';
$string['maxbytes_help'] = 'Maximum size of the uploaded .sb3 project file.';
$string['allowresubmit'] = 'Allow resubmission';
$string['allowresubmit_help'] = 'If enabled, students can edit and submit the project again after it has been submitted, until the cut-off date.';

$string['status'] = 'Status';
$string['statusnotsubmitted'] = 'Not submitted';
$string['statussubmitted'] = 'Submitted';
$string['statusdraft'] = 'Draft';
$string['openeditor'] = 'Open Scratch editor';
$string['viewproject'] = 'View project';
$string['downloadproject'] = 'Download .sb3';
$string['submissions'] = 'Submissions';
$string['nosubmissions'] = 'There are no submissions yet.';
$string['nosubmissionyet'] = 'This student has not uploaded a project yet.';
$string['gradeverb'] = 'Grade';
$string['gradesaved'] = 'Grade saved.';
$string['gradingdisabled'] = 'Grading is disabled for this activity (maximum grade is 0).';
$string['feedback'] = 'Feedback';
$string['savedraft'] = 'Save draft';
$string['submitproject'] = 'Submit project';
$string['confirmsubmit'] = 'Submit this project to the teacher?';
$string['backtoactivity'] = 'Back to activity';
$string['readonlyproject'] = 'Read-only preview';
$string['loading'] = 'Loading Scratch…';
$string['saving'] = 'Saving…';
$string['saved'] = 'Saved in Moodle';
$string['saveerror'] = 'Save failed';
$string['noinstances'] = 'There are no Scratch project activities in this course.';

$string['errorinvalidfile'] = 'The file is not a valid Scratch .sb3 project.';
$string['errorfiletoolarge'] = 'The project file is larger than the allowed maximum.';
$string['errornotallowed'] = 'You are not allowed to edit or submit this project.';
$string['invalidaction'] = 'Invalid action.';

$string['eventsubmissionsubmitted'] = 'Scratch project submitted';
$string['eventsubmissiongraded'] = 'Scratch project graded';

$string['privacy:metadata:scratch_submissions'] = 'Scratch project submissions, including project files, status, grades and feedback.';
$string['privacy:metadata:scratch_submissions:userid'] = 'The ID of the user who submitted the project.';
$string['privacy:metadata:scratch_submissions:status'] = 'The submission status (draft or submitted).';
$string['privacy:metadata:scratch_submissions:title'] = 'The title of the Scratch project.';
$string['privacy:metadata:scratch_submissions:filename'] = 'The file name of the submitted .sb3 project.';
$string['privacy:metadata:scratch_submissions:filesize'] = 'The size of the submitted project file.';
$string['privacy:metadata:scratch_submissions:timemodified'] = 'The time the submission was last modified.';
$string['privacy:metadata:scratch_submissions:timesubmitted'] = 'The time the project was submitted.';
$string['privacy:metadata:scratch_submissions:grade'] = 'The grade awarded for the project.';
$string['privacy:metadata:scratch_submissions:feedback'] = 'Feedback provided by the teacher.';
$string['privacy:metadata:scratch_submissions:grader'] = 'The ID of the user who graded the project.';
$string['privacy:metadata:scratch_submissions:timegraded'] = 'The time the project was graded.';
$string['privacy:metadata:files:submission'] = 'Submitted Scratch .sb3 project files.';
