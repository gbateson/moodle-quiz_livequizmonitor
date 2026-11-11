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
 * This class is deliberately the single place that knows the set of columns.
 * Everything else (renderer, templates, JS, external API) works generically
 * off {@see self::get_columns()} and the persisted preference, so adding a
 * new column later only means adding one entry here (plus its markup).
 */
class column_helper {
    /** @var string Name of the user preference that stores hidden column ids. */
    const PREFERENCE_NAME = 'quiz_livequizmonitor_hiddencolumns';

    /** @var string Setting: the student details (user identity fields) to show. */
    const SETTING_IDENTITYFIELDS = 'identityfields';

    /** @var string Setting: the optional attempt columns to show. */
    const SETTING_ATTEMPTCOLUMNS = 'attemptcolumns';

    /** @var string[] Student details shown if the setting has not been saved (as before the setting existed). */
    const DEFAULT_IDENTITYFIELDS = ['email'];

    /** @var string[] Attempt columns shown if the setting has not been saved (as before the setting existed). */
    const DEFAULT_ATTEMPTCOLUMNS = ['progress', 'timeremaining'];

    /** @var string[] Fields of the user table that may be shown, as in Moodle's "Show user identity" setting. */
    const USER_FIELDS = ['username', 'idnumber', 'email', 'phone1', 'phone2', 'department', 'institution', 'city', 'country'];

    /**
     * Ordered registry of the columns in the monitor table.
     *
     * The site admin chooses which student details and attempt columns appear
     * (see settings.php). With a context, student details the current user may
     * not see there are left out; without one, all chosen columns are returned.
     *
     * 'locked' columns (status, actions) can never be hidden by the user,
     * matching the always-visible columns used by other quiz reports.
     *
     * @param \context|null $context Context to check the current user's access to student details in.
     * @return array<string, array{label: string, locked: bool, identity: bool}> Column id => definition.
     */
    public static function get_columns(?\context $context = null): array {
        $component = 'quiz_livequizmonitor';
        $columns = [
            'status' => ['label' => get_string('table:status', $component), 'locked' => true, 'identity' => false],
            'student' => ['label' => get_string('table:student', $component), 'locked' => false, 'identity' => false],
        ];

        $choices = self::get_identity_field_choices();
        foreach (self::get_visible_identity_fields($context) as $field) {
            $columns[$field] = ['label' => $choices[$field] ?? $field, 'locked' => false, 'identity' => true];
        }

        $choices = self::get_attempt_column_choices();
        foreach (self::get_enabled_attempt_columns() as $columnid) {
            $columns[$columnid] = ['label' => $choices[$columnid], 'locked' => false, 'identity' => false];
        }

        $columns['actions'] = ['label' => get_string('table:actions', $component), 'locked' => true, 'identity' => false];
        return $columns;
    }

    /**
     * The student details the site admin can choose from: user id, the fields
     * offered by Moodle's "Show user identity" setting, and custom profile fields
     * that are text fields.
     *
     * Unlike "Show user identity", text fields longer than 255 characters are
     * offered too: a new text field's maximum length is 2048 by default, which
     * would leave out fields such as a UPI. Only the first 255 characters of
     * a value are loaded (see \core_user\fields::get_sql()), plenty for an id.
     *
     * @return array<string, string> Field name => label, e.g. 'profile_field_upi' => 'UPI'.
     */
    public static function get_identity_field_choices(): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $choices = ['userid' => get_string('table:userid', 'quiz_livequizmonitor')];
        foreach (self::USER_FIELDS as $field) {
            $choices[$field] = ($field === 'email') ? get_string('table:email', 'quiz_livequizmonitor') : get_string($field);
        }

