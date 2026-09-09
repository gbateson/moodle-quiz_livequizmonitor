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
 * Unit tests for overrides_manager.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_livequizmonitor\local\manager;

use advanced_testcase;
use context_module;

/**
 * Tests for overrides_manager user/group override lookups.
 *
 * @covers \quiz_livequizmonitor\local\manager\overrides_manager
 */
final class overrides_manager_test extends advanced_testcase {
    /**
     * Create a course, quiz, and two enrolled students.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: \stdClass, 4: \stdClass}
     *         course, quiz, cm, student1, student2
     */
    private function create_quiz_with_students(): array {
        $generator = $this->getDataGenerator();
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        $course = $generator->create_course();
        $quiz = $quizgenerator->create_instance(['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        $student1 = $generator->create_user();
        $student2 = $generator->create_user();
        $generator->enrol_user($student1->id, $course->id, 'student');
        $generator->enrol_user($student2->id, $course->id, 'student');

        return [$course, $quiz, $cm, $student1, $student2];
    }

    /**
     * A user override with a timelimit set is reported as "has override".
     */
    public function test_get_user_override_map_detects_timelimit_override(): void {
        global $DB;

        $this->resetAfterTest();
        [, $quiz, , $student1, $student2] = $this->create_quiz_with_students();

        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $student1->id,
            'timelimit' => 1800,
        ]);

        $map = overrides_manager::get_user_override_map((int) $quiz->id, [$student1->id, $student2->id]);

        $this->assertTrue($map[$student1->id]);
        $this->assertFalse($map[$student2->id]);
    }

    /**
     * Each of the five override value columns is independently detected.
     *
     * @dataProvider override_column_provider
     * @param string $column Override column to set.
     * @param mixed $value Value to store in that column.
     */
    public function test_get_user_override_map_detects_each_column(string $column, $value): void {
        global $DB;

        $this->resetAfterTest();
        [, $quiz, , $student1] = $this->create_quiz_with_students();

        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $student1->id,
            $column => $value,
        ]);

        $map = overrides_manager::get_user_override_map((int) $quiz->id, [$student1->id]);
        $this->assertTrue($map[$student1->id]);
    }

    /**
     * Data provider for override columns.
     *
     * @return array
     */
    public static function override_column_provider(): array {
        return [
            'timeopen' => ['timeopen', 1735689600],
            'timeclose' => ['timeclose', 1735776000],
            'timelimit' => ['timelimit', 900],
            'attempts' => ['attempts', 3],
            'password' => ['password', 'secret'],
        ];
    }

    /**
     * A quiz_overrides row where every value column is null does not count.
     *
     * This should not happen via core's override form, but overrides_manager
     * checks explicitly rather than relying on row-existence alone.
     */
    public function test_get_user_override_map_ignores_all_null_row(): void {
        global $DB;

        $this->resetAfterTest();
        [, $quiz, , $student1] = $this->create_quiz_with_students();

        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $student1->id,
        ]);

        $map = overrides_manager::get_user_override_map((int) $quiz->id, [$student1->id]);
        $this->assertFalse($map[$student1->id]);
    }

    /**
     * A time-related column (timeclose) is detected by the time-only map.
     */
    public function test_get_user_time_override_map_detects_time_columns(): void {
        global $DB;

        $this->resetAfterTest();
        [, $quiz, , $student1, $student2] = $this->create_quiz_with_students();

        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $student1->id,
            'timeclose' => time() + 3600,
        ]);

        $map = overrides_manager::get_user_time_override_map((int) $quiz->id, [$student1->id, $student2->id]);

        $this->assertTrue($map[$student1->id]);
        $this->assertFalse($map[$student2->id]);
    }

    /**
     * An attempts- or password-only override does NOT count as time-related.
     *
     * @dataProvider non_time_column_provider
     * @param string $column Non-time override column to set.
     * @param mixed $value Value to store in that column.
     */
    public function test_get_user_time_override_map_ignores_non_time_columns(string $column, $value): void {
        global $DB;

        $this->resetAfterTest();
        [, $quiz, , $student1] = $this->create_quiz_with_students();

        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'userid' => $student1->id,
            $column => $value,
        ]);

        // The general "has override" map still detects it...
        $anymap = overrides_manager::get_user_override_map((int) $quiz->id, [$student1->id]);
        $this->assertTrue($anymap[$student1->id]);

        // ...but the time-only map does not.
        $timemap = overrides_manager::get_user_time_override_map((int) $quiz->id, [$student1->id]);
        $this->assertFalse($timemap[$student1->id]);
    }

    /**
     * Data provider for non-time override columns.
     *
     * @return array
     */
    public static function non_time_column_provider(): array {
        return [
            'attempts' => ['attempts', 3],
            'password' => ['password', 'secret'],
        ];
    }

    /**
     * A group override does not appear in the user-override map, and vice versa.
     */
    public function test_user_and_group_overrides_are_independent(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        [$course, $quiz, , $student1] = $this->create_quiz_with_students();

        $group = $generator->create_group(['courseid' => $course->id]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id,
            'groupid' => $group->id,
            'timelimit' => 1200,
        ]);

        $usermap = overrides_manager::get_user_override_map((int) $quiz->id, [$student1->id]);
        $groupmap = overrides_manager::get_group_override_map((int) $quiz->id, [$group->id]);

        $this->assertFalse($usermap[$student1->id]);
        $this->assertTrue($groupmap[$group->id]);
    }

    /**
     * Empty id lists return an empty (but defined) map without querying the DB.
     */
    public function test_empty_ids_return_empty_map(): void {
        $this->resetAfterTest();
        [, $quiz] = $this->create_quiz_with_students();

        $this->assertSame([], overrides_manager::get_user_override_map((int) $quiz->id, []));
        $this->assertSame([], overrides_manager::get_group_override_map((int) $quiz->id, []));
    }

    /**
     * Capability gate: only users with mod/quiz:manageoverrides may view overrides.
     */
    public function test_user_can_view_overrides_respects_capability(): void {
        $this->resetAfterTest();
        [$course, $quiz, $cm, $student1] = $this->create_quiz_with_students();
        $context = context_module::instance($cm->id);

        $this->setUser($student1);
        $this->assertFalse(overrides_manager::user_can_view_overrides($context));

        $this->setAdminUser();
        $this->assertTrue(overrides_manager::user_can_view_overrides($context));
    }
}
