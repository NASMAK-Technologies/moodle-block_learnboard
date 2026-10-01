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
 * AJAX endpoint: a fresh LearnBoard pass for a page that has been open a while.
 *
 * A page is given a short-lived, read-only LearnBoard token when it loads.
 * Dashboards left open now keep their figures current by themselves, so the
 * page outlives that token; before it runs out, the SDK asks here for a new one
 * for the same person and place. Same checks as the page itself: signed in to
 * Moodle, allowed to see LearnBoard in that context.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

// The login check is the isloggedin() test below: a signed-out page is told so in JSON
// instead of being redirected, so it can ask the person to sign in again.
// phpcs:ignore moodle.Files.RequireLogin.Missing
require(__DIR__ . '/../../../config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Signed out of Moodle (the session ended): say so plainly rather than
// redirecting, so the page can tell the person to sign in again.
if (!isloggedin() || isguestuser()) {
    http_response_code(401);
    echo json_encode(['status' => 'signed_out']);
    die();
}
require_sesskey();

$contextid = required_param('contextid', PARAM_INT);
$context = context::instance_by_id($contextid, IGNORE_MISSING);
if (!$context || !\block_learnboard\client::viewer_may_see($context)) {
    http_response_code(403);
    echo json_encode(['status' => 'no_access']);
    die();
}

$tokendata = \block_learnboard\client::token_for_viewer($context);
if ($tokendata === null) {
    http_response_code(502);
    echo json_encode(['status' => 'unavailable']);
    die();
}

echo json_encode([
    'status' => 'ok',
    'token' => $tokendata['token'],
    'expires_at' => $tokendata['expires_at'] ?? null,
]);
