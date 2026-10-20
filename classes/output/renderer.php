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
 * Avatar renderer.
 *
 * @package   mod_avatar
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\output;

use core\output\plugin_renderer_base;

/**
 * Renderer of mod_avatar.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {
    /**
     * The avatars of an avatar activity.
     *
     * @param \stdClass $avatarinstance The activity instance.
     * @param \stdClass|\cm_info $cm The course module.
     * @param \stdClass $course The course.
     * @return string HTML
     */
    public function display_avatar_activity($avatarinstance, $cm, $course) {
        return $this->render(new avatar_activity($avatarinstance, $cm, $course));
    }

    /**
     * The avatar collection of a user (My avatars page).
     *
     * @param int $userid The user, the current user on the My avatars page.
     * @return string HTML
     */
    public function display_my_avatars(int $userid) {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT al.*, au.id AS useravatarid, au.variant AS uservariant, au.isprimary, au.cmid AS usercmid,
                    au.timecollected, au.timemodified AS usertimemodified
               FROM {avatar_list} al
               JOIN {avatar_user} au ON au.avatarid = al.id
              WHERE au.userid = :userid
           ORDER BY au.isprimary DESC, al.name ASC",
            ['userid' => $userid]
        );

        $context = \core\context\system::instance();
        $canprofilesync = (bool) get_config('mod_avatar', 'profileimagesync');
        $avatars = [];
        foreach ($records as $record) {
            $useravatar = (object) ['id' => $record->useravatarid, 'userid' => $userid, 'avatarid' => $record->id,
                'variant' => $record->uservariant, 'isprimary' => $record->isprimary, 'cmid' => $record->usercmid,
                'timecollected' => $record->timecollected, 'timemodified' => $record->usertimemodified];
            $avatar = new \mod_avatar\avatar($record->id, $record, $useravatar);
            $progress = $avatar->info->get_progress();

            // Upgrades happen in the activity in which the avatar was collected.
            $cmid = 0;
            if ($progress['canupgrade'] && $record->usercmid) {
                $cm = get_coursemodule_from_id('avatar', $record->usercmid, 0, false, IGNORE_MISSING);
                if ($cm) {
                    $cminfo = get_fast_modinfo($cm->course, $userid)->get_cm($cm->id);
                    if ($cminfo->uservisible && has_capability('mod/avatar:upgrade', $cminfo->context, $userid)) {
                        $cmid = $cminfo->id;
                    }
                }
            }

            $avatars[] = [
                'id' => $record->id,
                'name' => format_string($record->name, true, ['context' => $context, 'escape' => false]),
                'previewimage' => $avatar->info->get_thumbnail_image_url(),
                'variant' => $record->uservariant,
                'max_variant' => $record->variants,
                'progress_indicators' => $progress['indicators'],
                'cmid' => $cmid,
                'canupgrade' => (bool) $cmid,
                'isprimary' => (bool) $record->isprimary,
                'canprofilesync' => $canprofilesync,
                'view_url' => (new \moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $record->id]))->out(false),
                'setprimaryurl' => (new \moodle_url('/mod/avatar/myavatars.php'))->out(false),
                'sesskey' => sesskey(),
            ];
        }

        return $this->render_from_template('mod_avatar/myavatars', [
            'has_avatars' => !empty($avatars),
            'avatars' => $avatars,
        ]);
    }
}
