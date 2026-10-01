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
 * Configurable role mapping (#1): admin screen mapping each Moodle role onto a.
 * LearnBoard role. Registered as an admin external page in settings.php.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

admin_externalpage_setup('block_learnboard_rolemap');

$PAGE->set_url(new moodle_url('/blocks/learnboard/rolemap.php'));

// LearnBoard roles for the dropdowns (live, with a cached fallback), and the
// currently-effective map (saved config, or the safe default).
$lbroles = \block_learnboard\client::list_roles();
$current = \block_learnboard\ssouser::get_map();

// Moodle roles to map: a `siteadmin` pseudo-role, every defined role, and the
// `__default__` fallback for users with no mapped role.
$moodleroles = [['shortname' => 'siteadmin', 'label' => get_string('rolemap_siteadmin', 'block_learnboard')]];
foreach (role_fix_names(get_all_roles(), context_system::instance(), ROLENAME_ORIGINAL) as $role) {
    $label = format_string($role->localname !== '' ? $role->localname : $role->shortname);
    $moodleroles[] = ['shortname' => $role->shortname, 'label' => $label . ' (' . $role->shortname . ')'];
}
$moodleroles[] = ['shortname' => '__default__', 'label' => get_string('rolemap_default', 'block_learnboard')];

$form = new \block_learnboard\form\rolemap_form(null, [
    'moodleroles' => $moodleroles,
    'lbroles' => $lbroles,
    'current' => $current,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'blocksettinglearnboard']));
} else if ($data = $form->get_data()) {
    $map = \block_learnboard\form\rolemap_form::to_map($data);
    set_config('rolemap', json_encode($map), 'block_learnboard');
    redirect(
        $PAGE->url,
        get_string('rolemap_saved', 'block_learnboard'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rolemap', 'block_learnboard'));
echo html_writer::tag('p', get_string('rolemap_intro', 'block_learnboard'));

if (empty($lbroles)) {
    echo $OUTPUT->notification(get_string('rolemap_lbunreachable', 'block_learnboard'), 'notifywarning');
}

$form->display();
echo $OUTPUT->footer();
