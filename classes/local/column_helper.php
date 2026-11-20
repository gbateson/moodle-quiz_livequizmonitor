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
 * Column registry and hide/show preference handling for the live quiz monitor table.
 *
 * @package   quiz_livequizmonitor
 * @copyright 2026 SSYSTEMS
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quiz_livequizmonitor\local;

/**
 * Defines the monitor table's known columns, and reads/writes the per-user
 * hide/show preference for them.
 *
 * This class is deliberately the single place that knows the set of built-in columns.
 * Everything else (renderer, templates, JS, external API) works generically
 * off {@see self::get_columns()} and the persisted preference, so adding a
 * new column later only means adding one entry here (plus its markup).
 *
 * Other plugins add extension columns via the \quiz_livequizmonitor\hook\extend_rows hook.
 * Their ids ("<component>-<name>") are only known per request, so they are passed in where needed.
 */
class column_helper {
    /** @var string Name of the user preference that stores hidden column ids. */
    const PREFERENCE_NAME = 'quiz_livequizmonitor_hiddencolumns';

    /** @var string Name of the user preference that stores default-hidden extension columns the user showed. */
    const SHOWN_PREFERENCE_NAME = 'quiz_livequizmonitor_showncolumns';

    /** @var string Pattern of extension column ids: "<component>-<name>". */
    const EXTENSION_COLUMN_PATTERN = '/^[a-z][a-z0-9_]*-[a-z0-9]+$/';

    /** @var string Setting: the student details to show, chosen from the user id and the "Show user identity" fields. */
    const SETTING_IDENTITYCOLUMNS = 'identitycolumns';

    /** @var string Setting: the optional attempt columns to show. */
    const SETTING_ATTEMPTCOLUMNS = 'attemptcolumns';

    /** @var string[] Attempt columns shown if the setting has not been saved (as before the setting existed). */
    const DEFAULT_ATTEMPTCOLUMNS = ['progress', 'timeremaining', 'endtime'];

    /**
     * Ordered registry of the columns the current user sees in the monitor table.
     *
     * The site admin chooses the student details (from the user id and Moodle's
     * "Show user identity" fields) and the attempt columns in settings.php.
     * Student details that the current user may not see are left out.
     *
     * 'locked' columns (status, actions) can never be hidden by the user,
     * matching the always-visible columns used by other quiz reports.
     *
     * @param \context $context Context to check the current user's access to student details in.
     * @return array<string, array{label: string, locked: bool, identity: bool}> Column id => definition.
     */
    public static function get_columns(\context $context): array {
        return self::build_columns(self::get_visible_identity_fields($context));
    }

    /**
     * Ordered registry of all the columns that may appear, without checking
     * who may see the student details.
     *
     * Only for checking a user's saved hide/show preference, which isn't tied to
     * a quiz. To show columns, use get_columns(), which checks access.
     *
     * @return array<string, array{label: string, locked: bool, identity: bool}> Column id => definition.
     */
    public static function get_chosen_columns(): array {
        return self::build_columns(self::get_chosen_identity_fields());
    }

    /**
     * Build the ordered column registry: status, student, student details,
     * attempt columns, then actions.
     *
     * @param string[] $identityfields Student details to include, e.g. ['idnumber', 'profile_field_upi'].
     * @return array<string, array{label: string, locked: bool, identity: bool}> Column id => definition.
     */
    protected static function build_columns(array $identityfields): array {
        $component = 'quiz_livequizmonitor';
        $columns = [
            'status' => ['label' => get_string('table:status', $component), 'locked' => true, 'identity' => false],
            'student' => ['label' => get_string('table:student', $component), 'locked' => false, 'identity' => false],
        ];

        foreach ($identityfields as $field) {
            $columns[$field] = ['label' => self::get_identity_label($field), 'locked' => false, 'identity' => true];
        }

        $choices = self::get_attempt_column_choices();
        foreach (self::get_enabled_attempt_columns() as $columnid) {
            $columns[$columnid] = ['label' => $choices[$columnid], 'locked' => false, 'identity' => false];
        }

        $columns['actions'] = ['label' => get_string('table:actions', $component), 'locked' => true, 'identity' => false];
        return $columns;
    }

