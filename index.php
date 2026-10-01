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
 * Full-page LearnBoard dashboard. Unlike the block (a panel you drop on a page),.
 * this is a dedicated, full-width screen — reachable from Moodle's navigation —
 * that mirrors a shared LearnBoard dashboard live and read-only. Pick which
 * dashboard via the selector (?d=<id>); only tenant-shared dashboards appear.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$dashboardid = optional_param('d', 0, PARAM_INT);

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/blocks/learnboard/index.php', $dashboardid ? ['d' => $dashboardid] : []));
$PAGE->set_pagelayout('report'); // Full width, minimal side blocks.
$PAGE->set_title(get_string('fullpagetitle', 'block_learnboard'));
// No page heading: the LearnBoard header below names the dashboard itself.
$PAGE->set_heading('');

echo $OUTPUT->header();

if (!\block_learnboard\client::viewer_may_see($context)) {
    echo html_writer::div(get_string('noaccess', 'block_learnboard'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}

if (!\block_learnboard\client::is_configured()) {
    echo html_writer::div(get_string('notconfiguredsite', 'block_learnboard'), 'alert alert-warning');
    echo $OUTPUT->footer();
    exit;
}

$dashboards = \block_learnboard\client::list_dashboards(); // Dashboard id => name.

if (empty($dashboards)) {
    echo html_writer::div(get_string('fullpagenodashboards', 'block_learnboard'), 'alert alert-info');
    echo $OUTPUT->footer();
    exit;
}

// Default to the first shared dashboard; clamp an unknown id back to it.
if ($dashboardid <= 0 || !isset($dashboards[$dashboardid])) {
    $dashboardid = (int) array_key_first($dashboards);
}

// The page header: the dashboards as tabs, the current one's name and chart
// count, and ways into LearnBoard. Drawn by the SDK so it shares the charts'
// colours and dark mode; dashboard blocks elsewhere in Moodle get no header.
$detail = \block_learnboard\client::dashboard_detail();
$tabs = [];
foreach ($dashboards as $id => $name) {
    $info = $detail[(string) $id] ?? [];
    $tabs[] = [
        'id' => (int) $id,
        'name' => (string) $name,
        'href' => (new moodle_url('/blocks/learnboard/index.php', ['d' => $id]))->out(false),
        'count' => $info['widget_count'] ?? null,
        'primary' => !empty($info['is_primary']),
        'active' => (int) $id === (int) $dashboardid,
    ];
}
// The workspace's main dashboard first, the rest in LearnBoard's order.
usort($tabs, function ($a, $b) {
    return (int) $b['primary'] <=> (int) $a['primary'];
});
$appbase = \block_learnboard\client::appbase();
$header = [
    'title' => (string) $dashboards[$dashboardid],
    'eyebrow' => get_string('fullpageeyebrow', 'block_learnboard'),
    'source' => (string) parse_url($CFG->wwwroot, PHP_URL_HOST),
    'tabs' => $tabs,
    // Signed straight in where single sign-on is on; otherwise the app itself.
    'openUrl' => \block_learnboard\ssouser::enabled()
        ? (new moodle_url('/blocks/learnboard/sso.php'))->out(false)
        : $appbase,
    'labels' => [
        'live' => get_string('fullpagelive', 'block_learnboard'),
        'charts' => get_string('fullpagecharts', 'block_learnboard'),
        'open' => get_string('fullpageopen', 'block_learnboard'),
        'edit' => get_string('fullpageedit', 'block_learnboard'),
        'fullscreen' => get_string('fullpagefullscreen', 'block_learnboard'),
        'exitFullscreen' => get_string('fullpageexitfullscreen', 'block_learnboard'),
        'choose' => get_string('fullpagedashboard', 'block_learnboard'),
    ],
];
if (has_capability('moodle/site:config', $context)) {
    $header['editUrl'] = $appbase . '/dashboards';
}

$tokendata = \block_learnboard\client::token_for_viewer($context);
if ($tokendata === null) {
    echo html_writer::div(get_string('tokenfailed', 'block_learnboard'), 'alert alert-danger');
    echo $OUTPUT->footer();
    exit;
}

// Not uniqid(): it is only the current microsecond (see block_learnboard.php).
$elementid = 'learnboard-embed-page-' . random_string(8);
$config = [
    'elementId' => $elementid,
    // The chart script and styles come from LearnBoard itself.
    'bundleUrl' => \block_learnboard\client::sdk_url('js'),
    'cssUrl' => \block_learnboard\client::sdk_url('css'),
    'apiBase' => \block_learnboard\client::apibase(),
    'token' => $tokendata['token'],
    // Renewed before it runs out, so a page left open keeps working.
    'tokenExpiresAt' => $tokendata['expires_at'] ?? null,
    'tokenRefreshUrl' => (new moodle_url('/blocks/learnboard/ajax/token.php', [
        'contextid' => $context->id,
        'sesskey' => sesskey(),
    ]))->out(false),
    'tenantSlug' => \block_learnboard\client::tenant(),
    'theme' => \block_learnboard\client::theme(),
    'dashboardId' => $dashboardid,
    'header' => $header,
    'failText' => get_string('loadfailed', 'block_learnboard'),
];

echo html_writer::div('', 'learnboard-embed-host', [
    'id' => $elementid,
    'style' => 'min-height:640px;width:100%;',
]);

$json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$js = <<<JS
(function(){
  var cfg = {$json};
  function detectLmsTheme(){
    // Many themes mark dark mode with a class on <body>; Bootstrap 5.3
    // themes set data-bs-theme instead.
    var b = document.body, h = document.documentElement;
    var cls = ['theme-dark', 'darkmode', 'dark-mode', 'dark'];
    for (var i = 0; i < cls.length; i++) {
      if ((b && b.classList.contains(cls[i])) || h.classList.contains(cls[i])) { return 'dark'; }
    }
    if (h.getAttribute('data-bs-theme') === 'dark' || (b && b.getAttribute('data-bs-theme') === 'dark')) { return 'dark'; }
    return 'light';
  }
  function resolveTheme(){ return cfg.theme === 'moodle' ? detectLmsTheme() : cfg.theme; }
  function fail(el, msg){ if (el) { el.innerHTML = '<div style="padding:1rem;color:#b91c1c;font:14px system-ui,sans-serif">'+msg+'</div>'; } }
  var loadPromise = window.__learnboardLoadPromise;
  if (!loadPromise) {
    loadPromise = window.__learnboardLoadPromise = new Promise(function(resolve, reject){
      if (window.LearnBoard && window.LearnBoard.mountDashboard) { resolve(); return; }
      var s = document.createElement('script');
      s.src = cfg.bundleUrl; s.async = true;
      s.onload = function(){
        if (window.LearnBoard && window.LearnBoard.mountDashboard) { resolve(); }
        else { reject(new Error('SDK loaded but the LearnBoard global is unavailable')); }
      };
      s.onerror = function(){ reject(new Error('Failed to load the LearnBoard SDK')); };
      document.head.appendChild(s);
    });
  }
  loadPromise.then(function(){
    return fetch(cfg.cssUrl).then(function(r){ return r.ok ? r.text() : ''; });
  }).then(function(css){
    var el = document.getElementById(cfg.elementId);
    if (!el) { return; }
    window.LearnBoard.mountDashboard(el, {
      dashboardId: cfg.dashboardId,
      apiBase: cfg.apiBase,
      token: cfg.token,
      tokenExpiresAt: cfg.tokenExpiresAt,
      tokenRefreshUrl: cfg.tokenRefreshUrl,
      tenantSlug: cfg.tenantSlug,
      theme: resolveTheme(),
      styles: { css: css, tokens: '' },
      header: cfg.header
    });
    if (cfg.theme === 'moodle' && window.MutationObserver && !window.__learnboardThemeObserver) {
      window.__learnboardThemeObserver = new MutationObserver(function(){
        if (window.LearnBoard && window.LearnBoard.setTheme) { window.LearnBoard.setTheme(detectLmsTheme()); }
      });
      window.__learnboardThemeObserver.observe(document.body, { attributes: true, attributeFilter: ['class', 'data-bs-theme'] });
      window.__learnboardThemeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'data-bs-theme'] });
    }
  }).catch(function(e){ fail(document.getElementById(cfg.elementId), cfg.failText+' '+((e && e.message) || e)); });
})();
JS;

echo html_writer::script($js);

echo $OUTPUT->footer();
