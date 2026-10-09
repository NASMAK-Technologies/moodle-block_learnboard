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
 * Server-side client for the LearnBoard embed API.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard;

/**
 * Thin server-side client the block uses to talk to LearnBoard.
 *
 * The only privileged call is mint_token(): it authenticates with the
 * configured shared secret (X-Embed-Key) and receives a short-lived,
 * read-only, tenant-scoped bearer token. That token — never the secret — is
 * handed to the browser SDK. Mirrors LearnBoard's own DemoController /
 * X-Demo-Secret machine-auth pattern.
 */
class client {
    /**
     * Configured LearnBoard API base, no trailing slash.
     */
    public static function apibase(): string {
        return rtrim((string) get_config('block_learnboard', 'apibase'), '/');
    }

    /**
     * Where the chart script or its styles are loaded from.
     *
     * LearnBoard serves both, so each site draws with the charts its LearnBoard
     * currently runs and the plugin carries no compiled code of its own.
     *
     * @param string $kind 'js' for the script, 'css' for the styles
     * @return string
     */
    public static function sdk_url(string $kind): string {
        return self::apibase() . ($kind === 'css' ? '/embed/sdk.css' : '/embed/sdk.js');
    }

    /**
     * The LearnBoard tenant slug this site maps to.
     */
    public static function tenant(): string {
        return trim((string) get_config('block_learnboard', 'tenant'));
    }

    /**
     * Theme preference passed to the SDK ('system' | 'light' | 'dark').
     */
    public static function theme(): string {
        $theme = (string) get_config('block_learnboard', 'theme');

        return $theme !== '' ? $theme : 'system';
    }

    /**
     * The embed key and user identities go to this address, so it must be
     * https. Plain http is accepted only when a site admin forces
     * allowinsecurehttp in config.php, for a local test install.
     */
    public static function apibase_is_safe(): bool {
        global $CFG;

        $scheme = strtolower((string) parse_url(self::apibase(), PHP_URL_SCHEME));
        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && !empty($CFG->forced_plugin_settings['block_learnboard']['allowinsecurehttp']);
    }

    /**
     * A token for the person viewing the page, or null when they may not have one.
     *
     * With per-user sign-in on, the token is minted for that person and
     * LearnBoard limits it to the role their Moodle roles map to. Otherwise the
     * only token available is the workspace-wide reader, which sees every
     * learner, so it goes only to holders of block/learnboard:view in the
     * context the page is shown in.
     *
     * @param \context $context where the analytics are shown
     * @return array|null
     */
    public static function token_for_viewer(\context $context): ?array {
        if (!self::viewer_may_see($context)) {
            return null;
        }
        if (ssouser::enabled()) {
            $assertion = ssouser::assertion();

            return $assertion !== null ? self::mint_token(null, $assertion) : null;
        }

        return self::mint_token();
    }

    /**
     * Whether the viewer may be given any LearnBoard token in this context.
     *
     * @param \context $context
     * @return bool
     */
    public static function viewer_may_see(\context $context): bool {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        if (ssouser::enabled()) {
            return true;
        }

        // The shared reader sees every learner in the workspace, so it goes
        // only to people who may see LearnBoard across the whole site (1.1.13).
        // A teacher holds the capability in their own courses only; before
        // this, that was enough to be handed every course and every company.
        // Turn on per-user sign-in and they see their own groups instead.
        return has_capability('block/learnboard:view', \context_system::instance());
    }

    /**
     * True when the plugin has enough configuration to attempt a mount.
     */
    public static function is_configured(): bool {
        return self::apibase() !== ''
            && self::tenant() !== ''
            && (string) get_config('block_learnboard', 'embedkey') !== '';
    }

    /**
     * Mint a short-lived read-only embed token for the given tenant (or the
     * configured default). Returns the decoded response
     * (['token' => ..., 'expires_at' => ..., 'tenant' => [...]]) or null on
     * any failure — callers must render an educational message, never a token.
     *
     * @param string|null $tenant workspace slug, or null for the configured one
     * @param array|null $user the signed-in Moodle user's identity, for per-user sign-in
     * @return array|null
     */
    public static function mint_token(?string $tenant = null, ?array $user = null): ?array {
        $tenant = $tenant !== null && $tenant !== '' ? $tenant : self::tenant();
        $payload = ['tenant' => $tenant];
        // Per-user SSO (#1): forward the logged-in Moodle user's identity so the
        // token is minted for THAT individual (read-only, role-mapped) rather
        // than the shared tenant reader. Trusted because this call is
        // server-to-server, authenticated by the shared X-Embed-Key.
        if ($user !== null) {
            $payload['user'] = $user;
        }

        $data = self::post_json('/embed/token', $payload, $tenant);
        if ($data === null || empty($data['token'])) {
            return null;
        }

        return $data;
    }

