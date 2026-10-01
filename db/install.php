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
 * Install steps for block_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A site that ran only the connector plugin and adds this one fresh keeps its
 * connection: the connector's settings are copied in on install.
 *
 * @return bool
 */
function xmldb_block_learnboard_install() {
    \block_learnboard\migration::copy_legacy_settings();
    block_learnboard_apply_default_settings();

    return true;
}

/**
 * Stores every plugin setting's default, so Moodle does not stop after the
 * install on a "New settings" page full of optional, technical fields.
 *
 * None of those fields need filling in: pasting the connection code fills the
 * connection in, and the rest are optional. A new administrator shown them all
 * straight after installing could not tell that, so the defaults are saved
 * here and the next thing to do is paste the code. A value already set (by the
 * legacy copy above, or by config.php) is left alone.
 */
function block_learnboard_apply_default_settings() {
    global $CFG;
    require_once($CFG->libdir . '/adminlib.php');

    // The plugin list was read before this block was installed, earlier in the
    // same request; without a fresh read its settings pages are not in the tree.
    core_plugin_manager::reset_caches();
    $root = admin_get_root(true, true);
    foreach (['blocksettinglearnboard', 'block_learnboard_connect'] as $section) {
        $page = $root->locate($section);
        if (!$page instanceof admin_settingpage) {
            continue;
        }
        foreach ($page->settings as $setting) {
            if ($setting->get_setting() !== null) {
                continue;
            }
            $default = $setting->get_defaultsetting();
            if ($default !== null) {
                $setting->write_setting($default);
            }
        }
    }
}
