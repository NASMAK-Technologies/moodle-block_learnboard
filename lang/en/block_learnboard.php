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
 * Language strings for block_learnboard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LearnBoard';
$string['learnboard:addinstance'] = 'Add a new LearnBoard block';
$string['learnboard:myaddinstance'] = 'Add a new LearnBoard block to the Dashboard';

$string['blocktitle'] = 'Block title';
$string['blocktitle_help'] = 'Heading shown at the top of the block. Leave blank for the default.';
$string['minheight'] = 'Minimum height (px)';
$string['minheight_help'] = 'The minimum height of the block content area. The chart grid grows taller as you add or resize cards.';
$string['startercard'] = 'Starter chart (optional)';
$string['startercard_help'] = 'An optional first chart to seed a brand-new block. Once you add or rearrange charts with the in-block editor, this is ignored. Pick "Custom…" to enter any LearnBoard card kind.';
$string['nostarter'] = 'None — start empty';
$string['loadfailed'] = 'The LearnBoard charts could not be loaded:';
$string['starter_active30d'] = 'Active users (30 days)';
$string['starter_activitytrend'] = 'Activity trend';
$string['starter_atrisk'] = 'At-risk learners';
$string['starter_completionrate'] = 'Site completion rate';
$string['starter_courses'] = 'Total courses';
$string['starter_enrolments'] = 'Total enrolments';
$string['starter_totalusers'] = 'Total users';
$string['starter_healthscore'] = 'Health score';
$string['starter_enrolmentvscompletion'] = 'Enrolment vs completion';
$string['starter_topcourses'] = 'Top courses';
$string['starter_timetocompletion'] = 'Time to completion';
$string['starter_loginheatmap'] = 'Login heatmap';
$string['starter_certificateissuance'] = 'Certificate issuance';
$string['starter_completionfunnel'] = 'Completion funnel';
$string['starter_atriskoverview'] = 'At-risk overview';
$string['starter_skillsgapheatmap'] = 'Skills-gap heatmap';
$string['audience'] = 'Show to';
$string['audience_help'] = 'Show this block only to people who hold one of the chosen roles anywhere on the site, custom roles included. Choose none to show it to everyone. Authors always see the block while editing. To give each role its own view in one block, use the rules below instead.';
$string['audience_all'] = 'Everyone';
$string['audience_manager'] = 'Managers';
$string['audience_editingteacher'] = 'Teachers (editing)';
$string['audience_teacher'] = 'Teachers (non-editing)';
$string['audience_student'] = 'Students';
$string['card'] = 'Chart / card';
$string['card_help'] = 'Which LearnBoard chart to render. Pick one from the list, or choose "Custom…" and enter any LearnBoard card kind below.';
$string['customkind'] = 'Custom card kind';
$string['customkind_help'] = 'Advanced: a LearnBoard widget kind such as executive.activity-trend. Used only when "Custom…" is selected above. Overrides the list selection.';
$string['cardtitle'] = 'Card title';
$string['cardtitle_help'] = 'Optional title rendered inside the card itself.';
$string['size'] = 'Height';
$string['size_help'] = 'How tall the embedded chart should be.';
$string['size_small'] = 'Small (280px)';
$string['size_box'] = 'Box (360px)';
$string['size_tall'] = 'Tall (520px)';

$string['choose'] = 'Choose a chart…';
$string['custom'] = 'Custom…';

