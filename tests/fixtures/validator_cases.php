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
 * The cases the query validator is judged by, in one place.
 *
 * They are read twice: by the PHPUnit test Moodle's own CI runs, and by
 * cli/validator_attack_check.php, which needs no Moodle install and so can run
 * on a reviewer's laptop and in LearnBoard's pipeline. Holding them here keeps
 * the two honest: a case added for one is a case the other must also pass.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$allowed = [
    ['SELECT id, username, email FROM mdl_user WHERE deleted = ? AND id > ?', [0, 1]],
    ['SELECT u.id, COUNT(*) AS c FROM mdl_user u JOIN mdl_user_enrolments ue ON ue.userid = u.id LEFT JOIN `mdl_enrol` e ON e.id = ue.enrolid GROUP BY u.id', []],
    ['SELECT a.id FROM mdl_course a, mdl_course_categories b WHERE a.category = b.id', []],
    ['SELECT x.c FROM (SELECT COUNT(*) AS c FROM mdl_logstore_standard_log WHERE timecreated > ?) x', [1]],
    ['WITH recent AS (SELECT userid FROM mdl_logstore_standard_log) SELECT COUNT(*) FROM recent', []],
    ['WITH RECURSIVE tree AS (SELECT id, parent FROM mdl_course_categories) SELECT id, parent FROM tree', []],
    ['SELECT c.id, ROUND(SUM(cc.timecompleted > 0) * 100 / COUNT(*), 1) AS pct FROM mdl_course c '
        . 'LEFT JOIN mdl_course_completions cc ON cc.course = c.id GROUP BY c.id', []],
    ['SELECT x.userid, x.n FROM (SELECT userid, COUNT(*) AS n FROM mdl_logstore_standard_log GROUP BY userid) x '
        . 'JOIN mdl_user u ON u.id = x.userid WHERE u.deleted = 0', []],
    ['SELECT rc.roleid FROM mdl_role_capabilities rc JOIN mdl_context c ON c.id = rc.contextid '
        . 'WHERE rc.capability = ? AND c.contextlevel = ?', ['moodle/site:accessallgroups', 10]],
    ['SELECT EXTRACT(YEAR FROM FROM_UNIXTIME(timecreated)) AS y, TRIM(LEADING \'0\' FROM idnumber) FROM mdl_course', []],
    ["SELECT DATE_FORMAT(FROM_UNIXTIME(timecreated), '%H:%i -- not a comment') FROM mdl_course", []],
    ['SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ? AND TABLE_TYPE = ?', ['moodle_lms', 'mdl_%', 'BASE TABLE']],
    ['SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', ['mdl_task_log']],
    ['SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE ?', ['moodle_lms', 'mdl_%']],
    ['SELECT id FROM mdl_user WHERE id IN (SELECT userid FROM mdl_role_assignments WHERE roleid = ?)', [5]],
    ['SELECT 1', []],
];

