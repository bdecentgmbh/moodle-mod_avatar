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
 * Define all the restore steps to restore the avatar acitivity.
 *
 * @package     mod_avatar
 * @copyright   2025 bdecent GmbH <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one avatar activity
 */
class restore_avatar_activity_structure_step extends restore_activity_structure_step {
    /** @var bool Whether the restoring user may create avatars in the library. */
    protected $cancreateavatars = false;

    /** @var array Tags of the avatars created by this restore: new avatar id => tag names. */
    protected $createdtags = [];

    /**
     * Define the structure of the avatar activity, items and tags.
     *
     * @return array
     */
    protected function define_structure() {
        $this->cancreateavatars = has_capability(
            'mod/avatar:create',
            \core\context\system::instance(),
            $this->task->get_userid()
        );

        $paths = [];
        $avatarpath = new restore_path_element('avatar', '/activity/avatar');
        $paths[] = $avatarpath;
        $avataritempath = new restore_path_element('avatar_item', '/activity/avatar/avatars/avatar_item');
        $paths[] = $avataritempath;
        $paths[] = new restore_path_element('avatar_tag', '/activity/avatar/avatar_tags/avatar_tag');

        if ($this->get_setting_value('userinfo')) {
            $paths[] = new restore_path_element('avatar_user', '/activity/avatar/user_avatars/user_avatar');
        }

        // Data of the addons (activity and avatars).
        $this->add_subplugin_structure('avataraddon', $avatarpath);
        $this->add_subplugin_structure('avataraddon', $avataritempath);

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar($data) {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('avatar', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Maps an avatar of the backup to the avatar library.
     *
     * Existing avatars (same idnumber, or same name when the backup has no idnumber) are used as they are.
     * Missing avatars are only created when the restoring user may create avatars, and only when the backup
     * contains their content.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_item($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $idnumber = trim((string) ($data->idnumber ?? ''));
        if ($idnumber !== '') {
            $existing = $DB->get_record('avatar_list', ['idnumber' => $idnumber], 'id', IGNORE_MULTIPLE);
        } else {
            $existing = $DB->get_record('avatar_list', ['name' => $data->name, 'idnumber' => ''], 'id', IGNORE_MULTIPLE);
        }
        if ($existing) {
            $this->set_mapping('avatar_item', $oldid, $existing->id);
            return;
        }

        // Without its content (backup by a user who may not manage the library) the avatar cannot be recreated.
        if (!$this->cancreateavatars || !property_exists($data, 'description')) {
            return;
        }

        $record = new stdClass();
        foreach (
            ['name', 'idnumber', 'description', 'descriptionformat', 'internalnotes', 'secretinfo',
                'secretinfoformat', 'variants', 'status', 'archived'] as $field
        ) {
            $record->$field = $data->$field ?? null;
        }
        $record->name = clean_param($record->name, PARAM_TEXT);
        $record->idnumber = $idnumber;
        $record->internalnotes = clean_param((string) $record->internalnotes, PARAM_TEXT);
        $record->descriptionformat = (int) $record->descriptionformat;
        $record->secretinfoformat = (int) $record->secretinfoformat;
        $record->variants = max(1, min(\mod_avatar\avatar::MAX_VARIANTS, (int) $record->variants));
        $record->status = (int) !empty($record->status);
        $record->archived = (int) !empty($record->archived);
        $record->timearchived = $record->archived ? time() : null;
        $record->timecreated = time();
        $record->timemodified = $record->timecreated;
        $record->usermodified = $this->task->get_userid();

        $newid = $DB->insert_record('avatar_list', $record);
        $this->set_mapping('avatar_item', $oldid, $newid);
        $this->set_mapping('avatar_item_created', $oldid, $newid);
        $this->createdtags[$newid] = [];
    }

    /**
     * Collects the tags of the avatars created by this restore (they are added in after_execute()).
     *
     * Supports the format of this version (ti_itemid, t_rawname) and of older versions (all tag fields).
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_tag($data) {
        $newid = $this->get_mappingid('avatar_item_created', $data['ti_itemid'] ?? 0);
        $rawname = trim((string) ($data['t_rawname'] ?? ''));
        if ($newid && $rawname !== '') {
            $this->createdtags[$newid][] = clean_param($rawname, PARAM_TAG);
        }
    }

    /**
     * Restores the avatars users collected in the activity.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_user($data) {
        global $DB;

        $data = (object) $data;
        $data->avatarid = $this->get_mappingid('avatar_item', $data->avatarid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (
            !$data->avatarid || !$data->userid
                || $DB->record_exists('avatar_user', ['userid' => $data->userid, 'avatarid' => $data->avatarid])
        ) {
            return;
        }

        $variants = (int) $DB->get_field('avatar_list', 'variants', ['id' => $data->avatarid]);
        $record = (object) [
            'userid' => $data->userid,
            'avatarid' => $data->avatarid,
            'variant' => max(1, min($variants ?: 1, (int) $data->variant)),
            'cmid' => $this->task->get_moduleid(),
            // A user has one primary avatar; a restored avatar only becomes primary if the user has none.
            'isprimary' => (int) (!empty($data->isprimary)
                && !$DB->record_exists('avatar_user', ['userid' => $data->userid, 'isprimary' => 1])),
            'timecollected' => $this->apply_date_offset($data->timecollected),
            'timemodified' => $this->apply_date_offset($data->timemodified),
        ];
        $DB->insert_record('avatar_user', $record);
    }

    /**
     * Restores the files of the activity, and the files and tags of the avatars created by this restore.
     */
    protected function after_execute() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/avatar/lib.php');

        foreach (['intro', 'headercontent', 'footercontent', 'emptystate'] as $area) {
            $this->add_related_files('mod_avatar', $area, $area === 'intro' ? null : 'avatar');
        }

        if (!$this->createdtags) {
            return;
        }

        $syscontext = \core\context\system::instance();
        $oldsyscontextid = $this->task->get_info()->original_system_contextid;
        $areas = array_merge(array_diff(avatar_get_library_fileareas(), ['tagimages']), ['secretinfo']);
        foreach ($areas as $area) {
            restore_dbops::send_files_to_pool(
                $this->get_basepath(),
                $this->get_restoreid(),
                'mod_avatar',
                $area,
                $oldsyscontextid,
                $this->task->get_userid(),
                'avatar_item_created',
                null,
                $syscontext->id,
                true
            );
        }

        $fs = get_file_storage();
        foreach ($this->createdtags as $newid => $tags) {
            // Only images (and videos in the additional media) are allowed in the image areas.
            foreach ($areas as $area) {
                if (in_array($area, ['description', 'secretinfo'])) {
                    continue;
                }
                $allowed = $area === 'additionalmedia' ? ['web_image', 'web_video'] : ['web_image'];
                foreach ($fs->get_area_files($syscontext->id, 'mod_avatar', $area, $newid, 'id', false) as $file) {
                    if (!file_mimetype_in_typegroup($file->get_mimetype(), $allowed)) {
                        $file->delete();
                    }
                }
            }
            if ($tags) {
                \core_tag_tag::set_item_tags('mod_avatar', 'avatar', $newid, $syscontext, array_unique($tags));
            }
        }
    }
}
