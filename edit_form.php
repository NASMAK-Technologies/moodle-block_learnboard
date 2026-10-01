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
 * Per-instance configuration for block_learnboard. The dashboard contents.
 * (which charts, and their positions/sizes) are edited live inside the block;
 * this form only sets the heading, minimum height, and an optional starter card.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Settings for one LearnBoard block: its title, and which charts or dashboard it shows.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_learnboard_edit_form extends block_edit_form {
    /**
     * Adds this block's own fields to the standard block settings form.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('blocktitle', 'block_learnboard'));
        $mform->setType('config_title', PARAM_TEXT);
        $mform->addHelpButton('config_title', 'blocktitle', 'block_learnboard');

        $mform->addElement('text', 'config_minheight', get_string('minheight', 'block_learnboard'));
        $mform->setType('config_minheight', PARAM_INT);
        $mform->setDefault('config_minheight', 320);
        $mform->addHelpButton('config_minheight', 'minheight', 'block_learnboard');

        // Audience (M5): show this block's dashboard only to a given role, so a
        // page can carry a per-role set of blocks (a Managers dashboard, a
        // Teachers dashboard, …). Matched by role archetype in this context.
        $mform->addElement('select', 'config_audience', get_string('audience', 'block_learnboard'), [
            'all' => get_string('audience_all', 'block_learnboard'),
            'manager' => get_string('audience_manager', 'block_learnboard'),
            'editingteacher' => get_string('audience_editingteacher', 'block_learnboard'),
            'teacher' => get_string('audience_teacher', 'block_learnboard'),
            'student' => get_string('audience_student', 'block_learnboard'),
        ]);
        $mform->setDefault('config_audience', 'all');
        $mform->addHelpButton('config_audience', 'audience', 'block_learnboard');

        // Dashboard source (M7). "Custom" = arrange charts here in Moodle (the
        // original behaviour). "Linked" = mirror a named LearnBoard dashboard
        // live and read-only; edits happen in LearnBoard and show here on reload.
        $mform->addElement('select', 'config_source', get_string('source', 'block_learnboard'), [
            'custom' => get_string('source_custom', 'block_learnboard'),
            'linked' => get_string('source_linked', 'block_learnboard'),
        ]);
        $mform->setDefault('config_source', 'custom');
        $mform->addHelpButton('config_source', 'source', 'block_learnboard');

        // The dashboard picker: only the tenant's shared (tenant-visible)
        // dashboards are listed. Empty → guidance instead of an empty dropdown.
        $dashboards = \block_learnboard\client::is_configured()
            ? \block_learnboard\client::list_dashboards()
            : [];
        if (!empty($dashboards)) {
            $choices = ['' => get_string('choosedashboard', 'block_learnboard')] + $dashboards;
            $mform->addElement(
                'select',
                'config_dashboardid',
                get_string('dashboard', 'block_learnboard'),
                $choices
            );
            $mform->setType('config_dashboardid', PARAM_INT);
            $mform->addHelpButton('config_dashboardid', 'dashboard', 'block_learnboard');
            $mform->hideIf('config_dashboardid', 'config_source', 'neq', 'linked');
        } else {
            $mform->addElement(
                'static',
                'config_dashboardid_none',
                get_string('dashboard', 'block_learnboard'),
                get_string('nodashboards', 'block_learnboard')
            );
            $mform->hideIf('config_dashboardid_none', 'config_source', 'neq', 'linked');
        }

        // Optional starter card (only used before any live edit is saved, and
        // only for a custom block). The full catalogue is available via
        // "Add chart" inside the block.
        $options = ['' => get_string('nostarter', 'block_learnboard')]
            + block_learnboard::curated_cards()
            + ['__custom__' => get_string('custom', 'block_learnboard')];
        $mform->addElement('select', 'config_kind', get_string('startercard', 'block_learnboard'), $options);
        $mform->setType('config_kind', PARAM_TEXT);
        $mform->addHelpButton('config_kind', 'startercard', 'block_learnboard');
        $mform->hideIf('config_kind', 'config_source', 'eq', 'linked');

        $mform->addElement('text', 'config_customkind', get_string('customkind', 'block_learnboard'));
        $mform->setType('config_customkind', PARAM_TEXT);
        $mform->addHelpButton('config_customkind', 'customkind', 'block_learnboard');
        $mform->hideIf('config_customkind', 'config_kind', 'neq', '__custom__');
        $mform->hideIf('config_customkind', 'config_source', 'eq', 'linked');
    }
}
