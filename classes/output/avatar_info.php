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
 * Avatar info renderable.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\output;

use mod_avatar\util;
use renderable;
use templatable;
use renderer_base;
use moodle_url;

/**
 * Avatar info renderable class.
 */
class avatar_info implements renderable, templatable {

    /**
     * @var \mod_avatar\avatar Avatar instance.
     */
    private $avatar;

    /**
     * System context.
     * @var \context
     */
    private $context;

    /**
     * @var \mod_avatar\useravatar User avatar instance.
     */
    public $useravatar;

    /**
     * @var int Avatar Course module ID.
     */
    public $cmid;

    /**
     * Constructor.
     *
     * @param \mod_avatar\avatar $avatar
     * @param stdclass $useravatar
     * @param int $cmid
     */
    public function __construct(\mod_avatar\avatar $avatar, $useravatar=null, int $cmid=0) {
        $this->avatar = $avatar;
        $this->context = \context_system::instance();
        $this->useravatar = $useravatar;
        $this->cmid = $cmid;
    }

    /**
     * Verify the user avatar is primary.
     *
     * @return bool
     */
    public function isprimary(): bool {
        return $this->useravatar->isprimary ?? false;
    }

    /**
     * Export the avatar info for template.
     *
     * @param renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;

        // Get formatted tags.
        $tagdata = $this->get_formatted_tags();
        $progress = $this->get_progress();

        if ($this->cmid) {
            list($course, $cm) = get_course_and_cm_from_cmid($this->cmid);
            $cmavatar = util::get_avatar_cminstance($cm->instance);
        }

        $description = file_rewrite_pluginfile_urls(
            $this->avatar->description, 'pluginfile.php', $this->context->id, 'mod_avatar', 'description', $this->avatar->id);

        // Use the high variant image if not available user preview image.
        $data = array_merge([
            'id' => $this->avatar->id,
            'name' => $this->avatar->name,
            'description' => format_text($description, FORMAT_HTML),
            'variantimage' => $this->useravatar ? $this->get_variant_image_url() : $this->get_preview_image(),
            'current_variant' => !empty($this->useravatar) ? $this->useravatar->variant : 0,
            'total_variants' => $this->avatar->variants,
            'additional_media' => $this->useravatar ? $this->get_animationstate_images() : $this->get_additional_media(),
            'variants' => $this->get_variant_images(),
            'progress' => $progress['indicators'],
            'canpick' => $progress['canpick'],
            'canupgrade' => $progress['canupgrade'],
            'fully_upgraded' => !empty($this->useravatar) && $this->useravatar->variant == $this->avatar->variants,
            'secretinfo' => format_text($this->avatar->secretinfo, FORMAT_HTML),
            'cmid' => $this->cmid,
            'isavailable' => $this->avatar->is_avatar_available($USER->id, $cm ?? null),

        ], $tagdata);

        $data['hasadditionalmedia'] = !empty($data['additional_media']);
        $data['viewsecretinfo'] = $data['secretinfo'] && ($data['canupgrade'] || $data['fully_upgraded']);

        if (!empty($cmavatar)) {
            $avataractivity = new avatar_activity($cmavatar, $cm, $course);
            list($sql, $params) = $avataractivity->get_available_avatars_sql();
            $avatars = $DB->get_records_sql($sql, $params);
            $data['previousavatarurl'] = $this->get_previous_avatar_url($avatars);
            $data['nextavatarurl'] = $this->get_next_avatar_url($avatars);
        }
        // Get the avatar image URL.
        return $data;
    }

    /**
     * Get the thumbnail image URL for the avatar.
     *
     * @return string The URL of the thumbnail image
     */
    public function get_thumbnail_image_url() {
        global $OUTPUT;

        $variant = !empty($this->useravatar) ? $this->useravatar->variant : 1;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id, 'mod_avatar', "avatarthumbnail{$variant}", $this->avatar->id, 'sortorder', false);
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $this->context->id,
                'mod_avatar',
                "avatarthumbnail{$variant}",
                $this->avatar->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return $this->get_variant_image_url();
    }

    /**
     * Get the variant image URL for the avatar. if user collected the avatar, it will return the highest collected variant image.
     *
     * @return string The URL of the thumbnail image
     */
    public function get_variant_image_url() {
        global $OUTPUT;

        $variant = !empty($this->useravatar) ? $this->useravatar->variant : 1;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id, 'mod_avatar', "avatarimage{$variant}", $this->avatar->id, 'sortorder', false);
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $this->context->id, 'mod_avatar', "avatarimage{$variant}",
                $this->avatar->id, $file->get_filepath(), $file->get_filename()
            )->out();
        }
        return $OUTPUT->image_url('default_avatar', 'mod_avatar')->out();
    }

    /**
     * REturn the animation state images for the avatar.
     *
     * @return array List of animation state images of the user collected variant or additional media images.
     */
    public function get_animationstate_images() {
        global $OUTPUT;

        $variant = !empty($this->useravatar) ? $this->useravatar->variant : 1;
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id, 'mod_avatar', "animationstates{$variant}", $this->avatar->id, 'sortorder', false);

        $media = [];
        $i = 0;
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $media[] = [
                    'url' => moodle_url::make_pluginfile_url(
                        $this->context->id, 'mod_avatar', "animationstates{$variant}",
                        $this->avatar->id, $file->get_filepath(), $file->get_filename()
                    )->out(),
                    'type' => 'image',
                    'alt' => $file->get_filename(),
                    'active' => empty($media) ? true : false,
                    'index' => $i,
                ];
                $i++;
            }
        }

        return $media ?: $this->get_additional_media();
    }

    /**
     * Get additional media for the avatar.
     *
     * @return array The additional media
     */
    private function get_additional_media() {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id, 'mod_avatar', 'additionalmedia', $this->avatar->id, 'sortorder', false);

        $media = [];
        $i = 0;
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $media[] = [
                    'url' => moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'mod_avatar',
                        'additionalmedia',
                        $this->avatar->id,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(),
                    'type' => 'image',
                    'alt' => $file->get_filename(),
                    'active' => empty($media) ? true : false,
                    'index' => $i,
                ];
                $i++;
            }
        }

        return $media;
    }

    /**
     * Get the variant images for the avatar.
     *
     * @return array The variant images
     */
    private function get_variant_images() {
        $variants = [];
        $fs = get_file_storage();
        for ($i = 1; $i <= $this->avatar->variants; $i++) {

            $files = $fs->get_area_files(
                $this->context->id, 'mod_avatar', "avatarimage{$i}", $this->avatar->id, 'sortorder', false);

            if ($files) {
                $file = reset($files);
                $variants[] = [
                    'number' => $i,
                    'url' => moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'mod_avatar',
                        "avatarimage{$i}",
                        $this->avatar->id,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(),
                ];
            }
        }
        return $variants;
    }

    /**
     * Get the preview image for an avatar.
     *
     * @return string The URL of the preview image
     */
    public function get_preview_image() {
        global $OUTPUT;

        $context = \context_system::instance();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_avatar', 'previewimage', $this->avatar->id, 'sortorder', false);

        if ($files) {
            $file = reset($files);
            return \moodle_url::make_pluginfile_url(
                $context->id,
                'mod_avatar',
                'previewimage',
                $this->avatar->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out();
        }

        return $OUTPUT->image_url('default_avatar', 'mod_avatar')->out();
    }

    /**
     * Get the avatar progress information
     *
     * @return array Progress information
     */
    public function get_progress() {
        $totalstages = $this->avatar->variants;
        $currentstage = $this->useravatar->variant ?? 0;

        $indicators = [];
        for ($i = 1; $i <= $totalstages; $i++) {
            $indicators[] = ['completed' => $i <= $currentstage];
        }

        return [
            'indicators' => $indicators,
            'canpick' => !$this->useravatar,
            'canupgrade' => $this->useravatar && $currentstage < $totalstages,
            'cansetaspic' => $this->useravatar,
        ];
    }

    /**
     * Get the formatted tags for the avatar.
     *
     * @return array Formatted tags
     */
    private function get_formatted_tags() {
        // Get tags from the avatar.
        $tags = \core_tag_tag::get_item_tags('mod_avatar', 'avatar', $this->avatar->id);
        $imagetags = [];
        $texttags = [];

        // Get tag images from config.
        $fs = get_file_storage();
        $systemcontext = \context_system::instance();
        $tagimages = $fs->get_area_files($systemcontext->id, 'mod_avatar', 'tagimages', 0);
        $tagimagemap = [];

        // Create map of tag names to image URLs.
        foreach ($tagimages as $file) {

            if ($file->is_valid_image()) {
                $filename = $file->get_filename();
                $tagname = pathinfo($filename, PATHINFO_FILENAME); // Get filename without extension.
                $tagimagemap[$tagname] = moodle_url::make_pluginfile_url(
                    $systemcontext->id,
                    'mod_avatar',
                    'tagimages',
                    0,
                    $file->get_filepath(),
                    $filename
                )->out();
            }
        }

        // Sort tags into image and text arrays.
        foreach ($tags as $tag) {
            $tagname = $tag->get_display_name();
            if (isset($tagimagemap[$tagname])) {
                $imagetags[] = [
                    'name' => $tagname,
                    'url' => $tag->get_view_url()->out(),
                    'image_url' => $tagimagemap[$tagname],
                ];
            } else {
                $texttags[] = [
                    'name' => $tagname,
                    'url' => $tag->get_view_url()->out(),
                ];
            }
        }

        return [
            'imagetags' => $imagetags,
            'texttags' => $texttags,
            'hasimagetags' => !empty($imagetags),
            'hastexttags' => !empty($texttags),
        ];
    }

    /**
     * Get the avatar usage information.
     *
     * @return array Avatar usage information
     */
    public function get_avatar_usage() {
        global $DB;

        // Gets the avatars list in the order for each avatar cm instance.
        // For each cm instance the current avatar is available.
        // Therefore we need to count all the possibilities of this current avatar available in each cm.
        $cmlist = $this->get_avatar_usage_details('activities');

        $cmavatars = [];
        $courses = [];
        foreach ($cmlist as $cmid => $avatars) {
            $avatarlist = $avatars[$this->avatar->id] ?? [];
            foreach ($avatarlist as $avatar) {
                $cmavatars[$cmid] = $avatar['cm'];
                $courses[$avatar['course']->id] = $avatar['course'];
            }
        }

        $users = $this->get_avatar_usage_details('users');

        return [
            'courses' => count($courses),
            'activities' => count($cmavatars),
            'users' => count($users[$this->avatar->id] ?? []),
        ];
    }

    /**
     * Get the avatar usage details.
     *
     * @param string $type The type of usage to get
     * @return array Avatar usage details
     */
    public function get_avatar_usage_details($type) {
        global $DB;

        switch ($type) {
            case 'courses':
            case 'activities':

                $sql = "SELECT cm.id as cmid, av.* FROM {course_modules} cm
                        JOIN {modules} md ON md.name=:modname and md.id = cm.module
                        JOIN {avatar} av ON av.id = cm.instance";

                $cmlist = $DB->get_recordset_sql($sql, ['modname' => 'avatar']);

                $avatars = [];
                foreach ($cmlist as $cmavatar) {
                    list($course, $cm) = get_course_and_cm_from_cmid($cmavatar->cmid, 'avatar');
                    $avataractivity = new avatar_activity($cmavatar, $cm, $course);

                    list($sql, $params) = $avataractivity->get_available_avatars_sql();
                    $records = $DB->get_records_sql($sql, $params);

                    foreach ($records as $record) {
                        $avatars[$cm->id][$record->id][] = ['avatar' => $record, 'cm' => $cmavatar, 'course' => $course];
                    }
                }

                $cmlist->close();
                break;

            case 'users':

                $sql = "
                SELECT u.*, au.avatarid
                FROM {avatar_user} au
                JOIN {user} u ON u.id = au.userid";

                $records = $DB->get_recordset_sql($sql);

                $avatars = [];
                foreach ($records as $record) {
                    $avatars[$record->avatarid][$record->id] = $record;
                }

                $records->close();
                break;

            default:
                return [];

        }
        return $avatars ?? [];
    }

    /**
     * Get the previous avatar URL.
     *
     * @param array $avatars The list of avatars
     * @return moodle_url|null The URL of the previous avatar or null if not found
     */
    public function get_previous_avatar_url(array $avatars) {

        $avatars = array_values($avatars);
        $ids = array_column($avatars, 'id');
        $position = array_search($this->avatar->id, $ids);

        $previousavatar = isset($avatars[$position - 1]) ? $avatars[$position - 1] : null;
        if ($previousavatar) {
            return new moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $previousavatar->id, 'cmid' => $this->cmid]);
        }

        return null;
    }

    /**
     * Get the next avatar URL.
     *
     * @param array $avatars The list of avatars
     * @return moodle_url|null The URL of the next avatar or null if not found
     */
    public function get_next_avatar_url(array $avatars) {

        $avatars = array_values($avatars);
        $ids = array_column($avatars, 'id');
        $position = array_search($this->avatar->id, $ids);

        $nextavatar = isset($avatars[$position + 1]) ? $avatars[$position + 1] : null;
        if ($nextavatar) {
            return new moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $nextavatar->id, 'cmid' => $this->cmid]);
        }

        return null;
    }

}
