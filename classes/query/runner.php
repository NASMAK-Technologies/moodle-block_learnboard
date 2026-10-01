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
 * Opens its own read-only database session and runs queries on it.
 *
 * Deliberately not Moodle's $DB: the query endpoint loads config.php only
 * (no full Moodle bootstrap, which is most of the cost per request), and
 * Moodle's DML would misread a ':' inside a date format as a named parameter.
 * A plain mysqli prepared statement also returns integers and floats as
 * numbers, which is what LearnBoard's own connection returns, so every chart
 * sees the same types whichever way the data arrived.
 *
 * The session is set read-only before any query runs. If the site admin has
 * given the plugin a separate read-only database user, that user is used
 * instead of Moodle's own, which makes a write impossible at the grant level
 * too.
 */
class runner {
    /** Most rows one query may return. */
    public const MAX_ROWS = 100000;

    /** Most seconds one statement may run. */
    public const MAX_SECONDS = 60;

    /** How many times a refused connection is tried. */
    public const CONNECT_ATTEMPTS = 4;

    /** Base wait between connection attempts, in milliseconds. */
    public const CONNECT_RETRY_MS = 120;

    /** @var \mysqli */
    private $db;

    /**
     * Opens this plugin's own read-only connection to the site database.
     *
     * @param \stdClass $cfg Moodle's $CFG (database settings only are read)
     * @param string $user optional read-only database user
     * @param string $pass its password
     * @throws \RuntimeException when the database cannot be reached
     */
    public function __construct($cfg, string $user = '', string $pass = '') {
        if (!in_array((string) $cfg->dbtype, ['mysqli', 'mariadb', 'auroramysql'], true)) {
            throw new \RuntimeException('unsupported_database');
        }

        mysqli_report(MYSQLI_REPORT_OFF);
        $options = is_array($cfg->dboptions ?? null) ? $cfg->dboptions : [];
        $port = !empty($options['dbport']) ? (int) $options['dbport'] : 3306;
        $socket = !empty($options['dbsocket']) && is_string($options['dbsocket']) ? $options['dbsocket'] : null;

        // Tried more than once, with a short wait between attempts. LearnBoard
        // asks for a dashboard's cards at the same moment, so a dozen requests
        // reach this site together and each wants its own database connection.
        // Shared hosting refuses the surplus outright, in no time at all, and
        // the same connection a fraction of a second later is accepted. One
        // attempt turned that into a dashboard of failed cards.
        $db = null;
        for ($attempt = 1; $attempt <= self::CONNECT_ATTEMPTS; $attempt++) {
            $db = mysqli_init();
            $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
            $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

            $ok = @$db->real_connect(
                (string) $cfg->dbhost,
                $user !== '' ? $user : (string) $cfg->dbuser,
                $user !== '' ? $pass : (string) $cfg->dbpass,
                (string) $cfg->dbname,
                $port,
                $socket
            );
            if ($ok) {
                break;
            }
            @$db->close();
            $db = null;
            if ($attempt < self::CONNECT_ATTEMPTS) {
                // Spread the retries so the refused requests do not all come
                // back at the same moment and hit the same wall.
                usleep((self::CONNECT_RETRY_MS * $attempt + random_int(0, self::CONNECT_RETRY_MS)) * 1000);
            }
        }
        if ($db === null) {
            throw new \RuntimeException('database_unreachable');
        }

        if (!$db->set_charset('utf8mb4')) {
            $db->close();
            throw new \RuntimeException('database_unreachable');
        }
        // A ceiling on each statement. MySQL names the time limit in
        // milliseconds, MariaDB in seconds; the one the server does not know
        // is simply refused and ignored.
        @$db->query('SET SESSION max_execution_time = ' . (self::MAX_SECONDS * 1000));
        @$db->query('SET SESSION max_statement_time = ' . self::MAX_SECONDS);

        $this->db = $db;
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
     * session, and the result is read back.
     *
     * @throws \RuntimeException read_only_unavailable when either cannot be guaranteed
     */
    public function lock_down(): void {
        $this->db->query('SET SESSION TRANSACTION READ ONLY');

        $readonly = null;
        foreach (['@@SESSION.transaction_read_only', '@@SESSION.tx_read_only'] as $variable) {
            $res = @$this->db->query('SELECT ' . $variable . ' AS ro');
            if ($res instanceof \mysqli_result) {
                $row = $res->fetch_assoc();
                $res->free();
                $readonly = (int) ($row['ro'] ?? 0) === 1;
                break;
            }
        }
        if ($readonly !== true) {
            throw new \RuntimeException('read_only_unavailable');
        }

        $unsafe = ['ANSI', 'ANSI_QUOTES', 'NO_BACKSLASH_ESCAPES', 'ORACLE', 'MSSQL', 'DB2', 'POSTGRESQL', 'MAXDB'];
        $modes = $this->sql_modes();
        if (array_intersect($modes, $unsafe) !== []) {
            $safe = array_values(array_diff($modes, $unsafe));
            $stmt = $this->db->prepare('SET SESSION sql_mode = ?');
            if ($stmt === false) {
                throw new \RuntimeException('read_only_unavailable');
            }
            $value = implode(',', $safe);
            $stmt->bind_param('s', $value);
            $stmt->execute();
            $stmt->close();
            if (array_intersect($this->sql_modes(), $unsafe) !== []) {
                throw new \RuntimeException('read_only_unavailable');
            }
        }
    }

    /**
     * The session's SQL modes, upper case.
     *
     * @return string[]
     */
    private function sql_modes(): array {
        $res = $this->db->query('SELECT @@SESSION.sql_mode AS m');
        if (!$res instanceof \mysqli_result) {
            throw new \RuntimeException('read_only_unavailable');
        }
        $row = $res->fetch_assoc();
        $res->free();

        return array_values(array_filter(array_map('trim', explode(',', strtoupper((string) ($row['m'] ?? ''))))));
    }

    /**
     * Run one read and return its rows as associative arrays, secrets removed.
     *
     * @param string $sql
     * @param array $bindings positional values for ? placeholders
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException with a generic message on a database error
     */
    public function select(string $sql, array $bindings): array {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException('query_failed: ' . $this->db->error);
        }

        if ($bindings !== []) {
            $types = '';
            $values = [];
            foreach ($bindings as $value) {
                if (is_int($value) || is_bool($value)) {
                    $types .= 'i';
                    $values[] = (int) $value;
                } else if (is_float($value)) {
                    $types .= 'd';
                    $values[] = $value;
                } else {
                    $types .= 's';
                    $values[] = $value === null ? null : (string) $value;
                }
            }
            $stmt->bind_param($types, ...$values);
        }

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('query_failed: ' . $error);
        }

        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();

