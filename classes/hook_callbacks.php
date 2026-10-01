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
 * Hook callbacks for block_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard;

/**
 * Puts LearnBoard in Moodle's main menu.
 *
 * The connector plugin added its entry to the flat navigation drawer, which
 * Moodle removed in 4.0, so on every current site the entry was added to a
 * menu nobody sees. The primary navigation is where site-wide destinations
 * live now; themes that draw their own sidebar take their
 * items from the theme's settings instead, which the README covers.
 */
class hook_callbacks {
    /**
     * Add "LearnBoard" to the primary navigation for people who may open it.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function primary_extend(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if ((string) get_config('block_learnboard', 'shownav') === '0') {
            return;
        }
        if (!client::is_configured()) {
            return;
        }
        // The page shows workspace analytics: the entry is shown only to people
        // who may be given a LearnBoard token at site level.
        if (!client::viewer_may_see(\context_system::instance())) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('fullpagenav', 'block_learnboard'),
            new \moodle_url('/blocks/learnboard/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'block_learnboard',
            new \pix_icon('i/report', '')
        );
    }
}
