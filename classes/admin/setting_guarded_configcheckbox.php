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
 * A checkbox the connection code fills in.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\admin;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * A checkbox on LearnBoard advanced settings that a connection code also fills in.
 *
 * Moodle writes a form's settings in the order of the admin tree. Connect to
 * LearnBoard now comes first in that tree, so on a form holding both, such as
 * admin search results, this field's old value would be written after the
 * code had set the new one. When a code was saved in the same request, this
 * field leaves the code's value alone.
 */
class setting_guarded_configcheckbox extends \admin_setting_configcheckbox {
    /**
     * Writes the value, unless a connection code was saved in this request.
     *
     * @param mixed $data
     * @return string empty on success, or the error shown under the field
     */
    public function write_setting($data) {
        if (setting_connection_code::applied()) {
            return '';
        }

        return parent::write_setting($data);
    }
}
