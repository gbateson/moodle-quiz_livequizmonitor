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
 * Standard Moodle library for the Quiz module's Live Quiz Monitor report.
 *
 * @package    quiz_livequizmonitor
 * @copyright  2026 onwards University College London {@link https://www.ucl.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon Bateson <g.bateson@ucl.ac.uk>
 */

/**
 * Add Live Quiz Monitor settings to the Quiz activity settings form.
 *
 * Called from "plugin_extend_coursemodule_standard_elements()"
 * in "course/moodleform_mod.php".
 *
 * @param moodleform_mod $formwrapper
 * @param MoodleQuickForm $mform
 */
function quiz_livequizmonitor_coursemodule_standard_elements($formwrapper, $mform) {
    // We are only interested in quizzes.
    $cm = $formwrapper->get_current();
    if ($cm->modulename == 'quiz') {
        $name = 'maxduration';
        $plugin = 'quiz_livequizmonitor';
        $elementname = "{$plugin}_{$name}";
        $label = get_string($name, $plugin);
        $options = [
            0 => get_string('default'),
            1 => get_string('usetimelimit', $plugin),
            2 => get_string('useopenclosetimes', $plugin),
        ];
        $mform->insertElementBefore(
            $mform->createElement('select', $elementname, $label, $options),
            'timelimit'
        );
        $mform->addHelpButton($elementname, $name, $plugin);
        $mform->setDefault($elementname, get_config($plugin, $name, 0));
    }
}

/**
 * Populate Live Quiz Monitor settings when editing an existing Quiz.
 *
 * Called from "plugin_extend_coursemodule_definition_after_data()"
 * in "course/moodleform_mod.php".
 *
 * @param moodleform_mod $formwrapper
 * @param HTML_QuickForm $mform
 */
function quiz_livequizmonitor_coursemodule_definition_after_data($formwrapper, $mform) {
    global $DB;

    // We are only interested in quizzes.
    $cm = $formwrapper->get_current();
    if ($cm->modulename == 'quiz' && $cm->instance) {
        $name = 'maxduration';
        $plugin = 'quiz_livequizmonitor';
        $elementname = "{$plugin}_{$name}";
        if ($mform->elementExists($elementname)) {
            $record = $DB->get_record($plugin, ['quizid' => $cm->instance]);
            if ($record) {
                $mform->setDefault($elementname, $record->$name);
            }
        }
    }
}

/**
 * Validate incoming form data.
 *
 * Called from "plugin_extend_coursemodule_validation()"
 * in "course/moodleform_mod.php".
 *
 * @param moodleform_mod $formwrapper
 * @param stdClass $data
 */
function quiz_livequizmonitor_coursemodule_validation($formwrapper, $data) {
    global $DB;
    $errors = [];

    // We are only interested in quizzes.
    $cm = $formwrapper->get_current();
    if ($cm->modulename == 'quiz') {
        $name = 'maxduration';
        $plugin = 'quiz_livequizmonitor';
        $elementname = "{$plugin}_{$name}";
        if (array_key_exists($elementname, $data)) {
            switch ($data[$elementname]) {
                case 1:
                    if (empty($data['timelimit'])) {
                        $errors['timelimit'] = get_string('error:missingtimelimit', $plugin);
                        $errors[$elementname] = get_string('error:maxdurationtimelimit', $plugin);
                    }
                    break;
                case 2:
                    if (empty($data['timeopen'])) {
                        $errors['timeopen'] = get_string('error:missingtimeopen', $plugin);
                        $errors[$elementname] = get_string('error:maxdurationopenclosetime', $plugin);
                    }
                    if (empty($data['timeclose'])) {
                        $errors['timeclose'] = get_string('error:missingtimeclose', $plugin);
                        $errors[$elementname] = get_string('error:maxdurationopenclosetime', $plugin);
                    }
                    break;
            }
        }
    }
    return $errors;
}

/**
 * Save Live Quiz Monitor settings after a Quiz is created or updated.
 * Called from "plugin_extend_coursemodule_edit_post_actions()
 * in "course/modlib.php.
 *
 * @param stdClass $moduleinfo
 * @param stdClass $course
 * @return stdClass
 */
function quiz_livequizmonitor_coursemodule_edit_post_actions($moduleinfo, $course) {
    global $DB;

    // We are only interested in quizzes.
    if ($moduleinfo->modulename == 'quiz') {
        $name = 'maxduration';
        $plugin = 'quiz_livequizmonitor';
        $elementname = "{$plugin}_{$name}";
        $value = $moduleinfo->$elementname ?? 0;

        $params = ['quizid' => $moduleinfo->instance];
        if ($DB->record_exists($plugin, $params)) {
            $DB->set_field($plugin, $name, $value, $params);
        } else {
            $params[$name] = $value;
            $DB->insert_record($plugin, $params);
        }
    }
    return $moduleinfo;
}
