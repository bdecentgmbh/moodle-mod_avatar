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
 * Utility functions for mod_avatar.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar;

use stdClass;

/**
 * Utility class for mod_avatar.
 */
class util {

    /**
     * Update users profile picture with the assigned avatar.
     *
     * @param int $userid
     * @param int $avatarid
     * @param int $variant
     * @return bool
     */
    public static function update_user_profile_picture($userid, $avatarid, $variant = 1) {
        global $DB, $CFG, $USER;

        require_once($CFG->libdir . '/gdlib.php');
        require_once($CFG->dirroot.'/user/lib.php');

        $fs = get_file_storage();
        $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $usercontext = \context_user::instance($userid);
        $systemcontext = \context_system::instance();

        // Get the thumbnail of the avatar.
        $files = $fs->get_area_files($systemcontext->id, 'mod_avatar', "avatarthumbnail{$variant}", $avatarid, 'sortorder', false);

        // If thumbnail is not available, use the avatar image.
        if (empty($files)) {
            $files = $fs->get_area_files($systemcontext->id, 'mod_avatar', "avatarimage{$variant}", $avatarid, 'sortorder', false);
        }

        if ($files) {
            $file = reset($files);

            if (!$tempfile = $file->copy_content_to_temp()) {
                $fs->delete_area_files($context->id, 'user', 'newicon');
                throw new \moodle_exception('There was a problem copying the profile picture to temp.');
            }

            // Process the image and set it as the users profile picture.
            $newpicture = process_new_icon($usercontext, 'user', 'icon', 0, $tempfile);
            $DB->set_field('user', 'picture', $newpicture, ['id' => $userid]);

            // Trigger event for updated user profile picture.
            \core\event\user_updated::create_from_userid($userid)->trigger();

            // Remove stale sessions.
            \core\session\manager::gc();

            // Set the users picture.
            $updateuser = new stdClass();
            $updateuser->id = $userid;
            $updateuser->picture = $newpicture;

            // Update the current users picture.
            if ($USER->id == $userid) {
                $USER->picture = $newpicture;
            }

            user_update_user($updateuser);

            return true;
        }

        return false;
    }

    /**
     * Get users current avatars
     *
     * @param int $userid The user ID
     * @return array Array of users avatars indexed by avatar ID
     */
    public static function get_user_avatars($userid) {
        global $DB;

        $records = $DB->get_records('avatar_user', ['userid' => $userid]);
        $avatars = [];
        foreach ($records as $record) {
            $avatars[$record->avatarid] = new avatar($record->avatarid, null, $record);
        }
        return $avatars;
    }

    /**
     * Get the avatar course module instance.
     *
     * @param int $instanceid
     * @return void
     */
    public static function get_avatar_cminstance(int $instanceid) {
        global $DB;

        return $DB->get_record('avatar', ['id' => $instanceid]);
    }

    /**
     * Get mentees assigned students list.
     *
     * @return bool|mixed List of users assigned as child users.
     */
    public static function get_myusers() {
        global $DB, $USER;

        if ($usercontexts = $DB->get_records_sql("SELECT c.instanceid, c.instanceid
                                                FROM {role_assignments} ra, {context} c, {user} u
                                                WHERE ra.userid = ?
                                                        AND ra.contextid = c.id
                                                        AND c.instanceid = u.id
                                                        AND u.deleted = 0 AND u.suspended = 0
                                                        AND c.contextlevel = ".CONTEXT_USER, [$USER->id])) {

            $users = [];
            foreach ($usercontexts as $usercontext) {
                $users[] = $usercontext->instanceid;
            }
            return $users;
        }
        return false;
    }

    /**
     * Include myavatars link in the user menu.
     *
     * @param \core\navigation\views\core_user_menu $hook
     * @return void
     */
    public static function include_myavatars_usermenu($hook) {

        // Build a logout link.
        $avatar = new \stdClass();
        $avatar->itemtype = 'link';
        $avatar->url = new \moodle_url('/mod/avatar/myavatars.php');
        $avatar->title = get_string('myavatars', 'mod_avatar');
        $avatar->titleidentifier = 'myavatars,mod_avatar';

        $hook->add_navitem($avatar);
    }
}
