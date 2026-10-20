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
 * Avatar main class defined.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar;

use mod_avatar\output\avatar_activity;
use stdClass;

/**
 * Avatar definitions.
 */
class avatar {
    /**
     * @var int The ID of the avatar
     */
    private $id;

    /**
     * @var \stdClass The data of the avatar
     */
    private $data;

    /**
     * @var \mod_avatar\output\avatar_info The info of the avatar
     */
    public $info;

    /**
     * @var bool Display all the available avatars in the activity.
     */
    public const SELECTION_ALL = 0;

    /**
     * @var bool Display the specific tags related avatars in the activity.
     */
    public const SELECTION_SPECIFIC = 1;

    /**
     * @var int The highest number of variants of an avatar.
     */
    public const MAX_VARIANTS = 10;

    /**
     * Constructor
     *
     * @param int $id The ID of the avatar
     * @param stdClass|null $data The data of the avatar
     * @param stdClass|null $useravatar The user avatar data
     * @return void
     */
    public function __construct(int $id, stdClass|null $data = null, $useravatar = null) {
        global $DB;

        $this->id = $id;

        if ($data) {
            $this->data = (object) $data;
        } else {
            $this->data = $DB->get_record('avatar_list', ['id' => $id], '*', MUST_EXIST);
        }

        if ($useravatar) {
            $this->get_info($useravatar);
        } else {
            $this->get_info();
        }
    }

    /**
     * Get avatar info.
     *
     * @param int $avatarid The ID of the avatar
     * @return \mod_avatar\output\avatar_info
     */
    public static function get_avatar_by_id(int $avatarid) {
        global $DB;

        $avatar = $DB->get_record('avatar_list', ['id' => $avatarid], '*', MUST_EXIST);
        return new avatar($avatar->id, $avatar);
    }

    /**
     * Get avatar data.
     *
     * @param string $key The key of the data to get
     * @return mixed
     */
    public function __get($key) {

        if (property_exists($this->data, $key)) {
            return $this->data->$key;
        }

        throw new \coding_exception("Property $key does not exist");
    }

    /**
     * Get avatar data.
     *
     * @return \stdClass
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Get avatar info.
     *
     * @param int|null $useravatar The user avatar ID
     * @param int $cmid The course module ID
     * @return \mod_avatar\output\avatar_info
     */
    public function get_info($useravatar = null, $cmid = 0) {

        if (!empty($this->info) && $this->info->useravatar == $useravatar && $this->info->cmid == $cmid) {
            return $this->info;
        }

        $this->info = new \mod_avatar\output\avatar_info($this, $useravatar, $cmid);

        return $this->info;
    }

    /**
     * Update avatar data to the DB.
     *
     * @param \stdClass $data
     * @return bool
     */
    public function update($data) {
        global $DB, $USER, $CFG;

        $data->id = $this->id;
        $data->timemodified = time();
        $data->usermodified = $USER->id;

        // Handle tags.
        if (isset($data->tags)) {
            \core_tag_tag::set_item_tags('mod_avatar', 'avatar', $this->id, \context_system::instance(), $data->tags);
        }

        $result = $DB->update_record('avatar_list', $data);

        \core\di::get(\core\hook\manager::class)->dispatch(new \mod_avatar\hook\after_avatar_saved($data, false));

        if ($result) {
            $this->data = $DB->get_record('avatar_list', ['id' => $this->id]);
        }

        \mod_avatar\event\avatar_changed::create([
            'context' => \core\context\system::instance(),
            'objectid' => $data->id,
            'other' => ['name' => $data->name ?? $this->data->name],
        ])->trigger();

        return $result;
    }

    /**
     * Archive the avatar.
     *
     * @return bool
     */
    public function archive() {
        global $DB;
        $this->data->archived = 1;
        $this->data->timearchived = time();
        $this->data->timemodified = time();
        return $DB->update_record('avatar_list', $this->data);
    }

    /**
     * Restore the avatar.
     *
     * @return bool
     */
    public function restore() {
        global $DB;
        $this->data->archived = 0;
        $this->data->timearchived = null;
        $this->data->timemodified = time();
        return $DB->update_record('avatar_list', $this->data);
    }

    /**
     * Deletes the avatar, its files and tags, and removes it from the collections of the users.
     *
     * @return bool
     */
    public function delete() {
        global $DB;

        \core\di::get(\core\hook\manager::class)->dispatch(new \mod_avatar\hook\before_avatar_deleted($this->data));

        $context = \core\context\system::instance();
        $transaction = $DB->start_delegated_transaction();
        \core_tag_tag::remove_all_item_tags('mod_avatar', 'avatar', $this->id);
        $DB->delete_records('avatar_user', ['avatarid' => $this->id]);
        $DB->delete_records('avatar_list', ['id' => $this->id]);

        $fs = get_file_storage();
        foreach (['previewimage', 'additionalmedia', 'description', 'secretinfo'] as $area) {
            $fs->delete_area_files($context->id, 'mod_avatar', $area, $this->id);
        }
        for ($i = 1; $i <= self::MAX_VARIANTS; $i++) {
            $fs->delete_area_files($context->id, 'mod_avatar', "avatarimage{$i}", $this->id);
            $fs->delete_area_files($context->id, 'mod_avatar', "avatarthumbnail{$i}", $this->id);
            $fs->delete_area_files($context->id, 'mod_avatar', "animationstates{$i}", $this->id);
        }
        $transaction->allow_commit();

        \mod_avatar\event\avatar_deleted::create([
            'context' => $context,
            'objectid' => $this->id,
            'other' => ['name' => $this->data->name, 'idnumber' => $this->data->idnumber],
        ])->trigger();

        return true;
    }

