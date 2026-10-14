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
 *  - read any Moodle table outside the reporting tables LearnBoard uses, so
 *    site configuration, sessions and every kind of credential stay out of reach;
 *  - name a password, secret or key column, rename columns with a column
 *    list, or select every column with *;
 *  - list the server's schema beyond this site's own tables.
 *
 * Deny by default: every table a statement names must be on the allowlist
 * below, a name the statement defines itself (a WITH clause), or one of four
 * information_schema views restricted to this database. A FROM or JOIN the
 * walk cannot read completely is refused, not skipped. Every identifier that
 * carries this site's table prefix is checked against the allowlist as well,
 * wherever it appears, so no clause shape can bring in a table unseen.
 *
 * PHP 7.3 syntax, because the plugin supports Moodle 4.0.
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
     * The only Moodle tables (without prefix) a statement may read: the
     * reporting tables LearnBoard's screens use. Anything else, including every
     * table that holds configuration, sessions or credentials, is refused.
     */
    public const ALLOWED_TABLES = [
        'assign', 'attendance', 'bigbluebuttonbn', 'block_xp', 'book', 'certificate', 'certificate_issues', 'chat',
        'choice', 'cohort', 'cohort_members', 'context', 'course', 'course_categories', 'course_completion_criteria',
        'course_completions', 'course_modules', 'course_modules_completion', 'course_sections', 'customcert',
        'customcert_issues', 'data', 'enrol', 'feedback', 'files', 'folder', 'forum', 'glossary', 'grade_grades',
        'grade_items', 'groups', 'groups_members', 'h5pactivity', 'imscp', 'label', 'lesson', 'logstore_standard_log',
        'lti', 'modules', 'notifications', 'page', 'question', 'question_answers', 'question_attempt_steps',
        'question_attempts', 'questionnaire', 'questionnaire_question', 'questionnaire_response',
        'questionnaire_response_rank', 'questionnaire_response_text', 'quiz', 'quiz_attempts', 'resource', 'role',
        'role_assignments', 'role_capabilities', 'scorm', 'scorm_scoes_track', 'subsection', 'supervideo', 'survey',
        'tag', 'tag_instance', 'task_log', 'tool_certificate_issues', 'url', 'user', 'user_enrolments',
        'user_lastaccess', 'webgl', 'wiki', 'workshop',
    ];

    /** Keywords that end a table reference in a FROM list. */
    public const CLAUSE_KEYWORDS = [
        'WHERE', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL',
        'STRAIGHT_JOIN', 'ON', 'USING', 'UNION', 'WINDOW', 'EXCEPT', 'INTERSECT', 'OUTER', 'FULL',
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

        $this->assert_no_table_or_values_statement($tokens);
        $this->assert_no_star_projection($tokens);
        $this->assert_prefixed_names_allowed($tokens);

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
                $this->assert_table_allowed(substr($table, strlen($this->prefix)));
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
     * Refuses a table outside the reporting allowlist.
     *
     * @param string $bare table name without the prefix, lower case
     * @throws refused
     */
    private function assert_table_allowed(string $bare): void {
        if (!in_array($bare, self::ALLOWED_TABLES, true)) {
            throw new refused("the {$bare} table is not one LearnBoard reads");
        }
    }

    /**
     * Refuses MySQL's TABLE and VALUES statements anywhere in the statement:
     * "UNION TABLE mdl_config" reads a whole table without a FROM.
     *
     * @param array $tokens
     * @throws refused
     */
    private function assert_no_table_or_values_statement(array $tokens): void {
        foreach ($tokens as $token) {
            if ($token['type'] === 'ident' && in_array(strtoupper($token['value']), ['TABLE', 'VALUES'], true)) {
                throw new refused("the {$token['value']} statement is not allowed");
            }
        }
    }

    /**
     * Refuses SELECT * and t.*: a star returns columns the statement never
     * names, which the column checks cannot see. COUNT(*) and multiplication
     * are not projections and stay allowed.
     *
     * @param array $tokens
     * @throws refused
     */
    private function assert_no_star_projection(array $tokens): void {
        foreach ($tokens as $i => $token) {
            if ($token['type'] !== 'punct' || $token['value'] !== '*') {
                continue;
            }
            $prev = $tokens[$i - 1] ?? null;
            if ($prev === null) {
                continue;
            }
            $before = strtoupper($prev['value']);
            $keyword = $prev['type'] === 'ident' && in_array($before, ['SELECT', 'DISTINCT', 'ALL'], true);
            if ($before === ',' || $before === '.' || $keyword) {
                throw new refused('select named columns, not *');
            }
        }
    }

    /**
     * Every identifier carrying this site's table prefix must be an allowed
     * table, wherever it appears in the statement. Moodle tables always carry
     * the prefix, so a table cannot be brought in by a clause shape the walk
     * does not know.
     *
     * @param array $tokens
     * @throws refused
     */
    private function assert_prefixed_names_allowed(array $tokens): void {
        if ($this->prefix === '') {
            return;
        }
        foreach ($tokens as $i => $token) {
            if (!in_array($token['type'], ['ident', 'quoted'], true)) {
                continue;
            }
            $name = strtolower($token['value']);
            if (strpos($name, $this->prefix) !== 0) {
                continue;
            }
            // The column of a qualified name (alias.mdl_x) is not a table.
            if (($tokens[$i - 1]['value'] ?? '') === '.') {
                continue;
            }
            $this->assert_table_allowed(substr($name, strlen($this->prefix)));
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
     * A column list (WITH t (a, b) AS ...) is refused: it renames the columns
     * of the query inside, so a password column could leave under another name.
     *
     * @param array $tokens
     * @return array<int, string>
     * @throws refused
     */
    private function cte_names(array $tokens): array {
        $names = [];
        $count = count($tokens);
        $inwith = false;
        for ($i = 0; $i < $count; $i++) {
            $word = strtoupper($tokens[$i]['value']);
            if ($word === 'WITH' && $tokens[$i]['type'] === 'ident') {
                $inwith = true;
            }
            if (!$inwith || !in_array($word, ['WITH', 'RECURSIVE', ','], true)) {
                continue;
            }
            $name = $tokens[$i + 1] ?? null;
            if ($name === null || !in_array($name['type'], ['ident', 'quoted'], true)) {
                continue;
            }
            $next = $tokens[$i + 2]['value'] ?? '';
            if ($next === '(' && $word !== ',') {
                throw new refused('a column list after a WITH name is not allowed');
            }
            if (strtoupper($next) === 'AS' && ($tokens[$i + 3]['value'] ?? '') === '(') {
                $names[] = strtolower($name['value']);
            } else if ($next === '(' && $this->is_cte_column_list($tokens, $i + 2)) {
                throw new refused('a column list after a WITH name is not allowed');
            }
        }

        return $names;
    }

    /**
     * Whether "(" at $j opens a column list followed by AS (, the shape of a
     * WITH name with renamed columns, rather than a function call.
     *
     * @param array $tokens
     * @param int $j
     * @return bool
     */
    private function is_cte_column_list(array $tokens, int $j): bool {
        try {
            $close = $this->matching_paren($tokens, $j);
        } catch (refused $e) {
            return true;
        }

        return strtoupper($tokens[$close + 1]['value'] ?? '') === 'AS' && ($tokens[$close + 2]['value'] ?? '') === '(';
    }

    /**
     * Every table the statement reads: after FROM (the whole comma-separated
     * list) and after JOIN. A FROM inside EXTRACT(), TRIM() and similar is part
     * of that function, not a table. A derived table, (SELECT ...) or
     * (WITH ...), is stepped over here and its own FROM clauses are read when
     * the walk reaches them. Anything else after FROM or JOIN that the walk
     * cannot read completely is refused: a parenthesised table list, a table
     * function, a derived column list, a partition clause.
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
                while (true) {
                    $j = $this->read_from_item($tokens, $j, $refs);
                    if (($tokens[$j]['value'] ?? '') !== ',') {
                        break;
                    }
                    $j++;
                }
                $this->assert_reference_ends($tokens, $j);
                continue;
            }

            if ($word === 'JOIN' || $word === 'STRAIGHT_JOIN') {
                $j = $this->read_from_item($tokens, $i + 1, $refs);
                $this->assert_reference_ends($tokens, $j);
            }
        }

        return $refs;
    }

    /**
     * Reads one item of a FROM list or a JOIN: a table with optional schema
     * and alias, or a derived table with an alias. Index hints are not read,
     * so they end up refused: LearnBoard does not send them. Returns the
     * index of the token after it.
     *
     * @param array $tokens
     * @param int $j
     * @param array $refs collected table references
     * @return int
     * @throws refused
     */
    private function read_from_item(array $tokens, int $j, array &$refs): int {
        if (($tokens[$j]['value'] ?? '') === '(') {
            $inner = strtoupper($tokens[$j + 1]['value'] ?? '');
            if ($inner !== 'SELECT' && $inner !== 'WITH') {
                throw new refused('a parenthesised table list is not allowed');
            }
            $j = $this->matching_paren($tokens, $j) + 1;
            $j = $this->skip_alias($tokens, $j);
            if (($tokens[$j]['value'] ?? '') === '(') {
                throw new refused('a column list after a derived table is not allowed');
            }

            return $j;
        }

        [$ref, $j] = $this->read_table_reference($tokens, $j);
        $refs[] = $ref;
        $j = $this->skip_alias($tokens, $j);

        return $j;
    }

    /**
     * After a FROM list or a JOIN item the next token must end the reference:
     * a clause keyword, a closing parenthesis or the end of the statement.
     * Anything else is a shape the walk did not read.
     *
     * @param array $tokens
     * @param int $j
     * @throws refused
     */
    private function assert_reference_ends(array $tokens, int $j): void {
        $next = $tokens[$j] ?? null;
        if ($next === null || $next['value'] === ')') {
            return;
        }
        if ($next['type'] === 'ident' && in_array(strtoupper($next['value']), self::CLAUSE_KEYWORDS, true)) {
            return;
        }
        throw new refused('a FROM or JOIN clause could not be read completely');
    }

    /**
     * Index of the parenthesis closing the one at $j.
     *
     * @param array $tokens
     * @param int $j index of an opening parenthesis
     * @return int
     * @throws refused
     */
    private function matching_paren(array $tokens, int $j): int {
        if (($tokens[$j]['value'] ?? '') !== '(') {
            throw new refused('a table reference could not be read');
        }
        $depth = 0;
        $count = count($tokens);
        for ($k = $j; $k < $count; $k++) {
            if ($tokens[$k]['value'] === '(') {
                $depth++;
            } else if ($tokens[$k]['value'] === ')') {
                $depth--;
                if ($depth === 0) {
                    return $k;
                }
            }
        }
        throw new refused('unbalanced parentheses');
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
     * Step over "AS alias" or "alias" after a table name or a derived table.
     *
     * @param array $tokens
     * @param int $j
     * @return int
     */
    private function skip_alias(array $tokens, int $j): int {
        $stop = array_merge(self::CLAUSE_KEYWORDS, ['USE', 'FORCE', 'IGNORE', 'FOR', 'LOCK', 'INTO', 'PARTITION']);
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
