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

namespace mod_avatar\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_avatar\activity;
use mod_avatar\collection;

/**
 * Web service: the current user collects (picks) an avatar in an avatar activity.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_avatar extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'avatarid' => new external_value(PARAM_INT, 'The avatar to collect'),
            'cmid' => new external_value(PARAM_INT, 'The course module id of the avatar activity'),
        ]);
    }

    /**
     * Collects the avatar for the current user.
     *
     * @param int $avatarid The avatar.
     * @param int $cmid The course module id of the avatar activity.
     * @return array ['success' => bool, 'message' => string]
     */
    public static function execute(int $avatarid, int $cmid): array {
        global $USER;

        ['avatarid' => $avatarid, 'cmid' => $cmid] = self::validate_parameters(
            self::execute_parameters(),
            ['avatarid' => $avatarid, 'cmid' => $cmid]
        );

        [$cm, $instance, $avatar] = self::get_activity_and_avatar($cmid, $avatarid, 'mod/avatar:collect');

        if (collection::get_user_avatar($USER->id, $avatar->id)) {
            return ['success' => false, 'message' => get_string('alreadycollected', 'mod_avatar')];
        }
        if (
            !collection::get_activity_availability($cm, $instance, $USER->id)->is_available()
                || !collection::is_avatar_available($avatar, $USER->id, $cm)
        ) {
            return ['success' => false, 'message' => get_string('avatarnotavailable', 'mod_avatar')];
        }

        collection::collect($avatar, $USER->id, $cm);
        \core\notification::success(get_string('avatarcollected', 'mod_avatar'));
        return ['success' => true, 'message' => get_string('avatarcollected', 'mod_avatar')];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the avatar was collected'),
            'message' => new external_value(PARAM_TEXT, 'Message for the user'),
        ]);
    }

    /**
     * Validates the activity (login, visibility, capability) and that it offers the avatar.
     *
     * @param int $cmid The course module id.
     * @param int $avatarid The avatar.
     * @param string $capability The capability the current user needs in the activity.
     * @return array [\cm_info $cm, \stdClass $instance, \stdClass $avatar]
     */
    public static function get_activity_and_avatar(int $cmid, int $avatarid, string $capability): array {
        global $DB;

        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'avatar');
        $context = \core\context\module::instance($cm->id);
        self::validate_context($context);
        require_capability($capability, $context);

        $instance = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
        if (!activity::offers_avatar($instance, $avatarid)) {
            throw new \moodle_exception('avatarnotavailable', 'mod_avatar');
        }
        $avatar = $DB->get_record('avatar_list', ['id' => $avatarid], '*', MUST_EXIST);

        return [$cm, $instance, $avatar];
    }
}
