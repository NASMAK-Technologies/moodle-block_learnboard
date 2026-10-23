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
 * Steps 1 and 2 on Connect to LearnBoard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\admin;

/**
 * The first two steps of connecting, shown above the code field.
 *
 * Holds no value. Step 1 says where the code comes from, with a link to
 * LearnBoard; step 2 says to paste it in the box below, and reports whether
 * this site is connected. Step 3, the dashboards, follows the field in
 * {@see setting_connected_panel} and stays locked until the site connects.
 */
class setting_connection_steps extends \admin_setting {
    /** LearnBoard's address before a code has told this site its own. */
    private const DEFAULT_APP = 'https://learnboard.learnoow.com';

    /**
     * A display-only setting under the given name.
     *
     * @param string $name setting name, block_learnboard/...
     */
    public function __construct(string $name) {
        $this->nosave = true;
        parent::__construct($name, '', '', '');
    }

    /**
     * Always set, so Moodle never lists it as a new setting to fill in.
     *
     * @return bool
     */
    public function get_setting() {
        return true;
    }

    /**
     * Nothing to store.
     *
     * @param mixed $data
     * @return string
     */
    public function write_setting($data) {
        return '';
    }

    /**
     * Steps 1 and 2, done or current.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $connected = self::is_connected();
        $app = \block_learnboard\client::appbase() ?: self::DEFAULT_APP;

        return $OUTPUT->render_from_template('block_learnboard/connection_steps', [
            'steps' => [
                [
                    'number' => 1,
                    'done' => $connected,
                    'current' => !$connected,
                    'title' => get_string('connectstep1_title', 'block_learnboard'),
                    'text' => get_string('connectstep1_text', 'block_learnboard'),
                    'linkurl' => $connected ? '' : $app . '/admin/data-sources',
                    'linktext' => get_string('connectstep1_link', 'block_learnboard'),
                ],
                [
                    'number' => 2,
                    'done' => $connected,
                    'current' => !$connected,
                    'title' => get_string('connectstep2_title', 'block_learnboard'),
                    'text' => get_string($connected ? 'connectstep2_reconnect' : 'connectstep2_text', 'block_learnboard'),
                    'status' => setting_connection_code::status_text(),
                ],
            ],
        ]);
    }

    /**
     * Whether the last connection attempt connected this site.
     *
     * @return bool
     */
    public static function is_connected(): bool {
        $raw = get_config('block_learnboard', 'lastconnection');
        $last = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($last) && !empty($last['connected']);
    }
}
