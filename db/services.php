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
 * External functions the LearnBoard charts call from the page.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_learnboard_get_token' => [
        'classname' => 'block_learnboard\external\get_token',
        'description' => 'A fresh short-lived LearnBoard pass for the charts on a page, for the person viewing it.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
    ],
    'block_learnboard_save_layout' => [
        'classname' => 'block_learnboard\external\save_layout',
        'description' => 'Save the arrangement of charts in a LearnBoard block.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'block/learnboard:addinstance',
    ],
];
