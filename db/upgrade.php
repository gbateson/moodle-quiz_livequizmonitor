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
 * Post-install script for the Live Quiz Monitor report.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 University College London {link}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Gordon Bateson
 */

/**
 * Update script for the quiz_livequizmonitor plugin.
 *
 * @param int $oldversion The version we are upgrading from.
 * @return bool
 */
function xmldb_quiz_livequizmonitor_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    $plugintype = 'quiz';
    $pluginname = 'livequizmonitor';

    $newversion = 2025070115;
    if ($oldversion < $newversion) {
        // Define the new table.
        $table = new xmldb_table('quiz_livequizmonitor');

        // Add fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('quizid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('maxduration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);

        // Add primary and foreign keys.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('quizid', XMLDB_KEY_FOREIGN_UNIQUE, ['quizid'], 'quiz', ['id']);

        // Add the table, if it does not already exist.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, $newversion, $plugintype, $pluginname);
    }
    return true;
}
