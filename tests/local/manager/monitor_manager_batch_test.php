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
 * Batch loading tests for monitor_manager::get_state().
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_livequizmonitor\local\manager;

use advanced_testcase;
use mod_quiz\quiz_attempt;

/**
 * Tests that get_state() loads attempt progress in bulk and matches core's per-attempt results.
 *
 * @covers \quiz_livequizmonitor\local\manager\monitor_manager
 */
final class monitor_manager_batch_test extends advanced_testcase {
    /**
     * Create a quiz with two short-answer questions, a description and a true/false question.
     *
     * Slots: 1 shortanswer, 2 truefalse, 3 description, 4 shortanswer.
     *
     * @param \stdClass $course Course record.
     * @param array $settings Extra quiz settings.
     * @return array{0: \stdClass, 1: \stdClass, 2: \mod_quiz_generator}
     */
    private function create_mixed_quiz(\stdClass $course, array $settings = []): array {
        $generator = $this->getDataGenerator();
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');
        $questiongenerator = $generator->get_plugin_generator('core_question');

        $quiz = $quizgenerator->create_instance(array_merge([
            'course' => $course->id,
            'grade' => 100,
            'sumgrades' => 3,
            'timelimit' => 1800,
            'preferredbehaviour' => 'deferredfeedback',
        ], $settings));
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        $cat = $questiongenerator->create_question_category(['contextid' => \context_module::instance($cm->id)->id]);
        foreach (['shortanswer', 'truefalse', 'description', 'shortanswer'] as $qtype) {
            $question = $questiongenerator->create_question($qtype, null, ['category' => $cat->id]);
            quiz_add_quiz_question($question->id, $quiz);
        }

        return [$quiz, $cm, $quizgenerator];
    }

    /**
     * Start an attempt as the given student.
     *
     * @param \mod_quiz_generator $quizgenerator Quiz generator.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $user Student.
     * @return \stdClass Attempt record.
     */
    private function start_attempt($quizgenerator, \stdClass $quiz, \stdClass $user): \stdClass {
        $this->setUser($user);
        return $quizgenerator->create_attempt($quiz->id, $user->id);
    }

    /**
     * Backdate every question step of an attempt so it reads as idle.
     *
     * @param int $attemptid Attempt id.
     * @param int $minutesago Minutes to backdate.
     */
    private function backdate_all_activity(int $attemptid, int $minutesago): void {
        global $DB;

        $usageid = $DB->get_field('quiz_attempts', 'uniqueid', ['id' => $attemptid], MUST_EXIST);
        $DB->execute(
            'UPDATE {question_attempt_steps}
                SET timecreated = :time
              WHERE questionattemptid IN (SELECT id FROM {question_attempts} WHERE questionusageid = :usageid)',
            ['time' => time() - $minutesago * 60, 'usageid' => $usageid]
        );
    }

    /**
     * Per-attempt reference values computed the way the monitor did before batching.
     *
     * @param \stdClass $attempt Attempt record.
     * @param int $now Current time.
     * @return array Reference values: answered, attemptendat, timeremaining, idle.
     */
    private function reference_values(\stdClass $attempt, int $now): array {
        $attemptobj = quiz_attempt::create((int) $attempt->id);

        $real = 0;
        foreach ($attemptobj->get_slots() as $slot) {
            if ($attemptobj->is_real_question($slot)) {
                $real++;
            }
        }
        $values = [
            'answered' => $real - $attemptobj->get_number_of_unanswered_questions(),
            'attemptendat' => null,
            'timeremaining' => null,
            'idle' => false,
        ];

        if (!monitor_manager::is_active_attempt_state($attempt->state)) {
            return $values;
        }

        $accessmanager = $attemptobj->get_access_manager($now);
        $timeremaining = $accessmanager->get_time_left_display($attempt, $now);
        $endtime = $accessmanager->get_end_time($attempt);
        if ($endtime !== false) {
            $values['attemptendat'] = (int) $endtime;
            if ($timeremaining === false && $endtime > $now) {
                $timeremaining = $endtime - $now;
            }
        }
        if ($timeremaining !== false) {
            $values['timeremaining'] = max(0, (int) $timeremaining);
        }

        $values['idle'] = true;
        foreach ($attemptobj->get_slots() as $slot) {
            if ($attemptobj->get_question_action_time($slot) > $now - monitor_manager::IDLE_THRESHOLD_SECONDS) {
                $values['idle'] = false;
            }
        }

        return $values;
    }

