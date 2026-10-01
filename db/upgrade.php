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
 * Upgrade steps for block_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the block.
 *
 * @param int $oldversion the version being upgraded from
 * @return bool
 */
function xmldb_block_learnboard_upgrade($oldversion) {
    if ($oldversion < 2026092500) {
        // 1.1.0: the connector plugin (local_learnboard) is folded into this
        // one. Its connection, keys, data connector, sign-in and role map are
        // copied across so the site keeps working without being reconnected.
        // The capability local/learnboard:view carries over as
        // block/learnboard:view through clonepermissionsfrom in db/access.php.
        \block_learnboard\migration::copy_legacy_settings();
        upgrade_block_savepoint(true, 2026092500, 'learnboard', false);
    }

    return true;
}
