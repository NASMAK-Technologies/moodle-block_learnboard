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
 * LearnBoard query endpoint: LearnBoard asks, this site answers.
 *
 * LearnBoard sends signed read queries to this URL over the HTTPS address the
 * site already serves, and gets the rows back as JSON. The database is only
 * ever reached from here, on the server, so it can stay bound to localhost
 * with no port opened to anyone.
 *
 * Request (POST, JSON body):
 * {"v":1,"op":"ping"}                                  connection test
 * {"v":1,"op":"query","queries":[{"sql":"...","bindings":[...]}, ...]}
 * Headers:
 * X-LearnBoard-Timestamp  unix seconds, within 5 minutes of this server
 * X-LearnBoard-Nonce      32 hex characters, never reused
 * X-LearnBoard-Signature  hex HMAC-SHA256 of "timestamp\nnonce\nsha256(body)"
 * under the connector key set in the plugin settings
 * Response: {"v":1,"server":{...},"results":[{"ok":true,"rows":[...]}|{"ok":false,...}]}
 * signed back in X-LearnBoard-Signature as HMAC-SHA256 of "nonce\nbody", so an
 * answer belongs to the one request it was sent for.
 *
 * Checked in this order, cheapest first: HTTPS, method, size, header shape,
 * clock; then the settings, the caller's address, the signature and the nonce;
 * then every statement (classes/query/validator.php) on a session proven to be
 * read-only (classes/query/runner.php). Every request is written to
 * {dataroot}/block_learnboard/query-audit.log.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('ABORT_AFTER_CONFIG', true);
define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);

require(__DIR__ . '/../../config.php');
if (!defined('MOODLE_INTERNAL')) {
    define('MOODLE_INTERNAL', true);
}
require_once(__DIR__ . '/classes/query/refused.php');
require_once(__DIR__ . '/classes/query/validator.php');
require_once(__DIR__ . '/classes/query/runner.php');
require_once(__DIR__ . '/classes/query/guard.php');
require_once(__DIR__ . '/classes/query/busy.php');

use block_learnboard\query\busy;
use block_learnboard\query\guard;
use block_learnboard\query\refused;
use block_learnboard\query\runner;
use block_learnboard\query\validator;

/** Reported to LearnBoard in the connection test; matches version.php. */
const BLOCK_LEARNBOARD_QUERY_PLUGIN_VERSION = 2026091702;

/** Largest request body accepted. */
const BLOCK_LEARNBOARD_QUERY_MAX_REQUEST = 2 * 1024 * 1024;

/** Largest answer sent back, in bytes of row data. */
const BLOCK_LEARNBOARD_QUERY_MAX_ANSWER = 32 * 1024 * 1024;

@set_time_limit(180);

$audit = [
    'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    'op' => '',
    'status' => 0,
    'error' => '',
];
$started = microtime(true);

/**
 * Send a JSON response and stop. Signed over the request's nonce once the
 * request has been authenticated; errors before that are sent unsigned.
 *
 * @param int $status
 * @param array $body
 * @param string $key connector key, empty when the request was not authenticated
 * @param string $nonce the request's nonce, needed to sign
 */
