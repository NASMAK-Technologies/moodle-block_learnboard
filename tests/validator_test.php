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

namespace block_learnboard;

use block_learnboard\query\guard;
use block_learnboard\query\refused;
use block_learnboard\query\runner;
use block_learnboard\query\validator;

/**
 * What the connector will and will not run.
 *
 * The plugin accepts SQL from LearnBoard, so the validator is the whole of the
 * site's protection: everything it lets through runs against the live Moodle
 * database. These cases are therefore written as attacks rather than as
 * examples, and the ones that must pass are here too, because a validator that
 * refuses everything is equally useless.
 *
 * The cases live in tests/fixtures/validator_cases.php and are shared with
 * cli/validator_attack_check.php, which runs the same list without a Moodle
 * install.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_learnboard\query\validator
 */
final class validator_test extends \advanced_testcase {
    /** @var string The prefix and database name the cases are written against. */
    private const PREFIX = 'mdl_';

    /** @var string */
    private const DBNAME = 'moodle_lms';

    /**
     * The shared allowed and attack cases.
     *
     * @return array<string, mixed>
     */
    private static function cases(): array {
        return require(__DIR__ . '/fixtures/validator_cases.php');
    }

    /**
     * Queries the validator must accept.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public static function allowed_provider(): array {
        $out = [];
        foreach (self::cases()['allowed'] as $i => [$sql, $bindings]) {
            $out[$i . ': ' . substr($sql, 0, 60)] = [$sql, $bindings];
        }
        return $out;
    }

    /**
     * Queries the validator must refuse.
     *
     * @return array<string, array{0: string, 1: array}>
     */
    public static function attack_provider(): array {
        $out = [];
        foreach (self::cases()['attacks'] as $i => [$sql, $bindings]) {
            $out[$i . ': ' . substr($sql, 0, 60)] = [$sql, $bindings];
        }
        return $out;
    }

    /**
     * A query LearnBoard legitimately sends is answered.
     *
     * @dataProvider allowed_provider
     * @param string $sql
     * @param array $bindings
     */
    public function test_a_legitimate_query_is_allowed(string $sql, array $bindings): void {
        $validator = new validator(self::PREFIX, self::DBNAME);

        $validator->assert_allowed($sql, $bindings);

        // The assert_allowed call throws on refusal, so reaching here is the assertion.
        $this->assertTrue(true);
    }

    /**
     * Everything else is refused, and refused by this validator rather than by
     * the database being asked and saying no.
     *
     * @dataProvider attack_provider
     * @param string $sql
     * @param array $bindings
     */
    public function test_an_attack_is_refused(string $sql, array $bindings): void {
        $validator = new validator(self::PREFIX, self::DBNAME);

        $this->expectException(refused::class);

        $validator->assert_allowed($sql, $bindings);
    }

    /**
     * A column that holds a secret is dropped from the answer even when the
     * query never named it, which is what SELECT * does.
     */
    public function test_a_secret_column_is_dropped_whatever_the_query_asked_for(): void {
        foreach (self::cases()['columns'] as $column => $secret) {
            $this->assertSame(
                (bool) $secret,
                runner::is_secret_column((string) $column),
                "column {$column}"
            );
        }
    }

    /**
     * The endpoint answers over HTTPS only, unless a local install has
     * deliberately allowed otherwise.
     */
    public function test_the_endpoint_answers_only_over_https(): void {
        $saved = $_SERVER;

        foreach (self::cases()['transport'] as [$wwwroot, $server, $sslproxy, $forced, $want]) {
            unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);
            $_SERVER = $server + $_SERVER;
            $cfg = (object) ['wwwroot' => $wwwroot, 'sslproxy' => $sslproxy];

            $this->assertSame(
                $want,
                guard::transport_is_secure($cfg, $forced),
                $wwwroot . ' ' . json_encode($server)
            );

            $_SERVER = $saved;
        }
    }

    /**
     * Only the addresses the administrator listed may ask at all.
     */
    public function test_only_a_listed_address_may_ask(): void {
        foreach (self::cases()['addresses'] as [$ip, $list, $want]) {
            $this->assertSame(
                $want,
                guard::address_in_list($ip, $list),
                $ip . ' in ' . implode(',', $list)
            );
        }
    }
}
