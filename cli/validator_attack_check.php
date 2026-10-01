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
 * The validator's attack suite, without a Moodle install.
 *
 * Run from the plugin folder with any PHP 8.0+:
 *   php cli/validator_attack_check.php
 * Exit code 0 = every legitimate query passed and every attack was refused.
 *
 * The same cases run as a Moodle PHPUnit test (tests/validator_test.php);
 * this entry point exists because it needs nothing but PHP, so it runs in
 * LearnBoard's own pipeline and on a reviewer's laptop before they have a
 * Moodle to install into.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// This check runs without a Moodle install on purpose, so it does not include
// config.php. It defines MOODLE_INTERNAL itself only so the shared test
// fixtures load, and it refuses to run anywhere but the command line.
// phpcs:disable moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('MOODLE_INTERNAL', true);
// phpcs:enable moodle.Files.MoodleInternal.MoodleInternalGlobalState
require_once(__DIR__ . '/../classes/query/refused.php');
require_once(__DIR__ . '/../classes/query/validator.php');
require_once(__DIR__ . '/../classes/query/guard.php');
require_once(__DIR__ . '/../classes/query/runner.php');

use block_learnboard\query\guard;
use block_learnboard\query\refused;
use block_learnboard\query\runner;
use block_learnboard\query\validator;

$cases = require(__DIR__ . '/../tests/fixtures/validator_cases.php');
$validator = new validator('mdl_', 'moodle_lms');

$total = 0;
$failures = 0;

// Record one result: $ok says whether it passed, $what names the case.
$check = function (bool $ok, string $what) use (&$total, &$failures): void {
    $total++;
    if ($ok) {
        echo "ok    {$what}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$what}\n";
};

foreach ($cases['allowed'] as [$sql, $bindings]) {
    try {
        $validator->assert_allowed($sql, $bindings);
        $check(true, 'allowed  | ' . $sql);
    } catch (refused $e) {
        $check(false, 'allowed  | ' . $sql . ' -> refused: ' . $e->getMessage());
    }
}

foreach ($cases['attacks'] as [$sql, $bindings]) {
    try {
        $validator->assert_allowed($sql, $bindings);
        $check(false, 'attack   | ' . $sql . ' -> ALLOWED');
    } catch (refused $e) {
        $check(true, 'attack   | ' . $sql);
    }
}

foreach ($cases['addresses'] as [$ip, $list, $want]) {
    $check(guard::address_in_list($ip, $list) === $want, 'address  | ' . $ip . ' in ' . implode(',', $list));
}

foreach ($cases['columns'] as $column => $want) {
    $check(runner::is_secret_column((string) $column) === (bool) $want, 'column   | ' . $column);
}

foreach ($cases['transport'] as [$wwwroot, $server, $sslproxy, $forced, $want]) {
    $saved = $_SERVER;
    unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
    $_SERVER = $server + $_SERVER;
    $cfg = (object) ['wwwroot' => $wwwroot, 'sslproxy' => $sslproxy];
    $got = guard::transport_is_secure($cfg, $forced);
    $_SERVER = $saved;
    $check($got === $want, 'https    | ' . $wwwroot . ' ' . json_encode($server));
}

echo "\n{$total} cases, {$failures} failures\n";
exit($failures === 0 ? 0 : 1);
