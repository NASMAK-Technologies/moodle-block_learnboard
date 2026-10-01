<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Builds the per-user SSO assertion for the logged-in Moodle user.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard;

/**
 * Per-user SSO (#1): turns the current Moodle `$USER` into the identity
 * assertion LearnBoard provisions/maps against — id, display name, role
 * archetypes (across all their assignments) + a `siteadmin` pseudo-role, and the
 * site URL as the source. Sent server-to-server (never from the browser), so it
 * is trusted under the shared X-Embed-Key.
 */
class ssouser {
    /** Moodle role archetypes (+ pseudo-role `siteadmin`) that map to STAFF. */
    private const STAFF_ARCHETYPES = ['siteadmin', 'manager', 'coursecreator', 'editingteacher', 'teacher'];

    /**
     * Safe default Moodle-role → LearnBoard-role map, keyed by role shortname
     * (+ the `siteadmin` pseudo-role and a `__default__` fallback for unmapped
     * users). Reproduces the pre-configurable behaviour exactly: staff → the
     * read-only `Embed Service` role, students / everyone else → `Learner`. An
     * admin overrides this via the role-map settings page (config `rolemap`).
     */
    public const DEFAULT_MAP = [
        'siteadmin' => 'Embed Service',
        'manager' => 'Embed Service',
        'coursecreator' => 'Embed Service',
        'editingteacher' => 'Embed Service',
        'teacher' => 'Embed Service',
        'student' => 'Learner',
        '__default__' => 'Learner',
    ];

    /**
     * Whether per-user SSO is switched on in the plugin settings.
     */
    public static function enabled(): bool {
        return (int) get_config('block_learnboard', 'ssoenabled') === 1;
    }

    /**
     * The identity assertion for the current user, or null if there isn't a
     * real logged-in user (guest / not logged in).
     *
     * @return array|null ['external_id','name','roles','source']
     */
    public static function assertion(): ?array {
        global $USER, $CFG;

        if (!isloggedin() || isguestuser()) {
            return null;
        }

        return [
            'external_id' => (string) $USER->id,
            'name' => fullname($USER),
            'roles' => self::roles(),
            // Configurable role mapping (#1): the LearnBoard role name(s) this
            // user's Moodle roles resolve to. LearnBoard applies the
            // highest-privilege one that exists. `roles` (archetypes) is still
            // sent for LearnBoard's backward-compatible fallback + audit.
            'lb_roles' => self::lb_roles(),
            'source' => $CFG->wwwroot,
        ];
    }

    /**
     * The configured Moodle-role → LearnBoard-role map (shortname-keyed), or the
     * safe {@see DEFAULT_MAP} when unset / malformed.
     *
     * @return array<string, string>
     */
    public static function get_map(): array {
        $raw = get_config('block_learnboard', 'rolemap');
        if (!is_string($raw) || trim($raw) === '') {
            return self::DEFAULT_MAP;
        }
        $map = json_decode($raw, true);

        return is_array($map) && $map !== [] ? $map : self::DEFAULT_MAP;
    }

    /**
     * The LearnBoard role name(s) the current user maps to: each held Moodle
     * role shortname (+ `siteadmin`) looked up in the map, falling back to the
     * map's `__default__` (else `Learner`) when nothing matches.
     *
     * @return string[]
     */
    public static function lb_roles(): array {
        $map = self::get_map();

        $out = [];
        foreach (self::held_shortnames() as $shortname) {
            if (isset($map[$shortname]) && $map[$shortname] !== '') {
                $out[] = (string) $map[$shortname];
            }
        }

        if ($out === []) {
            $default = isset($map['__default__']) && $map['__default__'] !== ''
                ? (string) $map['__default__']
                : 'Learner';
            $out[] = $default;
        }

        return array_values(array_unique($out));
    }

    /**
     * Every Moodle role SHORTNAME the current user holds anywhere on the site
     * (not just one context), plus `siteadmin` when applicable. This is what the
     * configurable map is keyed on.
     *
     * @return string[]
     */
    public static function held_shortnames(): array {
        global $USER, $DB;

        $out = [];
        if (is_siteadmin()) {
            $out[] = 'siteadmin';
        }

        $shortnames = $DB->get_fieldset_sql(
            'SELECT DISTINCT r.shortname
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = ?',
            [$USER->id]
        );
        foreach ($shortnames as $shortname) {
            if ($shortname !== null && $shortname !== '') {
                $out[] = (string) $shortname;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Every role archetype the current user holds anywhere on the site (not just
     * one context), plus `siteadmin` when applicable. This is the broad "who is
     * this person" used for the LearnBoard role mapping.
     *
     * @return string[]
     */
    public static function roles(): array {
        global $USER, $DB;

        $out = [];
        if (is_siteadmin()) {
            $out[] = 'siteadmin';
        }

        $archetypes = $DB->get_fieldset_sql(
            'SELECT DISTINCT r.archetype
               FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid = ? AND r.archetype <> ?',
            [$USER->id, '']
        );
        foreach ($archetypes as $archetype) {
            if ($archetype !== null && $archetype !== '') {
                $out[] = $archetype;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Whether the current user maps to STAFF (drives the full-app hand-off entry).
     */
    public static function is_staff(): bool {
        foreach (self::roles() as $role) {
            if (in_array($role, self::STAFF_ARCHETYPES, true)) {
                return true;
            }
        }

        return false;
    }
}