    /**
     * Get the column header for a student detail.
     *
     * Moodle's own name for the field (e.g. 'ID number', or a profile field's name),
     * except the user id, which isn't an identity field in Moodle, and email, which
     * keeps the monitor's shorter "Email".
     *
     * @param string $field Field name, e.g. 'idnumber' or 'profile_field_upi'.
     * @return string
     */
    protected static function get_identity_label(string $field): string {
        if ($field === 'userid') {
            return get_string('table:userid', 'quiz_livequizmonitor');
        }
        if ($field === 'email') {
            return get_string('table:email', 'quiz_livequizmonitor');
        }
        return \core_user\fields::get_display_name($field);
    }

    /**
     * The optional attempt columns the site admin can choose from.
     *
     * @return array<string, string> Column id => label.
     */
    public static function get_attempt_column_choices(): array {
        return [
            'progress' => get_string('table:progress', 'quiz_livequizmonitor'),
            'timeremaining' => get_string('table:timeremaining', 'quiz_livequizmonitor'),
            'endtime' => get_string('table:endtime', 'quiz_livequizmonitor'),
        ];
    }

    /**
     * The attempt columns the site admin chose to show.
     *
     * @return string[] Column ids, in table order.
     */
    public static function get_enabled_attempt_columns(): array {
        $value = get_config('quiz_livequizmonitor', self::SETTING_ATTEMPTCOLUMNS);
        $enabled = ($value === false) ? self::DEFAULT_ATTEMPTCOLUMNS : explode(',', (string) $value);
        return array_values(array_intersect(array_keys(self::get_attempt_column_choices()), $enabled));
    }

    /**
     * The student details the site admin can choose from: the user id, and the fields
     * in Moodle's "Show user identity" setting
     * (Site administration > Users > Permissions > User policies > Show user identity).
     *
     * To offer more fields, add them to "Show user identity".
     *
     * @return array<string, string> Field name => label, e.g. 'profile_field_upi' => 'UPI'.
     */
    public static function get_identity_column_choices(): array {
        $choices = ['userid' => self::get_identity_label('userid')];
        foreach (\core_user\fields::get_identity_fields(null) as $field) {
            $choices[$field] = self::get_identity_label($field);
        }
        return $choices;
    }

    /**
     * The student details shown if the setting has never been saved: all the
     * "Show user identity" fields (as other Moodle reports show), but not the user id.
     *
     * @return string[] Field names.
     */
    public static function get_default_identity_columns(): array {
        return \core_user\fields::get_identity_fields(null);
    }

    /**
     * The student details that the site admin chose to show, without checking who may see them.
     *
     * Fields that are no longer in "Show user identity" are left out, even if they were chosen.
     * To show student details, use get_visible_identity_fields(), which checks access.
     *
     * @return string[] Field names, in the order of the choices, e.g. ['userid', 'idnumber'].
     */
    public static function get_chosen_identity_fields(): array {
        $value = get_config('quiz_livequizmonitor', self::SETTING_IDENTITYCOLUMNS);
        $chosen = ($value === false) ? self::get_default_identity_columns() : explode(',', (string) $value);
        return array_values(array_intersect(array_keys(self::get_identity_column_choices()), $chosen));
    }

    /**
     * The student details that the site admin chose to show, and that the
     * current user may see in the given context.
     *
     * Access to the "Show user identity" fields is decided by
     * \core_user\fields::get_identity_fields(), which applies Moodle's access rules.
     * The user id follows the same main rule: it needs moodle/site:viewuseridentity.
     *
     * @param \context $context Context to check access in.
     * @return string[] Field names, e.g. ['userid', 'idnumber', 'profile_field_upi'].
     */
    public static function get_visible_identity_fields(\context $context): array {
        $allowed = \core_user\fields::get_identity_fields($context);
        if (has_capability('moodle/site:viewuseridentity', $context)) {
            $allowed[] = 'userid';
        }
        return array_values(array_intersect(self::get_chosen_identity_fields(), $allowed));
    }

    /**
     * Column ids that are allowed to be hidden (i.e. not locked).
     *
     * @return string[]
     */
    public static function get_toggleable_column_ids(): array {
        return array_keys(array_filter(self::get_chosen_columns(), function (array $column): bool {
            return empty($column['locked']);
        }));
    }

