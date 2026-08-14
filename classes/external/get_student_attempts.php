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
 * External API for reading student quiz attempts.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_livequizmonitor\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;
use moodle_exception;
use quiz_livequizmonitor\local\manager\student_note_manager;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Returns quiz attempts for one student.
 */
class get_student_attempts extends external_api {
    /**
     * Parameter description.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id of the quiz'),
            'userid' => new external_value(PARAM_INT, 'Target student user id'),
            'groupid' => new external_value(PARAM_INT, 'Group id filter', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the external function.
     *
     * @param int $cmid Course module id.
     * @param int $userid Target student user id.
     * @param int $groupid Group id filter.
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $groupid = 0): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'groupid' => $groupid,
        ]);

        if ((int) $params['userid'] <= 0) {
            throw new invalid_parameter_exception('Invalid userid');
        }

        $cm = get_coursemodule_from_id('quiz', $params['cmid'], 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $targetuser = $DB->get_record('user', [
            'id' => $params['userid'],
            'deleted' => 0,
        ], '*', MUST_EXIST);

        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('quiz/livequizmonitor:view', $context);

        if (!student_note_manager::is_user_in_cohort((int) $params['userid'], $context, (int) $params['groupid'], $cm)) {
            throw new moodle_exception('error:usernotvisible', 'quiz_livequizmonitor');
        }

        // Get this student's attempts for this quiz.
        $attempts = quiz_get_user_attempts(
            $quiz->id,
            $targetuser->id,
            'all',
            false
        );

        $attemptdata = [];

        // Display the most recent attempts first.
        $attempts = array_reverse($attempts);

        // Cache the date format string.
        $datefmt = get_string('strftimerecentaccurate', 'quiz_livequizmonitor');

        foreach ($attempts as $attempt) {
            $attemptdata[] = [
                'id' => (int) $attempt->id,
                'attempt' => (int) $attempt->attempt,
                'state' => \quiz_attempt_state_name($attempt->state),
                'timestart' => (int) $attempt->timestart,
                'timefinish' => (int) $attempt->timefinish,
                'timestartformatted' => userdate($attempt->timestart, $datefmt),
                'timefinishformatted' => $attempt->timefinish
                    ? userdate($attempt->timefinish, $datefmt)
                    : '',
            ];
        }

        return [
            'attempts' => $attemptdata,
            'hasattempts' => !empty($attemptdata),
            'courseid' => (int) $course->id,
            'userid' => (int) $targetuser->id,
        ];
    }

    /**
     * Return structure description.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hasattempts' => new external_value(PARAM_BOOL, 'Whether attempts were found'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Attempt ID'),
                    'attempt' => new external_value(PARAM_INT, 'Attempt number'),
                    'state' => new external_value(PARAM_TEXT, 'Attempt state'),
                    'timestart' => new external_value(PARAM_INT, 'Start timestamp'),
                    'timefinish' => new external_value(PARAM_INT, 'Finish timestamp'),
                    'timestartformatted' => new external_value(
                        PARAM_TEXT,
                        'Formatted start time'
                    ),
                    'timefinishformatted' => new external_value(
                        PARAM_TEXT,
                        'Formatted finish time'
                    ),
                ])
            ),
        ]);
    }
}
