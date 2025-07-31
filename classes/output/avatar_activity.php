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
 * Avatar activity renderable. Activity related avatars data.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\output;

use renderable;
use templatable;
use core\output\renderer_base;
use mod_avatar\avatar;
use moodle_url;

/**
 * Avatar activity renderable class.
 */
class avatar_activity implements renderable, templatable {

    /** @var object The avatar instance */
    public $cmavatar;

    /** @var object The course module */
    public $cm;

    /** @var object The course */
    public $course;

    /** @var \mod_avatar\util The helper instance */
    public $helper;

    /** @var \context_system The system context */
    protected $syscontext;

    /** @var \context_module The course module context */
    protected $cmcontext;

    /**
     * Constructor.
     *
     * @param object $avatar The avatar instance
     * @param object $cm The course module
     * @param object $course The course
     * @return void
     */
    public function __construct($avatar, $cm, $course) {
        $this->cmavatar = $avatar;
        $this->cm = $cm;
        $this->course = $course;
        $this->helper = new \mod_avatar\util();
        $this->syscontext = \context_system::instance();
        $this->cmcontext = $cm ? \context_module::instance($cm->id) : null;
    }

    /**
     * Generate the data to render the templates.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        // Get users current avatars and progress.
        $useravatars = $this->helper->get_user_avatars($USER->id);

        $headercontent = file_rewrite_pluginfile_urls(
            $this->cmavatar->headercontent,  'pluginfile.php',
            $this->cmcontext->id ?? $this->syscontext,  'mod_avatar',  'headercontent',  $this->cmavatar->id);

        $footercontent = file_rewrite_pluginfile_urls(
            $this->cmavatar->footercontent,  'pluginfile.php',
            $this->cmcontext->id ?? $this->syscontext,  'mod_avatar',  'footercontent',  $this->cmavatar->id);

        $emptystate = file_rewrite_pluginfile_urls(
            $this->cmavatar->emptystate,  'pluginfile.php',
            $this->cmcontext->id ?? $this->syscontext,  'mod_avatar',  'emptystate',  $this->cmavatar->id);

        $data = [
            'name' => format_string($this->cmavatar->name),
            'course' => [
                'shortname' => $this->course->shortname,
                'fullname' => $this->course->fullname,
            ],
            'headercontent' => format_text($headercontent, FORMAT_HTML, ['noclean' => true]),
            'footercontent' => format_text($footercontent, FORMAT_HTML, ['noclean' => true]),
            'emptystate' => format_text($emptystate, FORMAT_HTML, ['noclean' => true]),
            'displaymode' => $this->cmavatar->displaymode,
            'displaymodepage' => ($this->cmavatar->displaymode == 0),
            'displaymodeinline' => ($this->cmavatar->displaymode == 1),
            'avatars' => $this->get_available_avatars($useravatars),
            'isavailable' => self::cm_avatar_available($this->cm, $USER->id),
        ];

        $data['hasavatars'] = !empty($data['avatars']['avatars']);

        return $data;
    }

    /**
     * Get the SQL for available avatars.
     *
     * @return array SQL and params
     */
    public function get_available_avatars_sql() {
        global $DB;

        if ($this->cmavatar->avatarselection == 0) {
            $sql = 'SELECT al.*
                    FROM {avatar_list} al
                    WHERE al.archived = 0 AND al.status = :status';
            $params = ['status' => 1];
        } else {
            // Specific tags.
            $tags = explode(',', $this->cmavatar->specifictags);
            $tags = array_map('trim', $tags);
            list($insql, $params) = $DB->get_in_or_equal($tags);
            $sql = "SELECT al.*
                    FROM {avatar_list} al
                    WHERE al.archived = 0 AND al.status = 1 AND al.id IN (
                        SELECT ti.itemid
                        FROM {tag_instance} ti
                        JOIN {tag} t ON t.id = ti.tagid
                        WHERE t.name $insql AND ti.component = 'mod_avatar' GROUP BY ti.itemid
                    ) ORDER BY al.timecreated";

        }

        return [$sql, $params];
    }

    /**
     * Get available avatars for this activity.
     *
     * @param array $useravatars Current users avatars
     * @return array
     */
    protected function get_available_avatars($useravatars) {
        global $DB, $USER;

        $avatars = [];

        list($sql, $params) = $this->get_available_avatars_sql();
        $records = $DB->get_records_sql($sql, $params);

        foreach ($records as $record) {
            // User avatar.
            $avatar = $useravatars[$record->id] ?? new avatar($record->id, $record);

            // Check the user own the avatar.
            $picked = isset($useravatars[$record->id]) ? true : false;

            $progress = $avatar->info->get_progress($record);
            $avatars[] = [
                'id' => $record->id,
                'name' => $record->name,
                'previewimage' => $picked ? $avatar->info->get_thumbnail_image_url() : $avatar->info->get_preview_image(),
                'progress_indicators' => $progress['indicators'],
                'canpick' => $progress['canpick'],
                'canupgrade' => $progress['canupgrade'],
                'view_url' => $this->get_avatar_view_url($record->id),
                'cmid' => $this->cm->id,
                'isavailable' => $avatar->is_avatar_available($USER->id, $this->cm),
            ];

        }

        return ['avatars' => $avatars];
    }

    /**
     * Get the URL for viewing an avatar
     *
     * @param int $avatarid The ID of the avatar
     * @return string The URL for viewing the avatar
     */
    protected function get_avatar_view_url($avatarid) {
        $params = ['avatarid' => $avatarid];
        if ($this->cm) {
            $params['cmid'] = $this->cm->id;
        }
        return (new moodle_url('/mod/avatar/view_avatar.php', $params))->out(false);
    }

    /**
     * Confirm the avatar is available for the user.
     *
     * @param object $cm
     * @param int $userid
     *
     * @return bool REsult of the availability of the user for this avatar.
     */
    public static function cm_avatar_available($cm, $userid = null) {

        // Currently there is no conditions available.
        return true;
    }
}
