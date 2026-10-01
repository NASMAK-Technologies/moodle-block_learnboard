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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/adminlib.php');

/**
 * Paste the connection code, save, and the site is connected.
 *
 * The code carries LearnBoard's address, the workspace name, the embed key
 * and the connector key. Saving it fills those four settings, switches the
 * data connector on, and asks LearnBoard to connect: LearnBoard reads from
 * this site with the connector key to prove the address, then creates the
 * data source on its side. The code itself is never stored.
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

        set_config('apibase', $decoded['api'], 'block_learnboard');
        set_config('tenant', $decoded['ws'], 'block_learnboard');
        set_config('embedkey', $decoded['ek'], 'block_learnboard');
        set_config('querykey', $decoded['ck'], 'block_learnboard');
        set_config('queryenabled', 1, 'block_learnboard');

        $result = \block_learnboard\client::register_site();
        set_config('lastconnection', json_encode($result + ['time' => time()]), 'block_learnboard');

        return '';
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

    /**
     * What the last connection attempt reported, for the field's description.
     *
     * @return string
     */
    public static function status_text(): string {
        $raw = get_config('block_learnboard', 'lastconnection');
        $last = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($last)) {
            return get_string('connectioncode_status_none', 'block_learnboard');
        }
        $when = userdate((int) ($last['time'] ?? time()));
        if (!empty($last['connected'])) {
            return get_string(
                $last['active'] ? 'connectioncode_status_active' : 'connectioncode_status_connected',
                'block_learnboard',
                $when
            );
        }

        return get_string(
            'connectioncode_status_failed',
            'block_learnboard',
            (object) ['when' => $when, 'reason' => (string) ($last['reason'] ?? '')]
        );
    }
}
