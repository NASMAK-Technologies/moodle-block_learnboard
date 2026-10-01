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
 * Privacy provider for block_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\privacy;

use core_privacy\local\metadata\collection;

/**
 * The plugin stores no personal data in Moodle, but it sends personal data to
 * LearnBoard in two ways, both declared here: the identity of the signed-in
 * user when per-user sign-in is on, and the reporting data the data connector
 * reads when that is on.
 */
class provider implements \core_privacy\local\metadata\provider {
    /**
     * Describe the personal data sent to LearnBoard.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('learnboard_sso', [
            'external_id' => 'privacy:metadata:learnboard_sso:external_id',
            'name' => 'privacy:metadata:learnboard_sso:name',
            'roles' => 'privacy:metadata:learnboard_sso:roles',
            'source' => 'privacy:metadata:learnboard_sso:source',
        ], 'privacy:metadata:learnboard_sso');

        $collection->add_external_location_link('learnboard_connector', [
            'userprofile' => 'privacy:metadata:learnboard_connector:userprofile',
            'learningrecords' => 'privacy:metadata:learnboard_connector:learningrecords',
        ], 'privacy:metadata:learnboard_connector');

        $collection->add_external_location_link('learnboard_charts', [
            'ipaddress' => 'privacy:metadata:learnboard_charts:ipaddress',
            'useragent' => 'privacy:metadata:learnboard_charts:useragent',
        ], 'privacy:metadata:learnboard_charts');

        return $collection;
    }
}
