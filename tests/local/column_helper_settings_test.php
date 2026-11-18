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

namespace quiz_livequizmonitor\local;

use advanced_testcase;
use context_module;

/**
 * Tests for the settings that choose the monitor's columns.
 *
 * The site admin chooses the identity fields from the user id and the fields in Moodle's
 * "Show user identity" setting ($CFG->showuseridentity). Access to those fields is decided by
 * \core_user\fields::get_identity_fields(), which applies Moodle's access rules.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \quiz_livequizmonitor\local\column_helper
 */
final class column_helper_settings_test extends advanced_testcase {
    /**
     * Create a course with a quiz and an editing teacher, and log in as the teacher.
     *
     * @return array [context_module $context, stdClass $teacher, stdClass $course]
     */
    private function setup_teacher(): array {
        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        return [context_module::instance($quiz->cmid), $teacher, $course];
    }

    /**
     * Stop editing teachers having a capability in a course.
     *
     * @param string $capability
     * @param \stdClass $course
     */
    private function prohibit_for_teachers(string $capability, \stdClass $course): void {
        $teacherrole = get_archetype_roles('editingteacher');
        assign_capability($capability, CAP_PROHIBIT, reset($teacherrole)->id, \context_course::instance($course->id));
    }

    /**
     * With Moodle's default "Show user identity" (email) and no saved settings,
     * the table has the same columns as before the settings existed.
     */
    public function test_default_columns(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->showuseridentity = 'email';
        [$context] = $this->setup_teacher();

        $expected = ['status', 'student', 'email', 'progress', 'timeremaining', 'actions'];
        $this->assertSame($expected, array_keys(column_helper::get_columns($context)));
    }

    /**
     * The admin can choose the user id, and any of the fields in "Show user identity".
     */
    public function test_identity_column_choices(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI', 'param2' => 50,
        ]);
        $CFG->showuseridentity = 'idnumber,email,profile_field_upi';

        $choices = column_helper::get_identity_column_choices();

        $this->assertSame(['userid', 'idnumber', 'email', 'profile_field_upi'], array_keys($choices));
        $this->assertSame('UPI', $choices['profile_field_upi']);
    }

    /**
     * The chosen identity fields appear after the name, with the user id first.
     * Fields in "Show user identity" that weren't chosen don't appear.
     * The attempt columns follow their own setting.
     */
    public function test_columns_follow_settings(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field([
            'datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI', 'param2' => 50,
        ]);
        $CFG->showuseridentity = 'idnumber,department,email,profile_field_upi';
        set_config('identitycolumns', 'userid,idnumber,profile_field_upi', 'quiz_livequizmonitor');
        set_config('attemptcolumns', 'progress', 'quiz_livequizmonitor');
        [$context] = $this->setup_teacher();

        $columns = column_helper::get_columns($context);

        // Department and email are in "Show user identity", but weren't chosen for the monitor.
        $expected = ['status', 'student', 'userid', 'idnumber', 'profile_field_upi', 'progress', 'actions'];
        $this->assertSame($expected, array_keys($columns));

        // Headers: Moodle's names for the fields (a profile field's own name), and "User ID".
        $this->assertSame('User ID', $columns['userid']['label']);
        $this->assertSame(get_string('idnumber'), $columns['idnumber']['label']);
        $this->assertSame('UPI', $columns['profile_field_upi']['label']);

        // The 'identity' flag is true for identity field columns (e.g. UPI), and false for the others.
        $this->assertTrue($columns['profile_field_upi']['identity']);
        $this->assertFalse($columns['progress']['identity']);
    }

    /**
     * A chosen field that is no longer in "Show user identity" isn't shown.
     */
    public function test_field_removed_from_show_user_identity_is_not_shown(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('identitycolumns', 'idnumber,department', 'quiz_livequizmonitor');
        $CFG->showuseridentity = 'idnumber';
        [$context] = $this->setup_teacher();

        $this->assertSame(['idnumber'], column_helper::get_chosen_identity_fields());
        $this->assertSame(['idnumber'], column_helper::get_visible_identity_fields($context));
    }

    /**
     * Identity fields, including the user id, need moodle/site:viewuseridentity.
     */
    public function test_identity_fields_need_viewuseridentity(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->showuseridentity = 'idnumber,email';
        set_config('identitycolumns', 'userid,idnumber,email', 'quiz_livequizmonitor');
        [$context, , $course] = $this->setup_teacher();
        $this->assertSame(['userid', 'idnumber', 'email'], column_helper::get_visible_identity_fields($context));

        $this->prohibit_for_teachers('moodle/site:viewuseridentity', $course);

        $this->assertSame([], column_helper::get_visible_identity_fields($context));
        $this->assertSame(
            ['status', 'student', 'progress', 'timeremaining', 'actions'],
            array_keys(column_helper::get_columns($context))
        );
    }

    /**
     * Users can hide or show any of the columns, except Status and Actions.
     */
    public function test_columns_can_be_hidden(): void {
        global $CFG;
        $this->resetAfterTest();
        $CFG->showuseridentity = 'idnumber';
        set_config('identitycolumns', 'idnumber', 'quiz_livequizmonitor');
        set_config('attemptcolumns', '', 'quiz_livequizmonitor');
        [, $teacher] = $this->setup_teacher();

        $this->assertSame(['student', 'idnumber'], column_helper::get_toggleable_column_ids());

        // A column that no longer appears is dropped from the saved preference.
        $saved = column_helper::set_hidden_columns(['idnumber', 'email', 'progress'], (int) $teacher->id);
        $this->assertSame(['idnumber'], $saved);
    }
}
