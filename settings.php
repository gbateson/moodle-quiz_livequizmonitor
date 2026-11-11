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
 * Site settings for the live quiz monitor: which columns to show.
 *
 * Moodle adds this page under Site administration > Plugins > Activity modules > Quiz.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quiz_livequizmonitor\local\column_helper;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Student details, e.g. ID number, email and custom profile fields such as a student number.
    $settings->add(new admin_setting_configmulticheckbox(
        'quiz_livequizmonitor/' . column_helper::SETTING_IDENTITYFIELDS,
        new lang_string('settings:identityfields', 'quiz_livequizmonitor'),
        new lang_string('settings:identityfields_desc', 'quiz_livequizmonitor'),
        array_fill_keys(column_helper::DEFAULT_IDENTITYFIELDS, 1),
        fn() => column_helper::get_identity_field_choices()
    ));

    // Attempt details, e.g. progress and time left.
    $settings->add(new admin_setting_configmulticheckbox(
        'quiz_livequizmonitor/' . column_helper::SETTING_ATTEMPTCOLUMNS,
        new lang_string('settings:attemptcolumns', 'quiz_livequizmonitor'),
        new lang_string('settings:attemptcolumns_desc', 'quiz_livequizmonitor'),
        array_fill_keys(column_helper::DEFAULT_ATTEMPTCOLUMNS, 1),
        fn() => column_helper::get_attempt_column_choices()
    ));
}