$string['source'] = 'Dashboard source';
$string['source_help'] = 'Custom layout: build and arrange charts here in Moodle. Linked to a LearnBoard dashboard: mirror a dashboard you built in LearnBoard — it shows live and read-only, and any edits you make in LearnBoard appear here on the next page load.';
$string['source_custom'] = 'Custom layout (edit here in Moodle)';
$string['source_linked'] = 'Linked to a LearnBoard dashboard';
$string['source_mylearning'] = 'My learning (the viewer\'s own progress)';
$string['source_team'] = 'My team (the viewer\'s own groups)';
$string['personalneedssso'] = 'My team and My learning show each person their own data, so they need per-user sign-in. Turn it on in Site administration, Plugins, Blocks, LearnBoard advanced settings.';
$string['rule'] = 'Rule';
$string['rule_add'] = 'Add two more rules';
$string['rule_dashboard'] = 'Dashboard: {$a}';
$string['rule_hide'] = 'Nothing (hide the block)';
$string['rule_none'] = 'Choose...';
$string['rule_role'] = 'People with the role';
$string['rule_view'] = 'see';
$string['rules'] = 'What each role sees';
$string['rules_desc'] = 'Give each role its own view in this one block, for example company managers see My team, students see My learning, and managers see a dashboard. The first rule whose role the viewer holds anywhere on the site applies. Anyone matching no rule sees the block as set above.';
$string['dashboard'] = 'LearnBoard dashboard';
$string['dashboard_help'] = 'The LearnBoard dashboard this block mirrors. A dashboard appears here when, in LearnBoard, it is published to everyone in the workspace, or shared with the "Moodle Embed (read-only)" entry in its Share dialog. The block always shows the current version.';
$string['choosedashboard'] = 'Choose a dashboard…';
$string['nodashboards'] = 'No shared dashboards found. In LearnBoard, open the dashboard, click Share, and either publish it to everyone in the workspace or add "Moodle Embed (read-only)" under Share with people. Then reopen these settings.';
$string['notlinked'] = 'This block is set to "Linked" but no LearnBoard dashboard is chosen yet. Turn editing on and pick one in the block settings.';

$string['notconfiguredsite'] = 'The LearnBoard connection is not set up yet. A site administrator connects it under Site administration → Plugins → Blocks → <a href="{$a}">Connect to LearnBoard</a>.';
$string['notconfiguredblock'] = 'This block has no chart selected yet. Turn editing on and choose a chart in the block settings.';
$string['tokenfailed'] = 'Could not connect to LearnBoard. A site administrator can check the connection under Site administration, Plugins, Blocks, Connect to LearnBoard, and save a new connection code there if LearnBoard no longer accepts this site.';