    /**
     * Assert every row with an attempt matches the per-attempt reference.
     *
     * @param \stdClass $state Monitor state.
     * @param array $attempts Attempt records keyed by user id.
     * @param int $now Time the state was built.
     */
    private function assert_rows_match_reference(\stdClass $state, array $attempts, int $now): void {
        global $DB;

        foreach ($state->students as $row) {
            if (!isset($attempts[$row->userid])) {
                $this->assertSame(0, $row->progressanswered, "User {$row->userid}");
                continue;
            }
            $attempt = $DB->get_record('quiz_attempts', ['id' => $attempts[$row->userid]->id], '*', MUST_EXIST);
            $expected = $this->reference_values($attempt, $now);
            $label = "User {$row->fullname}";

            $this->assertSame($expected['answered'], $row->progressanswered, "$label: answered");
            $this->assertSame($expected['attemptendat'], $row->attemptendat, "$label: attemptendat");
            if ($expected['timeremaining'] === null) {
                $this->assertNull($row->timeremaining, "$label: timeremaining");
            } else {
                $this->assertEqualsWithDelta($expected['timeremaining'], $row->timeremaining, 2, "$label: timeremaining");
            }
            if (monitor_manager::is_active_attempt_state($attempt->state)) {
                $expectedstatus = $expected['idle'] ? monitor_manager::STATUS_IDLE : monitor_manager::STATUS_INPROGRESS;
                $this->assertSame($expectedstatus, $row->status, "$label: status");
            }
        }
    }