    /**
     * Per-user SSO hand-off: ask LearnBoard for a single-use ticket and build the
     * full-app sign-in URL `<app>/sso?ticket=…` for the given Moodle user
     * assertion. Returns null on any failure.
     *
     * @param array $user  the assertion from \block_learnboard\ssouser::assertion()
     */
    public static function login_url(array $user): ?string {
        $data = self::post_json('/embed/login-link', ['tenant' => self::tenant(), 'user' => $user]);
        if ($data === null || empty($data['ticket'])) {
            return null;
        }

        // The full app lives at the API base minus its /api/vN segment.
        $appbase = preg_replace('#/api/v\d+/?$#', '', self::apibase());

        return $appbase . '/sso?ticket=' . rawurlencode($data['ticket']);
    }

    /**
     * Shared server-to-server POST to the LearnBoard embed API: sends the
     * X-Embed-Key, decodes a 2xx JSON body, returns null on any failure.
     *
     * @param string      $path    e.g. '/embed/token'
     * @param array       $payload JSON body
     * @param string|null $tenant  when set, short-circuits to null if unconfigured
     * @return array|null
     */
    /**
     * Introduce this site to LearnBoard after a connection code is saved.
     * LearnBoard reads from this site with the connector key to prove the
     * address, then creates or updates its data source.
     *
     * @return array{connected: bool, active: bool, reason: string} what LearnBoard reported,
     *     or reason set to a short code when the call itself failed
     */
    public static function register_site(): array {
        global $CFG;

        $key = (string) get_config('block_learnboard', 'embedkey');
        $apibase = self::apibase();
        if ($key === '' || $apibase === '' || self::tenant() === '' || !self::apibase_is_safe()) {
            return ['connected' => false, 'active' => false, 'reason' => 'not_configured'];
        }

        require_once($CFG->libdir . '/filelib.php');
        $version = (string) (get_config('block_learnboard', 'version') ?: '');
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader(['Content-Type: application/json', 'Accept: application/json', 'X-Embed-Key: ' . $key]);
        // Long enough for LearnBoard to call this site back and hear the answer.
        $curl->setopt(['CURLOPT_TIMEOUT' => 45, 'CURLOPT_CONNECTTIMEOUT' => 10]);
        if ((int) get_config('block_learnboard', 'insecuressl') === 1) {
            $curl->setopt(['CURLOPT_SSL_VERIFYPEER' => 0, 'CURLOPT_SSL_VERIFYHOST' => 0]);
        }

        $response = $curl->post($apibase . '/embed/register', json_encode([
            'tenant' => self::tenant(),
            'site_url' => rtrim((string) $CFG->wwwroot, '/'),
            'plugin_version' => $version,
        ]));
        $code = (int) ($curl->get_info()['http_code'] ?? 0);
        $data = json_decode((string) $response, true);

        if ($code >= 200 && $code < 300 && is_array($data['data'] ?? null)) {
            return [
                'connected' => !empty($data['data']['connected']),
                'active' => !empty($data['data']['active']),
                'reason' => (string) ($data['data']['reason'] ?? ''),
            ];
        }

        $reasons = [0 => 'unreachable', 401 => 'code_not_accepted', 409 => 'code_replaced', 422 => 'address_not_allowed', 429 => 'too_many_attempts'];

        return ['connected' => false, 'active' => false, 'reason' => $reasons[$code] ?? ('http_' . $code)];
    }

