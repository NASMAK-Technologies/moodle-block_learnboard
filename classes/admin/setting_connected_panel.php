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
 * The "You're connected" card on Connect to LearnBoard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\admin;

/**
 * Tells a newly connected administrator where the dashboards are.
 *
 * Holds no value: it only shows, once the site is connected, this site's own
 * LearnBoard page link (to open or copy), how to put it in a menu on this
 * Moodle version, and that the workspace starts with an LMS Overview
 * dashboard that can be edited, alongside as many others as they like.
 */
class setting_connected_panel extends \admin_setting {
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
     * The card, once the site is connected; nothing before that.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $CFG, $OUTPUT;

        $raw = get_config('block_learnboard', 'lastconnection');
        $last = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($last) || empty($last['connected'])) {
            return '';
        }

        $dashboardurl = (new \moodle_url('/blocks/learnboard/index.php'))->out(false);
        // Moodle 4.3 added the primary-navigation hook the menu entry uses.
        $menuhint = ((int) $CFG->branch >= 403)
            ? get_string('connectedmenuhint', 'block_learnboard')
            : get_string('connectedmenuhintold', 'block_learnboard', $dashboardurl);

        return $OUTPUT->render_from_template('block_learnboard/connected_panel', [
            'uniqid' => \html_writer::random_id('lbconnected'),
            'dashboardurl' => $dashboardurl,
            'editurl' => \block_learnboard\client::appbase() . '/dashboards',
            'menuhint' => $menuhint,
        ]);
    }
}