function block_learnboard_query_respond(int $status, array $body, string $key = '', string $nonce = ''): void {
    global $CFG, $audit, $started;

    $json = (string) json_encode($body + ['v' => 1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    if ($key !== '' && $nonce !== '') {
        header('X-LearnBoard-Signature: ' . hash_hmac('sha256', $nonce . "\n" . $json, $key));
    }

    $audit['status'] = $status;
    if ($audit['error'] === '' && isset($body['error']) && is_string($body['error'])) {
        $audit['error'] = $body['error'];
    }
    $audit['ms'] = (int) round((microtime(true) - $started) * 1000);
    $audit['bytes'] = strlen($json);
    guard::audit($CFG, $audit);

    echo $json;
    exit;
}

// Settings forced in config.php. Until 1.1.0 they were written under the
// connector's component, local_learnboard, and a site that upgrades keeps its
// config.php as it was, so those still count here; anything forced under
// block_learnboard wins.
$forced = [];
foreach (['local_learnboard', 'block_learnboard'] as $forcedcomponent) {
    if (isset($CFG->forced_plugin_settings[$forcedcomponent]) && is_array($CFG->forced_plugin_settings[$forcedcomponent])) {
        $forced = $CFG->forced_plugin_settings[$forcedcomponent] + $forced;
    }
}

if (!guard::transport_is_secure($CFG, $forced)) {
    block_learnboard_query_respond(403, ['error' => 'https_required']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    block_learnboard_query_respond(405, ['error' => 'method_not_allowed']);
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > BLOCK_LEARNBOARD_QUERY_MAX_REQUEST) {
    block_learnboard_query_respond(413, ['error' => 'request_too_large']);
}
$input = fopen('php://input', 'rb');
$raw = $input !== false ? (string) stream_get_contents($input, BLOCK_LEARNBOARD_QUERY_MAX_REQUEST + 1) : '';
if ($input !== false) {
    fclose($input);
}
if (strlen($raw) > BLOCK_LEARNBOARD_QUERY_MAX_REQUEST) {
    block_learnboard_query_respond(413, ['error' => 'request_too_large']);
}

$timestamp = (string) ($_SERVER['HTTP_X_LEARNBOARD_TIMESTAMP'] ?? '');
$nonce = (string) ($_SERVER['HTTP_X_LEARNBOARD_NONCE'] ?? '');
$signature = strtolower((string) ($_SERVER['HTTP_X_LEARNBOARD_SIGNATURE'] ?? ''));
if (!ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{32}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
    block_learnboard_query_respond(401, ['error' => 'unauthenticated']);
}

// A request outside the window is refused before the database is touched, so
// old captured requests cost this server nothing.
if (abs(time() - (int) $timestamp) > 300) {
    block_learnboard_query_respond(401, ['error' => 'stale_request']);
}

// Settings: forced in config.php when the admin prefers that, otherwise the
// plugin's own rows in the config table, read on Moodle's database account.
try {
    $settingsdb = new runner($CFG);
} catch (\RuntimeException $e) {
    block_learnboard_query_respond(503, ['error' => $e->getMessage() === 'unsupported_database' ? 'unsupported_database' : 'database_unreachable']);
}
$setting = function (string $name) use ($forced, $settingsdb, $CFG): string {
    return array_key_exists($name, $forced) ? (string) $forced[$name] : $settingsdb->plugin_setting((string) $CFG->prefix, $name);
};
$coresetting = function (string $name) use ($settingsdb, $CFG): string {
    return isset($CFG->$name) ? (string) $CFG->$name : $settingsdb->core_setting((string) $CFG->prefix, $name);
};

$audit['ip'] = guard::remote_address($coresetting('getremoteaddrconf'), $coresetting('reverseproxyignore'));

$enabled = $setting('queryenabled') === '1';
$key = trim($setting('querykey'));
$allowedips = guard::parse_list($setting('queryallowedips'));
$rouser = trim($setting('querydbuser'));
$ropass = $setting('querydbpass');

// The key is 256 random bits made by LearnBoard. A short one was typed by
// hand and is refused rather than trusted.
if (!$enabled || strlen($key) < 32) {
    $settingsdb->close();
    block_learnboard_query_respond(403, ['error' => 'connector_disabled']);
}

if ($allowedips !== [] && !guard::address_in_list($audit['ip'], $allowedips)) {
    $settingsdb->close();
    block_learnboard_query_respond(403, ['error' => 'address_not_allowed']);
}

$expected = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . hash('sha256', $raw), $key);
if (!hash_equals($expected, $signature)) {
    $settingsdb->close();
    block_learnboard_query_respond(401, ['error' => 'unauthenticated']);
}

// Replay guard: a nonce is a file in the data directory for ten minutes, so a
// captured request cannot be sent again. Old ones are swept as we go.
$noncedir = rtrim((string) $CFG->dataroot, '/\\') . '/temp/block_learnboard_nonce';
if (!is_dir($noncedir)) {
    @mkdir($noncedir, 0770, true);
}
$handle = @fopen($noncedir . '/' . $nonce, 'x');
if ($handle === false) {
    $settingsdb->close();
    block_learnboard_query_respond(409, ['error' => is_dir($noncedir) ? 'replayed_request' : 'nonce_store_unavailable'], $key, $nonce);
}
fclose($handle);
// A site upgrading from the two-plugin version runs both query endpoints, with
// the same key, until the old connector is uninstalled. The old one keeps its
// nonces in its own directory, so a request it accepted could otherwise be
// replayed here within the five-minute window. Claim the nonce there too.
$legacynoncedir = rtrim((string) $CFG->dataroot, '/\\') . '/temp/local_learnboard_nonce';
if (is_dir($legacynoncedir)) {
    $legacy = @fopen($legacynoncedir . '/' . $nonce, 'x');
    if ($legacy === false) {
        $settingsdb->close();
        block_learnboard_query_respond(409, ['error' => 'replayed_request'], $key, $nonce);
    }
    fclose($legacy);
}
if (random_int(1, 50) === 1) {
    foreach ((array) glob($noncedir . '/*') as $old) {
        if (is_string($old) && @filemtime($old) < time() - 600) {
            @unlink($old);
        }
    }
}

$request = json_decode($raw, true);
if (!is_array($request) || ($request['v'] ?? null) !== 1 || !in_array($request['op'] ?? '', ['ping', 'query'], true)) {
    $settingsdb->close();
    block_learnboard_query_respond(400, ['error' => 'bad_request'], $key, $nonce);
}

// This site's own ceiling on how much it will do at once, answered BEFORE the
// second database connection is opened so a busy moment costs almost nothing.
// LearnBoard waits and asks again, so the reader gets a slower card rather than
// a broken one. Ping is exempt: it is the health check, it does no work, and
// refusing it would have LearnBoard report a healthy site as down.
if ($request['op'] === 'query') {
    $maxconcurrent = (int) $setting('maxconcurrent');
    if (
        $maxconcurrent === 0 && !array_key_exists('maxconcurrent', $forced)
            && $settingsdb->plugin_setting((string) $CFG->prefix, 'maxconcurrent') === ''
    ) {
        // Never configured, on a site upgraded from a version without this.
        $maxconcurrent = 8;
    }
    if (!busy::take($CFG, $maxconcurrent)) {
        $settingsdb->close();
        header('Retry-After: ' . busy::RETRY_AFTER);
        block_learnboard_query_respond(429, ['error' => 'busy'], $key, $nonce);
    }
}
$audit['op'] = (string) $request['op'];

// The queries run on the read-only database user when one is configured, and
// always on a session that has been set read-only and checked.
try {
    if ($rouser !== '') {
        $settingsdb->close();
        $db = new runner($CFG, $rouser, (string) $ropass);
    } else {
        $db = $settingsdb;
    }
    $db->lock_down();
} catch (\RuntimeException $e) {
    $code = $e->getMessage() === 'read_only_unavailable' ? 'read_only_unavailable' : 'database_unreachable';
    block_learnboard_query_respond(503, ['error' => $code], $key, $nonce);
}

$server = $db->server_info() + [
    'moodle_prefix' => (string) $CFG->prefix,
    'database' => (string) $CFG->dbname,
    'plugin_version' => BLOCK_LEARNBOARD_QUERY_PLUGIN_VERSION,
    'read_only_user' => $rouser !== '',
];

if ($request['op'] === 'ping') {
    $db->close();
    block_learnboard_query_respond(200, ['server' => $server, 'results' => []], $key, $nonce);
}

$queries = $request['queries'] ?? null;
if (!is_array($queries) || count($queries) === 0 || count($queries) > 50) {
    $db->close();
    block_learnboard_query_respond(400, ['error' => 'bad_request'], $key, $nonce);
}

$validator = new validator((string) $CFG->prefix, (string) $CFG->dbname);
$results = [];
$bytes = 0;
$audit['queries'] = [];
foreach (array_values($queries) as $query) {
    $sql = is_array($query) && is_string($query['sql'] ?? null) ? $query['sql'] : '';
    $bindings = is_array($query) && is_array($query['bindings'] ?? null) ? array_values($query['bindings']) : [];
    $entry = ['sql_sha256' => substr(hash('sha256', $sql), 0, 16), 'outcome' => 'ok', 'rows' => 0];
    $querystarted = microtime(true);
    try {
        foreach ($bindings as $binding) {
            if (!is_scalar($binding) && $binding !== null) {
                throw new refused('bindings must be plain values');
            }
        }
        $validator->assert_allowed($sql, $bindings);
        $rows = $db->select($sql, $bindings);
        $bytes += strlen((string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        if ($bytes > BLOCK_LEARNBOARD_QUERY_MAX_ANSWER) {
            throw new \RuntimeException('response_too_large');
        }
        $entry['rows'] = count($rows);
        $results[] = ['ok' => true, 'rows' => $rows, 'ms' => (int) round((microtime(true) - $querystarted) * 1000)];
    } catch (refused $e) {
        $entry['outcome'] = 'refused';
        $entry['reason'] = substr($e->getMessage(), 0, 200);
        $results[] = ['ok' => false, 'error' => 'refused', 'message' => $e->getMessage()];
    } catch (\Throwable $e) {
        // The database's own message can name tables and columns; it goes back
        // only to the authenticated LearnBoard server that sent the query, and
        // is not written to the audit log.
        $entry['outcome'] = 'failed';
        $results[] = ['ok' => false, 'error' => 'query_failed', 'message' => substr($e->getMessage(), 0, 500)];
        if ($e->getMessage() === 'response_too_large') {
            $audit['queries'][] = $entry;
            break;
        }
    }
    $audit['queries'][] = $entry;
}
$db->close();

block_learnboard_query_respond(200, ['server' => $server, 'results' => $results], $key, $nonce);