            return [];
        }

        $rows = [];
        while (($row = $result->fetch_assoc()) !== null) {
            if (count($rows) >= self::MAX_ROWS) {
                $result->free();
                $stmt->close();
                throw new \RuntimeException('too_many_rows');
            }
            // A password, secret, key or token is never needed for reporting,
            // so a column whose name says it carries one is dropped, even from
            // SELECT * where the query check cannot see the column names.
            foreach (array_keys($row) as $column) {
                if (self::is_secret_column((string) $column)) {
                    unset($row[$column]);
                }
            }
            $rows[] = $row;
        }
        $result->free();
        $stmt->close();

        return $rows;
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
        $res = $this->db->query('SELECT VERSION() AS version, @@version_comment AS version_comment');
        $row = $res ? $res->fetch_assoc() : null;

        return [
            'version' => (string) ($row['version'] ?? ''),
            'version_comment' => (string) ($row['version_comment'] ?? ''),
        ];
    }

    /**
     * A plugin setting, read straight from the config table because the
     * endpoint does not load Moodle's config API.
     *
     * @param string $prefix
     * @param string $name
     * @return string
     */
    public function plugin_setting(string $prefix, string $name): string {
        $stmt = $this->db->prepare(
            'SELECT value FROM ' . $prefix . "config_plugins WHERE plugin = 'block_learnboard' AND name = ?"
        );
        if ($stmt === false) {
            return '';
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return (string) ($row['value'] ?? '');
    }

    /**
     * A core site setting, read straight from the config table.
     *
     * @param string $prefix
     * @param string $name
     * @return string
     */
    public function core_setting(string $prefix, string $name): string {
        $stmt = $this->db->prepare('SELECT value FROM ' . $prefix . 'config WHERE name = ?');
        if ($stmt === false) {
            return '';
        }
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return (string) ($row['value'] ?? '');
    }

    /**
     * Close the session.
     */
    public function close(): void {
        $this->db->close();
    }
}
