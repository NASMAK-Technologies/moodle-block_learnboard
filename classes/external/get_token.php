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
 * External function: a fresh LearnBoard pass for the charts on a page.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * A fresh LearnBoard pass for the charts on a page.
 *
 * The page mints a short-lived token when it loads. Charts left open renew it
 * through this function before it runs out, with the same checks the page
 * applied: the same person, the same context, access to LearnBoard there.
 *
 * Written against the external API classes Moodle 4.0 has; later versions keep
 * them as aliases of core_external.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_token extends external_api {
    /**
     * The page's context.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Context the charts are shown in'),
        ]);
    }

    /**
     * Mint a fresh token for the person viewing the page.
     *
     * @param int $contextid
     * @return array
     */
    public static function execute(int $contextid): array {
        ['contextid' => $contextid] = self::validate_parameters(self::execute_parameters(), ['contextid' => $contextid]);

        $context = \context::instance_by_id($contextid);
        self::validate_context($context);

        if (!\block_learnboard\client::viewer_may_see($context)) {
            return ['status' => 'no_access', 'token' => '', 'expires_at' => ''];
        }
        $tokendata = \block_learnboard\client::token_for_viewer($context);
        if ($tokendata === null) {
            return ['status' => 'unavailable', 'token' => '', 'expires_at' => ''];
        }

        return [
            'status' => 'ok',
            'token' => (string) $tokendata['token'],
            'expires_at' => (string) ($tokendata['expires_at'] ?? ''),
        ];
    }

    /**
     * The token, or why there is none.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHAEXT, 'ok, no_access or unavailable'),
            'token' => new external_value(PARAM_RAW, 'The LearnBoard token, empty unless status is ok'),
            'expires_at' => new external_value(PARAM_RAW, 'When the token expires, ISO 8601, empty if unknown'),
        ]);
    }
}
