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
 * External function: save the arrangement of charts in a LearnBoard block.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\external;

use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * Save the arrangement of charts in a LearnBoard block.
 *
 * Only someone who may add and configure the block can change its layout;
 * everyone else only views it. The other settings of the block are kept.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class save_layout extends external_api {
    /** Largest layout accepted, in bytes: a layout is small JSON. */
    private const MAX_LAYOUT = 262144;

    /**
     * The block and its new layout.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'blockid' => new external_value(PARAM_INT, 'Block instance id'),
            'layout' => new external_value(PARAM_RAW, 'The layout as JSON: {"items": [...], "layouts": {...}}'),
        ]);
    }

    /**
     * Store the layout on the block.
     *
     * @param int $blockid
     * @param string $layout
     * @return array
     */
    public static function execute(int $blockid, string $layout): array {
        ['blockid' => $blockid, 'layout' => $layout] = self::validate_parameters(
            self::execute_parameters(),
            ['blockid' => $blockid, 'layout' => $layout]
        );

        $context = \context_block::instance($blockid);
        self::validate_context($context);
        require_capability('block/learnboard:addinstance', $context);

        if (strlen($layout) > self::MAX_LAYOUT) {
            throw new \invalid_parameter_exception('Layout too large');
        }
        $decoded = json_decode($layout, true);
        if (!is_array($decoded) || !array_key_exists('items', $decoded) || !array_key_exists('layouts', $decoded)) {
            throw new \invalid_parameter_exception('Malformed layout');
        }

        $block = block_instance_by_id($blockid);
        if (!$block || $block->instance->blockname !== 'learnboard') {
            throw new \invalid_parameter_exception('Not a LearnBoard block');
        }

        $config = isset($block->config) && is_object($block->config) ? clone $block->config : new \stdClass();
        $config->layout = $layout;
        $block->instance_config_save($config);

        return ['status' => 'ok'];
    }

    /**
     * Saved.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_ALPHA, 'ok'),
        ]);
    }
}
