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
 * Role-map settings form: map each Moodle role onto a LearnBoard role.
 *
 * @package    block_learnboard
 * @copyright  2026 NASMAK Technologies <info@nasmak.com.au>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_learnboard\form;

// The formslib.php file (which defines \moodleform) is required by the page controller
// before this autoloaded class is instantiated.

/**
 * Configurable role mapping (#1): one dropdown per Moodle role (+ a `siteadmin`
 * pseudo-role and a `__default__` fallback), each offering the LearnBoard roles
 * fetched live from LearnBoard. Write-capable LearnBoard roles are labelled with
 * a warning so an operator sees the escalation before choosing it.
 *
 * Customdata:
 *   - moodleroles: array<int, array{shortname: string, label: string}>
 *   - lbroles:     array<int, array{name: string, write_capable: bool}>
 *   - current:     array<string, string>  the saved map (shortname => lb role)
 */
class rolemap_form extends \moodleform {
    /**
     * One row per Moodle role, each choosing the LearnBoard role it maps to.
     */
    protected function definition() {
        $mform = $this->_form;

        $moodleroles = $this->_customdata['moodleroles'] ?? [];
        $lbroles = $this->_customdata['lbroles'] ?? [];
        $current = $this->_customdata['current'] ?? [];

        // Build the shared <select> option list once: "Not mapped" + each LB role.
        $options = ['' => get_string('rolemap_notmapped', 'block_learnboard')];
        foreach ($lbroles as $role) {
            $name = (string) ($role['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $label = $name;
            if (!empty($role['write_capable'])) {
                $label .= '  ' . get_string('rolemap_writewarn', 'block_learnboard');
            }
            $options[$name] = $label;
        }

        foreach ($moodleroles as $role) {
            $shortname = (string) $role['shortname'];
            $mform->addElement('select', 'map_' . $shortname, $role['label'], $options);
            $mform->setType('map_' . $shortname, PARAM_TEXT);
            if (isset($current[$shortname]) && isset($options[$current[$shortname]])) {
                $mform->setDefault('map_' . $shortname, $current[$shortname]);
            }
        }

        $this->add_action_buttons();
    }

    /**
     * Collapse the per-role select values back into the shortname => lb-role
     * map, dropping unmapped ("") entries.
     *
     * @param \stdClass $data submitted form data
     * @return array<string, string>
     */
    public static function to_map(\stdClass $data): array {
        $map = [];
        foreach ((array) $data as $key => $value) {
            if (strpos($key, 'map_') !== 0) {
                continue;
            }
            $value = (string) $value;
            if ($value === '') {
                continue;
            }
            $shortname = substr($key, strlen('map_'));
            $map[$shortname] = $value;
        }

        return $map;
    }
}
