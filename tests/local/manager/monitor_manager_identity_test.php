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

namespace quiz_livequizmonitor\local\manager;

use advanced_testcase;
use core_external\external_api;
use quiz_livequizmonitor\external\get_monitor_state;

/**
 * Tests for the student details (identity fields) in the monitor's rows.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quiz_livequizmonitor\local\manager\monitor_manager
 * @covers    \quiz_livequizmonitor\external\get_monitor_state
 */
final class monitor_manager_identity_test extends advanced_testcase {
    /**
     * Create a quiz with three students, a UPI profile field, and a teacher.
     *
     * The site admin has chosen to show user id, ID number, UPI and country.
     * Carol has no UPI.
     *
     * @return array [course, cm, quiz, teacher, students keyed by first name]
     */
    private function setup_quiz(): array {
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI']);
        set_config('identityfields', 'userid,idnumber,profile_field_upi,country', 'quiz_livequizmonitor');

        $course = $generator->create_course();
        $quiz = $generator->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);

        $students = [];
        foreach ([['Alice', 'S002', 'ALICE01'], ['Bob', 'S001', 'BOB0001'], ['Carol', 'S003', '']] as [$name, $idnumber, $upi]) {
            $students[$name] = $generator->create_and_enrol($course, 'student', [
                'firstname' => $name,
                'lastname' => 'Student',
                'idnumber' => $idnumber,
                'country' => 'GB',
                'profile_field_upi' => $upi,
            ]);
        }
        $teacher = $generator->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        return [$course, $cm, $quiz, $teacher, $students];
    }

    /**
     * Get the rows of the monitor, keyed by first name.
     *
     * @param array $setup From setup_quiz().
     * @param string $sortcolumn Column to sort by.
     * @param string $sortdirection Sort direction.
     * @return array
     */
    private function get_rows(array $setup, string $sortcolumn = 'status', string $sortdirection = 'asc'): array {
        [$course, $cm, $quiz] = $setup;
        $state = monitor_manager::get_state($course, $cm, $quiz, 0, $sortcolumn, $sortdirection);
        $rows = [];
        foreach ($state->students as $row) {
            $rows[explode(' ', $row->fullname)[0]] = $row;
        }
        return $rows;
    }

    /**
     * Each row has the chosen student details, in column order.
     */
    public function test_rows_have_student_details(): void {
        $this->resetAfterTest();
        $setup = $this->setup_quiz();
        $students = $setup[4];

        $rows = $this->get_rows($setup);

        $expected = [
            ['column' => 'userid', 'value' => (string) $students['Alice']->id],
            ['column' => 'idnumber', 'value' => 'S002'],
            ['column' => 'profile_field_upi', 'value' => 'ALICE01'],
            ['column' => 'country', 'value' => 'United Kingdom'],
        ];
        $this->assertSame($expected, $rows['Alice']->identity);
        $this->assertSame('', $rows['Carol']->identity[2]['value']);
    }

    /**
     * Searching for a student detail, such as a UPI, finds the student.
     */
    public function test_search_includes_student_details(): void {
        $this->resetAfterTest();
        $setup = $this->setup_quiz();

        $rows = $this->get_rows($setup);

        $this->assertStringContainsString('alice01', $rows['Alice']->searchtext);
        $this->assertStringNotContainsString('alice01', $rows['Bob']->searchtext);
    }

    /**
     * Sorting by a student detail sorts by its value, with students without a value last.
     *
     * When sorting descending, only the students with a value are checked: on main, students
     * without a value come first when descending, for every column (CTP-6786 changes that).
     */
    public function test_sort_by_student_detail(): void {
        $this->resetAfterTest();
        $setup = $this->setup_quiz();

        // Ascending: ALICE01, BOB0001, then Carol (no UPI).
        $rows = $this->get_rows($setup, 'profile_field_upi', 'asc');
        $this->assertSame(['Alice', 'Bob', 'Carol'], array_keys($rows));

        // Descending: BOB0001, then ALICE01.
        $rows = $this->get_rows($setup, 'profile_field_upi', 'desc');
        $this->assertSame(['Bob', 'Alice'], array_values(array_diff(array_keys($rows), ['Carol'])));

        // By ID number: S001 (Bob), S002 (Alice), S003 (Carol).
        $rows = $this->get_rows($setup, 'idnumber', 'asc');
        $this->assertSame(['Bob', 'Alice', 'Carol'], array_keys($rows));
    }

    /**
     * Users who can't view user identity don't get the student details.
     */
    public function test_no_student_details_without_viewuseridentity(): void {
        $this->resetAfterTest();
        $setup = $this->setup_quiz();
        [$course] = $setup;

        $teacherrole = get_archetype_roles('editingteacher');
        assign_capability(
            'moodle/site:viewuseridentity',
            CAP_PROHIBIT,
            reset($teacherrole)->id,
            \context_course::instance($course->id)
        );

        $rows = $this->get_rows($setup);

        $this->assertSame([], $rows['Alice']->identity);
        $this->assertStringNotContainsString('alice01', $rows['Alice']->searchtext);
    }

    /**
     * The web service returns the student details, and can sort by a profile field.
     */
    public function test_web_service_returns_student_details(): void {
        $this->resetAfterTest();
        $setup = $this->setup_quiz();
        [, $cm] = $setup;

        $result = get_monitor_state::execute($cm->id, 0, 'profile_field_upi', 'desc');
        $result = external_api::clean_returnvalue(get_monitor_state::execute_returns(), $result);

        $this->assertSame('profile_field_upi', $result['sortcolumn']);
        $students = [];
        foreach ($result['students'] as $student) {
            $students[explode(' ', $student['fullname'])[0]] = $student;
        }
        $this->assertSame(['Bob', 'Alice'], array_values(array_diff(array_keys($students), ['Carol'])));
        $this->assertSame(['column' => 'profile_field_upi', 'value' => 'BOB0001'], $students['Bob']['identity'][2]);
    }
}
