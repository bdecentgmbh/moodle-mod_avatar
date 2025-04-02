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

    /**
     * @var array List of avatar items
     */
    public static $avatarslist = [];

    /**
     * Define the structure of the avatar activity, items and tags.
     *
     * @return mixed
     */
    protected function define_structure() {

        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('avatar', '/activity/avatar');
        $paths[] = new restore_path_element('avatar_item', '/activity/avatar/avatars/avatar_item');
        $paths[] = new restore_path_element('avatar_tag', '/activity/avatar/avatar_tags/avatar_tag');

        if ($userinfo) {
            $paths[] = new restore_path_element('avatar_user', '/activity/avatar/user_avatars/user_avatar');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the avatar activity.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Insert the avatar record.
        $newitemid = $DB->insert_record('avatar', $data);

        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process the avatar item.
     *
     * This function processes the avatar item data and checks if it already exists in the database.
     * If it does not exist, it inserts a new record into the database.
     * It also handles the mapping of the avatar item ID and restores related files.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Check if this avatar already exists (by idnumber or name).
        $existing = false;

        $likesql = $DB->sql_like('name', ':name', false);
        $param = ['name' => $data->name];
        $existing = $DB->get_record_select(
            'avatar_list', "idnumber=:idnumber OR $likesql", $param + ['idnumber' => $data->idnumber], '*', IGNORE_MULTIPLE);

        if (!$existing) {
            $data->timecreated = $this->apply_date_offset($data->timecreated);
            $data->timemodified = $this->apply_date_offset($data->timemodified);
            $data->timearchived = $this->apply_date_offset($data->timearchived);

            if (!empty($data->usermodified)) {
                $data->usermodified = $this->get_mappingid('user', $data->usermodified);
            }
            $newitemid = $DB->insert_record('avatar_list', $data);

            // Create a mapping of the avatar item.
            $syscontext = context_system::instance();
            $this->set_mapping('avatar_item', $oldid, $newitemid, true, $syscontext->id);

            $this->add_related_files('mod_avatar', 'previewimage', 'avatar_item', $syscontext->id, $oldid);
            $this->add_related_files('mod_avatar', 'additionalmedia', 'avatar_item', $syscontext->id, $oldid);
            $this->add_related_files('mod_avatar', 'description', 'avatar_item', $syscontext->id, $oldid);
            $this->add_related_files('mod_avatar', 'secretinfo', 'avatar_item', $syscontext->id, $oldid);

            // Restore all variant-related files.
            for ($i = 1; $i <= 10; $i++) {
                $this->add_related_files('mod_avatar', "avatarimage{$i}", 'avatar_item', $syscontext->id, $oldid);
                $this->add_related_files('mod_avatar', "avatarthumbnail{$i}", 'avatar_item', $syscontext->id, $oldid);
                $this->add_related_files('mod_avatar', "animationstates{$i}", 'avatar_item', $syscontext->id, $oldid);
            }

        } else {
            // If avatar already exists, use its ID for mapping.
            $this->set_mapping('avatar_item', $oldid, $existing->id);
        }
    }

    /**
     * Process the avatar tag.
     *
     * This function processes the avatar tag data and checks if it already exists in the database.
     * If it does not exist, it inserts a new record into the database.
     * It also handles the mapping of the avatar tag ID and restores related files.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_tag($data) {
        global $DB;

        $data = (object)$data;

        // Get the new avatar item ID
        // Filter the data based on the shortname.
        $tag = array_filter((array) $data, function($key) {
            return strpos($key, 't_') === 0;
        }, ARRAY_FILTER_USE_KEY);

        $taginstance = array_filter((array) $data, function($key) {
            return strpos($key, 'ti_') === 0;
        }, ARRAY_FILTER_USE_KEY);

        $tagrecord = new stdclass();
        foreach ($tag as $k => $value) {
            $newkey = str_replace('t_', '', $k);
            $tagrecord->$newkey = $value;
        }

        $taginstancerecord = new stdclass();
        foreach ($taginstance as $k => $value) {
            $newkey = str_replace('ti_', '', $k);
            $taginstancerecord->$newkey = $value;
        }

        $avatarid = $this->get_mappingid('avatar_item', $taginstancerecord->itemid);

        if (!$avatarid) {
            return; // Skip if we don't have a valid avatar ID.
        }

        // Check if the tag exists.
        $tag = $DB->get_record('tag', ['name' => $tagrecord->rawname]);

        if (!$tag) {
            $tag = (object)['id' => $DB->insert_record('tag', $tagrecord)];
        }

        // Check if the tag instance already exists.
        $taginstance = $DB->get_record('tag_instance', [
            'tagid' => $tag->id,
            'itemtype' => 'avatar',
            'itemid' => $avatarid,
            'component' => 'mod_avatar',
        ]);

        if (!$taginstance) {
            // Create the tag instance.
            $taginstancedata = new stdClass();
            $taginstancedata->tagid = $tag->id;
            $taginstancedata->component = 'mod_avatar';
            $taginstancedata->itemtype = 'avatar';
            $taginstancedata->itemid = $avatarid;
            $taginstancedata->contextid = context_system::instance()->id;
            $taginstancedata->tiuserid = 0;
            $taginstancedata->ordering = 0;
            $taginstancedata->timecreated = time();
            $taginstancedata->timemodified = time();
            $DB->insert_record('tag_instance', $taginstancedata);
        }
    }

    /**
     * Process the avatar user.
     *
     * This function processes the avatar user data and checks if it already exists in the database.
     * If it does not exist, it inserts a new record into the database.
     * It also handles the mapping of the avatar user ID and restores related files.
     *
     * @param array $data The data to process.
     */
    protected function process_avatar_user($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->avatarid = $this->get_mappingid('avatar_item', $data->avatarid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->cmid = $this->task->get_moduleid();

        // Only restore if we have valid mappings.
        if ($data->avatarid && $data->userid) {
            $data->timecollected = $this->apply_date_offset($data->timecollected);
            $data->timemodified = $this->apply_date_offset($data->timemodified);

            // Check if this user-avatar relationship already exists.
            $existing = $DB->get_record('avatar_user', [
                'userid' => $data->userid,
                'avatarid' => $data->avatarid,
                'cmid' => $data->cmid,
            ]);

            if (!$existing) {
                $newitemid = $DB->insert_record('avatar_user', $data);
                $this->set_mapping('avatar_user', $oldid, $newitemid);
            }
        }
    }

    /**
     * Restore the avatar files.
     *
     * This function restores the avatar files after the activity has been restored.
     * It adds related files for the avatar activity and its items.
     */
    protected function after_execute() {
        // Restore the avatar filemanager and editor files.
        $this->add_related_files('mod_avatar', 'intro', $this->task->get_modulename());
        $this->add_related_files('mod_avatar', 'headercontent', $this->task->get_modulename());
        $this->add_related_files('mod_avatar', 'footercontent', $this->task->get_modulename());
        $this->add_related_files('mod_avatar', 'emptystate', $this->task->get_modulename());
    }
}
