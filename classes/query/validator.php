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
 * Read-only and data-scope enforcement for queries LearnBoard sends through the plugin.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\query;

/**
 * The plugin's own check on every statement before it reaches the database.
 *
 * LearnBoard already refuses anything that is not a read before it sends a
 * query. This check does not rely on that. It is applied on the Moodle server,
 * so a leaked connector key still cannot be used to:
 *
 *  - write, lock, sleep, read files or change session state;
 *  - read another database on the same server, or the server's own schemas;
 *  - read Moodle tables that hold secrets (site configuration, sessions, web
 *    service and LTI credentials, private keys, MFA secrets);
 *  - name a password, secret or key column at all;
 *  - list the server's schema beyond this site's own tables.
 *
 * Every table a statement reads must be one of this site's own prefixed
 * tables, a name the statement defines itself (a WITH clause), or one of
 * four information_schema views restricted to this database.
 *
 * Pessimistic by design: anything that cannot be understood is refused. PHP
 * 7.3 syntax, because the plugin supports Moodle 4.0.
 */
class validator {
    /** Verbs a statement may start with. SHOW, DESCRIBE and EXPLAIN are not needed and not allowed. */
    public const ALLOWED_LEADING_VERBS = ['SELECT', 'WITH'];

    /** Tokens that mean a write, a lock, a delay or a side effect even inside a SELECT. */
    public const FORBIDDEN_TOKENS = [
        'INTO OUTFILE', 'INTO DUMPFILE', 'LOAD DATA', 'LOAD_FILE', 'LOAD XML', 'INTO @', ' INTO ',
        'LOCK TABLES', 'UNLOCK TABLES', 'FOR UPDATE', 'FOR SHARE', 'LOCK IN SHARE MODE', 'NOWAIT', 'SKIP LOCKED',
        'INSERT', 'UPDATE ', 'DELETE ', 'TRUNCATE', 'DROP ', 'CREATE ', 'ALTER ', 'RENAME ',
        'REPLACE ', 'GRANT ', 'REVOKE ', 'CALL ', 'HANDLER ', 'KILL ', 'SET ', 'PREPARE ', 'EXECUTE ', 'DEALLOCATE ',
        'PROCEDURE ANALYSE', '@',
    ];

    /**
     * Functions that delay, lock, wait on replication, read files or run
     * commands. Checked on the token stream, so a space before the
     * parenthesis ("SLEEP (30)") does not hide one.
     */
    public const FORBIDDEN_FUNCTIONS = [
        'benchmark', 'sleep', 'get_lock', 'release_lock', 'release_all_locks', 'is_free_lock', 'is_used_lock',
        'master_pos_wait', 'source_pos_wait', 'wait_for_executed_gtid_set', 'wait_until_sql_thread_after_gtids',
        'load_file', 'sys_exec', 'sys_eval', 'sys_get', 'sys_set', 'user', 'current_user', 'session_user',
        'system_user', 'connection_id', 'last_insert_id', 'uuid_short', 'found_rows', 'row_count',
    ];

    /**
     * Moodle tables (without prefix) that hold secrets or credentials.
     * LearnBoard's reports never read them.
     */
    public const FORBIDDEN_TABLES = [
        'config', 'config_plugins', 'config_log', 'sessions', 'cache_flags',
        'external_tokens', 'external_services_users', 'user_private_key',
        'user_password_resets', 'user_password_history', 'user_devices',
        'oauth2_issuer', 'oauth2_endpoint', 'oauth2_system_account', 'oauth2_access_token', 'oauth2_refresh_token',
        'auth_oauth2_linked_login', 'auth_lti_linked_login',
        'tool_mfa', 'tool_mfa_secrets', 'tool_mfa_auth_token',
        'lti_types_config', 'lti_access_tokens', 'lti_tool_proxies', 'enrol_lti_tools', 'enrol_lti_lti2_consumer',
        'enrol_lti_app_registration', 'enrol_lti_deployment',
        'repository_instance_config', 'portfolio_instance_config', 'portfolio_instance_user',
        'mnet_host', 'mnet_session', 'mnet_sso_access_control',
        'badge_backpack', 'badge_external_backpack', 'message_airnotifier_devices',
        'task_adhoc', 'backup_controllers', 'webdav_locks',
        // Account unlock and repository tokens live in preferences.
        'user_preferences',
        // Payment, AI and SMS provider credentials (Moodle 3.10, 4.5, 4.5).
        'payment_gateways', 'ai_providers', 'sms_gateways',
        'registration_hubs', 'messageinbound_datakeys', 'badge_backpack_oauth2',
        'repository_onedrive_access', 'portfolio_mahara_queue', 'portfolio_tempdata',
    ];

