<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * English language strings for quiz_livequizmonitor.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['emptycohort'] = 'No eligible students were found for this quiz.';
$string['error:groupnotvisible'] = 'You do not have permission to view that group in this monitor.';
$string['error:maxdurationopenclosetime'] = 'For this setting, you must specify both an open time and a close time.';
$string['error:maxdurationtimelimit'] = 'For this setting, you must specify a time limit.';
$string['error:missingtimeclose'] = 'Please specify a date and time to close the quiz.';
$string['error:missingtimelimit'] = 'Please specify a time limit.';
$string['error:missingtimeopen'] = 'Please specify a date and time to open the quiz.';
$string['error:usernotvisible'] = 'The selected student is not visible in this monitor view.';
$string['extend:addtime'] = 'Add time';
$string['extend:bulklabel'] = 'Extend time';
$string['extend:confirm'] = 'Confirm — add {$a} min';
$string['extend:errornoinprogress'] = 'No students are currently in progress.';
$string['extend:errornopermission'] = 'You do not have permission to extend quiz time.';
$string['extend:mineach'] = '+{$a} min each';
$string['extend:modalbodybulk'] = 'Add time to all {$a->count} students currently taking the quiz. The quiz close time stays the same.';
$string['extend:modalbodyindividual'] = 'Grant {$a->name} extra time to finish their attempt. They will be notified instantly.';
$string['extend:modaltitle'] = 'Extend quiz time';
$string['extend:newdeadlinebulk'] = 'New deadline for {$a->count} students';
$string['extend:newdeadlineindividual'] = 'New deadline for this student';
$string['extend:rowaction'] = 'Extend time';
$string['extend:successbulk'] = 'Added {$a->minutes} minutes for {$a->count} students.';
$string['extend:successindividual'] = 'Added {$a->minutes} minutes for {$a->name}.';
$string['filter:all'] = 'All';
$string['filter:clear'] = 'Clear filters';
$string['filter:empty'] = 'No students match the current filters.';
$string['filter:searchplaceholder'] = 'Search students…';
$string['filter:toolbarlabel'] = 'Filter students by status';
$string['invalidminutes'] = 'Invalid extension duration.';
$string['invalidscope'] = 'Invalid extend scope.';
$string['lastupdated'] = 'Last updated: {$a}';
$string['liveindicator'] = 'Live';
$string['livequizmonitor'] = 'Live monitor';
$string['livequizmonitor:view'] = 'View the live quiz monitor report';
$string['livequizmonitorreport'] = 'Live monitor';
$string['maxduration'] = 'Maximum duration';
$string['maxduration_help'] = 'This setting specifies how to determine the maximum allowable duration of an attempt.

- By default, it will be set to the quiz time limit.
- If no time limit is defined, it will be calculated as the difference between the quiz open and close times.
- You can also explicitly choose to use either the quiz time limit or the difference between the open and close times.';
$string['message:timeextendedbody'] = 'Your teacher added {$a->minutes} minutes to your attempt for the quiz "{$a->quizname}".';
$string['message:timeextendedsmall'] = '+{$a} min added to your quiz attempt';
$string['message:timeextendedsubject'] = 'Extra time granted for {$a}';
$string['messageprovider:timeextended'] = 'Quiz time extended notification';
$string['noattempttoextend'] = 'No in-progress attempt to extend for {$a}.';
$string['noextendablelimit'] = 'Cannot extend time for {$a} — no time limit applies.';
$string['notes:addlabel'] = 'Add note';
$string['notes:cancel'] = 'Cancel';
$string['notes:deleted'] = 'Note removed.';
$string['notes:editlabel'] = 'Edit note';
$string['notes:errorload'] = 'Could not load note.';
$string['notes:errorsave'] = 'Could not save note.';
$string['notes:errortoolong'] = 'Note must be 2000 characters or fewer.';
$string['notes:modalbody'] = 'Add a supervision note for this student.';
$string['notes:modaltitle'] = 'Note for {$a}';
$string['notes:save'] = 'Save';
$string['notes:saved'] = 'Note saved.';
$string['onesession:blockedflag'] = 'Blocked by concurrent session rule';
$string['onesession:errnotinprogress'] = 'Only in-progress attempts can be unblocked.';
$string['onesession:notactive'] = 'Concurrent session rule is not enabled for this quiz.';
$string['onesession:unblockcancel'] = 'Cancel';
$string['onesession:unblockconfirm'] = 'Unblock';
$string['onesession:unblocklabel'] = 'Unblock user';
$string['onesession:unblockmodalbody'] = 'Allow this student to continue the quiz attempt on another device or browser.';
$string['onesession:unblockmodaltitle'] = 'Unblock {$a}';
$string['onesession:unblocksuccess'] = 'Student unblocked.';
$string['pluginname'] = 'Live quiz monitor';
$string['privacy:metadata'] = 'The live quiz monitor report stores supervision notes linked to students and quizzes.';
$string['privacy:metadata:notes'] = 'Student supervision notes written from the live monitor report.';
$string['privacy:metadata:notes:content'] = 'The note text.';
$string['privacy:metadata:notes:timemodified'] = 'When the note was last modified.';
$string['privacy:metadata:notes:userid'] = 'The student the note is about.';
$string['privacy:metadata:notes:usermodified'] = 'The user who last edited the note.';
$string['progressanswered'] = '{$a->answered} of {$a->total} answered';
$string['staleindicator'] = 'Updates paused — showing last known data';
$string['status:completed'] = 'Completed';
$string['status:inprogress'] = 'In progress';
$string['status:notstarted'] = 'Not started';
$string['summary:completed'] = 'Completed';
$string['summary:inprogress'] = 'In progress';
$string['summary:notstarted'] = 'Not started';
$string['table:actions'] = 'Actions';
$string['table:email'] = 'Email';
$string['table:progress'] = 'Progress';
$string['table:status'] = 'Status';
$string['table:student'] = 'Student';
$string['table:timeremaining'] = 'Time left';
$string['timeup'] = 'Time up';
$string['useopenclosetimes'] = 'Use open/close times';
$string['usetimelimit'] = 'Use time limit';
