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
 * Avatar management class.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar;

use context_system;

/**
 * Avatar management class.
 */
class avatar_manager {
    /**
     * Get all active avatars.
     *
     * @return array
     */
    public static function get_active_avatars() {
        global $DB;
        return $DB->get_records('avatar_list', ['archived' => 0]);
    }

    /**
     * Get all archived avatars.
     *
     * @return array
     */
    public static function get_archived_avatars() {
        global $DB;
        return $DB->get_records('avatar_list', ['archived' => 1]);
    }

    /**
     * Create a new avatar.
     *
     * @param \stdClass $data
     * @return int The ID of the new avatar
     */
    public static function create_avatar($data) {
        global $DB, $USER;
        $time = time();
        $data->timecreated = $time;
        $data->timemodified = $time;
        $data->usermodified = $USER->id;
        $data->archived = 0;

        $avatarid = $DB->insert_record('avatar_list', $data);

        $data->id = $avatarid;
        $context = \core\context\system::instance();

        // Set tags for the new avatar.
        if (!empty($data->tags)) {
            \core_tag_tag::set_item_tags('mod_avatar', 'avatar', $avatarid, $context, $data->tags);
        }

        \core\di::get(\core\hook\manager::class)->dispatch(new \mod_avatar\hook\after_avatar_saved($data, true));

        \mod_avatar\event\avatar_created::create([
            'context' => $context,
            'objectid' => $avatarid,
            'other' => ['name' => $data->name],
        ])->trigger();

        return $avatarid;
    }

    /**
     * Get avatar by ID.
     *
     * @param int $avatarid
     * @return \mod_avatar\avatar
     */
    public static function get_avatar($avatarid) {
        return new \mod_avatar\avatar($avatarid);
    }

    /**
     * Verify the avatar is exists.
     *
     * @param int $avatarid
     * @return bool
     */
    public static function is_avatar_exists($avatarid) {
        global $DB;
        return $DB->record_exists('avatar_list', ['id' => $avatarid]);
    }
}
