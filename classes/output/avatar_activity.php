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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use mod_avatar\activity;
use mod_avatar\avatar;
use mod_avatar\collection;
use mod_avatar\util;

/**
 * The avatars of an avatar activity, as shown to the current user (activity page or course page).
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class avatar_activity implements renderable, templatable {
    /** @var \stdClass The activity instance (record of avatar). */
    public $cmavatar;

    /** @var \cm_info The course module. */
    public $cm;

    /** @var \stdClass The course. */
    public $course;

    /** @var \core\context\module The context of the activity. */
    protected $cmcontext;

    /**
     * Constructor.
     *
     * @param \stdClass $avatar The activity instance (record of avatar).
     * @param \stdClass|\cm_info $cm The course module.
     * @param \stdClass $course The course.
     */
    public function __construct($avatar, $cm, $course) {
        $this->cmavatar = $avatar;
        $this->cm = $cm instanceof \cm_info ? $cm : \cm_info::create($cm);
        $this->course = $course;
        $this->cmcontext = \core\context\module::instance($this->cm->id);
    }

    /**
     * Data for the template mod_avatar/avatar_activity.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $USER;

        $availability = collection::get_activity_availability($this->cm, $this->cmavatar, $USER->id);
        $avatars = $this->get_available_avatars(util::get_user_avatars($USER->id), $availability->is_available());
        $messages = array_map(fn($message) => ['message' => $message], $availability->get_messages());

        return [
            'name' => format_string($this->cmavatar->name, true, ['context' => $this->cmcontext, 'escape' => false]),
            'headercontent' => $this->format_text_area('headercontent'),
            'footercontent' => $this->format_text_area('footercontent'),
            'emptystate' => $this->format_text_area('emptystate'),
            'displaymodepage' => ($this->cmavatar->displaymode != \mod_avatar\activity::DISPLAY_INLINE),
            'displaymodeinline' => ($this->cmavatar->displaymode == \mod_avatar\activity::DISPLAY_INLINE),
            'avatars' => $avatars,
            'hasavatars' => !empty($avatars),
            'isavailable' => $availability->is_available(),
            'availabilitymessages' => $messages,
            'has_availability_restriction' => !empty($messages) || !$availability->is_available(),
        ];
    }

    /**
     * Formats a text of the activity (header, footer, empty state).
     *
     * @param string $area The field and file area.
     * @return string HTML
     */
    protected function format_text_area(string $area): string {
        $text = (string) ($this->cmavatar->$area ?? '');
        if (trim(strip_tags($text, '<img><video><audio><iframe><object>')) === '') {
            return '';
        }
        $text = file_rewrite_pluginfile_urls(
            $text,
            'pluginfile.php',
            $this->cmcontext->id,
            'mod_avatar',
            $area,
            $this->cmavatar->id
        );
        return format_text($text, $this->cmavatar->{$area . 'format'} ?? FORMAT_HTML, ['context' => $this->cmcontext]);
    }

    /**
     * SQL of the avatars the activity offers.
     *
     * @return array [$sql, $params]
     */
    public function get_available_avatars_sql() {
        return activity::get_avatars_sql($this->cmavatar);
    }

    /**
     * The avatars the activity offers, with the progress of the current user.
     *
     * @param array $useravatars The avatars of the current user (from util::get_user_avatars()).
     * @param bool $activityavailable Whether the user may collect avatars in the activity.
     * @return array
     */
    protected function get_available_avatars(array $useravatars, bool $activityavailable): array {
        global $USER;

        $canpick = has_capability('mod/avatar:collect', $this->cmcontext);
        $canupgrade = has_capability('mod/avatar:upgrade', $this->cmcontext);

        $records = activity::get_avatars($this->cmavatar);
        $variants = array_map(fn($avatar) => (int) $avatar->info->useravatar->variant, $useravatars);
        $images = avatar_info::get_gallery_images(array_keys($records), array_intersect_key($variants, $records));

        $avatars = [];
        foreach ($records as $record) {
            $avatar = $useravatars[$record->id] ?? new avatar($record->id, $record);
            $progress = $avatar->info->get_progress();

            $avatars[] = [
                'id' => $record->id,
                'name' => format_string($record->name, true, ['context' => \core\context\system::instance(), 'escape' => false]),
                'previewimage' => $images[$record->id],
                'progress_indicators' => $progress['indicators'],
                'canpick' => $canpick && $progress['canpick'],
                'canupgrade' => $canupgrade && $progress['canupgrade'],
                'view_url' => $this->get_avatar_view_url($record->id),
                'cmid' => $this->cm->id,
                'isavailable' => $activityavailable && collection::is_avatar_available($record, $USER->id, $this->cm),
            ];
        }

        return $avatars;
    }

    /**
     * The URL of the page of an avatar, within this activity.
     *
     * @param int $avatarid The ID of the avatar
     * @return string
     */
    protected function get_avatar_view_url($avatarid) {
        return (new \moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatarid, 'cmid' => $this->cm->id]))->out(false);
    }
}
