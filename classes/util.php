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
     * Makes a variant of an avatar the profile picture of a user.
     *
     * @param int $userid The user.
     * @param int $avatarid The avatar.
     * @param int $variant The variant; its thumbnail is used, or its image when it has no thumbnail.
     * @return bool Whether the picture was changed.
     */
    public static function update_user_profile_picture($userid, $avatarid, $variant = 1) {
        global $CFG, $DB, $USER;

        require_once($CFG->libdir . '/gdlib.php');

        $fs = get_file_storage();
        $systemcontext = \core\context\system::instance();
        $files = $fs->get_area_files($systemcontext->id, 'mod_avatar', "avatarthumbnail{$variant}", $avatarid, 'sortorder', false);
        if (!$files) {
            $files = $fs->get_area_files($systemcontext->id, 'mod_avatar', "avatarimage{$variant}", $avatarid, 'sortorder', false);
        }
        $file = reset($files);
        if (!$file || !$file->is_valid_image()) {
            return false;
        }

        $tempfile = $file->copy_content_to_temp();
        if (!$tempfile) {
            return false;
        }
        $usercontext = \core\context\user::instance($userid);
        $newpicture = (int) process_new_icon($usercontext, 'user', 'icon', 0, $tempfile);
        @unlink($tempfile);
        if (!$newpicture) {
            return false;
        }

        $DB->set_field('user', 'picture', $newpicture, ['id' => $userid]);
        if ($USER->id == $userid) {
            $USER->picture = $newpicture;
        }
        \core\event\user_updated::create_from_userid($userid)->trigger();

        return true;
    }

    /**
     * The avatars of a user's collection.
     *
     * @param int $userid The user ID
     * @return \mod_avatar\avatar[] By avatar ID.
     */
    public static function get_user_avatars($userid) {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT au.*
               FROM {avatar_user} au
               JOIN {avatar_list} al ON al.id = au.avatarid
              WHERE au.userid = :userid",
            ['userid' => $userid]
        );
        $avatars = [];
        foreach ($records as $record) {
            $avatars[$record->avatarid] = new avatar($record->avatarid, null, $record);
        }
        return $avatars;
    }

    /**
     * The instance of an avatar activity.
     *
     * @param int $instanceid
     * @return \stdClass|false
     */
    public static function get_avatar_cminstance(int $instanceid) {
        global $DB;
        return $DB->get_record('avatar', ['id' => $instanceid]);
    }

    /**
     * Adds "My avatars" to the user menu.
     *
     * @param \core_user\hook\extend_user_menu $hook
     */
    public static function include_myavatars_usermenu($hook) {
        if (!isloggedin() || isguestuser() || !has_capability('mod/avatar:view', \core\context\system::instance())) {
            return;
        }
        $url = new \moodle_url('/mod/avatar/myavatars.php');
        $title = get_string('myavatars', 'mod_avatar');
        if (method_exists($hook, 'add_menu_item')) {
            // Moodle 5.3 and later.
            $hook->add_menu_item(new \core_user\output\user_action_menu\link($url, $title));
        } else {
            $hook->add_navitem((object) [
                'itemtype' => 'link',
                'url' => $url,
                'title' => $title,
                'titleidentifier' => 'myavatars,mod_avatar',
            ]);
        }
    }

    /**
     * Sets up a page of the avatar library (Manage avatars, edit an avatar, addon pages).
     *
     * Site administrators get the page in the admin tree. Other users (e.g. managers with mod/avatar:overview)
     * get the same page outside of it, because the admin tree only contains plugin settings for site admins.
     * Callers check the capabilities of their page.
     *
     * @param \moodle_url $url The URL of the page.
     * @param string $title The title and heading.
     * @return \core\context\system
     */
    public static function setup_library_page(\moodle_url $url, string $title): \core\context\system {
        global $CFG, $PAGE;
        require_once($CFG->libdir . '/adminlib.php');

        $context = \core\context\system::instance();
        require_login(null, false);

        if (has_capability('moodle/site:config', $context)) {
            admin_externalpage_setup('manageavatars', '', null, $url);
        } else {
            $PAGE->set_context($context);
            $PAGE->set_url($url);
            $PAGE->set_pagelayout('admin');
        }
        $PAGE->set_title($title);
        $PAGE->set_heading($title);

        return $context;
    }
}
