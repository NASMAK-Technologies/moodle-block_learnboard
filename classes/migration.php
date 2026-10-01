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
 * Carries a site across from the two-plugin version.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard;

/**
 * Settings and state that lived in local_learnboard, the separate connector
 * plugin, before it was folded into this one.
 *
 * Copied, never moved: the old plugin keeps working untouched until an admin
 * uninstalls it, so a site can upgrade this block, check it, and only then
 * remove the connector. A value this plugin already holds is never
 * overwritten, so running the copy twice, or after an admin has changed
 * something here, changes nothing.
 */
class migration {
    /** The component the connector used to be. */
    public const LEGACY = 'local_learnboard';

    /** Every setting and piece of state the connector kept in plugin config. */
    public const KEYS = [
        'apibase', 'embedkey', 'tenant', 'insecuressl', 'theme',
        'queryenabled', 'querykey', 'queryallowedips', 'maxconcurrent',
        'querydbuser', 'querydbpass', 'ssoenabled',
        'rolemap', 'roleshint', 'lastconnection',
    ];

    /**
     * Copy the connector's settings into this plugin, where this plugin has none.
     *
     * @return int how many values were copied
     */
    public static function copy_legacy_settings(): int {
        $legacy = get_config(self::LEGACY);
        if (!is_object($legacy)) {
            return 0;
        }
        $mine = get_config('block_learnboard');
        $copied = 0;
        foreach (self::KEYS as $key) {
            if (!isset($legacy->$key) || (string) $legacy->$key === '') {
                continue;
            }
            if (is_object($mine) && isset($mine->$key) && (string) $mine->$key !== '') {
                continue;
            }
            set_config($key, $legacy->$key, 'block_learnboard');
            $copied++;
        }

        return $copied;
    }

    /**
     * Whether the old connector plugin is still installed on this site.
     */
    public static function legacy_installed(): bool {
        $plugins = \core_plugin_manager::instance()->get_installed_plugins('local');

        return isset($plugins['learnboard']);
    }
}