    /**
     * Toggle the status of the avatar.
     *
     * @return bool
     */
    public function toggle_status() {
        global $DB;
        $this->data->status = $this->data->status ? 0 : 1;
        return $DB->update_record('avatar_list', $this->data);
    }

    /**
     * Get the status of the avatar.
     *
     * @return int
     */
    public function get_status() {
        return $this->data->status;
    }

    /**
     * Options of the editors of the avatar form (description and secret information).
     *
     * @param int $maxfiles Maximum number of embedded files (0 for none).
     * @return array
     */
    public static function get_editor_options(int $maxfiles = EDITOR_UNLIMITED_FILES): array {
        global $CFG;
        require_once($CFG->libdir . '/formslib.php');

        return [
            'maxfiles' => $maxfiles,
            'maxbytes' => $CFG->maxbytes,
            'subdirs' => false,
            'trusttext' => false,
            'noclean' => false,
            'context' => \core\context\system::instance(),
        ];
    }

    /**
     * Options of a file manager of the avatar form.
     *
     * @param string $area The file area (previewimage, additionalmedia, avatarimageN, avatarthumbnailN, animationstatesN).
     * @return array
     */
    public static function get_filemanager_options(string $area): array {
        global $CFG;

        $options = ['maxbytes' => $CFG->maxbytes, 'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];
        if ($area === 'additionalmedia') {
            $options['maxfiles'] = -1;
            $options['accepted_types'] = ['image', 'video'];
        } else if (str_starts_with($area, 'animationstates')) {
            $options['maxfiles'] = -1;
        }
        return $options;
    }

    /**
     * Saves the editors and the files of the avatar form.
     *
     * @param \stdClass $data The data of the avatar form.
     */
    public function save_form_files(\stdClass $data): void {
        global $DB;

        $context = \core\context\system::instance();

        $texts = (object) ['id' => $this->id];
        foreach (['description' => EDITOR_UNLIMITED_FILES, 'secretinfo' => 0] as $field => $maxfiles) {
            if (!isset($data->{$field . '_editor'})) {
                continue;
            }
            $data = file_postupdate_standard_editor(
                $data,
                $field,
                self::get_editor_options($maxfiles),
                $context,
                'mod_avatar',
                $field,
                $this->id
            );
            $texts->$field = $data->$field;
            $texts->{$field . 'format'} = $data->{$field . 'format'};
        }
        $DB->update_record('avatar_list', $texts);

        $variants = (int) ($data->variants ?? $this->data->variants);
        $areas = ['previewimage', 'additionalmedia'];
        for ($i = 1; $i <= $variants; $i++) {
            array_push($areas, "avatarimage{$i}", "avatarthumbnail{$i}", "animationstates{$i}");
        }
        foreach ($areas as $area) {
            if (isset($data->$area)) {
                file_save_draft_area_files(
                    $data->$area,
                    $context->id,
                    'mod_avatar',
                    $area,
                    $this->id,
                    self::get_filemanager_options($area)
                );
            }
        }

        // Variants that were removed: delete their files.
        $fs = get_file_storage();
        for ($i = $variants + 1; $i <= self::MAX_VARIANTS; $i++) {
            $fs->delete_area_files($context->id, 'mod_avatar', "avatarimage{$i}", $this->id);
            $fs->delete_area_files($context->id, 'mod_avatar', "avatarthumbnail{$i}", $this->id);
            $fs->delete_area_files($context->id, 'mod_avatar', "animationstates{$i}", $this->id);
        }

        $this->data = $DB->get_record('avatar_list', ['id' => $this->id], '*', MUST_EXIST);
    }

    /**
     * Prepares the data of the avatar for the avatar form: editors, draft areas of the files and tags.
     *
     * @return \stdClass
     */
    public function prepare_form_data(): \stdClass {
        $context = \core\context\system::instance();
        $data = clone $this->data;

        foreach (['description' => EDITOR_UNLIMITED_FILES, 'secretinfo' => 0] as $field => $maxfiles) {
            $data = file_prepare_standard_editor(
                $data,
                $field,
                self::get_editor_options($maxfiles),
                $context,
                'mod_avatar',
                $field,
                $this->id
            );
        }

        $areas = ['previewimage', 'additionalmedia'];
        for ($i = 1; $i <= $data->variants; $i++) {
            array_push($areas, "avatarimage{$i}", "avatarthumbnail{$i}", "animationstates{$i}");
        }
        foreach ($areas as $area) {
            $draftitemid = file_get_submitted_draft_itemid($area);
            file_prepare_draft_area(
                $draftitemid,
                $context->id,
                'mod_avatar',
                $area,
                $this->id,
                self::get_filemanager_options($area)
            );
            $data->$area = $draftitemid;
        }

        $data->tags = \core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $this->id);
        return $data;
    }

    /**
     * Get tags for this avatar.
     *
     * @return array
     */
    public function get_tags() {
        return \core_tag_tag::get_item_tags('mod_avatar', 'avatar', $this->id);
    }

    /**
     * Get the URL for viewing an avatar.
     *
     * @return string The URL for viewing the avatar
     */
    public function get_avatar_view_url() {
        $params = ['avatarid' => $this->id];
        return new \moodle_url('/mod/avatar/view_avatar.php', $params);
    }

    /**
     * Whether the avatar can be collected or upgraded by a user (see {@see collection::is_avatar_available()}).
     *
     * @param int $userid The user ID
     * @param \stdClass|\cm_info|null $cm The course module (optional)
     * @return bool True if the avatar is available, false otherwise
     */
    public function is_avatar_available($userid, $cm = null) {
        if ($cm && !$cm instanceof \cm_info) {
            $cm = \cm_info::create($cm);
        }
        return collection::is_avatar_available($this->data, (int) $userid, $cm ?: null);
    }
}
