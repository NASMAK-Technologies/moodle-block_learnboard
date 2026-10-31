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
 * The one field a Moodle administrator fills in: the connection code made in LearnBoard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\admin;

use block_learnboard\connection_status;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Paste the connection code, press Save and connect, and the site is connected.
 *
 * The code carries LearnBoard's address, the workspace name, the embed key
 * and the connector key. Saving it fills those four settings, switches the
 * data connector on, and asks LearnBoard to connect: LearnBoard reads from
 * this site with the connector key to prove the address, then creates the
 * data source on its side. The code itself is never stored.
 *
 * The field draws steps 1 and 2 of Connect to LearnBoard around itself (LB-36):
 * step 1 opens LearnBoard where the code is made, step 2 holds the field, its
 * own Save and connect button and the result, so nothing depends on Moodle's
 * Save changes at the bottom of the page. The button that is wanted next
 * pulses (LB-39).
 *
 * A plain text field rather than a password one: the code is never shown
 * back, and a password field needs page scripts that some themes leave out of
 * the page Moodle shows straight after an install.
 *
 * PHP 7.3 syntax, because the plugin supports Moodle 4.0.
 */
class setting_connection_code extends \admin_setting_configtext {
    /** Prefix every LearnBoard connection code starts with. */
    public const PREFIX = 'LBC1.';

    /** LearnBoard's address before a code has told this site its own. */
    public const DEFAULT_APP = 'https://learnboard.learnoow.com';

    /** @var bool whether a code was saved in this request */
    private static $applied = false;

    /**
     * Whether a connection code was saved in this request. The fields it
     * fills in on the advanced page then leave its values alone.
     *
     * @return bool
     */
    public static function applied(): bool {
        return self::$applied;
    }

    /**
     * Nothing is stored, so the field is always empty and never listed as a
     * new setting after an upgrade.
     *
     * @return string
     */
    public function get_setting() {
        return '';
    }

    /**
     * Decodes the pasted connection code and connects the site to LearnBoard.
     *
     * @param string $data
     * @return string empty on success, or the error shown under the field
     */
    public function write_setting($data) {
        global $CFG;

        $data = trim((string) $data);
        if ($data === '') {
            return '';
        }

        $decoded = self::decode($data);
        if ($decoded === null) {
            return get_string('connectioncode_invalid', 'block_learnboard');
        }

        $scheme = strtolower((string) parse_url($decoded['api'], PHP_URL_SCHEME));
        $insecureallowed = !empty($CFG->forced_plugin_settings['block_learnboard']['allowinsecurehttp']);
        if ($scheme !== 'https' && !($scheme === 'http' && $insecureallowed)) {
            return get_string('connectioncode_invalid', 'block_learnboard');
        }

        self::$applied = true;
        set_config('apibase', $decoded['api'], 'block_learnboard');
        set_config('tenant', $decoded['ws'], 'block_learnboard');
        set_config('embedkey', $decoded['ek'], 'block_learnboard');
        set_config('querykey', $decoded['ck'], 'block_learnboard');
        set_config('queryenabled', 1, 'block_learnboard');
        // Per-user sign-in too, as the field promises ("charts, sign-in and
        // reading data"). Left off, a fresh site gave every teacher, manager
        // and learner "could not load" and only site managers one shared view.
        // Each person now sees only their own groups; an admin can turn it off.
        set_config('ssoenabled', 1, 'block_learnboard');

        $result = \block_learnboard\client::register_site();
        set_config('lastconnection', json_encode($result + ['time' => time()]), 'block_learnboard');
        connection_status::forget();

        return '';
    }

