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
 * Settings for the Live Quiz Monitor report
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 onwards University College London {@link https://ucl.ac.uk}
 * @author    Gordon Bateson <g.bateson@ucl.ac.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $plugin = 'quiz_livequizmonitor';
    $name = 'maxduration';
    $label = get_string($name, $plugin);
    $help = get_string("{$name}_help", $plugin);
    $options = [
        0 => get_string('default'),
        1 => get_string('usetimelimit', $plugin),
        2 => get_string('useopenclosetimes', $plugin),
    ];
    $settings->add(new admin_setting_configselect("$plugin/$name", $label, $help, 0, $options));
}
