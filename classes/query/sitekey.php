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
 * When this site takes a site key from LearnBoard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\query;

defined('MOODLE_INTERNAL') || die();

/**
 * The site key hand-over is accepted only while this site is registering.
 *
 * The connector key is the workspace's, so every Moodle site connected to
 * the same workspace holds it, and a signature alone cannot tell LearnBoard
 * from another of those sites. So right before this site registers, it makes
 * a one-time pending value, keeps only its hash with an expiry, and sends the
 * value to LearnBoard with the registration; LearnBoard sends it back with
 * the site key. Without the value, after it expired, or once it was used,
 * the hand-over is refused and nothing is written.
 *
 * Plain PHP, no Moodle calls, so cli/sitekey_check.php runs it anywhere.
 * PHP 7.3 syntax, because the plugin supports Moodle 4.0.
 */
class sitekey {
    /** How long a registration may wait for its site key, in seconds. */
    public const TTL = 600;

    /**
     * A new pending value, and what to store for it.
     *
     * @param int $now unix time
     * @return array{0: string, 1: string} the value to send, the JSON to store
     */
    public static function pending(int $now): array {
        $nonce = bin2hex(random_bytes(32));

        return [$nonce, (string) json_encode(['hash' => hash('sha256', $nonce), 'expires' => $now + self::TTL])];
    }

    /**
     * Whether a hand-over may be accepted.
     *
     * @param string $stored the stored JSON, or '' when nothing is pending
     * @param mixed $nonce the value the hand-over carries
     * @param mixed $key the site key it carries
     * @param int $now unix time
     * @return bool
     */
    public static function accept(string $stored, $nonce, $key, int $now): bool {
        if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/', $key)) {
            return false;
        }
        if (!is_string($nonce) || !preg_match('/^[a-f0-9]{64}$/', $nonce)) {
            return false;
        }
        $pending = $stored !== '' ? json_decode($stored, true) : null;
        if (!is_array($pending) || !is_string($pending['hash'] ?? null) || (int) ($pending['expires'] ?? 0) < $now) {
            return false;
        }

        return hash_equals($pending['hash'], hash('sha256', $nonce));
    }
}
