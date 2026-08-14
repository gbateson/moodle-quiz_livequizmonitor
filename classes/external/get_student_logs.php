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
 * External API for reading student logs.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Gordon Bateson <g.bateson@ucl.ac.uk>
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
use quiz_livequizmonitor\local\manager\supervision_scope_manager;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/report/log/lib.php');

/**
 * Returns log entries for one student in a course.
 */
class get_student_logs extends external_api {
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
        $targetuser = $DB->get_record('user', ['id' => $params['userid'], 'deleted' => 0], '*', MUST_EXIST);

        $context = context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('quiz/livequizmonitor:view', $context);

        if (!supervision_scope_manager::is_user_in_cohort((int) $params['userid'], $context, (int) $params['groupid'], $cm)) {
            throw new moodle_exception('error:usernotvisible', 'quiz_livequizmonitor');
        }

        // Verify user activity report access capabilities matching report/log/user.php.
        [$all, $today] = report_log_can_access_user_report($targetuser, $course);
        if (!$today && !$all) {
            throw new moodle_exception('nocapability', 'report_log');
        }

        // Select active log reader.
        $logreaders = get_log_manager()->get_readers('core\log\sql_reader');
        $logreader = reset($logreaders); // Get primary SQL log reader.

        $logs = [];
        if ($logreader) {
            // Table has an index on courseid, so include that first in the search params.
            $selectwhere = "courseid = :courseid AND contextid = :contextid AND userid = :userid";
            $params = [
                'courseid' => $course->id,
                'contextid' => $context->id,
                'userid' => $targetuser->id,
            ];
            // Alternatively, we could also use component = 'mod_quiz' AND objectid = $cm->instance.
            // To be really safe, we could add contextlevel = CONTEXT_MODULE AND contextinstanceid = $cm->instance.

            // Fetch first 100 logs for this user at this quiz.
            $events = $logreader->get_events_select($selectwhere, $params, 'timecreated DESC', 0, 100);

            // Cache the date format. It includes seconds but omits the year.
            $datefmt = get_string('strftimerecentaccurate', 'quiz_livequizmonitor');

            foreach ($events as $eventid => $event) {
                $eventdata = $event->get_data();
                $logs[] = [
                    'id' => (int) $eventid,
                    'timecreated' => (int) $eventdata['timecreated'],
                    'timeformatted' => userdate($eventdata['timecreated'], $datefmt),
                    'action' => (string) ($eventdata['action'] ?? ''),
                    'target' => (string) ($eventdata['target'] ?? ''),
                    'component' => (string) ($eventdata['component'] ?? ''),
                    'eventname' => (string) $event->get_name(),
                ];
            }
        }

        return [
            'logs' => $logs,
            'haslogs' => !empty($logs),
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
            'haslogs' => new external_value(PARAM_BOOL, 'Whether of not logs were found'),
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'logs' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Log entry ID'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                    'timeformatted' => new external_value(PARAM_TEXT, 'Formatted time'),
                    'action' => new external_value(PARAM_ALPHA, 'Action type'),
                    'target' => new external_value(PARAM_ALPHANUMEXT, 'Target entity'),
                    'component' => new external_value(PARAM_COMPONENT, 'Moodle component'),
                    'eventname' => new external_value(PARAM_NOTAGS, 'Human-readable event name'),
                ])
            ),
        ]);
    }
}
