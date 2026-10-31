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
 * The site key hand-over rules, without a Moodle install.
 *
 * Run from the plugin folder with any PHP 7.3+:
 *   php cli/sitekey_check.php
 * Exit code 0 = a hand-over is taken only with the pending value, before it
 * expires, and never twice.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../classes/query/sitekey.php');

use block_learnboard\query\sitekey;

$now = 1793400000;
$key = str_repeat('ab', 32);
list($nonce, $stored) = sitekey::pending($now);

$cases = [
    'pending value, in time' => [true, sitekey::accept($stored, $nonce, $key, $now + 60)],
    'nothing pending' => [false, sitekey::accept('', $nonce, $key, $now)],
    'no value sent' => [false, sitekey::accept($stored, null, $key, $now)],
    'wrong value' => [false, sitekey::accept($stored, str_repeat('0', 64), $key, $now)],
    'expired' => [false, sitekey::accept($stored, $nonce, $key, $now + sitekey::TTL + 1)],
    'malformed key' => [false, sitekey::accept($stored, $nonce, 'not-a-key', $now)],
    'malformed store' => [false, sitekey::accept('{"hash":1}', $nonce, $key, $now)],
    // query.php deletes the pending value once used, so a second hand-over
    // meets an empty store.
    'second use' => [false, sitekey::accept('', $nonce, $key, $now + 61)],
];

$failures = 0;
foreach ($cases as $name => [$want, $got]) {
    $ok = $want === $got;
    $failures += $ok ? 0 : 1;
    printf("%-5s %s\n", $ok ? 'ok' : 'FAIL', $name);
}
printf("\n%d cases, %d failures\n", count($cases), $failures);
exit($failures === 0 ? 0 : 1);
