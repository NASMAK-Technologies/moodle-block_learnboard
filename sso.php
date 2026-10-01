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
 * Per-user SSO hand-off: sign the logged-in Moodle user straight into the full.
 * LearnBoard app. Requests a single-use ticket server-side and redirects the
 * browser to <app>/sso?ticket=… . This is also the target of the "Sign in with
 * Moodle" reverse-entry button on the LearnBoard login page: require_login()
 * takes an unauthenticated visitor through Moodle login first, then back here.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/learnboard/sso.php'));

// Gated only on SSO being enabled — any signed-in Moodle user may sign into
// their OWN LearnBoard account (staff land on the app, learners on their own
// "My Learning" surface). (No sesskey by design — the only outcome is signing
// the user into their own account; the backend ticket endpoint is throttled.)
if (!\block_learnboard\ssouser::enabled()) {
    throw new \moodle_exception('ssonotavailable', 'block_learnboard');
}

$assertion = \block_learnboard\ssouser::assertion();
$url = $assertion !== null ? \block_learnboard\client::login_url($assertion) : null;

if ($url === null) {
    throw new \moodle_exception('ssofailed', 'block_learnboard');
}

redirect($url);
