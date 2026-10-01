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
 * AJAX endpoint: persist an edited LearnBoard dashboard layout into a block's.
 * instance config. Called (debounced) by the block's in-page editor when an
 * author adds/removes/drag-resizes cards.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

require_login();
require_sesskey();

$blockid = required_param('blockid', PARAM_INT);
$layout = required_param('layout', PARAM_RAW);

header('Content-Type: application/json; charset=utf-8');

// Cap payload size defensively (a layout is small JSON).
if (strlen($layout) > 262144) {
    http_response_code(413);
    echo json_encode(['status' => 'error', 'message' => 'Layout too large']);
    die();
}

$decoded = json_decode($layout, true);
if (!is_array($decoded) || !array_key_exists('items', $decoded) || !array_key_exists('layouts', $decoded)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Malformed layout']);
    die();
}

$block = block_instance_by_id($blockid);
if (!$block || $block->instance->blockname !== 'learnboard') {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Not a LearnBoard block']);
    die();
}

// Authoring is capability-gated: only users who may add/configure the block
// may change its layout. End users (view only) can never reach this.
$context = context_block::instance($blockid);
require_capability('block/learnboard:addinstance', $context);

// Preserve the other config fields (title, height); overwrite only the layout.
$config = isset($block->config) && is_object($block->config) ? clone $block->config : new stdClass();
$config->layout = $layout;
$block->instance_config_save($config);

echo json_encode(['status' => 'ok']);
