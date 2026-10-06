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
 * LearnBoard for Moodle: live LearnBoard charts on Moodle pages, a full-page
 * LearnBoard view in the main menu, the data connector and single sign-on,
 * in one plugin. Until 1.1.0 the connector was the separate local_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_learnboard';
$plugin->version = 2026100601;      // YYYYMMDDXX.
$plugin->requires = 2022041900;     // Moodle 4.0.
$plugin->supported = [400, 503];    // Moodle 4.0 to 5.3.
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.1.12';
