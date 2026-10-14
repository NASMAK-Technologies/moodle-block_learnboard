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
 * Runs LearnBoard's read queries against this site's own database.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\query;

/**
 * Runs queries through Moodle's DML API on a session set read-only.
 *
 * Moodle's own connection ($DB) is used, unless the site admin has given the
 * plugin a separate SELECT-only database user, in which case a second DML
 * connection is opened with it, so a write is impossible at the grant level
 * too.
 *
 * LearnBoard writes its reporting queries in MySQL's dialect, so the connector
 * answers on the MySQL family (MySQL, MariaDB, Aurora MySQL) and says so
 * plainly on anything else.
 */
class runner {
    /** Most rows one query may return. */
    public const MAX_ROWS = 100000;

    /** Most seconds one statement may run. */
    public const MAX_SECONDS = 60;

    /** @var \moodle_database */
    private $db;

    /** @var bool whether this runner opened its own connection, which it must close */
    private $owned = false;

    /**
     * Picks the connection the queries run on.
     *
     * @param \moodle_database $db Moodle's $DB
     * @param \stdClass $cfg Moodle's $CFG
     * @param string $user optional SELECT-only database user
     * @param string $pass its password
     * @throws \RuntimeException unsupported_database or database_unreachable
     */
    public function __construct(\moodle_database $db, $cfg, string $user = '', string $pass = '') {
        if ($db->get_dbfamily() !== 'mysql') {
            throw new \RuntimeException('unsupported_database');
        }
        if ($user === '') {
            $this->db = $db;
            return;
        }

        $readonly = \moodle_database::get_driver_instance((string) $cfg->dbtype, (string) $cfg->dblibrary);
        if (!$readonly) {
            throw new \RuntimeException('database_unreachable');
        }
        try {
            $readonly->connect(
                (string) $cfg->dbhost,
                $user,
                $pass,
                (string) $cfg->dbname,
                (string) $cfg->prefix,
                is_array($cfg->dboptions ?? null) ? $cfg->dboptions : []
            );
        } catch (\Throwable $e) {
            throw new \RuntimeException('database_unreachable');
        }
        $this->db = $readonly;
        $this->owned = true;
    }