        $options = ['context' => \context_system::instance()];
        foreach (profile_get_custom_fields() as $field) {
            if ($field->datatype === 'text') {
                $choices['profile_field_' . $field->shortname] = format_string($field->name, true, $options);
            }
        }
        return $choices;
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
        ];
    }

    /**
     * The attempt columns the site admin chose to show.
     *
     * @return string[] Column ids, in table order.
     */
    public static function get_enabled_attempt_columns(): array {
        $enabled = self::get_setting(self::SETTING_ATTEMPTCOLUMNS, self::DEFAULT_ATTEMPTCOLUMNS);
        return array_values(array_intersect(array_keys(self::get_attempt_column_choices()), $enabled));
    }

    /**
     * The student details that the site admin chose to show, and that the
     * current user may see in the given context.
     *
     * The access rules follow \core_user\fields::get_identity_fields():
     * - all student details need moodle/site:viewuseridentity;
     * - fields in "Hide user fields" ($CFG->hiddenuserfields) also need moodle/course:viewhiddenuserfields;
     * - custom profile fields also follow their own "Who is this field visible to?" setting.
     *
     * @param \context|null $context Context to check access in, or null to skip the access checks.
     * @return string[] Field names, e.g. ['idnumber', 'email', 'profile_field_upi'].
     */
    public static function get_visible_identity_fields(?\context $context): array {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');

        $fields = self::get_setting(self::SETTING_IDENTITYFIELDS, self::DEFAULT_IDENTITYFIELDS);
        if ($context && $fields && !has_capability('moodle/site:viewuseridentity', $context)) {
            return [];
        }

        $hiddenfields = array_filter(explode(',', $CFG->hiddenuserfields ?? ''));
        $canviewhidden = null;
        $visible = [];
        foreach ($fields as $field) {
            if (preg_match(\core_user\fields::PROFILE_FIELD_REGEX, $field, $matches)) {
                // The profile field may have been deleted since the setting was saved.
                $data = profile_get_custom_field_data_by_shortname($matches[1], false);
                if ($data === null) {
                    continue;
                }
                if ($context && !profile_get_user_field($data->datatype, $data->id, 0, $data)->is_visible($context)) {
                    continue;
                }
            } else if ($field !== 'userid' && !in_array($field, self::USER_FIELDS, true)) {
                continue;
            } else if ($context && in_array($field, $hiddenfields, true)) {
                $canviewhidden ??= has_capability('moodle/course:viewhiddenuserfields', $context);
                if (!$canviewhidden) {
                    continue;
                }
            }
            $visible[] = $field;
        }
        return $visible;
    }

    /**
     * Read a multi-choice setting, using the default if the setting has never been saved.
     *
     * @param string $name Setting name.
     * @param string[] $default Default value.
     * @return string[]
     */
    protected static function get_setting(string $name, array $default): array {
        $value = get_config('quiz_livequizmonitor', $name);
        if ($value === false) {
            return $default;
        }
        return array_values(array_filter(explode(',', (string) $value)));
    }

    /**
     * Column ids that are allowed to be hidden (i.e. not locked).
     *
     * @return string[]
     */
    public static function get_toggleable_column_ids(): array {
        return array_keys(array_filter(self::get_columns(), function (array $column): bool {
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
     * Read the current user's hidden-column preference.
     *
     * Unknown or locked column ids are filtered out, so a stale preference
     * (e.g. left over from a since-removed column) never leaks through.
     *
     * @param int $userid User id, defaults to the current user.
     * @return string[] Hidden column ids.
     */
    public static function get_hidden_columns(int $userid = 0): array {
        global $USER;

        $userid = $userid ?: (int) $USER->id;
        $raw = get_user_preferences(self::PREFERENCE_NAME, '', $userid);
        $hidden = $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($hidden)) {
            $hidden = [];
        }

        return self::filter_valid_columns($hidden);
    }

    /**
     * Persist the current user's hidden-column preference.
     *
     * @param string[] $hidden Column ids to hide.
     * @param int $userid User id, defaults to the current user.
     * @return string[] The saved (validated) hidden column ids.
     */
    public static function set_hidden_columns(array $hidden, int $userid = 0): array {
        global $USER;

        $userid = $userid ?: (int) $USER->id;
        $hidden = self::filter_valid_columns($hidden);

        set_user_preference(self::PREFERENCE_NAME, json_encode(array_values($hidden)), $userid);

        return $hidden;
    }

    /**
     * Keep only column ids that exist and are not locked.
     *
     * @param array $columnids Candidate column ids.
     * @return string[]
     */
    protected static function filter_valid_columns(array $columnids): array {
        $toggleable = self::get_toggleable_column_ids();

        $valid = array_filter($columnids, function ($columnid) use ($toggleable): bool {
            return is_string($columnid) && in_array($columnid, $toggleable, true);
        });

        return array_values(array_unique($valid));
    }
}