    /** Column names that are never readable, whatever table they are in. */
    public const FORBIDDEN_IDENTIFIERS = [
        'password', 'passwordhash', 'secret', 'sesskey', 'salt', 'privatekey', 'private_key',
        'apikey', 'api_key', 'clientsecret', 'client_secret', 'consumersecret', 'consumer_secret', 'secretkey',
        'token', 'confirmtoken', 'refreshtoken', 'datakey', 'moderatorpass', 'viewerpass', 'resourcekey',
    ];

    /** The only information_schema views, and only for this database. */
    public const INFORMATION_SCHEMA_VIEWS = ['tables', 'columns', 'key_column_usage', 'statistics'];

    /** Functions whose own syntax contains FROM, which is not a table. */
    public const FUNCTIONS_WITH_FROM = ['extract', 'trim', 'substring', 'substr', 'position', 'overlay'];

    /** @var string */
    private $prefix;

    /** @var string */
    private $dbname;

    /**
     * Sets the table prefix and database the queries must stay within.
     *
     * @param string $prefix the Moodle table prefix, e.g. mdl_
     * @param string $dbname this site's database name
     */
    public function __construct(string $prefix, string $dbname) {
        $this->prefix = strtolower($prefix);
        $this->dbname = $dbname;
    }

    /**
     * Refuse the statement unless it is a single read of this site's permitted data.
     *
     * @param string $sql
     * @param array $bindings positional bindings, needed to check information_schema scoping
     * @throws refused
     */
    public function assert_allowed(string $sql, array $bindings = []): void {
        $trimmed = trim($sql);
        if ($trimmed === '') {
            throw new refused('empty query');
        }
        if (strlen($trimmed) > 200000) {
            throw new refused('query too long');
        }
        // MySQL executes /*! ... */ even though it looks like a comment.
        if (strpos($trimmed, '/*!') !== false || strpos($trimmed, '/*+') !== false) {
            throw new refused('executable comment is not allowed');
        }
        if (strpos($trimmed, "\0") !== false) {
            throw new refused('control characters are not allowed');
        }

        $stripped = trim($this->strip_comments_and_strings($trimmed));
        if ($stripped === '') {
            throw new refused('query is only comments or strings');
        }

        $body = rtrim($stripped, "; \t\n\r\0\x0B");
        if (strpos($body, ';') !== false) {
            throw new refused('multiple statements are not allowed');
        }

        $lead = ltrim($body, "( \t\n\r");
        $verb = strtoupper((string) (preg_split('/\s+/', $lead, 2)[0] ?? ''));
        if (!in_array($verb, self::ALLOWED_LEADING_VERBS, true)) {
            throw new refused("leading verb '{$verb}' is not a read");
        }

        $upper = ' ' . strtoupper((string) preg_replace('/\s+/', ' ', $body)) . ' ';
        foreach (self::FORBIDDEN_TOKENS as $needle) {
            if (strpos($upper, $needle) !== false) {
                throw new refused("forbidden token '" . trim($needle) . "'");
            }
        }

        $tokens = $this->tokenize($body);

        foreach ($tokens as $index => $token) {
            if (
                ($token['type'] === 'ident' || $token['type'] === 'quoted')
                    && in_array(strtolower($token['value']), self::FORBIDDEN_IDENTIFIERS, true)
            ) {
                throw new refused("the {$token['value']} column is not readable");
            }
            if (
                $token['type'] === 'ident' && ($tokens[$index + 1]['value'] ?? '') === '('
                    && in_array(strtolower($token['value']), self::FORBIDDEN_FUNCTIONS, true)
            ) {
                throw new refused("the {$token['value']} function is not allowed");
            }
        }

        $ctes = $this->cte_names($tokens);
        $usesinfoschema = false;
        foreach ($this->table_references($tokens) as $ref) {
            $schema = $ref['schema'] === null ? null : strtolower($ref['schema']);
            $table = strtolower($ref['table']);

            if ($schema === null) {
                if (in_array($table, $ctes, true) || $table === 'dual') {
                    continue;
                }
                if ($this->prefix === '' || strpos($table, $this->prefix) !== 0) {
                    throw new refused("table '{$ref['table']}' is not one of this site's tables");
                }
                $bare = substr($table, strlen($this->prefix));
                if (in_array($bare, self::FORBIDDEN_TABLES, true)) {
                    throw new refused("the {$bare} table is not readable");
                }
                continue;
            }

            if ($schema === 'information_schema' && in_array($table, self::INFORMATION_SCHEMA_VIEWS, true)) {
                $usesinfoschema = true;
                continue;
            }

            throw new refused("reading from schema '{$ref['schema']}' is not allowed");
        }

        // An information_schema read is scoped by the caller's WHERE clause, so that
        // clause is held to one exact shape: TABLE_SCHEMA equal to this
        // database, with no OR or UNION that could widen it.
        foreach ($tokens as $token) {
            if ($token['type'] === 'ident' && strtolower($token['value']) === 'information_schema') {
                $usesinfoschema = true;
            }
        }
        if ($usesinfoschema) {
            $this->assert_information_schema_scoped($tokens, $bindings);
        }
    }