    /**
     * Posts JSON to the LearnBoard API and decodes the reply.
     *
     * @param string $path API path under the configured base
     * @param array $payload body to send
     * @param string|null $tenant workspace slug, when the call is for one
     * @return array|null the decoded reply, or null when there was none
     */
    private static function post_json(string $path, array $payload, ?string $tenant = null): ?array {
        global $CFG;

        $key = (string) get_config('block_learnboard', 'embedkey');
        $apibase = self::apibase();
        $tenant = $tenant ?? self::tenant();

        if ($tenant === '' || $key === '' || $apibase === '' || !self::apibase_is_safe()) {
            return null;
        }

        require_once($CFG->libdir . '/filelib.php');

        // Ignoresecurity: the LearnBoard URL is trusted infrastructure set by a
        // site admin (not user input), so bypass Moodle's SSRF host blocklist —
        // otherwise a LearnBoard reachable on localhost/a private network (common
        // in dev and on-prem deployments) is refused with "The URL is blocked".
        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Embed-Key: ' . $key,
        ]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 10]);

        // Allow a self-signed / private CA for local + on-prem installs, opt-in
        // via the plugin's "insecuressl" advanced setting (default off = verify).
        if ((int) get_config('block_learnboard', 'insecuressl') === 1) {
            $curl->setopt(['CURLOPT_SSL_VERIFYPEER' => 0, 'CURLOPT_SSL_VERIFYHOST' => 0]);
        }

        $response = $curl->post($apibase . $path, json_encode($payload));
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        if ($code < 200 || $code >= 300) {
            return null;
        }

        $data = json_decode($response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * List the tenant's shareable (tenant-visible) LearnBoard dashboards for the
     * block's "link a dashboard" picker. Mints a read-only token, calls
     * GET /embed/dashboards, and returns an id => name map (empty on any
     * failure — the edit form falls back to guidance text).
     *
     * @return array<string, string> dashboard id => name
     */
    public static function list_dashboards(): array {
        global $CFG;

        $token = self::mint_token();
        if ($token === null || empty($token['token'])) {
            return [];
        }

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Accept: application/json',
            'Authorization: Bearer ' . $token['token'],
            'X-Tenant-Slug: ' . self::tenant(),
        ]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 10]);

        if ((int) get_config('block_learnboard', 'insecuressl') === 1) {
            $curl->setopt(['CURLOPT_SSL_VERIFYPEER' => 0, 'CURLOPT_SSL_VERIFYHOST' => 0]);
        }

        $response = $curl->get(self::apibase() . '/embed/dashboards');
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        if ($code < 200 || $code >= 300) {
            return [];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return [];
        }

        $out = [];
        foreach ($data['data'] as $d) {
            if (isset($d['id'], $d['name'])) {
                $out[(string) $d['id']] = (string) $d['name'];
            }
        }
        self::$dashboarddetail = [];
        foreach ($data['data'] as $d) {
            if (isset($d['id'], $d['name'])) {
                self::$dashboarddetail[(string) $d['id']] = [
                    'id' => (int) $d['id'],
                    'name' => (string) $d['name'],
                    'is_primary' => !empty($d['is_primary']),
                    'widget_count' => isset($d['widget_count']) ? (int) $d['widget_count'] : null,
                ];
            }
        }

        return $out;
    }

    /** @var array<string, array{id: int, name: string, is_primary: bool, widget_count: ?int}> */
    private static $dashboarddetail = [];

    /**
     * The dashboards list_dashboards() last fetched, with whether each is the
     * workspace's main one and how many charts it holds. Read by the full
     * LearnBoard page for its tabs; costs no second request.
     *
     * @return array<string, array{id: int, name: string, is_primary: bool, widget_count: ?int}>
     */
    public static function dashboard_detail(): array {
        return self::$dashboarddetail;
    }

    /**
     * The LearnBoard app's address: the API address without its /api/vN.
     */
    public static function appbase(): string {
        return (string) preg_replace('#/api/v\d+/?$#', '', self::apibase());
    }

    /**
     * List the LearnBoard roles a Moodle role can be mapped onto (drives the
     * role-map settings dropdowns). Server-to-server GET with the X-Embed-Key —
     * NOT a bearer token — so it works before any user context exists.
     *
     * On success the list is cached in config `roleshint` so the settings page
     * still renders if LearnBoard is briefly unreachable. Returns
     * [['name' => string, 'write_capable' => bool], ...] or the last-known hint
     * (possibly empty) on failure.
     *
     * @return array<int, array{name: string, write_capable: bool}>
     */
    public static function list_roles(): array {
        global $CFG;

        $key = (string) get_config('block_learnboard', 'embedkey');
        $apibase = self::apibase();
        $tenant = self::tenant();

        if ($tenant === '' || $key === '' || $apibase === '' || !self::apibase_is_safe()) {
            return self::roles_hint();
        }

        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['ignoresecurity' => true]);
        $curl->setHeader([
            'Accept: application/json',
            'X-Embed-Key: ' . $key,
        ]);
        $curl->setopt(['CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 10]);

        if ((int) get_config('block_learnboard', 'insecuressl') === 1) {
            $curl->setopt(['CURLOPT_SSL_VERIFYPEER' => 0, 'CURLOPT_SSL_VERIFYHOST' => 0]);
        }

        $response = $curl->get($apibase . '/embed/roles?tenant=' . rawurlencode($tenant));
        $info = $curl->get_info();
        $code = (int) ($info['http_code'] ?? 0);

        if ($code < 200 || $code >= 300) {
            return self::roles_hint();
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
            return self::roles_hint();
        }

        $out = [];
        foreach ($data['data'] as $r) {
            if (isset($r['name'])) {
                $out[] = ['name' => (string) $r['name'], 'write_capable' => !empty($r['write_capable'])];
            }
        }

        // Cache the fresh list so the settings page survives a brief outage.
        set_config('roleshint', json_encode($out), 'block_learnboard');

        return $out;
    }

    /**
     * The last-known LearnBoard role list cached by {@see list_roles()}, or an
     * empty array if none has ever been fetched.
     *
     * @return array<int, array{name: string, write_capable: bool}>
     */
    private static function roles_hint(): array {
        $raw = get_config('block_learnboard', 'roleshint');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $hint = json_decode($raw, true);

        return is_array($hint) ? $hint : [];
    }
}