    /**
     * Batch results equal core's per-attempt results across answer states, limits and overrides.
     */
    public function test_get_state_matches_per_attempt_reference(): void {
        global $DB;

        $this->resetAfterTest();

        $now = time();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        [$quiz, $cm, $quizgenerator] = $this->create_mixed_quiz($course, ['timeclose' => $now + 7200]);

        $names = ['notstarted', 'fresh', 'onesaved', 'allsaved', 'cleared', 'finished', 'useroverride',
            'groupoverride', 'idle', 'overdue'];
        $users = [];
        foreach ($names as $name) {
            $users[$name] = $generator->create_and_enrol($course, 'student', ['firstname' => $name, 'lastname' => 'Test']);
        }

        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group, $users['groupoverride']);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id, 'groupid' => $group->id, 'timeclose' => $now + 900,
        ]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id, 'userid' => $users['useroverride']->id, 'timelimit' => 3600,
        ]);

        $attempts = [];
        foreach (array_slice($names, 1) as $name) {
            $attempts[$users[$name]->id] = $this->start_attempt($quizgenerator, $quiz, $users[$name]);
        }

        $sa = 'frog';
        $id = fn(string $name) => (int) $attempts[$users[$name]->id]->id;

        $this->setUser($users['onesaved']);
        $quizgenerator->submit_responses($id('onesaved'), [1 => $sa], false, false);

        $this->setUser($users['allsaved']);
        $quizgenerator->submit_responses($id('allsaved'), [1 => $sa, 2 => 'True', 4 => $sa], false, false);

        $this->setUser($users['cleared']);
        $quizgenerator->submit_responses($id('cleared'), [1 => $sa], false, false);
        $quizgenerator->submit_responses($id('cleared'), [1 => ''], false, false);

        $this->setUser($users['finished']);
        $quizgenerator->submit_responses($id('finished'), [2 => 'False'], false, true);

        $this->setUser($users['useroverride']);
        $quizgenerator->submit_responses($id('useroverride'), [4 => $sa], false, false);

        $this->backdate_all_activity($id('idle'), 10);

        $DB->update_record('quiz_attempts', (object) [
            'id' => $id('overdue'),
            'state' => quiz_attempt::OVERDUE,
            'timestart' => $now - 3600,
        ]);

        $this->setAdminUser();
        $state = monitor_manager::get_state($course, $cm, $quiz, 0);

        $this->assertCount(count($names), $state->students);
        $this->assert_rows_match_reference($state, $attempts, time());

        // Spot checks that the scenarios really differ.
        $rows = [];
        foreach ($state->students as $row) {
            $rows[$row->fullname] = $row;
        }
        $this->assertSame(0, $rows['fresh Test']->progressanswered);
        $this->assertSame(1, $rows['onesaved Test']->progressanswered);
        $this->assertSame(3, $rows['allsaved Test']->progressanswered);
        $this->assertSame(0, $rows['cleared Test']->progressanswered);
        $this->assertSame(monitor_manager::STATUS_COMPLETED, $rows['finished Test']->status);
        $this->assertSame(monitor_manager::STATUS_IDLE, $rows['idle Test']->status);
        $this->assertSame($now + 900, $rows['groupoverride Test']->attemptendat);
        $this->assertGreaterThan($rows['fresh Test']->attemptendat, $rows['useroverride Test']->attemptendat);
    }

    /**
     * Immediate feedback with an incomplete check (invalid state) counts as unanswered, like core.
     */
    public function test_get_state_matches_reference_for_immediate_feedback(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        [$quiz, $cm, $quizgenerator] = $this->create_mixed_quiz($course, ['preferredbehaviour' => 'immediatefeedback']);

        $checked = $generator->create_and_enrol($course, 'student', ['firstname' => 'checked']);
        $invalid = $generator->create_and_enrol($course, 'student', ['firstname' => 'invalid']);

        $attempts = [
            $checked->id => $this->start_attempt($quizgenerator, $quiz, $checked),
            $invalid->id => $this->start_attempt($quizgenerator, $quiz, $invalid),
        ];

        $this->setUser($checked);
        $quizgenerator->submit_responses($attempts[$checked->id]->id, [1 => 'frog'], true, false);
        $this->setUser($invalid);
        $quizgenerator->submit_responses($attempts[$invalid->id]->id, [1 => ''], true, false);

        $this->setAdminUser();
        $state = monitor_manager::get_state($course, $cm, $quiz, 0);

        $this->assert_rows_match_reference($state, $attempts, time());
    }

    /**
     * Build a course with the given number of in-progress students (each answered one question).
     *
     * @param int $students Number of students.
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass}
     */
    private function create_inprogress_cohort(int $students): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        [$quiz, $cm, $quizgenerator] = $this->create_mixed_quiz($course);

        for ($i = 0; $i < $students; $i++) {
            $user = $generator->create_and_enrol($course, 'student');
            $attempt = $this->start_attempt($quizgenerator, $quiz, $user);
            $quizgenerator->submit_responses($attempt->id, [1 => 'frog'], false, false);
        }

        return [$course, $cm, $quiz];
    }

    /**
     * Count DB queries of one get_state() call (after a warm-up call).
     *
     * @param \stdClass $course Course.
     * @param \stdClass $cm Course module.
     * @param \stdClass $quiz Quiz.
     * @return int
     */
    private function count_get_state_queries(\stdClass $course, \stdClass $cm, \stdClass $quiz): int {
        global $DB;

        monitor_manager::get_state($course, $cm, $quiz, 0);

        $before = $DB->perf_get_queries();
        monitor_manager::get_state($course, $cm, $quiz, 0);
        return $DB->perf_get_queries() - $before;
    }

    /**
     * The number of queries per poll does not grow with the number of attempts (no N+1).
     */
    public function test_get_state_query_count_independent_of_attempts(): void {
        $this->resetAfterTest();

        [$smallcourse, $smallcm, $smallquiz] = $this->create_inprogress_cohort(3);
        [$largecourse, $largecm, $largequiz] = $this->create_inprogress_cohort(15);

        $this->setAdminUser();
        $small = $this->count_get_state_queries($smallcourse, $smallcm, $smallquiz);
        $large = $this->count_get_state_queries($largecourse, $largecm, $largequiz);

        $this->assertSame($small, $large, "Queries for 3 attempts: $small, for 15 attempts: $large");
    }
}
