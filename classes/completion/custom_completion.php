<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Activity custom completion subclass for the avatar activity.
 *
 * @package     mod_avatar
 * @copyright   2025 bdecent GmbH <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules for mod_avatar.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        switch ($rule) {

            case 'completioncollect':
                $collected = $DB->record_exists('avatar_user', [
                    'userid' => $this->userid,
                    'cmid' => $this->cm->instance,
                ]);
                return $collected ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;

            default:
                // If we've reached here, something has gone wrong.
                debugging("Invalid completion rule '{$rule}' specified.", DEBUG_DEVELOPER);
                return COMPLETION_INCOMPLETE;
        }
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {

        return [
            'completioncollect',
        ];
    }

    /**
     * List of descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completioncollect' => get_string('completioncollect_desc', 'mod_avatar'),
        ];
    }

    /**
     * List of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completioncollect',
        ];
    }
}
