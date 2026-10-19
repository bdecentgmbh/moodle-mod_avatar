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

namespace mod_avatar;

use core\context;
use mod_avatar\event\avatar_assigned;
use mod_avatar\event\avatar_collected;
use mod_avatar\event\avatar_completed;
use mod_avatar\event\avatar_upgraded;
use mod_avatar\hook\activity_availability;
use mod_avatar\hook\avatar_availability;

/**
 * The avatar collections of the users: who owns which avatar in which variant.
 *
 * This is the API for collecting, upgrading and querying avatars. It does not check capabilities: callers
 * (web services, pages, addons) check that the current user may act for the given user.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collection {
    /**
     * The avatar of a user's collection.
     *
     * @param int $userid The user.
     * @param int $avatarid The avatar.
     * @return \stdClass|null The record of avatar_user, null if the user does not own the avatar.
     */
    public static function get_user_avatar(int $userid, int $avatarid): ?\stdClass {
        global $DB;
        return $DB->get_record('avatar_user', ['userid' => $userid, 'avatarid' => $avatarid]) ?: null;
    }

    /**
     * Whether a user owns an avatar, optionally in at least a given variant.
     *
     * @param int $userid The user.
     * @param int $avatarid The avatar.
     * @param int $minvariant The lowest variant that counts (1 = owns the avatar at all).
     * @return bool
     */
    public static function user_has_avatar(int $userid, int $avatarid, int $minvariant = 1): bool {
        global $DB;
        return $DB->record_exists_select(
            'avatar_user',
            'userid = :userid AND avatarid = :avatarid AND variant >= :variant',
            ['userid' => $userid, 'avatarid' => $avatarid, 'variant' => $minvariant]
        );
    }

    /**
     * The primary avatar of a user.
     *
     * @param int $userid The user.
     * @return \stdClass|null The record of avatar_user, null if the user has no avatar.
     */
    public static function get_primary_avatar(int $userid): ?\stdClass {
        global $DB;
        return $DB->get_record('avatar_user', ['userid' => $userid, 'isprimary' => 1], '*', IGNORE_MULTIPLE) ?: null;
    }

    /**
     * Whether the avatars of an activity can be collected by the user right now.
     *
     * Asks the plugins listening to {@see activity_availability}; their messages are shown in the activity.
     *
     * @param \cm_info $cm The avatar activity.
     * @param \stdClass $instance The activity instance (record of avatar).
     * @param int $userid The user.
     * @return activity_availability The dispatched hook (is_available(), get_messages()).
     */
    public static function get_activity_availability(\cm_info $cm, \stdClass $instance, int $userid): activity_availability {
        $hook = new activity_availability($cm, $instance, $userid);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook;
    }

    /**
     * Whether a user may collect or upgrade an avatar.
     *
     * Disabled and archived avatars are never available; plugins listening to {@see avatar_availability}
     * can restrict the others further.
     *
     * @param \stdClass $avatar The avatar (record of avatar_list).
     * @param int $userid The user.
     * @param \cm_info|null $cm The activity in which the avatar is collected, null outside of an activity.
     * @return bool
     */
    public static function is_avatar_available(\stdClass $avatar, int $userid, ?\cm_info $cm = null): bool {
        if (empty($avatar->status) || !empty($avatar->archived)) {
            return false;
        }
        $hook = new avatar_availability($avatar, $userid, $cm);
        \core\di::get(\core\hook\manager::class)->dispatch($hook);
        return $hook->is_available();
    }

    /**
     * Adds an avatar (its first variant) to the collection of a user and makes it the primary avatar.
     *
     * @param \stdClass $avatar The avatar (record of avatar_list).
     * @param int $userid The user who gets the avatar.
     * @param \cm_info|null $cm The activity in which it is collected, null for an assignment outside of activities.
     * @return \stdClass The new record of avatar_user.
     * @throws \moodle_exception When the user already owns the avatar.
     */
    public static function collect(\stdClass $avatar, int $userid, ?\cm_info $cm = null): \stdClass {
        global $DB, $USER;

        if (self::get_user_avatar($userid, $avatar->id)) {
            throw new \moodle_exception('alreadycollected', 'mod_avatar');
        }

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'avatarid' => $avatar->id,
            'variant' => 1,
            'cmid' => $cm ? $cm->id : 0,
            'isprimary' => 1,
            'timecollected' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        $record->id = $DB->insert_record('avatar_user', $record);
        $DB->set_field_select(
            'avatar_user',
            'isprimary',
            0,
            'userid = :userid AND id <> :id',
            ['userid' => $userid, 'id' => $record->id]
        );
        $transaction->allow_commit();

        $params = [
            'context' => $cm ? $cm->context : context\system::instance(),
            'objectid' => $avatar->id,
            'relateduserid' => $userid,
            'other' => ['avatarid' => $avatar->id],
        ];
        $event = ($userid == $USER->id) ? avatar_collected::create($params) : avatar_assigned::create($params);
        $event->trigger();

        self::after_change($avatar, $record, $cm);
        return $record;
    }

    /**
     * Upgrades the avatar of a user to its next variant and makes it the primary avatar.
     *
     * @param \stdClass $avatar The avatar (record of avatar_list).
     * @param int $userid The user.
     * @param \cm_info|null $cm The activity in which it is upgraded, null outside of activities.
     * @return \stdClass The updated record of avatar_user.
     * @throws \moodle_exception When the user does not own the avatar or owns its last variant.
     */
    public static function upgrade(\stdClass $avatar, int $userid, ?\cm_info $cm = null): \stdClass {
        global $DB;

        $record = self::get_user_avatar($userid, $avatar->id);
        if (!$record) {
            throw new \moodle_exception('avatarnotcollected', 'mod_avatar');
        }
        if ($record->variant >= $avatar->variants) {
            throw new \moodle_exception('alreadyfullyupgraded', 'mod_avatar');
        }

        $record->variant++;
        $record->isprimary = 1;
        $record->timemodified = time();
        if ($cm) {
            $record->cmid = $cm->id;
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->update_record('avatar_user', $record);
        $DB->set_field_select(
            'avatar_user',
            'isprimary',
            0,
            'userid = :userid AND id <> :id',
            ['userid' => $userid, 'id' => $record->id]
        );
        $transaction->allow_commit();

        $context = $cm ? $cm->context : context\system::instance();
        avatar_upgraded::create([
            'context' => $context,
            'objectid' => $avatar->id,
            'relateduserid' => $userid,
            'other' => ['avatarid' => $avatar->id, 'variant' => $record->variant],
        ])->trigger();

        if ($record->variant >= $avatar->variants) {
            avatar_completed::create([
                'context' => $context,
                'objectid' => $avatar->id,
                'relateduserid' => $userid,
                'other' => ['avatarid' => $avatar->id, 'newvariant' => $record->variant],
            ])->trigger();
        }

        self::after_change($avatar, $record, $cm);
        return $record;
    }

    /**
     * Makes an avatar of a user's collection the primary avatar (and the profile picture, if synced).
     *
     * @param int $userid The user.
     * @param int $avatarid The avatar, which the user must own.
     * @throws \moodle_exception When the user does not own the avatar.
     */
    public static function set_primary(int $userid, int $avatarid): void {
        global $DB;

        $record = self::get_user_avatar($userid, $avatarid);
        if (!$record) {
            throw new \moodle_exception('avatarnotcollected', 'mod_avatar');
        }
        $transaction = $DB->start_delegated_transaction();
        $DB->set_field('avatar_user', 'isprimary', 0, ['userid' => $userid]);
        $DB->set_field('avatar_user', 'isprimary', 1, ['id' => $record->id]);
        $transaction->allow_commit();

        if (get_config('mod_avatar', 'profileimagesync')) {
            util::update_user_profile_picture($userid, $avatarid, $record->variant);
        }
    }

    /**
     * Syncs the profile picture and updates the completion of the activity after a collection changed.
     *
     * @param \stdClass $avatar The avatar.
     * @param \stdClass $record The record of avatar_user.
     * @param \cm_info|null $cm The activity, if any.
     */
    private static function after_change(\stdClass $avatar, \stdClass $record, ?\cm_info $cm): void {
        global $CFG;

        if (get_config('mod_avatar', 'profileimagesync')) {
            util::update_user_profile_picture($record->userid, $avatar->id, $record->variant);
        }

        if ($cm && !empty($cm->customdata['customcompletionrules']['completioncollect'])) {
            require_once($CFG->libdir . '/completionlib.php');
            $completion = new \completion_info($cm->get_course());
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC) {
                $completion->update_state($cm, COMPLETION_COMPLETE, $record->userid);
            }
        }
    }
}
