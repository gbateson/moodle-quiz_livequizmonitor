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

namespace quiz_livequizmonitor;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/report/livequizmonitor/lib.php');

/**
 * Tests for the quiz_livequizmonitor plugin library.
 *
 * @package    quiz_livequizmonitor
 * @category   test
 */
final class lib_test extends advanced_testcase {
    /**
     * Test that the plugin database table exists.
     *
     * @coversNothing
     */
    public function test_database_table_exists(): void {
        global $DB;

        $this->resetAfterTest(true);

        $this->assertTrue(
            $DB->get_manager()->table_exists('quiz_livequizmonitor')
        );
    }

    /**
     * Test that a new Quiz setting is saved.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_edit_post_actions
     */
    public function test_coursemodule_edit_post_actions_insert(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Test quiz',
        ]);

        $moduleinfo = (object)[
            'modulename' => 'quiz',
            'instance' => $quiz->id,
            'quiz_livequizmonitor_maxduration' => 1,
        ];

        quiz_livequizmonitor_coursemodule_edit_post_actions($moduleinfo, $course);

        $record = $DB->get_record(
            'quiz_livequizmonitor',
            ['quizid' => $quiz->id]
        );

        $this->assertNotFalse($record);
        $this->assertEquals(1, $record->maxduration);
    }

    /**
     * Test that an existing Quiz setting is updated rather than duplicated.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_edit_post_actions
     */
    public function test_coursemodule_edit_post_actions_update(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'name' => 'Test quiz',
        ]);

        $moduleinfo = (object)[
            'modulename' => 'quiz',
            'instance' => $quiz->id,
            'quiz_livequizmonitor_maxduration' => 1,
        ];

        quiz_livequizmonitor_coursemodule_edit_post_actions($moduleinfo, $course);

        $moduleinfo->quiz_livequizmonitor_maxduration = 2;

        quiz_livequizmonitor_coursemodule_edit_post_actions($moduleinfo, $course);

        $records = $DB->get_records(
            'quiz_livequizmonitor',
            ['quizid' => $quiz->id]
        );

        $this->assertCount(1, $records);

        $record = reset($records);

        $this->assertEquals(2, $record->maxduration);
    }

    /**
     * Test that the time limit is required when maxduration is set to 1.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_requires_timelimit(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 1,
            'timelimit' => 0,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertArrayHasKey('timelimit', $errors);
        $this->assertEquals(
            get_string('error:missingtimelimit', 'quiz_livequizmonitor'),
            $errors['timelimit']
        );

        $this->assertArrayHasKey('quiz_livequizmonitor_maxduration', $errors);
    }

    /**
     * Test that a valid time limit is accepted when maxduration is 1.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_accepts_timelimit(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 1,
            'timelimit' => 600,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertArrayNotHasKey('timelimit', $errors);
        $this->assertArrayNotHasKey('quiz_livequizmonitor_maxduration', $errors);
    }

    /**
     * Test that the open time is required when maxduration is set to 2.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_requires_timeopen(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 2,
            'timeopen' => 0,
            'timeclose' => time() + 3600,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertArrayHasKey('timeopen', $errors);
        $this->assertEquals(
            get_string('error:missingtimeopen', 'quiz_livequizmonitor'),
            $errors['timeopen']
        );
    }

    /**
     * Test that the close time is required when maxduration is set to 2.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_requires_timeclose(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 2,
            'timeopen' => time(),
            'timeclose' => 0,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertArrayHasKey('timeclose', $errors);
        $this->assertEquals(
            get_string('error:missingtimeclose', 'quiz_livequizmonitor'),
            $errors['timeclose']
        );
    }

    /**
     * Test that valid open and close times are accepted when maxduration is 2.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_accepts_openclosetimes(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 2,
            'timeopen' => time(),
            'timeclose' => time() + 3600,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertArrayNotHasKey('timeopen', $errors);
        $this->assertArrayNotHasKey('timeclose', $errors);
        $this->assertArrayNotHasKey('quiz_livequizmonitor_maxduration', $errors);
    }

    /**
     * Test that maxduration = 0 does not impose additional requirements.
     *
     * @covers ::quiz_livequizmonitor_coursemodule_validation
     */
    public function test_validation_default(): void {
        $this->resetAfterTest(true);

        $formwrapper = $this->get_formwrapper_mock();

        $data = [
            'quiz_livequizmonitor_maxduration' => 0,
            'timelimit' => 0,
            'timeopen' => 0,
            'timeclose' => 0,
        ];

        $errors = quiz_livequizmonitor_coursemodule_validation($formwrapper, $data);

        $this->assertEmpty($errors);
    }

    /**
     * Create a mock moodleform_mod for testing callbacks that require the
     * current course module.
     *
     * @return moodleform_mod
     */
    private function get_formwrapper_mock(): object {
        return new class {
            /**
             * Return the current course module.
             *
             * @return stdClass Course module record.
             */
            public function get_current(): stdClass {
                return (object)[
                    'modulename' => 'quiz',
                    'instance' => 1,
                ];
            }
        };
    }
}
