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
 * LearnBoard block — renders a live, editable LearnBoard dashboard natively.
 * (Shadow DOM, no iframe): multiple cards in the product's real grid, added
 * from the full catalogue and dragged/resized like on LearnBoard.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A Moodle block that mounts an embedded LearnBoard dashboard via the LearnBoard
 * embed SDK. The block mints a short-lived read-only token server-side, hands it
 * plus the saved layout to the browser SDK, and the SDK renders the real
 * LearnBoard cards in a Shadow DOM grid. Authors (capability-gated, while Moodle
 * editing is on) can add charts, remove them, and drag/resize each card; every
 * change is saved back into the block config via an AJAX endpoint. End users
 * only ever view.
 */
class block_learnboard extends block_base {
    /**
     * Sets the block title.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_learnboard');
    }

    /**
     * Where the block may be added: every page type.
     *
     * @return array
     */
    public function applicable_formats() {
        return ['all' => true];
    }

    /**
     * Several LearnBoard blocks may sit on one page.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return true;
    }

    /**
     * The plugin has site-wide settings.
     *
     * @return bool
     */
    public function has_config() {
        // The connection, the data connector, sign-in and the menu entry live
        // in this plugin's settings since the connector was folded in.
        return true;
    }

    /**
     * Uses the title an editor gave this block instance, when there is one.
     */
    public function specialization() {
        if (isset($this->config->title) && trim((string) $this->config->title) !== '') {
            $this->title = format_string($this->config->title);
        } else {
            $this->title = get_string('pluginname', 'block_learnboard');
        }
    }

    /**
     * Curated set of LearnBoard card kinds offered as quick starters. The live
     * editor's "Add chart" picker exposes the full ~150-card catalogue.
     *
     * @return array<string, string> kind => human label
     */
    public static function curated_cards(): array {
        return [
            'executive.activity-trend' => get_string('starter_activitytrend', 'block_learnboard'),
            'executive.health-score' => get_string('starter_healthscore', 'block_learnboard'),
            'executive.enrolment-vs-completion' => get_string('starter_enrolmentvscompletion', 'block_learnboard'),
            'executive.top-courses' => get_string('starter_topcourses', 'block_learnboard'),
            'executive.time-to-completion' => get_string('starter_timetocompletion', 'block_learnboard'),
            'executive.login-heatmap' => get_string('starter_loginheatmap', 'block_learnboard'),
            'executive.certificate-issuance' => get_string('starter_certificateissuance', 'block_learnboard'),
            'completion-funnel.funnel' => get_string('starter_completionfunnel', 'block_learnboard'),
            'at-risk.overview-gauge' => get_string('starter_atriskoverview', 'block_learnboard'),
            'skills-gap.heatmap' => get_string('starter_skillsgapheatmap', 'block_learnboard'),
        ];
    }

    /**
     * What a new block shows before anyone arranges it: the six headline
     * figures from the LMS Overview and two of its charts, not the whole
     * dashboard. A new block used to open on "No charts yet".
     *
     * Sizes are LearnBoard grid cells (12 columns); in a narrow column the
     * cards stack. The first edit in the block saves its own layout.
     *
     * @return array{items: array, layouts: stdClass}
     */
    public static function starter_layout(): array {
        $cards = [
            ['kpi.total-users', 'starter_totalusers', 2, 3],
            ['kpi.active-30d', 'starter_active30d', 2, 3],
            ['kpi.courses', 'starter_courses', 2, 3],
            ['kpi.enrolments', 'starter_enrolments', 2, 3],
            ['kpi.completion', 'starter_completionrate', 2, 3],
            ['kpi.at-risk', 'starter_atrisk', 2, 3],
            ['executive.activity-trend', 'starter_activitytrend', 6, 8],
            ['executive.completion-funnel', 'starter_completionfunnel', 6, 9],
        ];
        $items = [];
        foreach ($cards as [$kind, $string, $w, $h]) {
            $items[] = [
                'i' => 'starter::' . $kind,
                'kind' => $kind,
                'title' => get_string($string, 'block_learnboard'),
                'size' => ['w' => $w, 'h' => $h],
            ];
        }

        return ['items' => $items, 'layouts' => new stdClass()];
    }

