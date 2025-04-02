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

        throw new \Exception("Property $key does not exist");
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

        if ($result) {
            $this->data = $DB->get_record('avatar_list', ['id' => $this->id]);
        }

        // Avatar updated successfully, Trigger the event.
        $event = \mod_avatar\event\avatar_changed::create([
            'context' => \context_system::instance(),
            'objectid' => $data->id,
            'other' => [
                'data' => $data,
            ],
        ]);
        $event->trigger();

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
        return $DB->update_record('avatar_list', $this->data);
    }

    /**
     * Delete the avatar.
     *
     * @return bool
     */
    public function delete() {
        global $DB;
        // Delete tags.
        \core_tag_tag::remove_all_item_tags('mod_avatar', 'avatar', $this->id);
        return $DB->delete_records('avatar_list', ['id' => $this->id]);
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
     * Handle file uploads for the avatar.
     *
     * @param \stdClass $data Form data
     * @param \context $context Context
     * @return void
     */
    public function handle_file_uploads($data, $context) {
        global $CFG;

        $filemanageroptions = ['maxbytes' => $CFG->maxbytes, 'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];

        // Save preview image fileareas.
        $draftitemid = file_get_submitted_draft_itemid('previewimage');
        file_save_draft_area_files($draftitemid, $context->id, 'mod_avatar', 'previewimage', $this->id, $filemanageroptions);

        // Save additional media files.
        $draftitemid = file_get_submitted_draft_itemid('additionalmedia');
        file_save_draft_area_files($draftitemid, $context->id, 'mod_avatar', 'additionalmedia', $this->id,
            ['maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'subdirs' => 0, 'accepted_types' => ['image', 'video']]);

        // Save description files.
        $draftitemid = file_get_submitted_draft_itemid('description_editor');
        $data = file_postupdate_standard_editor($data, 'description',
            $this->get_description_editor_options($context), $context, 'mod_avatar', 'description', $this->id);

        // Save secretinfo.
        $data = file_postupdate_standard_editor($data, 'secretinfo',
            ['trusttext' => true, 'context' => $context, 'maxfiles' => 0], $context, 'mod_avatar', 'secretinfo', $this->id);

        // Save variant images.
        for ($i = 1; $i <= $data->variants; $i++) {
            $draftitemid = file_get_submitted_draft_itemid("avatarimage{$i}");
            file_save_draft_area_files(
                $draftitemid, $context->id, 'mod_avatar', "avatarimage{$i}", $this->id, $filemanageroptions);

            $draftitemid = file_get_submitted_draft_itemid("avatarthumbnail{$i}");
            file_save_draft_area_files(
                $draftitemid, $context->id, 'mod_avatar', "avatarthumbnail{$i}", $this->id, $filemanageroptions);

            $draftitemid = file_get_submitted_draft_itemid("animationstates{$i}");
            file_save_draft_area_files($draftitemid, $context->id, 'mod_avatar', "animationstates{$i}", $this->id,
                ['maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'subdirs' => 0, 'accepted_types' => ['image']]);
        }

    }

    /**
     * Prepare file areas for the avatar form.
     *
     * @param \context $context Context
     * @return \stdClass Updated avatar data.
     */
    public function prepare_fileareas($context) {
        global $CFG;

        $avatar = $this->data;

        // Prepare preview image.
        $filemanageroptions = ['maxbytes' => $CFG->maxbytes, 'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];

        $draftitemid = file_get_submitted_draft_itemid('previewimage');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_avatar', 'previewimage', $this->id, $filemanageroptions);
        $avatar->previewimage = $draftitemid;

        $draftitemid = file_get_submitted_draft_itemid('additionalmedia');
        file_prepare_draft_area($draftitemid, $context->id, 'mod_avatar', 'additionalmedia', $this->id,
            ['maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'subdirs' => 0, 'accepted_types' => ['image', 'video']]);
        $avatar->additionalmedia = $draftitemid;

        // Prepare description files.
        $avatar = file_prepare_standard_editor($avatar, 'description',
            $this->get_description_editor_options($context), $context, 'mod_avatar', 'description', $this->id);
        $avatar = file_prepare_standard_editor($avatar, 'secretinfo',
            ['trusttext' => true, 'context' => $context, 'maxfiles' => 0], $context, 'mod_avatar', 'secretinfo', $this->id);

        for ($i = 1; $i <= $avatar->variants; $i++) {

            $draftitemid = file_get_submitted_draft_itemid("avatarimage{$i}");
            file_prepare_draft_area(
                $draftitemid, $context->id, 'mod_avatar', "avatarimage{$i}", $this->id, $filemanageroptions);
            $avatar->{"avatarimage{$i}"} = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid("avatarthumbnail{$i}");
            file_prepare_draft_area(
                $draftitemid, $context->id, 'mod_avatar', "avatarthumbnail{$i}", $this->id, $filemanageroptions);
            $avatar->{"avatarthumbnail{$i}"} = $draftitemid;

            $draftitemid = file_get_submitted_draft_itemid("animationstates{$i}");
            file_prepare_draft_area($draftitemid, $context->id, 'mod_avatar', "animationstates{$i}", $this->id,
                ['maxbytes' => $CFG->maxbytes, 'maxfiles' => -1, 'subdirs' => 0, 'accepted_types' => ['image']]);
            $avatar->{"animationstates{$i}"} = $draftitemid;
        }

        return $avatar;
    }

    /**
     * Get description editor options.
     *
     * @param \context $context Context
     * @return array
     */
    private function get_description_editor_options($context) {
        global $CFG;

        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL,
            'context' => $context,
        ];
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
     * Check if an avatar is available to the user.
     *
     * @param int $userid The user ID
     * @param object|null $cm The course module (optional)
     * @return bool True if the avatar is available, false otherwise
     */
    public function is_avatar_available($userid, $cm = null) {
        // No restriction conditions available.
        return true;
    }

}
