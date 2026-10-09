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

        // Audience (M5): show this block's dashboard only to people holding one
        // of the chosen roles. Any role on the site is offered, custom ones
        // (such as a company manager role) included. Blocks set up before
        // 1.1.13 kept a single archetype; that still applies until roles are
        // chosen here.
        $roles = self::site_roles();
        $audience = $mform->addElement('select', 'config_audienceroles', get_string('audience', 'block_learnboard'), $roles);
        $audience->setMultiple(true);
        $mform->addHelpButton('config_audienceroles', 'audience', 'block_learnboard');

        // Dashboard source (M7). "Custom" = arrange charts here in Moodle (the
        // original behaviour). "Linked" = mirror a named LearnBoard dashboard
        // live and read-only; edits happen in LearnBoard and show here on reload.
        $mform->addElement('select', 'config_source', get_string('source', 'block_learnboard'), [
            'custom' => get_string('source_custom', 'block_learnboard'),
            'linked' => get_string('source_linked', 'block_learnboard'),
            'team' => get_string('source_team', 'block_learnboard'),
            'mylearning' => get_string('source_mylearning', 'block_learnboard'),
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
        $mform->hideIf('config_kind', 'config_source', 'neq', 'custom');

        $mform->addElement('text', 'config_customkind', get_string('customkind', 'block_learnboard'));
        $mform->setType('config_customkind', PARAM_TEXT);
        $mform->addHelpButton('config_customkind', 'customkind', 'block_learnboard');
        $mform->hideIf('config_customkind', 'config_kind', 'neq', '__custom__');
        $mform->hideIf('config_customkind', 'config_source', 'neq', 'custom');

        // Per-role rules (1.1.13): one block, a different view for each role.
        // The first rule whose role the viewer holds anywhere on the site
        // decides what they see; nobody matching sees the block as set above.
        $mform->addElement('header', 'rulesheader', get_string('rules', 'block_learnboard'));
        $mform->addElement('static', 'rules_help', '', get_string('rules_desc', 'block_learnboard'));
        $views = [
            '' => get_string('rule_none', 'block_learnboard'),
            'team' => get_string('source_team', 'block_learnboard'),
            'mylearning' => get_string('source_mylearning', 'block_learnboard'),
            'hide' => get_string('rule_hide', 'block_learnboard'),
        ];
        foreach ($dashboards as $id => $name) {
            $views['dash:' . (int) $id] = get_string('rule_dashboard', 'block_learnboard', $name);
        }
        $roleoptions = ['' => get_string('rule_none', 'block_learnboard')] + $roles;
        $group = [
            $mform->createElement('select', 'config_rulerole', get_string('rule_role', 'block_learnboard'), $roleoptions),
            $mform->createElement('select', 'config_ruleview', get_string('rule_view', 'block_learnboard'), $views),
        ];
        $existing = 0;
        if (isset($this->block->config->rulerole) && is_array($this->block->config->rulerole)) {
            $existing = count($this->block->config->rulerole);
        }
        $this->repeat_elements(
            [$mform->createElement('group', 'rulegroup', get_string('rule', 'block_learnboard'), $group, ' ', false)],
            max(3, $existing + 1),
            [],
            'rule_repeats',
            'rule_add',
            2,
            get_string('rule_add', 'block_learnboard'),
            true
        );
    }

    /**
     * Every role on the site that a person can hold, custom ones included,
     * keyed by shortname.
     *
     * @return array<string, string>
     */
    public static function site_roles(): array {
        $out = [];
        foreach (role_get_names(\context_system::instance(), ROLENAME_ORIGINAL) as $role) {
            if (in_array($role->archetype, ['guest', 'user', 'frontpage'], true)) {
                continue;
            }
            $out[$role->shortname] = $role->localname;
        }

        return $out;
    }
}
