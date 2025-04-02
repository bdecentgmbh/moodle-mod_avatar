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
 * Avatar external API.
 *
 * @package    mod_avatar
 * @category   external
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/filelib.php");
require_once($CFG->dirroot . '/user/lib.php');

use mod_avatar\event\avatar_collected;
use mod_avatar\event\avatar_upgraded;
use mod_avatar\event\avatar_assigned;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

/**
 * Avatar external methods to defined, contains collect and upgrade avatar.
 */
class external extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function collect_avatar_parameters() {
        return new external_function_parameters(
            [
                'avatarid' => new external_value(PARAM_INT, 'The ID of the avatar to collect'),
                'cmid' => new external_value(PARAM_INT, 'The course module ID'),
                'userid' => new external_value(PARAM_INT, 'The user ID (optional, defaults to current user)', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Collect an avatar for the current user.
     *
     * @param int $avatarid
     * @param int $cmid
     * @param int|null $userid
     * @return array
     */
    public static function collect_avatar($avatarid, $cmid, $userid=null) {
        global $DB, $USER;

        $params = self::validate_parameters(self::collect_avatar_parameters(),
            ['avatarid' => $avatarid, 'cmid' => $cmid, 'userid' => $userid]);

        $cm = get_coursemodule_from_id('avatar', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $avatar = $DB->get_record('avatar_list', ['id' => $params['avatarid']], '*', MUST_EXIST);

        $userid = $params['userid'] ?? $USER->id;
        // Check if the avatar is available for the user.
        $avatarins = new \mod_avatar\avatar($avatar->id, $avatar);
        if (!$avatarins->is_avatar_available($userid, $cm)) {
            return ['success' => false, 'message' => get_string('avatarnotavailable', 'mod_avatar')];
        }

        // Check if the user already has this avatar.
        $existingrecord = $DB->get_record('avatar_user', ['userid' => $userid, 'avatarid' => $avatar->id]);
        if ($existingrecord) {
            return ['success' => false, 'message' => get_string('alreadycollected', 'mod_avatar')];
        }

        // Add the avatar to the users collection.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->avatarid = $avatar->id;
        $record->variant = 1; // Start with the first variant.
        $record->cmid = $cmid;
        $record->isprimary = 1;
        $record->timecollected = time();
        $record->timemodified = time();

        $newid = $DB->insert_record('avatar_user', $record);

        // Remove the primary flag from any existing primary avatars for the user.
        $DB->set_field_select('avatar_user', 'isprimary', 0,
            'id != :newid AND userid=:userid', ['newid' => $newid, 'userid' => $userid]);

        // Trigger avatar collected event.
        $params = [
            'context' => $context,
            'objectid' => $avatar->id,
            'relateduserid' => $userid,
            'other' => [
                'avatarid' => $avatar->id,
            ],
        ];

        $event = ($userid == $USER->id) ? avatar_collected::create($params) : avatar_assigned::create($params);
        $event->trigger();

        if (get_config('mod_avatar', 'profileimagesync')) {
            self::set_avatar_as_profile_picture($userid, $avatar->id);
        }

        // Avatar collected successfully. notify the usr.
        \core\notification::success(get_string('avatarcollected', 'mod_avatar'));

        return ['success' => true, 'message' => get_string('avatarcollected', 'mod_avatar')];
    }

    /**
     * Returns description of collect avatar result value.
     *
     * @return external_description
     */
    public static function collect_avatar_returns() {
        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'User collected the avatar or not'),
                'message' => new external_value(PARAM_TEXT, 'User notification message'),
            ]
        );
    }

    /**
     * Returns description of upgrade avatar parameters.
     *
     * @return external_function_parameters
     */
    public static function upgrade_avatar_parameters() {
        return new external_function_parameters(
            [
                'avatarid' => new external_value(PARAM_INT, 'The ID of the avatar to upgrade'),
                'cmid' => new external_value(PARAM_INT, 'The course module ID'),
                'userid' => new external_value(PARAM_INT, 'The user ID (optional, defaults to current user)', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * Upgrade an avatar for the current user.
     *
     * @param int $avatarid
     * @param int $cmid
     * @param int|null $userid
     * @return array
     */
    public static function upgrade_avatar($avatarid, $cmid, $userid=null) {
        global $DB, $USER;

        $params = self::validate_parameters(self::upgrade_avatar_parameters(),
            ['avatarid' => $avatarid, 'cmid' => $cmid, 'userid' => $userid]);

        $cm = get_coursemodule_from_id('avatar', $params['cmid'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $userid = $params['userid'] ?? $USER->id;

        $avatar = $DB->get_record('avatar_list', ['id' => $params['avatarid']], '*', MUST_EXIST);
        $useravatar = $DB->get_record('avatar_user', ['userid' => $userid, 'avatarid' => $avatar->id], '*', MUST_EXIST);

        if ($useravatar->variant >= $avatar->variants) {
            return ['success' => false, 'message' => get_string('alreadyfullyupgraded', 'mod_avatar')];
        }

        // Upgrade the avatar.
        $useravatar->variant++;
        $useravatar->timemodified = time();
        $useravatar->cmid = $cmid;
        $useravatar->isprimary = 1;

        $DB->update_record('avatar_user', $useravatar);

        // Remove the primary flag from any existing primary avatars for the user.
        $DB->set_field_select('avatar_user', 'isprimary', 0, 'id != :newid AND userid=:userid',
            ['newid' => $useravatar->id, 'userid' => $userid]);

        // User upgrade to new variant, Trigger avatar upgraded event.
        $params = [
            'context' => $context,
            'objectid' => $avatar->id,
            'relateduserid' => $userid,
            'other' => [
                'avatarid' => $avatar->id,
                'variant' => $useravatar->variant,
            ],
        ];
        $event = avatar_upgraded::create($params);
        $event->trigger();

        // User collected all the variants of this avatar, so trigger the avatar completed event.
        if ($useravatar->variant >= $avatar->variants) {
            $event = \mod_avatar\event\avatar_completed::create([
                'context' => $context,
                'objectid' => $avatarid,
                'relateduserid' => $userid,
                'other' => [
                    'avatarid' => $avatar->id,
                    'newvariant' => $useravatar->variant,
                ],
            ]);
            $event->trigger();
        }

        if (get_config('mod_avatar', 'profileimagesync')) {
            self::set_avatar_as_profile_picture($userid, $avatar->id, $useravatar->variant);
        }

        \core\notification::success(get_string('avatarupgraded', 'mod_avatar'));

        return ['success' => true, 'message' => get_string('avatarupgraded', 'mod_avatar')];
    }

    /**
     * Returns description of upgrade avatar result value.
     *
     * @return external_description
     */
    public static function upgrade_avatar_returns() {

        return new external_single_structure(
            [
                'success' => new external_value(PARAM_BOOL, 'Result of the upgrade avatar'),
                'message' => new external_value(PARAM_TEXT, 'User notification message'),
            ]
        );
    }

    /**
     * Set the avatar as the users profile picture.
     *
     * @param int $userid
     * @param int $avatarid
     * @param int $variant
     */
    private static function set_avatar_as_profile_picture($userid, $avatarid, $variant = 1) {
        return \mod_avatar\util::update_user_profile_picture($userid, $avatarid, $variant);
    }

    /**
     * Validate the context for the current user.
     *
     * @param \context $context
     * @return void
     */
    public static function validate_context($context) {
        // Check for either collect or upgrade capability.
        if (!has_capability('mod/avatar:collect', $context) && !has_capability('mod/avatar:upgrade', $context)) {
            throw new \moodle_exception('nopermissions', 'error', '', 'collect or upgrade avatar');
        }
    }
}
