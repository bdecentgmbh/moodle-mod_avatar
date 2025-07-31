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

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use html_writer;

require_once($CFG->dirroot . '/mod/avatar/lib.php');

if (class_exists('\core\output\renderer_base')) {
    class_alias('\core\output\renderer_base', '\plugin_render_base', true);
}

use plugin_renderer_base as render;

/**
 * Renderer for mod_avatar.
 */
class renderer extends render {

    /**
     * Render the avatar management page.
     *
     * @param \mod_avatar\output\manage_page $page
     * @return string HTML
     */
    public function render_manage_page(\mod_avatar\output\manage_page $page) {
        $data = $page->export_for_template($this);
        return $this->render_from_template('mod_avatar/manage_page', $data);
    }

    /**
     * Render a table of avatars.
     *
     * @param \table_sql $table
     * @return string HTML
     */
    public function render_avatars_table(\table_sql $table) {
        ob_start();
        $table->out(10, true);
        return ob_get_clean();
    }

    /**
     * Display the avatar activity.
     *
     * @param object $avatarinstance The avatar cm instance.
     * @param object $cm The course module
     * @param object $course The course
     * @return string HTML
     */
    public function display_avatar_activity($avatarinstance, $cm, $course) {
        $activity = new \mod_avatar\output\avatar_activity($avatarinstance, $cm, $course);
        return $this->render($activity);
    }

    /**
     * Display the My Avatars page.
     *
     * @param int|null $userid User ID (optional, defaults to current user)
     * @return string HTML
     */
    public function display_my_avatars(?int $userid) {
        global $DB, $USER;

        $userid = $userid ?: $USER->id;
        // Fetch users avatars.
        $avatars = $DB->get_records_sql(
            "SELECT a.*, au.variant, au.isprimary
            FROM {avatar_list} a
            JOIN {avatar_user} au ON a.id = au.avatarid
            WHERE au.userid = :userid
            ORDER BY au.isprimary DESC, a.name ASC",
            ['userid' => $userid]
        );

        $data = [
            'has_avatars' => !empty($avatars),
            'avatars' => [],
        ];

        $useravatars = [];
        $records = $DB->get_records('avatar_user', ['userid' => $userid]);
        foreach ($records as $record) {
            $useravatars[$record->avatarid] = $record;
        }

        foreach ($avatars as $avatar) {
            $avatarobj = new \mod_avatar\avatar($avatar->id, $avatar, $useravatars[$avatar->id] ?? []);

            $progress = $avatarobj->info->get_progress();
            $cmid = isset($useravatars[$avatar->id]) ? $useravatars[$avatar->id]->cmid : 0;

            // Export avatar info for template.
            $infodata = $avatarobj->info->export_for_template($this);

            $data['avatars'][] = [
                'id' => $avatar->id,
                'name' => format_text($avatar->name, FORMAT_HTML),
                'previewimage' => $avatarobj->info->get_thumbnail_image_url(),
                'variant' => $avatar->variant,
                'max_variant' => $avatar->variants,
                'description' => $infodata['description'],
                'secretinfo' => $infodata['secretinfo'],
                'progress_indicators' => $progress['indicators'],
                'canpick' => $progress['canpick'],
                'canupgrade' => $progress['canupgrade'],
                'isprimary' => $avatar->isprimary,
                'cansetasprofilepic' => $progress['cansetaspic'],
                'view_url' => new \moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatar->id]),
                'setprofileurl' => new \moodle_url('/mod/avatar/myavatars.php', ['setasprimary' => 1, 'avatarid' => $avatar->id]),
            ];
        }

        return $this->render_from_template('mod_avatar/myavatars', $data);
    }
}
