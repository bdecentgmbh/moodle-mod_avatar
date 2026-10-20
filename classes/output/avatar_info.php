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

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use mod_avatar\activity;
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
    public function __construct(\mod_avatar\avatar $avatar, $useravatar = null, int $cmid = 0) {
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

        $tagdata = $this->get_formatted_tags();
        $progress = $this->get_progress();

        $cm = null;
        $cmavatar = null;
        if ($this->cmid) {
            [$course, $cm] = get_course_and_cm_from_cmid($this->cmid, 'avatar');
            $cmavatar = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
        }

        $options = ['context' => $this->context];
        $description = file_rewrite_pluginfile_urls(
            (string) $this->avatar->description,
            'pluginfile.php',
            $this->context->id,
            'mod_avatar',
            'description',
            $this->avatar->id
        );
        $fullyupgraded = !empty($this->useravatar) && $this->useravatar->variant >= $this->avatar->variants;

        // The secret information is revealed once the avatar is collected.
        $viewsecretinfo = !empty($this->useravatar) && trim(strip_tags((string) $this->avatar->secretinfo)) !== '';

        $data = array_merge([
            'id' => $this->avatar->id,
            'name' => format_string($this->avatar->name, true, $options + ['escape' => false]),
            'description' => format_text($description, $this->avatar->descriptionformat, $options),
            'variantimage' => $this->useravatar ? $this->get_variant_image_url() : $this->get_preview_image(),
            'current_variant' => !empty($this->useravatar) ? $this->useravatar->variant : 0,
            'total_variants' => $this->avatar->variants,
            'additional_media' => $this->useravatar ? $this->get_animationstate_images() : $this->get_additional_media(),
            'variants' => $this->get_variant_images(),
            'progress' => $progress['indicators'],
            'canpick' => $progress['canpick'] && $cm && has_capability('mod/avatar:collect', $cm->context),
            'canupgrade' => $progress['canupgrade'] && $cm && has_capability('mod/avatar:upgrade', $cm->context),
            'fully_upgraded' => $fullyupgraded,
            'viewsecretinfo' => $viewsecretinfo,
            'secretinfo' => $viewsecretinfo
                ? format_text($this->avatar->secretinfo, $this->avatar->secretinfoformat, $options) : '',
            'cmid' => $this->cmid,
            'isavailable' => $cm && \mod_avatar\collection::get_activity_availability($cm, $cmavatar, $USER->id)->is_available()
                && $this->avatar->is_avatar_available($USER->id, $cm),
        ], $tagdata);

        $data['hasadditionalmedia'] = !empty($data['additional_media']);

        if ($cmavatar) {
            $avatars = activity::get_avatars($cmavatar);
            $data['previousavatarurl'] = $this->get_previous_avatar_url($avatars);
            $data['nextavatarurl'] = $this->get_next_avatar_url($avatars);
        }
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
            $this->context->id,
            'mod_avatar',
            "avatarthumbnail{$variant}",
            $this->avatar->id,
            'sortorder',
            false
        );
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $this->context->id,
                'mod_avatar',
                "avatarthumbnail{$variant}",
                $this->avatar->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
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
            $this->context->id,
            'mod_avatar',
            "avatarimage{$variant}",
            $this->avatar->id,
            'sortorder',
            false
        );
        if ($files) {
            $file = reset($files);
            return moodle_url::make_pluginfile_url(
                $this->context->id,
                'mod_avatar',
                "avatarimage{$variant}",
                $this->avatar->id,
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
        }
        return $OUTPUT->image_url('default_avatar', 'mod_avatar')->out(false);
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
            $this->context->id,
            'mod_avatar',
            "animationstates{$variant}",
            $this->avatar->id,
            'sortorder',
            false
        );

        $media = [];
        $i = 0;
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $media[] = [
                    'url' => moodle_url::make_pluginfile_url(
                        $this->context->id,
                        'mod_avatar',
                        "animationstates{$variant}",
                        $this->avatar->id,
                        $file->get_filepath(),
                        $file->get_filename()
                    )->out(false),
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
            $this->context->id,
            'mod_avatar',
            'additionalmedia',
            $this->avatar->id,
            'sortorder',
            false
        );

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
                    )->out(false),
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
                $this->context->id,
                'mod_avatar',
                "avatarimage{$i}",
                $this->avatar->id,
                'sortorder',
                false
            );

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
                    )->out(false),
                ];
            }
        }
        return $variants;
    }

    /**
     * The gallery images of several avatars, loaded together.
     *
     * For each avatar the same image as {@see get_thumbnail_image_url()} if the user collected it (the
     * thumbnail of the collected variant, else its image), otherwise as {@see get_preview_image()}.
     *
     * @param int[] $avatarids The avatars.
     * @param int[] $variants The collected variant, by avatar id, of the avatars the user collected.
     * @return string[] Image URLs by avatar id.
     */
    public static function get_gallery_images(array $avatarids, array $variants): array {
        global $DB, $OUTPUT;

        $context = \core\context\system::instance();
        $areas = ['previewimage'];
        foreach (array_unique($variants) as $variant) {
            array_push($areas, "avatarthumbnail{$variant}", "avatarimage{$variant}");
        }
        [$areasql, $areaparams] = $DB->get_in_or_equal($areas, SQL_PARAMS_NAMED, 'area');

        // The image areas hold one file each. Chunks keep the number of query parameters low.
        $files = [];
        foreach (array_chunk($avatarids, 1000) as $chunk) {
            [$itemsql, $itemparams] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'item');
            $records = $DB->get_records_select(
                'files',
                "contextid = :contextid AND component = :component AND filearea $areasql AND itemid $itemsql
                 AND filename <> :dir",
                ['contextid' => $context->id, 'component' => 'mod_avatar', 'dir' => '.'] + $areaparams + $itemparams,
                'sortorder, id',
                'id, filearea, itemid, filepath, filename'
            );
            foreach ($records as $record) {
                $files[$record->itemid][$record->filearea] ??= $record;
            }
        }

        $default = $OUTPUT->image_url('default_avatar', 'mod_avatar')->out(false);
        $images = [];
        foreach ($avatarids as $avatarid) {
            $images[$avatarid] = $default;
            $candidates = isset($variants[$avatarid])
                ? ["avatarthumbnail{$variants[$avatarid]}", "avatarimage{$variants[$avatarid]}"]
                : ['previewimage'];
            foreach ($candidates as $area) {
                if ($file = $files[$avatarid][$area] ?? null) {
                    $images[$avatarid] = moodle_url::make_pluginfile_url(
                        $context->id,
                        'mod_avatar',
                        $area,
                        $avatarid,
                        $file->filepath,
                        $file->filename
                    )->out(false);
                    break;
                }
            }
        }
        return $images;
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
            )->out(false);
        }

        return $OUTPUT->image_url('default_avatar', 'mod_avatar')->out(false);
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
                )->out(false);
            }
        }

        // Sort tags into image and text arrays.
        foreach ($tags as $tag) {
            $tagname = $tag->get_display_name(false);
            if (isset($tagimagemap[$tagname])) {
                $imagetags[] = [
                    'name' => $tagname,
                    'url' => $tag->get_view_url()->out(false),
                    'image_url' => $tagimagemap[$tagname],
                ];
            } else {
                $texttags[] = [
                    'name' => $tagname,
                    'url' => $tag->get_view_url()->out(false),
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
     * Where the avatar is used (see {@see \mod_avatar\local\usage::get_counts()}).
     *
     * @return int[] ['activities' => int, 'courses' => int, 'users' => int]
     */
    public function get_avatar_usage() {
        return \mod_avatar\local\usage::get_counts($this->avatar->id);
    }

    /**
     * Get the previous avatar URL.
     *
     * @param array $avatars The list of avatars
     * @return string|null The URL of the previous avatar or null if not found
     */
    public function get_previous_avatar_url(array $avatars) {

        $avatars = array_values($avatars);
        $ids = array_column($avatars, 'id');
        $position = array_search($this->avatar->id, $ids);

        $previousavatar = isset($avatars[$position - 1]) ? $avatars[$position - 1] : null;
        if ($previousavatar) {
            return (new moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $previousavatar->id,
                'cmid' => $this->cmid]))->out(false);
        }

        return null;
    }

    /**
     * Get the next avatar URL.
     *
     * @param array $avatars The list of avatars
     * @return string|null The URL of the next avatar or null if not found
     */
    public function get_next_avatar_url(array $avatars) {

        $avatars = array_values($avatars);
        $ids = array_column($avatars, 'id');
        $position = array_search($this->avatar->id, $ids);

        $nextavatar = isset($avatars[$position + 1]) ? $avatars[$position + 1] : null;
        if ($nextavatar) {
            return (new moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $nextavatar->id,
                'cmid' => $this->cmid]))->out(false);
        }

        return null;
    }
}