    /**
     * The saved dashboard layout ({items, layouts}). Falls back to a legacy
     * single-card config (v0.1.0 blocks) or the starter set.
     *
     * @return array{items: array, layouts: mixed}
     */
    private function resolve_layout(): array {
        if (isset($this->config->layout) && is_string($this->config->layout) && $this->config->layout !== '') {
            $decoded = json_decode($this->config->layout, true);
            if (is_array($decoded) && isset($decoded['items']) && is_array($decoded['items'])) {
                if (!isset($decoded['layouts'])) {
                    $decoded['layouts'] = new stdClass();
                }
                return $decoded;
            }
        }

        // Legacy single-card starter (block v0.1.0): promote it into a one-item layout.
        $kind = '';
        if (isset($this->config->customkind) && trim((string) $this->config->customkind) !== '') {
            $kind = trim((string) $this->config->customkind);
        } else if (isset($this->config->kind) && trim((string) $this->config->kind) !== '' && $this->config->kind !== '__custom__') {
            $kind = trim((string) $this->config->kind);
        }
        if ($kind !== '') {
            $title = isset($this->config->cardtitle) && trim((string) $this->config->cardtitle) !== ''
                ? (string) $this->config->cardtitle
                : $kind;
            return [
                'items' => [['i' => $kind . '::1', 'kind' => $kind, 'title' => $title]],
                'layouts' => new stdClass(),
            ];
        }

        return self::starter_layout();
    }