    /**
     * Steps 1 and 2, with the field, its button and the result inside step 2.
     *
     * @param mixed $data the posted value when saving it failed, else ''
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;

        $state = connection_status::current();
        $connected = $state['connected'];
        $app = \block_learnboard\client::appbase() ?: self::DEFAULT_APP;

        $root = admin_get_root();
        $error = isset($root->errors[$this->get_full_name()]) ? (string) $root->errors[$this->get_full_name()]->error : '';
        $value = $error !== '' ? (string) $data : '';

        // One button pulses: the one wanted next. Open LearnBoard until the
        // person has been there, Save and connect once there is a code to
        // save (the page script moves it on), Open dashboards once connected.
        $savenext = !$connected && $value !== '';

        return $OUTPUT->render_from_template('block_learnboard/connection_steps', [
            'uniqid' => \html_writer::random_id('lbconnect'),
            'steps' => [
                [
                    'number' => 1,
                    'done' => $connected,
                    'current' => !$connected,
                    'title' => get_string('connectstep1_title', 'block_learnboard'),
                    'text' => get_string('connectstep1_text', 'block_learnboard'),
                    'link' => $connected ? null : [
                        'url' => $app . '/admin/data-sources?from=moodle#connection-code',
                        'text' => get_string('connectstep1_link', 'block_learnboard'),
                        'attention' => !$savenext,
                    ],
                ],
                [
                    'number' => 2,
                    'done' => $connected,
                    'current' => !$connected,
                    'title' => get_string('connectstep2_title', 'block_learnboard'),
                    'text' => get_string($connected ? 'connectstep2_reconnect' : 'connectstep2_text', 'block_learnboard'),
                    'field' => [
                        'id' => $this->get_id(),
                        'name' => $this->get_full_name(),
                        'value' => $value,
                        'label' => get_string('connectioncode', 'block_learnboard'),
                        'help' => get_string('connectioncode_desc', 'block_learnboard'),
                        'button' => get_string('connectsave', 'block_learnboard'),
                        'attention' => $savenext,
                    ],
                    'result' => self::result($state, $error),
                ],
            ],
        ]);
    }

    /**
     * The result line in step 2: green when connected, red with the reason
     * when not, and a plain line before any attempt.
     *
     * @param array $state from connection_status::current()
     * @param string $error the error from saving the field in this request
     * @return array{tone: string, ok: bool, error: bool, text: string, note: string}
     */
    public static function result(array $state, string $error = ''): array {
        $out = function (string $tone, string $text, string $note = ''): array {
            return ['tone' => $tone, 'ok' => $tone === 'ok', 'error' => $tone === 'error', 'text' => $text, 'note' => $note];
        };

        if ($error !== '') {
            return $out('error', $error);
        }
        if ($state['lost'] !== '') {
            return $out('error', get_string('connectionlost_' . $state['lost'], 'block_learnboard'));
        }

        $note = ($state['connected'] && $state['probe']['state'] === 'unreachable')
            ? get_string('connectioncheck_unreachable', 'block_learnboard')
            : '';
        $last = $state['last'];
        if (!is_array($last)) {
            return $state['connected']
                ? $out('ok', get_string('connectioncode_status_reading', 'block_learnboard'), $note)
                : $out('none', get_string('connectioncode_status_none', 'block_learnboard'), $note);
        }

        $when = userdate((int) ($last['time'] ?? time()));
        if ($state['connected']) {
            $active = $state['probe']['state'] === 'ok' ? !empty($state['probe']['active']) : !empty($last['active']);

            return $out(
                'ok',
                get_string($active ? 'connectioncode_status_active' : 'connectioncode_status_connected', 'block_learnboard', $when),
                $note
            );
        }

        // Say what went wrong in words. The bare code read "code_replaced"
        // for a workspace that had simply not made a connection code yet.
        $reason = (string) ($last['reason'] ?? '');
        if ($reason !== '' && get_string_manager()->string_exists('connectionreason_' . $reason, 'block_learnboard')) {
            $reason = get_string('connectionreason_' . $reason, 'block_learnboard');
        }

        return $out(
            'error',
            get_string('connectioncode_status_failed', 'block_learnboard', (object) ['when' => $when, 'reason' => $reason])
        );
    }

    /**
     * Unpacks a connection code into its parts.
     *
     * @param string $code
     * @return array|null api, ws, ek, ck
     */
    public static function decode(string $code) {
        if (strpos($code, self::PREFIX) !== 0) {
            return null;
        }
        $json = base64_decode(strtr(substr($code, strlen(self::PREFIX)), '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($data) || ($data['v'] ?? null) !== 1) {
            return null;
        }
        foreach (['api', 'ws', 'ek', 'ck'] as $field) {
            if (!is_string($data[$field] ?? null) || $data[$field] === '') {
                return null;
            }
        }
        if (
            !preg_match('/^[a-f0-9]{64}$/', $data['ek']) || !preg_match('/^[a-f0-9]{64}$/', $data['ck'])
                || !preg_match('/^[a-z0-9-]{1,120}$/', $data['ws'])
        ) {
            return null;
        }

        return ['api' => rtrim($data['api'], '/'), 'ws' => $data['ws'], 'ek' => $data['ek'], 'ck' => $data['ck']];
    }
}
