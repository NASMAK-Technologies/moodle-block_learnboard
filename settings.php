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

// The block's own page ($settings, section "blocksettinglearnboard") carries the
// connection, the data connector, sign-in and the menu entry. The connection
// code and the role map are pages of their own beside it, as they were in the
// connector plugin.
if ($ADMIN->fulltree) {
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
    $settings->add(new admin_setting_configtext(
        'block_learnboard/apibase',
        get_string('apibase', 'block_learnboard'),
        get_string('apibase_desc', 'block_learnboard'),
        'https://learnboard.learnoow.com/api/v1',
        PARAM_URL
    ));

    // Shared secret (X-Embed-Key) — never exposed to the browser.
    $settings->add(new admin_setting_configpasswordunmask(
        'block_learnboard/embedkey',
        get_string('embedkey', 'block_learnboard'),
        get_string('embedkey_desc', 'block_learnboard'),
        ''
    ));

    // The LearnBoard tenant this Moodle site maps to (1:1 for v1).
    $settings->add(new admin_setting_configtext(
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

    $settings->add(new admin_setting_configcheckbox(
        'block_learnboard/queryenabled',
        get_string('queryenabled', 'block_learnboard'),
        get_string('queryenabled_desc', 'block_learnboard'),
        0
    ));

    $settings->add(new admin_setting_configpasswordunmask(
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
    $settings->add(new admin_setting_configcheckbox(
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

// Order matters. Moodle writes a form's settings in tree order, and the page it
// shows after an install lists every new setting on one form: the connection
// code must be written after the detailed fields, or their old values would be
// saved over what the code just set. Core would add $settings after this file
// runs, which puts it last, so the main page is added here, first, and
// $settings is cleared so it is not added twice.
if ($hassiteconfig) {
    $ADMIN->add('blocksettings', $settings);

    // Connect to LearnBoard: the one field an administrator fills in. Its own
    // page, added after the detailed settings, for two reasons. Moodle writes
    // a form's settings in order, so on a shared form the detailed fields
    // saved after the code would put their old values back. And on the page
    // Moodle shows straight after an install, which lists every new setting,
    // this one is then written last.
    $connect = new admin_settingpage('block_learnboard_connect', get_string('connectioncode_heading', 'block_learnboard'));
    // No heading title: the page is already called "Connect to LearnBoard",
    // and the same words twice in a row read as a mistake.
    $connect->add(new admin_setting_heading(
        'block_learnboard/codeheading',
        '',
        get_string('connectioncode_heading_desc', 'block_learnboard')
    ));
    $connect->add(new \block_learnboard\admin\setting_connection_code(
        'block_learnboard/connectioncode',
        get_string('connectioncode', 'block_learnboard'),
        get_string('connectioncode_desc', 'block_learnboard') . '<br><strong>' .
            \block_learnboard\admin\setting_connection_code::status_text() . '</strong>',
        ''
    ));
    // Once connected: where the dashboards are, and what comes with them.
    $connect->add(new \block_learnboard\admin\setting_connected_panel('block_learnboard/connectedpanel'));
    $ADMIN->add('blocksettings', $connect);

    // Configurable role mapping (#1): a dedicated page (dynamic dropdowns fetched
    // from LearnBoard, so it can't live as a static setting element).
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_learnboard_rolemap',
        get_string('rolemap', 'block_learnboard'),
        new moodle_url('/blocks/learnboard/rolemap.php')
    ));
    $settings = null;
}
