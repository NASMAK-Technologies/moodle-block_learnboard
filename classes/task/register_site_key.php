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
 * One-off task: register again after an upgrade to take this site's own query key.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\task;

/**
 * Registers a connected site with LearnBoard again, once, after the upgrade
 * to 1.1.25, so it takes a query key of its own without anyone saving the
 * connection code again. Queued by db/upgrade.php and run by cron.
 *
 * Until it runs, or if LearnBoard cannot be reached, the site keeps working
 * on the workspace's connector key exactly as before. Saving the connection
 * code again does the same thing by hand.
 */
class register_site_key extends \core\task\adhoc_task {
    /**
     * Whether this site is connected and still has no query key of its own.
     *
     * @return bool
     */
    public static function needed(): bool {
        $config = get_config('block_learnboard');

        return !empty($config->embedkey) && !empty($config->tenant) && !empty($config->querykey)
            && empty($config->sitequerykey);
    }

    /**
     * Register again. Never throws: a failure must not keep cron retrying.
     */
    public function execute() {
        if (!self::needed()) {
            mtrace('LearnBoard: nothing to do, the site has its own query key or is not connected.');

            return;
        }
        try {
            $result = \block_learnboard\admin\setting_connection_code::register(null, true);
            $taken = !empty(get_config('block_learnboard', 'sitequerykey'));
            mtrace('LearnBoard: registered again, ' . ($taken ? 'the site now has its own query key.'
                : 'no query key taken (' . ($result['reason'] ?? '') . '); saving the connection code again will retry.'));
        } catch (\Throwable $e) {
            mtrace('LearnBoard: could not register again: ' . $e->getMessage());
        }
    }
}
