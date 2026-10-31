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
 * Site-admin settings for the LearnBoard integration.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Three pages under Plugins, Blocks, in this order (LB-36): Connect to
// LearnBoard, the one page an administrator needs; the block's own page
// ($settings, section "blocksettinglearnboard"), now LearnBoard advanced
// settings, with the connection details, data connector, sign-in and menu
// entry, changed only if support asks; and the role map.
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'block_learnboard/advancedintro',
        '',
        $OUTPUT->notification(get_string(
            'advancedintro_desc',
            'block_learnboard',
            (new moodle_url('/admin/settings.php', ['section' => 'block_learnboard_connect']))->out(false)
        ), 'info', false)
    ));

    // Upgraded from the two-plugin version: say so once, next to the settings
    // it brought across, so an admin knows the old connector can go.
    if (\block_learnboard\migration::legacy_installed()) {
        $settings->add(new admin_setting_heading(
            'block_learnboard/legacynotice',
            get_string('legacynotice_heading', 'block_learnboard'),
            get_string('legacynotice_desc', 'block_learnboard')
        ));
    }

    $settings->add(new admin_setting_heading(
        'block_learnboard/connheading',
        get_string('connheading', 'block_learnboard'),
        get_string('connheading_desc', 'block_learnboard')
    ));

    // Absolute LearnBoard API base, e.g. https://learnboard.example.com/api/v1.
    $settings->add(new \block_learnboard\admin\setting_guarded_configtext(
        'block_learnboard/apibase',
        get_string('apibase', 'block_learnboard'),
        get_string('apibase_desc', 'block_learnboard'),
        'https://learnboard.learnoow.com/api/v1',
        PARAM_URL
    ));

    // Shared secret (X-Embed-Key) — never exposed to the browser.
    $settings->add(new \block_learnboard\admin\setting_guarded_configpasswordunmask(
        'block_learnboard/embedkey',
        get_string('embedkey', 'block_learnboard'),
        get_string('embedkey_desc', 'block_learnboard'),
        ''
    ));

    // The LearnBoard tenant this Moodle site maps to (1:1 for v1).
    $settings->add(new \block_learnboard\admin\setting_guarded_configtext(
        'block_learnboard/tenant',
        get_string('tenant', 'block_learnboard'),
        get_string('tenant_desc', 'block_learnboard'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_learnboard/insecuressl',
        get_string('insecuressl', 'block_learnboard'),
        get_string('insecuressl_desc', 'block_learnboard'),
        0
    ));
    // Certificate checks off is for a development or on-premises install only.
    // Said in plain sight while it is on, so it is not left on in production.
    if ((int) get_config('block_learnboard', 'insecuressl') === 1) {
        $settings->add(new admin_setting_heading(
            'block_learnboard/insecuresslwarning',
            '',
            $OUTPUT->notification(get_string('insecuressl_warning', 'block_learnboard'), 'warning')
        ));
    }

    $settings->add(new admin_setting_configselect(
        'block_learnboard/theme',
        get_string('theme', 'block_learnboard'),
        get_string('theme_desc', 'block_learnboard'),
        'moodle',
        [
            'moodle' => get_string('theme_moodle', 'block_learnboard'),
            'system' => get_string('theme_system', 'block_learnboard'),
            'light' => get_string('theme_light', 'block_learnboard'),
            'dark' => get_string('theme_dark', 'block_learnboard'),
        ]
    ));

    // Data connector: LearnBoard reads this site's data through query.php
    // instead of connecting to the database, so no database port is opened.
    $settings->add(new admin_setting_heading(
        'block_learnboard/queryheading',
        get_string('queryheading', 'block_learnboard'),
        get_string('queryheading_desc', 'block_learnboard', (new moodle_url('/blocks/learnboard/query.php'))->out(false))
    ));

    $settings->add(new \block_learnboard\admin\setting_guarded_configcheckbox(
        'block_learnboard/queryenabled',
        get_string('queryenabled', 'block_learnboard'),
        get_string('queryenabled_desc', 'block_learnboard'),
        0
    ));

    $settings->add(new \block_learnboard\admin\setting_guarded_configpasswordunmask(
        'block_learnboard/querykey',
        get_string('querykey', 'block_learnboard'),
        get_string('querykey_desc', 'block_learnboard'),
        ''
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_learnboard/queryallowedips',
        get_string('queryallowedips', 'block_learnboard'),
        get_string('queryallowedips_desc', 'block_learnboard'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_learnboard/maxconcurrent',
        get_string('maxconcurrent', 'block_learnboard'),
        get_string('maxconcurrent_desc', 'block_learnboard'),
        '8',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'block_learnboard/querydbuser',
        get_string('querydbuser', 'block_learnboard'),
        get_string('querydbuser_desc', 'block_learnboard'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'block_learnboard/querydbpass',
        get_string('querydbpass', 'block_learnboard'),
        get_string('querydbpass_desc', 'block_learnboard'),
        ''
    ));

    // Per-user SSO (#1): when on, charts render AS the logged-in user
    // (role-aware) and staff get a one-click "Open LearnBoard" full-app sign-in.
    // When off, everything renders as the shared read-only tenant reader.
    $settings->add(new \block_learnboard\admin\setting_guarded_configcheckbox(
        'block_learnboard/ssoenabled',
        get_string('ssoenabled', 'block_learnboard'),
        get_string('ssoenabled_desc', 'block_learnboard'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'block_learnboard/shownav',
        get_string('shownav', 'block_learnboard'),
        get_string('shownav_desc', 'block_learnboard'),
        1
    ));
}

// Connect to LearnBoard first, then the advanced page, then the role map.
// Moodle writes a form's settings in tree order, so on a form holding both
// pages (admin search results) the advanced fields come after the code; the
// ones a code fills in are guarded and leave the code's values alone (see
// setting_guarded_configtext). Core would add $settings after this file runs,
// so the pages are added here, in order, and $settings is cleared so it is
// not added twice.
if ($hassiteconfig) {
    // Connect to LearnBoard: the one field an administrator fills in. No
    // heading title: the page is already called "Connect to LearnBoard", and
    // the same words twice in a row read as a mistake. Steps 1 and 2 are drawn
    // by the code field, with its own Save and connect inside step 2; step 3
    // stays locked until the site connects (owner request, 23 Oct 2026).
    $connect = new admin_settingpage('block_learnboard_connect', get_string('connectioncode_heading', 'block_learnboard'));
    $connect->add(new \block_learnboard\admin\setting_connection_code(
        'block_learnboard/connectioncode',
        get_string('connectioncode', 'block_learnboard'),
        get_string('connectioncode_desc', 'block_learnboard'),
        ''
    ));
    // Step 3: where the dashboards are, and what comes with them.
    $connect->add(new \block_learnboard\admin\setting_connected_panel('block_learnboard/connectedpanel'));
    $ADMIN->add('blocksettings', $connect);

    // The technical page: change only if support asks.
    $settings->visiblename = get_string('advancedsettings', 'block_learnboard');
    $ADMIN->add('blocksettings', $settings);

    // Configurable role mapping (#1): a dedicated page (dynamic dropdowns fetched
    // from LearnBoard, so it can't live as a static setting element).
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_learnboard_rolemap',
        get_string('rolemap', 'block_learnboard'),
        new moodle_url('/blocks/learnboard/rolemap.php')
    ));
    $settings = null;
}