    /**
     * Whether the current viewer belongs to the block's configured audience
     * (by role archetype in this context). 'all' and site admins always match.
     */
    private function viewer_matches_audience(): bool {
        global $USER, $DB;

        $audience = isset($this->config->audience) ? (string) $this->config->audience : 'all';
        if ($audience === '' || $audience === 'all' || is_siteadmin()) {
            return true;
        }

        $roles = get_user_roles($this->context, $USER->id, true);
        foreach ($roles as $ra) {
            if ($DB->get_field('role', 'archetype', ['id' => $ra->roleid]) === $audience) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds the block: the chart grid, or a note saying why there is none.
     *
     * @return stdClass|null
     */
    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->footer = '';

        if (!\block_learnboard\client::is_configured()) {
            $this->content->text = html_writer::div(
                get_string('notconfiguredsite', 'block_learnboard'),
                'learnboard-embed-notice'
            );

            return $this->content;
        }

        // Dashboard source (M7): a "linked" block mirrors a named LearnBoard
        // dashboard live + read-only; a "custom" block is arranged here in Moodle.
        $source = isset($this->config->source) ? (string) $this->config->source : 'custom';
        $linked = $source === 'linked';
        $dashboardid = ($linked && isset($this->config->dashboardid)) ? (int) $this->config->dashboardid : 0;

        // Authoring = page editing + capability. Only CUSTOM blocks are edited in
        // Moodle (linked ones are edited in LearnBoard), so the grid is editable
        // only for custom blocks — but authoring still lets an author see a
        // linked block even when the audience gate would hide it from end users.
        $authoring = $this->page->user_is_editing()
            && has_capability('block/learnboard:addinstance', $this->context);
        $editable = !$linked && $authoring;

        $layout = $linked ? ['items' => [], 'layouts' => new stdClass()] : $this->resolve_layout();

        // Audience gate (M5): if restricted to a role and the viewer neither
        // holds it nor is authoring, render nothing so the block hides — letting
        // a page carry a different LearnBoard dashboard per role.
        if (!$authoring && !$this->viewer_matches_audience()) {
            $this->content->text = '';

            return $this->content;
        }

        // Linked but no dashboard picked yet → educate the author, hide from others.
        if ($linked && $dashboardid <= 0) {
            $this->content->text = $authoring
                ? html_writer::div(get_string('notlinked', 'block_learnboard'), 'learnboard-embed-notice')
                : '';

            return $this->content;
        }

        // Custom block with nothing to show and the viewer can't author →
        // educational empty state.
        if (!$linked && empty($layout['items']) && !$authoring) {
            $this->content->text = html_writer::div(
                get_string('notconfiguredblock', 'block_learnboard'),
                'learnboard-embed-notice'
            );

            return $this->content;
        }

        // Per-user SSO (#1): when enabled, mint the token for the individual
        // logged-in user (charts run as them, role-aware) rather than the shared
        // tenant reader. Assertion is built server-side from $USER.
        // Without it, the only token is the workspace-wide reader, given only
        // to holders of block/learnboard:view here; anyone else sees nothing
        // rather than a token that reads every learner.
        if (!\block_learnboard\client::viewer_may_see($this->context)) {
            $this->content->text = $authoring
                ? html_writer::div(get_string('noaccess', 'block_learnboard'), 'learnboard-embed-notice')
                : '';

            return $this->content;
        }
        $tokendata = \block_learnboard\client::token_for_viewer($this->context);
        if ($tokendata === null) {
            $this->content->text = html_writer::div(
                get_string('tokenfailed', 'block_learnboard'),
                'learnboard-embed-notice'
            );

            return $this->content;
        }

        // Course context (M6): on a real course page, scope cards to that course
        // by injecting its id into card params (cards ignore params they don't use).
        $courseid = (isset($this->page->course->id) && (int) $this->page->course->id > 1) ? (int) $this->page->course->id : 0;

        $minheight = isset($this->config->minheight) ? max(160, (int) $this->config->minheight) : 320;
        // One id per block, never shared. uniqid() is only the current
        // microsecond, so two blocks drawn in the same instant got the same id:
        // both mounted into the first block (its charts twice) and the second
        // stayed empty. The block's own id is unique on the page by definition.
        $elementid = 'learnboard-embed-' . (int) $this->instance->id . '-' . random_string(6);

        $config = [
            'elementId' => $elementid,
            // The chart script and styles come from LearnBoard itself.
            'bundleUrl' => \block_learnboard\client::sdk_url('js'),
            'cssUrl' => \block_learnboard\client::sdk_url('css'),
            'saveUrl' => (new moodle_url('/blocks/learnboard/ajax/savelayout.php'))->out(false),
            'apiBase' => \block_learnboard\client::apibase(),
            'token' => $tokendata['token'],
            // Renewed before it runs out, so a page left open keeps working.
            'tokenExpiresAt' => $tokendata['expires_at'] ?? null,
            'tokenRefreshUrl' => (new moodle_url('/blocks/learnboard/ajax/token.php', [
                'contextid' => $this->context->id,
                'sesskey' => sesskey(),
            ]))->out(false),
            'tenantSlug' => \block_learnboard\client::tenant(),
            'theme' => \block_learnboard\client::theme(),
            'layout' => $layout,
            'editable' => $editable,
            'dashboardId' => $dashboardid > 0 ? $dashboardid : null,
            'courseId' => $courseid,
            'blockId' => (int) $this->instance->id,
            'failText' => get_string('loadfailed', 'block_learnboard'),
            'sesskey' => sesskey(),
        ];

        $host = html_writer::div('', 'learnboard-embed-host', [
            'id' => $elementid,
            'style' => 'min-height:' . $minheight . 'px;width:100%;',
        ]);

        $this->content->text = $host . self::mount_script($config);

        return $this->content;
    }

    /**
     * Self-contained loader + mount script. Loads the SDK bundle once per page,
     * fetches the compiled CSS, mounts the dashboard (editable or read-only), and
     * — in edit mode — debounces layout saves back to the block via AJAX.
     *
     * @param array $config
     * @return string
     */
    private static function mount_script(array $config): string {
        $json = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $js = <<<JS
(function(){
  var cfg = {$json};
  // This script's own chart area is the element printed just before it.
  // Some themes print a block a second time outside edit mode. The copy
  // repeats this HTML, id included, so both copies mounted into
  // the first chart area (charts twice) and the second stayed empty. Each copy
  // now takes its own area and a fresh id, and a repeat of a block already on
  // the page hides itself: one block, drawn once.
  var me = document.currentScript;
  var own = me && me.previousElementSibling;
  if (own && own.classList && own.classList.contains('learnboard-embed-host')) {
    window.__learnboardShown = window.__learnboardShown || {};
    if (window.__learnboardShown[cfg.blockId]) {
      var copy = own.closest('.block') || own;
      copy.style.display = 'none';
      return;
    }
    window.__learnboardShown[cfg.blockId] = true;
    cfg.elementId = cfg.elementId + '-' + Math.random().toString(36).slice(2, 8);
    own.id = cfg.elementId;
  }
  window.__learnboardLoad = window.__learnboardLoad || function(url){
    if (window.__learnboardLoadPromise) { return window.__learnboardLoadPromise; }
    window.__learnboardLoadPromise = new Promise(function(resolve, reject){
      if (window.LearnBoard && window.LearnBoard.mountDashboard) { resolve(); return; }
      var s = document.createElement('script');
      s.src = url; s.async = true;
      s.onload = function(){
        if (window.LearnBoard && window.LearnBoard.mountDashboard) { resolve(); }
        else { reject(new Error('SDK loaded but the LearnBoard global is unavailable')); }
      };
      s.onerror = function(){ reject(new Error('Failed to load the LearnBoard SDK')); };
      document.head.appendChild(s);
    });
    return window.__learnboardLoadPromise;
  };
  window.__learnboardSaveTimers = window.__learnboardSaveTimers || {};
  window.__learnboardPending = window.__learnboardPending || {};
  function saveBody(layout){
    return 'blockid=' + encodeURIComponent(cfg.blockId)
         + '&sesskey=' + encodeURIComponent(cfg.sesskey)
         + '&layout=' + encodeURIComponent(JSON.stringify(layout));
  }
  function saveLayout(layout){
    window.__learnboardPending[cfg.blockId] = layout;
    var timers = window.__learnboardSaveTimers;
    if (timers[cfg.blockId]) { clearTimeout(timers[cfg.blockId]); }
    timers[cfg.blockId] = setTimeout(function(){
      var l = window.__learnboardPending[cfg.blockId];
      if (l == null) { return; }
      window.__learnboardPending[cfg.blockId] = null;
      fetch(cfg.saveUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: saveBody(l)
      }).then(function(r){ if (!r.ok && window.console) { console.warn('[learnboard] layout save failed: HTTP ' + r.status); } })
        .catch(function(){ /* transient; next edit retries */ });
    }, 600);
  }
  // In Moodle, leaving edit mode is a FULL PAGE RELOAD, which destroys the
  // debounced fetch above before it fires — so the arrangement was lost on
  // exit. Flush any pending layout synchronously on unload via sendBeacon (it
  // survives the navigation; a debounced fetch does not).
  function flushLayout(){
    var l = window.__learnboardPending[cfg.blockId];
    if (l == null) { return; }
    window.__learnboardPending[cfg.blockId] = null;
    var timers = window.__learnboardSaveTimers;
    if (timers[cfg.blockId]) { clearTimeout(timers[cfg.blockId]); timers[cfg.blockId] = null; }
    try {
      var blob = new Blob([saveBody(l)], { type: 'application/x-www-form-urlencoded' });
      if (navigator.sendBeacon && navigator.sendBeacon(cfg.saveUrl, blob)) { return; }
    } catch (e) { /* fall through to keepalive fetch */ }
    fetch(cfg.saveUrl, {
      method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: saveBody(l)
    }).catch(function(){});
  }
  window.addEventListener('pagehide', flushLayout);
  window.addEventListener('beforeunload', flushLayout);
  function fail(el, msg){ if (el) { el.innerHTML = '<div style="padding:1rem;color:#b91c1c;font:14px system-ui,sans-serif">'+msg+'</div>'; } }
  // 'moodle' theme follows the LMS: dark when the theme puts
  // .theme-dark on <body>, light otherwise. Resolved live so it tracks toggles.
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
  window.__learnboardLoad(cfg.bundleUrl).then(function(){
    return fetch(cfg.cssUrl).then(function(r){ return r.ok ? r.text() : ''; });
  }).then(function(css){
    var el = document.getElementById(cfg.elementId);
    if (!el) { return; }
    var layout = cfg.layout || { items: [], layouts: {} };
    if (cfg.courseId) {
      (layout.items || []).forEach(function(it){
        it.params = it.params || {};
        if (it.params.course_id === undefined) { it.params.course_id = cfg.courseId; }
      });
    }
    // Linked block → mirror a named LearnBoard dashboard (read-only); the SDK
    // fetches it and ignores layout/editable/onChange. Otherwise render the
    // custom layout.
    window.LearnBoard.mountDashboard(el, {
      layout: layout,
      dashboardId: cfg.dashboardId || undefined,
      editable: !!cfg.editable,
      apiBase: cfg.apiBase,
      token: cfg.token,
      tokenExpiresAt: cfg.tokenExpiresAt,
      tokenRefreshUrl: cfg.tokenRefreshUrl,
      tenantSlug: cfg.tenantSlug,
      theme: resolveTheme(),
      styles: { css: css, tokens: '' },
      onChange: cfg.editable ? saveLayout : undefined
    });
    // Live-sync with the LMS light/dark toggle.
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

        return html_writer::script($js);
    }
}