    /**
     * Put the session into the state the query check assumes, and prove it.
     *
     * Read-only: set, then read back, and refused if the server did not take
     * it. SQL mode: the query check reads "..." as a string and a backslash as
     * an escape, which is MySQL's default. ANSI_QUOTES (or a mode that
     * includes it) turns "..." into a column name and NO_BACKSLASH_ESCAPES
     * changes where a string ends, either of which would let a statement mean
     * something other than what was checked. Both are removed for this
     * session, and the result is read back. A ceiling is put on each
     * statement's run time.
     *
     * @throws \RuntimeException read_only_unavailable when either cannot be guaranteed
     */
    public function lock_down(): void {
        try {
            $this->db->execute('SET SESSION TRANSACTION READ ONLY');
        } catch (\Throwable $e) {
            throw new \RuntimeException('read_only_unavailable');
        }

        $readonly = null;
        foreach (['@@SESSION.transaction_read_only', '@@SESSION.tx_read_only'] as $variable) {
            try {
                $readonly = (int) $this->db->get_field_sql('SELECT ' . $variable) === 1;
                break;
            } catch (\Throwable $e) {
                continue;
            }
        }
        if ($readonly !== true) {
            throw new \RuntimeException('read_only_unavailable');
        }

        $unsafe = ['ANSI', 'ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'ORACLE', 'MSSQL', 'DB2', 'POSTGRESQL', 'MAXDB'];
        $modes = $this->sql_modes();
        if (array_intersect($modes, $unsafe) !== []) {
            $safe = implode(',', array_values(array_diff($modes, $unsafe)));
            try {
                $this->db->execute('SET SESSION sql_mode = ?', [$safe]);
            } catch (\Throwable $e) {
                throw new \RuntimeException('read_only_unavailable');
            }
            if (array_intersect($this->sql_modes(), $unsafe) !== []) {
                throw new \RuntimeException('read_only_unavailable');
            }
        }

        // MySQL names the time limit in milliseconds, MariaDB in seconds; the
        // one the server does not know is refused and ignored.
        foreach (['SET SESSION max_execution_time = ' . (self::MAX_SECONDS * 1000),
                'SET SESSION max_statement_time = ' . self::MAX_SECONDS] as $limit) {
            try {
                $this->db->execute($limit);
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    /**
     * The session's SQL modes, upper case.
     *
     * @return string[]
     */
    private function sql_modes(): array {
        try {
            $mode = (string) $this->db->get_field_sql('SELECT @@SESSION.sql_mode');
        } catch (\Throwable $e) {
            throw new \RuntimeException('read_only_unavailable');
        }

        return array_values(array_filter(array_map('trim', explode(',', strtoupper($mode)))));
    }

    /**
     * Run one read and return its rows as associative arrays, secrets removed.
     *
     * Moodle's DML returns every value as a string. LearnBoard's own MySQL
     * connection returns whole numbers and decimals as numbers, so they are
     * turned back into numbers here, and every chart sees the same types
     * whichever way the data arrived. A value with a leading zero (a postcode,
     * an ID number) stays text.
     *
     * @param string $sql
     * @param array $bindings positional values for ? placeholders
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException on a database error or too many rows
     */
    public function select(string $sql, array $bindings): array {
        $params = [];
        foreach ($bindings as $value) {
            $params[] = is_bool($value) ? (int) $value : $value;
        }

        $rs = $this->db->get_recordset_sql($sql, $params);
        $rows = [];
        try {
            foreach ($rs as $record) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new \RuntimeException('too_many_rows');
                }
                $row = [];
                foreach ((array) $record as $column => $value) {
                    // A password, secret, key or token is never needed for
                    // reporting, so a column whose name says it carries one is
                    // dropped. The query check already refuses naming one,
                    // renaming one and SELECT *; this is the second line.
                    if (self::is_secret_column((string) $column)) {
                        continue;
                    }
                    $row[$column] = self::native($value);
                }
                $rows[] = $row;
            }
        } finally {
            $rs->close();
        }

        return $rows;
    }

    /**
     * A database string as the number it is, or unchanged.
     *
     * @param mixed $value
     * @return mixed
     */
    public static function native($value) {
        if (!is_string($value)) {
            return $value;
        }
        if (preg_match('/^-?(0|[1-9][0-9]{0,17})$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^-?(0|[1-9][0-9]{0,14})\.[0-9]{1,15}$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * Whether a result column's name marks it as carrying a credential.
     *
     * @param string $column
     * @return bool
     */
    public static function is_secret_column(string $column): bool {
        $name = strtolower($column);
        foreach (['password', 'secret', 'sesskey', 'privatekey', 'private_key', 'apikey', 'api_key'] as $part) {
            if (strpos($name, $part) !== false) {
                return true;
            }
        }

        return in_array($name, ['salt', 'token', 'confirmtoken', 'refreshtoken', 'accesstoken', 'datakey',
            'moderatorpass', 'viewerpass', 'resourcekey'], true);
    }

    /**
     * What the database reports about itself, for LearnBoard's connection test.
     *
     * @return array{version: string, version_comment: string}
     */
    public function server_info(): array {
        $info = $this->db->get_server_info();
        $comment = '';
        try {
            $comment = (string) $this->db->get_field_sql('SELECT @@version_comment');
        } catch (\Throwable $e) {
            $comment = '';
        }

        return [
            'version' => (string) ($info['version'] ?? ''),
            'version_comment' => $comment,
        ];
    }

    /**
     * Close the connection this runner opened. Moodle's own is left to Moodle.
     */
    public function close(): void {
        if ($this->owned) {
            $this->db->dispose();
        }
    }
}
