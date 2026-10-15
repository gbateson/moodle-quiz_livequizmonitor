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
 * Bootstrap separate-groups course for security / authorization manual testing.
 *
 * Run from Moodle root:
 *   php mod/quiz/report/livequizmonitor/cli/setup_security_test_data.php
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/testing/classes/util.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once(__DIR__ . '/demo_lib.php');
require_once(__DIR__ . '/setup_lib.php');

use core\session\manager;

/** @var string Security test course shortname. */
const SECURITY_COURSE_SHORTNAME = 'LQMSEC';
/** @var string Security test quiz name. */
const SECURITY_QUIZ_NAME = 'Security Test Quiz';
/** @var string Username for teacher A in the security fixture. */
const SECURITY_TEACHER_USERNAME = 'teachera';
/** @var string Username for the teacher without any group in the security fixture. */
const SECURITY_TEACHER_NOGROUP_USERNAME = 'teachernogroup';
/** @var string Username for student A in the security fixture. */
const SECURITY_STUDENT_A = 'studenta';
/** @var string Username for student B in the security fixture. */
const SECURITY_STUDENT_B = 'studentb';

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'quiet' => false,
        'force' => false,
        'password' => false,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help'])) {
    echo <<<EOF
Create separate-groups course for security authorization manual testing.

Options:
  --force   Delete and recreate course if it already exists
  --password=PW  Password for newly created accounts (default: random)
  --quiet   Suppress informational output
  -h, --help  Print this help

Requires \$CFG->quiz_livequizmonitor_devtools = true; in config.php (development sites only).

Example:
  php mod/quiz/report/livequizmonitor/cli/setup_security_test_data.php --force

EOF;
    exit(0);
}

demo_require_devtools();

$quiet = !empty($options['quiet']);
$force = !empty($options['force']);
$password = demo_resolve_password($options['password']);

manager::set_user(get_admin());

global $DB;

$existing = $DB->get_record('course', ['shortname' => SECURITY_COURSE_SHORTNAME], 'id', IGNORE_MISSING);
if ($existing) {
    if (!$force) {
        cli_error('Course ' . SECURITY_COURSE_SHORTNAME . ' already exists. Re-run with --force to recreate.');
    }
    if (!$quiet) {
        mtrace('Deleting existing course ' . SECURITY_COURSE_SHORTNAME . ' (id ' . $existing->id . ')');
    }
    delete_course($existing->id, false);
}

$generator = testing_util::get_data_generator();
/** @var mod_quiz_generator $quizgenerator */
$quizgenerator = $generator->get_plugin_generator('mod_quiz');

$course = $generator->create_course([
    'shortname' => SECURITY_COURSE_SHORTNAME,
    'fullname' => 'LQM Security Authorization Test',
    'summary' => 'Separate groups course for live quiz monitor security testing.',
    'format' => 'topics',
    'numsections' => 1,
    'groupmode' => SEPARATEGROUPS,
    'groupmodeforce' => 1,
]);

$createdcount = 0;
$teacher = demo_get_user(SECURITY_TEACHER_USERNAME, $password, $created);
$createdcount += (int) $created;
$teachernogroup = demo_get_user(SECURITY_TEACHER_NOGROUP_USERNAME, $password, $created);
$createdcount += (int) $created;
$studenta = demo_get_user(SECURITY_STUDENT_A, $password, $created);
$createdcount += (int) $created;
$studentb = demo_get_user(SECURITY_STUDENT_B, $password, $created);
$createdcount += (int) $created;

$generator->enrol_user($teacher->id, $course->id, 'editingteacher');
$generator->enrol_user($teachernogroup->id, $course->id, 'editingteacher');
$generator->enrol_user($studenta->id, $course->id, 'student');
$generator->enrol_user($studentb->id, $course->id, 'student');

$context = \context_course::instance($course->id);
$roleid = (int) $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $roleid, $context->id, true);

$groupa = $generator->create_group(['courseid' => $course->id, 'name' => 'Group A']);
$groupb = $generator->create_group(['courseid' => $course->id, 'name' => 'Group B']);
groups_add_member($groupa, $teacher);
groups_add_member($groupa, $studenta);
groups_add_member($groupb, $studentb);

$quiz = $quizgenerator->create_instance([
    'course' => $course->id,
    'name' => SECURITY_QUIZ_NAME,
    'intro' => 'Quiz for security authorization manual testing.',
    'groupmode' => SEPARATEGROUPS,
    'sumgrades' => 1,
    'grade' => 10,
    'preferredbehaviour' => 'deferredfeedback',
    'attempts' => 0,
    'timelimit' => 3600,
    'timeopen' => 0,
    'timeclose' => 0,
    'questionsperpage' => 1,
]);

$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
$modcontext = \context_module::instance($cm->id);
setup_add_demo_questions($quiz, $modcontext->id);

$courseurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
$reporturl = (new moodle_url('/mod/quiz/report.php', [
    'id' => $cm->id,
    'mode' => 'livequizmonitor',
]))->out(false);

if (!$quiet) {
    echo PHP_EOL;
    mtrace('Security test data ready.');
    mtrace('Course:   ' . SECURITY_COURSE_SHORTNAME . ' (id ' . $course->id . ')');
    mtrace('Groups:   Group A (teacher + studenta), Group B (studentb)');
    mtrace('Teacher:  ' . SECURITY_TEACHER_USERNAME . ' (Group A only, no accessallgroups)');
    mtrace('Teacher:  ' . SECURITY_TEACHER_NOGROUP_USERNAME . ' (no group, no accessallgroups)');
    mtrace('Students: ' . SECURITY_STUDENT_A . ' (Group A), ' . SECURITY_STUDENT_B . ' (Group B)');
    demo_print_password($password, $createdcount, 4);
    mtrace('Quiz:     ' . SECURITY_QUIZ_NAME . ' (cmid ' . $cm->id . ')');
    mtrace('URLs:');
    mtrace('  ' . $courseurl);
    mtrace('  ' . $reporturl);
    echo PHP_EOL;
}
