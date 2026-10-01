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
 * Network and audit checks around the data connector endpoint.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\query;

/**
 * What query.php checks about a request before and after it is authenticated,
 * and the audit trail it leaves.
 *
 * Kept free of Moodle's libraries because the endpoint loads config.php only.
 * PHP 7.3 syntax, because the plugin supports Moodle 4.0.
 */
class guard {
    /** Audit log size at which it is rotated to .1 (one previous file is kept). */
    public const AUDIT_ROTATE_BYTES = 20 * 1024 * 1024;

    /**
     * Whether this request reached the site over HTTPS.
     *
     * The answer carries learner records and the request carries the key's
     * signature, so both must travel encrypted. A site whose own address is
     * http is refused too, unless a site admin forces allowinsecurehttp in
     * config.php for a local test install.
     *
     * @param \stdClass $cfg
     * @param array $forced forced plugin settings from config.php
     * @return bool
     */
    public static function transport_is_secure($cfg, array $forced): bool {
        if (!empty($forced['allowinsecurehttp'])) {
            return true;
        }
        if (stripos((string) $cfg->wwwroot, 'https://') !== 0) {
            return false;
        }
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        // TLS ended at a reverse proxy the site admin has declared.
        return !empty($cfg->sslproxy);
    }

    /**
     * The caller's address, as Moodle itself would work it out.
     *
     * REMOTE_ADDR, unless the site is configured to trust X-Forwarded-For; then
     * the last address in it that is not one of the site's own proxies, since
     * anything to its left was written by the client and can be forged.
     * HTTP_CLIENT_IP is never trusted.
     *
     * @param string $getremoteaddrconf Moodle's getremoteaddrconf setting ('' when unset)
     * @param string $reverseproxyignore Moodle's reverseproxyignore setting
     * @return string
     */
    public static function remote_address(string $getremoteaddrconf, string $reverseproxyignore): string {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $skip = $getremoteaddrconf === '' ? 3 : (int) $getremoteaddrconf;
        $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if (($skip & 2) !== 0 || $forwarded === '') {
            return $remote;
        }

        $ignore = self::parse_list($reverseproxyignore);
        $candidates = array_reverse(array_map('trim', explode(',', $forwarded)));
        foreach ($candidates as $candidate) {
            $address = self::strip_port($candidate);
            if (filter_var($address, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }
            if (!self::address_in_list($address, $ignore)) {
                return $address;
            }
        }

        return $remote;
    }

    /**
     * Whether an address is inside any of the listed addresses or CIDR ranges.
     *
     * @param string $address
     * @param string[] $list e.g. ['203.0.113.10', '198.51.100.0/24', '2001:db8::/32']
     * @return bool
     */
    public static function address_in_list(string $address, array $list): bool {
        $packed = @inet_pton($address);
        if ($packed === false) {
            return false;
        }
        foreach ($list as $entry) {
            $parts = explode('/', $entry, 2);
            $network = @inet_pton(trim($parts[0]));
            if ($network === false || strlen($network) !== strlen($packed)) {
                continue;
            }
            $bits = strlen($packed) * 8;
            $prefix = isset($parts[1]) && ctype_digit(trim($parts[1])) ? (int) trim($parts[1]) : $bits;
            if ($prefix > $bits) {
                continue;
            }
            $bytes = intdiv($prefix, 8);
            if (substr($packed, 0, $bytes) !== substr($network, 0, $bytes)) {
                continue;
            }
            $remainder = $prefix % 8;
            if ($remainder === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $remainder)) & 0xFF;
            if ((ord($packed[$bytes]) & $mask) === (ord($network[$bytes]) & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Split a setting of addresses separated by commas, spaces or new lines.
     *
     * @param string $value
     * @return string[]
     */
    public static function parse_list(string $value): array {
        return array_values(array_filter(preg_split('/[\s,]+/', trim($value)) ?: [], 'strlen'));
    }

    /**
     * Append one line to the connector's audit log in the data directory.
     *
     * Records who called, what was asked and what happened. Never the
     * bindings or any row: the log must not become a second copy of the data.
     *
     * @param \stdClass $cfg
     * @param array $entry
     */
    public static function audit($cfg, array $entry): void {
        $dir = rtrim((string) $cfg->dataroot, '/\\') . '/block_learnboard';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true)) {
            return;
        }
        $file = $dir . '/query-audit.log';
        if (@filesize($file) > self::AUDIT_ROTATE_BYTES) {
            @rename($file, $file . '.1');
        }
        $line = json_encode(['time' => gmdate('Y-m-d\TH:i:s\Z')] + $entry, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * An address without a port or IPv6 brackets.
     *
     * @param string $value
     * @return string
     */
    private static function strip_port(string $value): string {
        if (preg_match('/^\[([^\]]+)\](?::\d+)?$/', $value, $m)) {
            return $m[1];
        }
        if (substr_count($value, ':') === 1) {
            return explode(':', $value)[0];
        }

        return $value;
    }
}
