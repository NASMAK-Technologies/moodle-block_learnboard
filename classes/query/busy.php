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
 * Caps how many LearnBoard requests this site will work on at the same moment.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\query;

/**
 * Lets the site say "busy, come back shortly" instead of failing a query.
 *
 * Every LearnBoard request is a PHP process on this server holding a database
 * connection. When a dashboard asks for several at once and the site is already
 * busy serving its own learners, the surplus used to be refused by the host or
 * the database, which reached LearnBoard as a failed query and the person
 * looking at it as a broken card.
 *
 * A refusal is the wrong answer twice over: the reader loses a card, and the
 * work done up to that point was wasted anyway. LearnBoard already knows how to
 * wait and try again, so the site is better off saying so. Answering 429 with
 * Retry-After costs almost nothing, is returned BEFORE the second database
 * connection is opened, and turns a broken card into a slightly slower one.
 *
 * It also puts the ceiling where it belongs. LearnBoard limits itself per site,
 * but only this site knows what else it is doing right now: a Monday morning of
 * real learners is invisible from the outside. This is the site's own answer
 * and it wins.
 *
 * Counting is N lock files in the data directory, because the endpoint loads
 * config.php only and has no cache, no session and no Moodle API. A slot is
 * held by an open file lock, so it is released when the process ends however
 * it ends, including a fatal error or a killed worker. Nothing is left behind
 * to leak.
 *
 * Best-effort by design: if the directory cannot be made or a lock cannot be
 * taken, the request proceeds. Being unable to count is not a reason to refuse
 * a customer their data.
 */
class busy {
    /** @var resource|null Kept open for the life of the request: closing it frees the slot. */
    private static $handle = null;

    /** How long LearnBoard is asked to wait, in seconds. */
    public const RETRY_AFTER = 2;

    /**
     * Take one of the site's slots.
     *
     * @param \stdClass $cfg Moodle's $CFG, for dataroot
     * @param int $slots how many requests this site will work on at once, 0 = no limit
     * @return bool false when the site is already at its limit
     */
    public static function take($cfg, int $slots): bool {
        if ($slots <= 0) {
            return true;
        }

        $dir = rtrim((string) $cfg->dataroot, '/\\') . '/temp/block_learnboard_slots';
        if (!is_dir($dir) && !@mkdir($dir, 0770, true) && !is_dir($dir)) {
            return true;
        }

        // Each process starts at a different slot, so they do not all contend
        // for slot 0 and serialise behind one another.
        $offset = random_int(0, $slots - 1);
        for ($i = 0; $i < $slots; $i++) {
            $index = ($offset + $i) % $slots;
            $handle = @fopen($dir . '/slot-' . $index, 'c');
            if ($handle === false) {
                return true;
            }
            if (@flock($handle, LOCK_EX | LOCK_NB)) {
                self::$handle = $handle;

                return true;
            }
            @fclose($handle);
        }

        return false;
    }
}