$attacks = [
    // Hiding the rest of the statement inside a "comment" that is really a string.
    ["SELECT '-- ', password FROM mdl_user", []],
    ["SELECT '#', username FROM otherdb.mdl_user", []],
    ["SELECT 'a\\\\', password FROM mdl_user", []],
    ["SELECT \"/*\", password, \"*/\" FROM mdl_user", []],
    // Other databases and server schemas.
    ['SELECT * FROM otherdb.mdl_user', []],
    ['SELECT * FROM `otherdb`.`mdl_user`', []],
    ['SELECT * FROM mysql.user', []],
    ['SELECT * FROM mdl_user u, otherdb.people p', []],
    ['SELECT * FROM mdl_user u JOIN (SELECT * FROM performance_schema.threads) t ON 1 = 1', []],
    ['SELECT * FROM moodle_lms.mdl_user', []],
    ['SELECT * FROM wp_users', []],
    // Secret tables and columns.
    ['SELECT * FROM mdl_config', []],
    ['SELECT value FROM `MDL_CONFIG_PLUGINS`', []],
    ['SELECT token FROM mdl_external_tokens', []],
    ['SELECT * FROM mdl_sessions', []],
    ['SELECT value FROM mdl_lti_types_config', []],
    ['SELECT password FROM mdl_user', []],
    ['SELECT `password` AS p FROM mdl_user', []],
    ['SELECT u.password FROM mdl_user u', []],
    ['SELECT x FROM (SELECT secret AS x FROM mdl_user) t', []],
    ['SELECT id FROM mdl_user WHERE id IN (SELECT userid FROM mdl_user_private_key)', []],
    // An information_schema read widened or unscoped.
    ['SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES', []],
    ['SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? OR 1 = 1', ['moodle_lms']],
    ['SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?', ['otherdb']],
    ['SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? '
        . 'UNION SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()', ['moodle_lms']],
    ['SELECT * FROM information_schema.PROCESSLIST', []],
    ['SELECT * FROM information_schema.USER_PRIVILEGES WHERE TABLE_SCHEMA = DATABASE()', []],
    // Writes, locks, delays, files, session state, server variables.
    ['DELETE FROM mdl_user', []],
    ['SHOW DATABASES', []],
    ['SHOW PROCESSLIST', []],
    ['EXPLAIN SELECT * FROM mdl_user', []],
    ['SELECT 1; DROP TABLE mdl_user', []],
    ['SELECT id INTO @x FROM mdl_user', []],
    ['SELECT id FROM mdl_user INTO OUTFILE \'/tmp/x\'', []],
    ['SELECT @@datadir', []],
    ['SELECT SLEEP(30)', []],
    ['SELECT BENCHMARK(100000000, MD5(1))', []],
    ['SELECT LOAD_FILE(\'/etc/passwd\')', []],
    ['SELECT * FROM mdl_user FOR UPDATE', []],
    ['/*!50000 DROP TABLE mdl_user */ SELECT 1', []],
    ['SELECT 1 /*! , (SELECT password FROM mdl_user) */', []],
    ['SELECT GET_LOCK(\'x\', 10)', []],
    ['SELECT SLEEP (30)', []],
    ['SELECT benchmark  (1000000000, SHA2(1, 256))', []],
    ['SELECT USER()', []],
    ['SELECT load_file (\'/etc/passwd\')', []],
    ["SELECT 'unterminated", []],
    ['SELECT value FROM mdl_user_preferences WHERE name = ?', ['login_lockout_secret']],
    ['SELECT config FROM mdl_payment_gateways', []],
    ['SELECT token FROM mdl_user_password_resets', []],
    ['SELECT t.token FROM mdl_portfolio_mahara_queue t', []],
    ['SELECT moderatorpass FROM mdl_bigbluebuttonbn', []],
    ['SELECT * FROM mdl_ai_providers', []],
    // Reported in the Moodle plugins directory review (October 2026).
    ['SELECT b.plugin, b.name, b.value FROM (SELECT 1 AS x) a, mdl_config_plugins b', []],
    ['SELECT s.sid, s.userid FROM (SELECT 1 AS x) a, mdl_sessions s', []],
    ['SELECT u.id FROM (SELECT 1 AS x) a, mysql.user u', []],
    ['SELECT b.value FROM mdl_course c JOIN (mdl_config_plugins b) ON 1 = 1', []],
    ['SELECT id, shortname, fullname FROM mdl_course UNION TABLE mdl_config', []],
    ['WITH t (c1, c2, c3, c4, c5, c6, c7, c8, c9, c10, c11, c12) AS (SELECT * FROM mdl_user) SELECT c5 FROM t', []],
    ['WITH t (c1, c2) AS (SELECT e.* FROM (SELECT 1 AS x) a, mdl_external_tokens e) SELECT * FROM t', []],
    // Every column, renamed columns, and tables outside the reporting allowlist.
    ['SELECT * FROM mdl_user', []],
    ['SELECT u.* FROM mdl_user u', []],
    ['SELECT id, x.* FROM (SELECT id, username FROM mdl_user) x', []],
    ['SELECT c2 FROM (SELECT id, username FROM mdl_user) AS t (c1, c2)', []],
    ['WITH a AS (SELECT 1 AS z), t (k, v) AS (SELECT id, username FROM mdl_user) SELECT v FROM t', []],
    ['SELECT id FROM mdl_message', []],
    ['SELECT id FROM mdl_course WHERE id IN (SELECT itemid FROM mdl_message_read)', []],
    ['SELECT id FROM mdl_course c, mdl_course_categories cc, (SELECT 1 AS x) d, mdl_config cf', []],
    ['SELECT id FROM mdl_course UNION SELECT id FROM mdl_external_services', []],
    ['SELECT id FROM mdl_course JOIN (mdl_user u, mdl_sessions s) ON 1 = 1', []],
    ['SELECT a FROM JSON_TABLE(?, \'$[*]\' COLUMNS (a INT PATH \'$\')) j', ['[1]']],
    ['SELECT 1 FROM (VALUES ROW(1)) v', []],
    ['SELECT id FROM mdl_course PARTITION (p0)', []],
    ['SELECT id FROM mdl_course c, otherdb.mdl_user u', []],
    ['SELECT l.id FROM mdl_logstore_standard_log l FORCE INDEX (ix) WHERE l.timecreated > ?', [1]],
];

$ipcases = [
    ['203.0.113.10', ['203.0.113.10'], true],
    ['203.0.113.11', ['203.0.113.10'], false],
    ['198.51.100.77', ['198.51.100.0/24'], true],
    ['198.51.101.1', ['198.51.100.0/24'], false],
    ['10.1.2.3', ['10.0.0.0/9'], true],
    ['10.200.0.1', ['10.0.0.0/9'], false],
    ['2001:db8::1', ['2001:db8::/32'], true],
    ['2001:db9::1', ['2001:db8::/32'], false],
    ['203.0.113.10', ['2001:db8::/32'], false],
    ['not-an-ip', ['0.0.0.0/0'], false],
];

$columns = ['password' => true, 'PASSWORD' => true, 'clientsecret' => true, 'token' => true, 'sesskey' => true,
    'username' => false, 'timecreated' => false, 'tokenised_name' => false, 'passgrade' => false];

$transport = [
    ['https://lms.example.edu', ['HTTPS' => 'on'], false, [], true],
    ['https://lms.example.edu', ['HTTPS' => 'off', 'SERVER_PORT' => '80'], false, [], false],
    ['https://lms.example.edu', ['SERVER_PORT' => '8080'], true, [], true],
    ['http://lms.example.edu', ['HTTPS' => 'on'], false, [], false],
    ['http://lms.example.edu', [], false, ['allowinsecurehttp' => 1], true],
];

return [
    'allowed' => $allowed,
    'attacks' => $attacks,
    'addresses' => $ipcases,
    'columns' => $columns,
    'transport' => $transport,
];