    /**
     * Whether the given column id is a known, toggleable column.
     *
     * @param string $columnid Column id.
     * @return bool
     */
    public static function is_toggleable_column(string $columnid): bool {
        return in_array($columnid, self::get_toggleable_column_ids(), true);
    }

    /**
     * Whether the id has the format of an extension column id ("<component>-<name>").
     *
     * @param string $columnid Column id.
     * @return bool
     */
    public static function is_extension_column_id(string $columnid): bool {
        return (bool) preg_match(self::EXTENSION_COLUMN_PATTERN, $columnid);
    }

    /**
     * Read the current user's effective set of hidden columns.
     *
     * Unknown or locked column ids are filtered out, so a stale preference
     * (e.g. left over from a since-removed column, or an extension column of an
     * uninstalled plugin) never leaks through. Extension columns that are hidden by
     * default stay hidden until the user shows them.
     *
     * @param int $userid User id, defaults to the current user.
     * @param array[] $extensioncolumns Extension columns of this request (['id', 'label', 'defaulthidden']).
     * @return string[] Hidden column ids.
     */
    public static function get_hidden_columns(int $userid = 0, array $extensioncolumns = []): array {
        $extensionids = array_column($extensioncolumns, 'id');
        $allowed = array_merge(self::get_toggleable_column_ids(), $extensionids);

        $hidden = array_filter(
            self::read_preference(self::PREFERENCE_NAME, $userid),
            static fn($columnid): bool => in_array($columnid, $allowed, true)
        );

        $shown = self::get_shown_columns($userid);
        foreach ($extensioncolumns as $column) {
            if (!empty($column['defaulthidden']) && !in_array($column['id'], $shown, true)) {
                $hidden[] = $column['id'];
            }
        }

        return array_values(array_unique($hidden));
    }

    /**
     * Read the default-hidden extension columns the user has explicitly shown.
     *
     * @param int $userid User id, defaults to the current user.
     * @return string[] Column ids.
     */
    public static function get_shown_columns(int $userid = 0): array {
        return array_values(array_filter(
            self::read_preference(self::SHOWN_PREFERENCE_NAME, $userid),
            static fn($columnid): bool => is_string($columnid) && self::is_extension_column_id($columnid)
        ));
    }

    /**
     * Persist the current user's hidden-column preference.
     *
     * Extension column ids are accepted by format only; ids of columns that no longer
     * exist are dropped when the preference is read.
     *
     * @param string[] $hidden Column ids to hide.
     * @param int $userid User id, defaults to the current user.
     * @param string[] $shown Default-hidden extension columns the user has shown.
     * @return string[] The saved (validated) hidden column ids.
     */
    public static function set_hidden_columns(array $hidden, int $userid = 0, array $shown = []): array {
        global $USER;

        $userid = $userid ?: (int) $USER->id;
        $hidden = self::filter_valid_columns($hidden);
        $shown = array_values(array_unique(array_filter(
            $shown,
            static fn($columnid): bool => is_string($columnid) && self::is_extension_column_id($columnid)
        )));

        set_user_preference(self::PREFERENCE_NAME, json_encode(array_values($hidden)), $userid);
        set_user_preference(self::SHOWN_PREFERENCE_NAME, json_encode($shown), $userid);

        return $hidden;
    }

    /**
     * Read a JSON list preference.
     *
     * @param string $name Preference name.
     * @param int $userid User id, defaults to the current user.
     * @return array Decoded list, [] if unset or invalid.
     */
    protected static function read_preference(string $name, int $userid = 0): array {
        global $USER;

        $userid = $userid ?: (int) $USER->id;
        $raw = get_user_preferences($name, '', $userid);
        $values = $raw !== '' ? json_decode($raw, true) : [];

        return is_array($values) ? $values : [];
    }

    /**
     * Keep only column ids that are toggleable built-in columns or extension column ids.
     *
     * @param array $columnids Candidate column ids.
     * @return string[]
     */
    protected static function filter_valid_columns(array $columnids): array {
        $toggleable = self::get_toggleable_column_ids();

        $valid = array_filter($columnids, function ($columnid) use ($toggleable): bool {
            return is_string($columnid)
                && (in_array($columnid, $toggleable, true) || self::is_extension_column_id($columnid));
        });

        return array_values(array_unique($valid));
    }
}
