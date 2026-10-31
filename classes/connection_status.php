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
 * Whether this site is connected to LearnBoard, as LearnBoard sees it now.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard;

/**
 * The connection's state for Connect to LearnBoard.
 *
 * Saving a code records whether it connected (config lastconnection), but a
 * workspace deleted in LearnBoard, or given a new code, stops accepting this
 * site without Moodle hearing of it, and the page went on saying "You're
 * connected" (LB-45). So the page also asks LearnBoard, and keeps the answer
 * for a minute in the application cache. A LearnBoard that cannot be reached
 * does not undo a connection: only a clear "no" does.
 *
 * PHP 7.3 syntax, because the plugin supports Moodle 4.0.
 */
class connection_status {
    /** States in which LearnBoard has said it no longer accepts this site. */
    private const LOST = ['revoked', 'missing', 'pending_deletion'];

    /**
     * What LearnBoard says now, cached for a minute.
     *
     * The cache key carries the address, workspace and key, so saving a new
     * code is never answered from the old connection's entry.
     *
     * @return array{state: string, active: bool, siteconnected: bool}
     */
    public static function probe(): array {
        if (!client::is_configured()) {
            return ['state' => 'not_configured', 'active' => false, 'siteconnected' => false];
        }

        $cache = \cache::make('block_learnboard', 'connectionstatus');
        $key = sha1(client::apibase() . '|' . client::tenant() . '|' . (string) get_config('block_learnboard', 'embedkey'));
        $hit = $cache->get($key);
        if (is_array($hit) && isset($hit['state'])) {
            return $hit;
        }

        $result = client::probe_status();
        $cache->set($key, $result);

        return $result;
    }

    /**
     * Drops the cached answer, after a code is saved.
     */
    public static function forget(): void {
        \cache::make('block_learnboard', 'connectionstatus')->purge();
    }

    /**
     * The last saved attempt, as stored when the code was saved.
     *
     * @return array|null connected, active, reason, time
     */
    public static function last(): ?array {
        $raw = get_config('block_learnboard', 'lastconnection');
        $last = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($last) ? $last : null;
    }

    /**
     * Everything the page needs: connected or not, and why.
     *
     * @return array{connected: bool, lost: string, probe: array, last: array|null}
     *     lost is the probe state when LearnBoard no longer accepts the site,
     *     otherwise ''
     */
    public static function current(): array {
        $last = self::last();
        $saved = is_array($last) && !empty($last['connected']);
        $probe = self::probe();
        $lost = ($saved && in_array($probe['state'], self::LOST, true)) ? $probe['state'] : '';

        return [
            'connected' => ($saved || ($probe['state'] === 'ok' && !empty($probe['siteconnected']))) && $lost === '',
            'lost' => $lost,
            'probe' => $probe,
            'last' => $last,
        ];
    }
}