    /**
     * Refuses an information_schema read that is not limited to this site's database.
     *
     * @param array $tokens
     * @param array $bindings
     * @throws refused
     */
    private function assert_information_schema_scoped(array $tokens, array $bindings): void {
        $words = array_map(function ($t) {
            return $t['type'] === 'ident' ? strtoupper($t['value']) : $t['value'];
        }, $tokens);
        if (in_array('OR', $words, true) || in_array('UNION', $words, true) || in_array('XOR', $words, true)) {
            throw new refused('information_schema queries may not use OR or UNION');
        }

        $count = count($tokens);
        $placeholder = 0;
        $scoped = false;
        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]['value'] === '?') {
                $placeholder++;
            }
            if (strtoupper($tokens[$i]['value']) !== 'TABLE_SCHEMA' || ($tokens[$i + 1]['value'] ?? '') !== '=') {
                continue;
            }
            $next = $tokens[$i + 2]['value'] ?? '';
            if (strtoupper($next) === 'DATABASE' && ($tokens[$i + 3]['value'] ?? '') === '(' && ($tokens[$i + 4]['value'] ?? '') === ')') {
                $scoped = true;
                continue;
            }
            if ($next === '?') {
                // The binding for this placeholder: those before it, plus this one.
                $value = $bindings[$placeholder] ?? null;
                if ((string) $value !== $this->dbname) {
                    throw new refused('information_schema may only be read for this site\'s database');
                }
                $scoped = true;
                continue;
            }
            throw new refused('information_schema may only be read for this site\'s database');
        }
        if (!$scoped) {
            throw new refused('information_schema queries must be limited to this site\'s database');
        }
    }

    /**
     * Break the (comment- and string-stripped) statement into identifiers,
     * quoted identifiers, placeholders and punctuation.
     *
     * @param string $body
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $body): array {
        preg_match_all('/`((?:[^`]|``)*)`|([A-Za-z_][A-Za-z0-9_$]*)|(\d+(?:\.\d+)?)|(\?)|(\'\'|"")|([(),.=*<>!+\-\/%]|\S)/', $body, $matches, PREG_SET_ORDER);
        $tokens = [];
        foreach ($matches as $m) {
            if (isset($m[1]) && $m[1] !== '' && $m[0][0] === '`') {
                $tokens[] = ['type' => 'quoted', 'value' => str_replace('``', '`', $m[1])];
            } else if (isset($m[2]) && $m[2] !== '') {
                $tokens[] = ['type' => 'ident', 'value' => $m[2]];
            } else if (isset($m[3]) && $m[3] !== '') {
                $tokens[] = ['type' => 'number', 'value' => $m[3]];
            } else if (isset($m[4]) && $m[4] !== '') {
                $tokens[] = ['type' => 'punct', 'value' => '?'];
            } else if (isset($m[5]) && $m[5] !== '') {
                $tokens[] = ['type' => 'string', 'value' => "''"];
            } else {
                $tokens[] = ['type' => 'punct', 'value' => $m[0]];
            }
        }

        return $tokens;
    }

    /**
     * Names the statement defines with WITH name AS ( ... ).
     *
     * @param array $tokens
     * @return array<int, string>
     */
    private function cte_names(array $tokens): array {
        $names = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $word = strtoupper($tokens[$i]['value']);
            if (!in_array($word, ['WITH', 'RECURSIVE', ','], true)) {
                continue;
            }
            $name = $tokens[$i + 1] ?? null;
            if ($name === null || !in_array($name['type'], ['ident', 'quoted'], true)) {
                continue;
            }
            $k = $i + 2;
            if (($tokens[$k]['value'] ?? '') === '(') {
                // An optional column list: name (a, b, c) AS (...).
                while ($k < $count && $tokens[$k]['value'] !== ')') {
                    $k++;
                }
                $k++;
            }
            if (strtoupper($tokens[$k]['value'] ?? '') === 'AS' && ($tokens[$k + 1]['value'] ?? '') === '(') {
                $names[] = strtolower($name['value']);
            }
        }

        return $names;
    }

    /**
     * Every table the statement reads: after FROM (including comma-separated
     * lists) and after JOIN. A FROM inside EXTRACT(), TRIM() and similar is
     * part of that function, not a table; a derived table in parentheses is
     * checked as its own FROM clauses when the walk reaches them.
     *
     * @param array $tokens
     * @return array<int, array{schema: ?string, table: string}>
     * @throws refused
     */
    private function table_references(array $tokens): array {
        $refs = [];
        $stack = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $value = $tokens[$i]['value'];
            if ($value === '(') {
                $prev = $tokens[$i - 1] ?? null;
                $stack[] = ($prev !== null && $prev['type'] === 'ident') ? strtolower($prev['value']) : '';
                continue;
            }
            if ($value === ')') {
                array_pop($stack);
                continue;
            }
            if ($tokens[$i]['type'] !== 'ident') {
                continue;
            }
            $word = strtoupper($value);

            if ($word === 'FROM') {
                $context = end($stack);
                if ($context !== false && in_array($context, self::FUNCTIONS_WITH_FROM, true)) {
                    continue;
                }
                $j = $i + 1;
                while ($j < $count) {
                    if (($tokens[$j]['value'] ?? '') === '(') {
                        break; // Derived table: its own FROM is checked by the walk.
                    }
                    [$ref, $j] = $this->read_table_reference($tokens, $j);
                    $refs[] = $ref;
                    $j = $this->skip_alias($tokens, $j);
                    if (($tokens[$j]['value'] ?? '') !== ',') {
                        break;
                    }
                    $j++;
                }
                continue;
            }

            if ($word === 'JOIN' || $word === 'STRAIGHT_JOIN') {
                if (($tokens[$i + 1]['value'] ?? '') === '(') {
                    continue;
                }
                [$ref, $j] = $this->read_table_reference($tokens, $i + 1);
                $refs[] = $ref;
            }
        }

        return $refs;
    }

    /**
     * Reads one table reference, with its optional schema, starting at a token.
     *
     * @param array $tokens
     * @param int $j
     * @return array{0: array{schema: ?string, table: string}, 1: int}
     * @throws refused
     */
    private function read_table_reference(array $tokens, int $j): array {
        $first = $tokens[$j] ?? null;
        if ($first === null || !in_array($first['type'], ['ident', 'quoted'], true)) {
            throw new refused('a table reference could not be read');
        }
        if (($tokens[$j + 1]['value'] ?? '') === '.') {
            $second = $tokens[$j + 2] ?? null;
            if ($second === null || !in_array($second['type'], ['ident', 'quoted'], true)) {
                throw new refused('a table reference could not be read');
            }
            if (($tokens[$j + 3]['value'] ?? '') === '.') {
                throw new refused('a table reference could not be read');
            }

            return [['schema' => $first['value'], 'table' => $second['value']], $j + 3];
        }

        return [['schema' => null, 'table' => $first['value']], $j + 1];
    }

    /**
     * Step over "AS alias" or "alias" after a table name.
     *
     * @param array $tokens
     * @param int $j
     * @return int
     */
    private function skip_alias(array $tokens, int $j): int {
        $stop = ['WHERE', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL',
            'STRAIGHT_JOIN', 'ON', 'USING', 'UNION', 'WINDOW', 'FOR', 'LOCK', 'INTO', 'FULL', 'OUTER', 'USE', 'FORCE', 'IGNORE'];
        if (strtoupper($tokens[$j]['value'] ?? '') === 'AS') {
            $j++;
        }
        $candidate = $tokens[$j] ?? null;
        if (
            $candidate !== null && in_array($candidate['type'], ['ident', 'quoted'], true)
                && !in_array(strtoupper($candidate['value']), $stop, true)
        ) {
            $j++;
        }

        return $j;
    }

    /**
     * Reduce the statement to its skeleton: comments removed, every string
     * literal replaced by an empty one, identifiers left in place.
     *
     * One left-to-right pass, in the order MySQL itself reads the text. Doing
     * comments and strings as two separate passes lets each hide the other:
     * a string holding "-- " made the rest of the line look like a comment,
     * so everything after it (a password column, another table) was never
     * checked while MySQL ran it all.
     *
     * The session this plugin opens clears ANSI_QUOTES and
     * NO_BACKSLASH_ESCAPES, so double quotes delimit strings and a backslash
     * escapes, exactly as read here.
     *
     * @param string $sql
     * @return string
     * @throws refused
     */
    private function strip_comments_and_strings(string $sql): string {
        $out = '';
        $len = strlen($sql);
        $i = 0;
        while ($i < $len) {
            $c = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($c === "'" || $c === '"') {
                $quote = $c;
                $i++;
                $closed = false;
                while ($i < $len) {
                    if ($sql[$i] === "\\") {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === $quote) {
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                            $i += 2;
                            continue;
                        }
                        $closed = true;
                        $i++;
                        break;
                    }
                    $i++;
                }
                if (!$closed) {
                    throw new refused('unterminated string');
                }
                $out .= $quote . $quote;
                continue;
            }

            if ($c === '`') {
                $end = $i + 1;
                while (true) {
                    $end = strpos($sql, '`', $end);
                    if ($end === false) {
                        throw new refused('unterminated identifier');
                    }
                    if ($end + 1 < $len && $sql[$end + 1] === '`') {
                        $end += 2;
                        continue;
                    }
                    break;
                }
                $out .= substr($sql, $i, $end - $i + 1);
                $i = $end + 1;
                continue;
            }

            if ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new refused('unterminated comment');
                }
                $out .= ' ';
                $i = $end + 2;
                continue;
            }

            if ($c === '#' || ($c === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]) || ctype_cntrl($sql[$i + 2])))) {
                $end = strpos($sql, "\n", $i);
                $out .= ' ';
                $i = $end === false ? $len : $end;
                continue;
            }

            $out .= $c;
            $i++;
        }

        return $out;
    }
}
