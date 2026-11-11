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
 * Tests for the site settings that choose the monitor's columns.
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
     * Without saved settings, the table has the same columns as before the settings existed.
     */
    public function test_default_columns(): void {
        $this->resetAfterTest();
        [$context] = $this->setup_teacher();

        $expected = ['status', 'student', 'email', 'progress', 'timeremaining', 'actions'];
        $this->assertSame($expected, array_keys(column_helper::get_columns($context)));
    }

    /**
     * The table has the columns chosen in the site settings, with student details after the name.
     */
    public function test_columns_follow_settings(): void {
        $this->resetAfterTest();
        $this->getDataGenerator()->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI']);
        set_config('identityfields', 'userid,idnumber,department,profile_field_upi', 'quiz_livequizmonitor');
        set_config('attemptcolumns', 'progress', 'quiz_livequizmonitor');
        [$context] = $this->setup_teacher();

        $columns = column_helper::get_columns($context);

        $expected = ['status', 'student', 'userid', 'idnumber', 'department', 'profile_field_upi', 'progress', 'actions'];
        $this->assertSame($expected, array_keys($columns));
        $this->assertSame('UPI', $columns['profile_field_upi']['label']);
        $this->assertSame('User ID', $columns['userid']['label']);
        $this->assertTrue($columns['profile_field_upi']['identity']);
        $this->assertFalse($columns['progress']['identity']);
    }

    /**
     * The admin can choose user id, the standard identity fields, and text profile fields.
     */
    public function test_identity_field_choices(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI']);
        $generator->create_custom_profile_field(['datatype' => 'textarea', 'shortname' => 'bio', 'name' => 'Biography']);

        $choices = column_helper::get_identity_field_choices();

        foreach (['userid', 'username', 'idnumber', 'email', 'department', 'profile_field_upi'] as $field) {
            $this->assertArrayHasKey($field, $choices);
        }
        // Other kinds of profile field, such as text areas, can't be used.
        $this->assertArrayNotHasKey('profile_field_bio', $choices);
    }

    /**
     * Student details need moodle/site:viewuseridentity.
     */
    public function test_student_details_need_viewuseridentity(): void {
        $this->resetAfterTest();
        set_config('identityfields', 'idnumber,email', 'quiz_livequizmonitor');
        [$context, , $course] = $this->setup_teacher();
        $this->assertSame(['idnumber', 'email'], column_helper::get_visible_identity_fields($context));

        $teacherrole = get_archetype_roles('editingteacher');
        assign_capability(
            'moodle/site:viewuseridentity',
            CAP_PROHIBIT,
            reset($teacherrole)->id,
            \context_course::instance($course->id)
        );

        $this->assertSame([], column_helper::get_visible_identity_fields($context));
        $this->assertSame(
            ['status', 'student', 'progress', 'timeremaining', 'actions'],
            array_keys(column_helper::get_columns($context))
        );
    }

    /**
     * Fields listed in "Hide user fields" also need moodle/course:viewhiddenuserfields.
     */
    public function test_hidden_user_fields_need_viewhiddenuserfields(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('identityfields', 'idnumber,department', 'quiz_livequizmonitor');
        $CFG->hiddenuserfields = 'department';
        [$context, , $course] = $this->setup_teacher();
        $this->assertSame(['idnumber', 'department'], column_helper::get_visible_identity_fields($context));

        $teacherrole = get_archetype_roles('editingteacher');
        assign_capability(
            'moodle/course:viewhiddenuserfields',
            CAP_PROHIBIT,
            reset($teacherrole)->id,
            \context_course::instance($course->id)
        );

        $this->assertSame(['idnumber'], column_helper::get_visible_identity_fields($context));
    }

    /**
     * Custom profile fields follow their own "Who is this field visible to?" setting.
     */
    public function test_profile_fields_follow_their_visibility(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php'); // For the PROFILE_VISIBLE_* constants.
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'upi', 'name' => 'UPI',
            'visible' => PROFILE_VISIBLE_ALL]);
        $generator->create_custom_profile_field(['datatype' => 'text', 'shortname' => 'secret', 'name' => 'Secret',
            'visible' => PROFILE_VISIBLE_NONE]);
        set_config('identityfields', 'profile_field_upi,profile_field_secret', 'quiz_livequizmonitor');
        [$context] = $this->setup_teacher();

        $this->assertSame(['profile_field_upi'], column_helper::get_visible_identity_fields($context));
    }

    /**
     * A profile field deleted after the settings were saved is ignored.
     */
    public function test_deleted_profile_field_is_ignored(): void {
        $this->resetAfterTest();
        set_config('identityfields', 'idnumber,profile_field_deleted', 'quiz_livequizmonitor');
        [$context] = $this->setup_teacher();

        $this->assertSame(['idnumber'], column_helper::get_visible_identity_fields($context));
        $this->assertSame(['idnumber'], column_helper::get_visible_identity_fields(null));
    }

    /**
     * Users can hide or show any of the chosen columns, except Status and Actions.
     */
    public function test_chosen_columns_can_be_hidden(): void {
        $this->resetAfterTest();
        set_config('identityfields', 'idnumber', 'quiz_livequizmonitor');
        set_config('attemptcolumns', '', 'quiz_livequizmonitor');
        [, $teacher] = $this->setup_teacher();

        $this->assertSame(['student', 'idnumber'], column_helper::get_toggleable_column_ids());

        // A column that is no longer chosen is dropped from the saved preference.
        $saved = column_helper::set_hidden_columns(['idnumber', 'email', 'progress'], (int) $teacher->id);
        $this->assertSame(['idnumber'], $saved);
    }
}
