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
 * Link the signed-in Moodle user's account to their own LearnBoard account.
 *
 * A LearnBoard user starts this from Profile and account, Link my Moodle
 * account, and LearnBoard sends the browser here with a state that works once
 * and for ten minutes. The page shows whose LearnBoard account would be
 * linked; on Link my accounts (a POST carrying the session key) this site
 * vouches for the signed-in Moodle user, server to server with the embed key,
 * and sends the browser back to LearnBoard with a one-time code. LearnBoard
 * makes the link only when the LearnBoard user who started it redeems it.
 * From then on, opening LearnBoard from Moodle signs this person in as that
 * LearnBoard account.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

$state = required_param('state', PARAM_ALPHANUM);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$url = new moodle_url('/blocks/learnboard/link.php', ['state' => $state]);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

// A real account: no guest, and nobody logged in as somebody else, since the
// link would hand that other person's Moodle identity to the LearnBoard
// account that asked.
require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}
if (\core\session\manager::is_loggedinas()) {
    throw new \moodle_exception('linkpage_loginas', 'block_learnboard');
}
if (!\block_learnboard\client::is_configured()) {
    throw new \moodle_exception('linkpage_notconfigured', 'block_learnboard');
}
if (!\block_learnboard\ssouser::enabled()) {
    throw new \moodle_exception('ssonotavailable', 'block_learnboard');
}

$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('linkpage_title', 'block_learnboard'));
$PAGE->set_heading(get_string('linkpage_title', 'block_learnboard'));

$account = \block_learnboard\client::appbase() . '/account';
$cancel = new moodle_url($account, ['moodle_link_cancelled' => 1]);

/**
 * Ends the page with a message and a way back to LearnBoard.
 *
 * @param string $message already-escaped text
 * @param moodle_url $back where Back to LearnBoard goes
 */
function block_learnboard_link_stop(string $message, moodle_url $back): void {
    global $OUTPUT;

    echo $OUTPUT->header();
    echo $OUTPUT->notification($message, 'error', false);
    echo $OUTPUT->single_button($back, get_string('linkpage_back', 'block_learnboard'), 'get');
    echo $OUTPUT->footer();
    exit;
}

/**
 * The message for a refusal from LearnBoard.
 *
 * @param int $code HTTP status
 * @return string
 */
function block_learnboard_link_reason(int $code): string {
    if ($code === 409) {
        return get_string('linkpage_conflict', 'block_learnboard');
    }
    if ($code === 422) {
        return get_string('linkpage_wrongsite', 'block_learnboard');
    }
    if ($code === 410 || $code === 401 || $code === 404) {
        return get_string('linkpage_expired', 'block_learnboard');
    }

    return get_string('linkpage_failed', 'block_learnboard');
}

if ($confirm) {
    require_sesskey();

    list($code, $data) = \block_learnboard\client::link_confirm($state, [
        'external_id' => (string) $USER->id,
        'name' => fullname($USER),
        'source' => rtrim((string) $CFG->wwwroot, '/'),
    ]);
    $done = is_array($data) ? (string) ($data['code'] ?? '') : '';
    if ($code === 200 && $done !== '' && preg_match('/^[A-Za-z0-9]{1,128}$/', $done)) {
        redirect(new moodle_url($account, ['moodle_link' => $done]));
    }

    block_learnboard_link_stop(block_learnboard_link_reason($code), $cancel);
}

list($code, $about) = \block_learnboard\client::link_describe($state);
if ($code !== 200 || !is_array($about) || !is_array($about['account'] ?? null)) {
    block_learnboard_link_stop(block_learnboard_link_reason($code), $cancel);
}

$message = get_string('linkpage_confirm', 'block_learnboard', (object) [
    'moodle' => s(fullname($USER)),
    'name' => s((string) ($about['account']['name'] ?? '')),
    'email' => s((string) ($about['account']['email'] ?? '')),
    'workspace' => s((string) ($about['workspace'] ?? '')),
]);

echo $OUTPUT->header();
echo $OUTPUT->confirm(
    $message,
    // Moodle's confirm() makes the continue button the primary one.
    new single_button(new moodle_url($url, ['confirm' => 1]), get_string('linkpage_continue', 'block_learnboard'), 'post'),
    new single_button($cancel, get_string('cancel'), 'get')
);
echo $OUTPUT->footer();