// Connector, sign-in, full page and settings (from local_learnboard).
$string['connectioncode_heading'] = 'Connect to LearnBoard';
$string['connectioncode'] = 'Connection code';
$string['connectioncode_desc'] = 'Starts with LBC1. The code is not kept, so this box is empty again after saving. Leave it empty to keep the current connection.';
$string['connectioncode_invalid'] = 'That is not a LearnBoard connection code. In LearnBoard, open Admin, Data sources, make a new code and copy it again.';
$string['connectioncode_status_none'] = 'Not connected yet.';
$string['connectioncode_status_active'] = 'Connected on {$a}. LearnBoard is reading this site.';
$string['connectioncode_status_connected'] = 'Connected on {$a}. Switch this site on in LearnBoard, Admin, Data sources, to start reading it.';
$string['connectioncode_status_failed'] = 'Last attempt on {$a->when} did not connect ({$a->reason}). Check the code is the newest one, and that this site is reachable over https.';
$string['connectionreason_address_not_allowed'] = 'the address of this site is not a public https address';
$string['connectionreason_code_not_accepted'] = 'LearnBoard did not accept the code';
$string['connectionreason_code_replaced'] = 'the LearnBoard workspace has no connection code yet';
$string['connectionreason_no_connection_code'] = 'the LearnBoard workspace has no connection code yet';
$string['connectionreason_not_configured'] = 'the connection code has not been saved here';
$string['connectionreason_site_key_refused'] = 'LearnBoard did not hear back from this site while it registered, so nothing changed. Save the code again';
$string['connectionreason_too_many_attempts'] = 'too many attempts, wait a minute and save again';
$string['connectionreason_unreachable'] = 'LearnBoard could not be reached from this site';
$string['connectedcopied'] = 'Link copied.';
$string['connectedcopy'] = 'Copy link';
$string['connectededit'] = 'Edit dashboards';
$string['connectedeyebrow'] = 'Step 3 · LearnBoard is ready';
$string['connectstep'] = 'Step {$a}';
$string['connectstepdone'] = 'Done';
$string['connectstep1_link'] = 'Open LearnBoard';
$string['connectstep1_text'] = 'Sign in to LearnBoard. Open LearnBoard takes you to Data sources, where you make a connection code and copy it.';
$string['connectstep1_title'] = 'Get your connection code';
$string['connectstep2_reconnect'] = 'This site is connected. To connect it again, for example with a new code, paste the new code here and press Save and connect.';
$string['connectstep2_text'] = 'Paste the code here, then press Save and connect. The result shows below the box.';
$string['connectstep2_title'] = 'Paste the code and connect';
$string['connectstep3_locked'] = 'Opens once the code is saved and this site is connected.';
$string['connectstep3_title'] = 'Open your dashboards';
$string['connectedlede'] = 'Your charts are live. Here is where to find them and how to make them easy to reach.';
$string['connectedlinklabel'] = 'Your LearnBoard dashboards';
$string['connectedmenuhint'] = 'LearnBoard is already in the top menu for people allowed to see it. For a left-hand sidebar, paste the link above into your theme\'s menu or sidebar settings.';
$string['connectedmenuhintold'] = 'Add it as a custom menu item under Site administration, Appearance: LearnBoard|{$a}. For a left-hand sidebar, paste the link into your theme\'s menu or sidebar settings.';
$string['connectedmenutitle'] = 'Add it to your menu';
$string['connectedopen'] = 'Open dashboards';
$string['connectedstarttext'] = 'Your workspace comes with a ready-made LMS Overview dashboard. Edit it, or create as many dashboards as you like, in LearnBoard. They appear here as tabs.';
$string['connectedstarttitle'] = 'Start with LMS Overview';
$string['connectedtitle'] = 'You\'re connected';
$string['connheading'] = 'LearnBoard connection';
$string['connheading_desc'] = 'Filled in by the connection code.';
$string['apibase'] = 'LearnBoard API base URL';
$string['apibase_desc'] = 'Filled in by the connection code. The address of the LearnBoard API, including the version segment, for example https://learnboard.example.com/api/v1. It must be reachable from this server and from the browser.';
$string['embedkey'] = 'Embed API key';
$string['embedkey_desc'] = 'Filled in by the connection code: the key that opens this site\'s LearnBoard workspace, and only that workspace. Sent from this server only, as the X-Embed-Key header, over https; it is never shown to the browser. To change it, make a new connection code in LearnBoard and save it on Connect to LearnBoard.';
$string['tenant'] = 'LearnBoard tenant slug';
$string['tenant_desc'] = 'Filled in by the connection code: the LearnBoard workspace this Moodle site connects to.';
$string['insecuressl'] = 'Allow self-signed SSL (dev / on-prem)';
$string['insecuressl_desc'] = 'For a development or on-premises LearnBoard with a self-signed or private-CA certificate only. It turns off certificate checks on requests to LearnBoard. Leave it off for any LearnBoard on the internet. If LearnBoard is on a private network, also allow its address in Site administration > Security > HTTP security.';
$string['insecuressl_warning'] = 'Certificate checks on requests to LearnBoard are turned off. Turn "Allow self-signed SSL" off unless this is a development or on-premises install.';
$string['theme'] = 'Theme';
$string['theme_desc'] = 'Colour theme for embedded charts. "Follow the LMS" tracks this Moodle site\'s light/dark mode live, so the charts switch when the user toggles the LMS theme.';
$string['theme_moodle'] = 'Follow the LMS (light / dark)';
$string['theme_system'] = 'Follow the device';
$string['theme_light'] = 'Light';
$string['theme_dark'] = 'Dark';
$string['fullpagetitle'] = 'LearnBoard dashboard';
$string['fullpagenav'] = 'LearnBoard';
$string['ssoenabled'] = 'Enable per-user sign-in (SSO)';
$string['ssoenabled_desc'] = 'When enabled, embedded charts render as the individual logged-in user (role-aware), and staff get a one-click "Open LearnBoard" link that signs them into the full LearnBoard app with no second login. When disabled, everything renders as a single shared read-only reader for the tenant.';
$string['ssonav'] = 'Open LearnBoard';
$string['ssonotavailable'] = 'Opening the full LearnBoard app is not available for your account.';
$string['ssologinas'] = 'LearnBoard cannot be opened while you are logged in as another user.';
$string['ssofailed'] = 'Could not create a LearnBoard sign-in link. Try again, or ask a site administrator to check Connect to LearnBoard.';
$string['rolemap'] = 'Role mapping';
$string['rolemap_intro'] = 'Choose which LearnBoard role each Moodle role receives when per-user sign-in (SSO) is enabled. When a user holds several Moodle roles, they get the most-privileged LearnBoard role among them. Leaving a Moodle role unmapped falls back to the "Unmapped users" default. The role list is fetched live from LearnBoard.';
$string['rolemap_notmapped'] = 'Not mapped (use default)';
$string['rolemap_default'] = 'Unmapped users (default)';
$string['rolemap_siteadmin'] = 'Site administrator';
$string['rolemap_writewarn'] = '⚠ grants write access';
$string['rolemap_saved'] = 'Role mapping saved.';
$string['rolemap_lbunreachable'] = 'Could not reach LearnBoard to load its current roles. Showing the last known list (which may be empty or out of date). Check Connect to LearnBoard, then reload this page.';
$string['fullpagedashboard'] = 'Dashboard';
$string['fullpagenodashboards'] = 'No LearnBoard dashboards are shared with this site yet. In LearnBoard, open a dashboard, click Share, and either add "Moodle Embed (read-only)" under Share with people or publish it to everyone in the workspace. Then reload this page.';
$string['learnboard:view'] = 'See LearnBoard analytics through the shared workspace reader';
$string['noaccess'] = 'LearnBoard analytics are not available for your account here. Ask a site administrator if you need access.';
$string['privacy:metadata:learnboard_sso'] = 'When per-user sign-in is on, the identity of the signed-in user is sent to LearnBoard so that charts and the full app run as that person. The same identity is sent when the person links their Moodle account to their own LearnBoard account.';
$string['privacy:metadata:learnboard_sso:external_id'] = 'The Moodle user id.';
$string['privacy:metadata:learnboard_sso:name'] = 'The full name of the user.';
$string['privacy:metadata:learnboard_sso:roles'] = 'The Moodle roles the user holds, and the LearnBoard roles they map to.';
$string['privacy:metadata:learnboard_sso:source'] = 'The address of this Moodle site.';
$string['privacy:metadata:learnboard_charts'] = 'To draw the charts, the browser of the person viewing loads the LearnBoard chart script and the figures directly from LearnBoard, so LearnBoard receives what any website receives from a visitor.';
$string['privacy:metadata:learnboard_charts:ipaddress'] = 'The IP address of the browser.';
$string['privacy:metadata:learnboard_charts:useragent'] = 'The browser and device the viewer uses.';
$string['privacy:metadata:learnboard_connector'] = 'When the data connector is on, LearnBoard reads reporting data from the database of this site through the plugin: user profiles, enrolments, course activity, grades, completions and logs. Tables holding site secrets and password, token and key columns are never sent. The data is shown in LearnBoard dashboards for this organisation.';
$string['privacy:metadata:learnboard_connector:userprofile'] = 'User profile fields such as name, email, department and last access.';
$string['privacy:metadata:learnboard_connector:learningrecords'] = 'Enrolments, activity logs, grades, completions and certificates.';
$string['maxconcurrent'] = 'Requests to answer at once';
$string['maxconcurrent_desc'] = 'How many LearnBoard requests this site will work on at the same moment. Anything above this is told to come back shortly, and LearnBoard waits and asks again, so a reader sees a slower chart rather than a broken one. Lower it on a small or shared server. Set to 0 for no limit.';
$string['queryallowedips'] = 'LearnBoard server addresses (optional)';
$string['queryallowedips_desc'] = 'Only accept connector requests from these addresses. Enter IP addresses or ranges such as 203.0.113.10 or 198.51.100.0/24, one per line. Leave empty to accept signed requests from any address.';
$string['queryheading'] = 'Data connector';
$string['queryheading_desc'] = 'Lets LearnBoard read the reporting data of this site through the plugin, so the database never has to accept connections from outside this server. Saving a connection code on Connect to LearnBoard switches it on and fills in the connector key. LearnBoard sends its queries to {$a}.';
$string['queryenabled'] = 'Allow LearnBoard to read data through the plugin';
$string['queryenabled_desc'] = 'When on, LearnBoard can send signed, read-only queries to this site. Every query is checked here before it runs: only reads are allowed, the database session is read-only, and tables holding site secrets (configuration, sessions, web service tokens, private keys) are refused.';
$string['querykey'] = 'Connector key';
$string['querykey_desc'] = 'Filled in by the connection code. LearnBoard signs every request to the data connector with it, and requests that are not signed with it are refused. To change it, make a new connection code in LearnBoard and save it on Connect to LearnBoard.';
$string['querydbuser'] = 'Read-only database user (optional)';
$string['querydbuser_desc'] = 'A database account granted SELECT only on the database of this site. When set, the queries from LearnBoard run on it instead of the Moodle account, so a write is impossible at the database level as well. Leave empty to use the Moodle account with a read-only session.';
$string['querydbpass'] = 'Read-only database password';
$string['querydbpass_desc'] = 'The password for the read-only database user above.';

