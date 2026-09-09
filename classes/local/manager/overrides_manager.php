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
 * Reads core {quiz_overrides} rows for the live quiz monitor.
 *
 * Shared between the user-override filter (CTP-6723) and the
 * group-override filter (CTP-6724), since both read the same table -
 * they differ only in which column (userid vs groupid) is set.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_livequizmonitor\local\manager;

use context_module;

/**
 * Manager for {quiz_overrides} lookups.
 */
class overrides_manager {
    /**
     * Columns in {quiz_overrides} that represent an id in another table.
     *
     * @var string[] columns that hold ids
     */
    protected const ID_COLUMNS = ['userid', 'groupid'];

    /**
     * Columns on {quiz_overrides} that represent an actual override value.
     *
     * A row only counts as "having an override" if at least one of these
     * is non-null. (In practice core's override form never saves a row
     * with all of these empty, but we check explicitly rather than relying
     * on that as an implementation detail of core.)
     *
     * @var string[]
     */
    protected const VALUE_COLUMNS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /**
     * Columns on {quiz_overrides} that relate to attempt timing.
     *
     * @var string[]
     */
    protected const TIME_COLUMNS = ['timeopen', 'timeclose', 'timelimit'];

    /**
     * Whether the viewer may see override information for this quiz.
     *
     * @param context_module $context Module context.
     * @return bool
     */
    public static function user_can_view_overrides(context_module $context): bool {
        return has_capability('mod/quiz:manageoverrides', $context);
    }

    /**
     * Load has-override flags for a set of users, keyed by a given column.
     *
     * @param int $quizid Quiz instance id.
     * @param string $idcolumn One of the ID_COLUMNS.
     * @param int[] $ids User ids or group ids to check.
     * @param string[] $valuecolumns Which VALUE_COLUMNS count as "a value". Defaults to all of them.
     * @return array<int, bool> Map id => has at least one overridden value in $valuecolumns.
     */
    protected static function get_override_map(
        int $quizid,
        string $idcolumn,
        array $ids,
        array $valuecolumns = self::VALUE_COLUMNS
    ): array {
        global $DB;

        $map = array_fill_keys($ids, false);
        if ($ids === [] || !in_array($idcolumn, self::ID_COLUMNS, true) || $valuecolumns === []) {
            return $map;
        }

        [$selectids, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
        $params['quizid'] = $quizid;

        // Join conditions on value columns, e.g. "timeopen IS NOT NULL OR timeclose IS NOT NULL ...".
        $selectnotnull = implode(' OR ', array_map(
            static fn(string $col): string => "$col IS NOT NULL",
            $valuecolumns
        ));

        $records = $DB->get_records_select(
            'quiz_overrides',
            "quiz = :quizid AND $idcolumn $selectids AND ($selectnotnull)",
            $params,
            '', // No ordering fields.
            "id, $idcolumn AS overrideid"
        );

        foreach ($records as $record) {
            $map[(int) $record->overrideid] = true;
        }

        return $map;
    }

    /**
     * Load has-user-override flags for a set of students in one quiz.
     *
     * @param int $quizid Quiz instance id.
     * @param int[] $userids Student user ids.
     * @return array<int, bool> Map userid => has a user override (any field).
     */
    public static function get_user_override_map(int $quizid, array $userids): array {
        return self::get_override_map($quizid, 'userid', $userids);
    }

    /**
     * Load has-time-related-user-override flags for a set of students in one quiz.
     *
     * True only when the override touches timeopen, timeclose, or timelimit -
     * an attempts- or password-only override returns false here.
     *
     * @param int $quizid Quiz instance id.
     * @param int[] $userids Student user ids.
     * @return array<int, bool> Map userid => has a time-related user override.
     */
    public static function get_user_time_override_map(int $quizid, array $userids): array {
        return self::get_override_map($quizid, 'userid', $userids, self::TIME_COLUMNS);
    }

    /**
     * Load has-group-override flags for a set of groups in one quiz.
     *
     * @param int $quizid Quiz instance id.
     * @param int[] $groupids Group ids.
     * @return array<int, bool> Map groupid => has a group override (any field).
     */
    public static function get_group_override_map(int $quizid, array $groupids): array {
        return self::get_override_map($quizid, 'groupid', $groupids);
    }

    /**
     * Load has-time-related-group-override flags for a set of groups in one quiz.
     *
     * @param int $quizid Quiz instance id.
     * @param int[] $groupids Group ids.
     * @return array<int, bool> Map groupid => has a time-related group override.
     */
    public static function get_group_time_override_map(int $quizid, array $groupids): array {
        return self::get_override_map($quizid, 'groupid', $groupids, self::TIME_COLUMNS);
    }

    /**
     * Resolve, per student, whether they belong to a group that has an override.
     *
     * Deliberately simple: if a student is in several groups with different
     * overrides, this does not attempt to work out which one core would
     * actually apply - it just flags "belongs to a group with an override".
     * Callers that also track user overrides should suppress this flag for
     * students who have one, since a user override always takes precedence
     * over group overrides in core.
     *
     * @param int $quizid Quiz instance id.
     * @param int $courseid Course id (to enumerate groups).
     * @param int[] $userids Student user ids.
     * @param string[] $valuecolumns Which VALUE_COLUMNS count as "a value". Defaults to all of them.
     * @return array<int, bool> Map userid => belongs to at least one overridden group.
     */
    protected static function get_student_group_flag_map(
        int $quizid,
        int $courseid,
        array $userids,
        array $valuecolumns = self::VALUE_COLUMNS
    ): array {
        global $DB;

        $map = array_fill_keys($userids, false);
        if ($userids === []) {
            return $map;
        }

        $groups = groups_get_all_groups($courseid, 0, 0, 'g.id');
        $groupids = array_map('intval', array_keys($groups));
        if ($groupids === []) {
            return $map;
        }

        $groupoverridemap = self::get_override_map($quizid, 'groupid', $groupids, $valuecolumns);
        $overriddengroupids = array_keys(array_filter($groupoverridemap));
        if ($overriddengroupids === []) {
            return $map;
        }

        [$selectgroups, $groupparams] = $DB->get_in_or_equal($overriddengroupids, SQL_PARAMS_NAMED, 'group');
        [$selectusers, $userparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'user');
        $params = array_merge($groupparams, $userparams);

        $memberuserids = $DB->get_fieldset_select(
            'groups_members',
            'DISTINCT userid',
            "groupid $selectgroups AND userid $selectusers",
            $params
        );

        foreach ($memberuserids as $userid) {
            $map[(int) $userid] = true;
        }

        return $map;
    }

    /**
     * Load has-group-override flags for a set of students in one quiz.
     *
     * @param int $quizid Quiz instance id.
     * @param int $courseid Course id (to enumerate groups).
     * @param int[] $userids Student user ids.
     * @return array<int, bool> Map userid => belongs to a group with an override (any field).
     */
    public static function get_student_group_override_map(int $quizid, int $courseid, array $userids): array {
        return self::get_student_group_flag_map($quizid, $courseid, $userids);
    }

    /**
     * Load has-time-related-group-override flags for a set of students in one quiz.
     *
     * @param int $quizid Quiz instance id.
     * @param int $courseid Course id (to enumerate groups).
     * @param int[] $userids Student user ids.
     * @return array<int, bool> Map userid => belongs to a group with a time-related override.
     */
    public static function get_student_group_time_override_map(int $quizid, int $courseid, array $userids): array {
        return self::get_student_group_flag_map($quizid, $courseid, $userids, self::TIME_COLUMNS);
    }
}