// Main menu entry and the move from the two-plugin version (1.1.0).
$string['shownav'] = 'Show LearnBoard in the main menu';
$string['shownav_desc'] = 'Adds a LearnBoard item to the main menu, leading to the full-page view of your LearnBoard dashboards, for people allowed to see LearnBoard at site level. Needs Moodle 4.3 or later. Themes with their own sidebar add the link through the theme settings instead: see the README.';
$string['legacynotice_heading'] = 'The LearnBoard connector plugin is no longer needed';
$string['legacynotice_desc'] = 'This plugin now includes everything the separate LearnBoard connector (local_learnboard) did, and its settings were copied here when you upgraded. Once charts and the LearnBoard page work, uninstall the connector under Site administration, Plugins, Plugins overview. LearnBoard keeps working throughout.';
$string['fullpagecharts'] = 'charts';
$string['fullpageedit'] = 'Edit dashboards in LearnBoard';
$string['fullpageexitfullscreen'] = 'Exit full screen';
$string['fullpageeyebrow'] = 'LearnBoard dashboards';
$string['fullpagefullscreen'] = 'Full screen';
$string['fullpagelive'] = 'Live from';
$string['fullpageopen'] = 'Open in LearnBoard';
$string['advancedsettings'] = 'LearnBoard advanced settings';
$string['advancedintro_desc'] = 'Change these only if LearnBoard support asks you to. To connect this site, use <a href="{$a}">Connect to LearnBoard</a>.';
$string['connectioncode_status_reading'] = 'Connected. LearnBoard is reading this site.';
$string['connectsave'] = 'Save and connect';
$string['connectionlost_revoked'] = 'This site\'s LearnBoard workspace no longer accepts it. Make a new code in LearnBoard and save it here.';
$string['connectionlost_missing'] = 'LearnBoard no longer lists this site as a data source. Make a new code in LearnBoard and save it here.';
$string['connectionlost_pending_deletion'] = 'This site\'s LearnBoard workspace is scheduled for deletion, so it no longer accepts this site. Restore it in LearnBoard, or make a new code and save it here.';
$string['connectioncheck_unreachable'] = 'LearnBoard could not be reached just now to confirm the connection.';
$string['cachedef_connectionstatus'] = 'Whether LearnBoard still accepts this site, for Connect to LearnBoard';
$string['linkpage_title'] = 'Link your Moodle account to LearnBoard';
$string['linkpage_confirm'] = 'Link your Moodle account, {$a->moodle}, to the LearnBoard account {$a->name} ({$a->email}) in the workspace {$a->workspace}? Opening LearnBoard from Moodle then signs you in as that LearnBoard account, with the role it has there.';
$string['linkpage_continue'] = 'Link my accounts';
$string['linkpage_expired'] = 'This link request is invalid or has expired. Start again from Profile and account in LearnBoard.';
$string['linkpage_conflict'] = 'Your Moodle account is already linked to another LearnBoard account in this workspace, or the LearnBoard account Moodle sign-in made for you has saved work. Ask a LearnBoard admin to sort it out, then link again.';
$string['linkpage_wrongsite'] = 'This link request was started for a different Moodle site. Start again from Profile and account in LearnBoard, and choose this site.';
$string['linkpage_failed'] = 'LearnBoard could not be reached to link your accounts. Try again in a moment.';
$string['linkpage_loginas'] = 'Accounts cannot be linked while you are logged in as another user.';
$string['linkpage_notconfigured'] = 'This site is not connected to LearnBoard yet.';
$string['linkpage_back'] = 'Back to LearnBoard';
