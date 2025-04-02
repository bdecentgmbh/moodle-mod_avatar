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
 * Define all the backup steps that will be used by the backup_avatar_activity_task
 *
 * @package     mod_avatar
 * @copyright   2025 bdecent GmbH <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define the complete avatar structure for backup, with file and id annotations
 */
class backup_avatar_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the avatar activity
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        global $DB;

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $avatar = new backup_nested_element('avatar', ['id'], [
            'course', 'name', 'intro', 'introformat', 'displaymode',
            'headercontent', 'headercontentformat', 'footercontent', 'footercontentformat',
            'avatarselection', 'specifictags', 'emptystate', 'emptystateformat',
            'completionview', 'completioncollect', 'timecreated', 'timemodified']);

        $avatars = new backup_nested_element('avatars');
        $avataritem = new backup_nested_element('avatar_item', ['id'], [
            'name', 'idnumber', 'description', 'descriptionformat', 'internalnotes',
            'secretinfo', 'secretinfoformat', 'previewimage', 'additionalimages',
            'variants', 'variantimages', 'thumbnails', 'animationstates',
            'timecreated', 'timemodified', 'usermodified', 'status', 'archived', 'timearchived']);

        // Define avatar tags.
        $select = self::get_avatar_tags_fields();
        $tagfields = array_keys($select);
        array_shift($tagfields);
        $avatartags = new backup_nested_element('avatar_tags');
        $avatartag = new backup_nested_element('avatar_tag', ['ti_id'], $tagfields);

        // Define user avatars.
        $useravatars = new backup_nested_element('user_avatars');
        $useravatar = new backup_nested_element('user_avatar', ['id'], [
            'userid', 'avatarid', 'variant', 'cmid', 'isprimary', 'timecollected', 'timemodified']);

        // Build the tree.
        $avatar->add_child($avatars);
        $avatars->add_child($avataritem);

        $avatar->add_child($avatartags);
        $avatartags->add_child($avatartag);

        $avatar->add_child($useravatars);
        $useravatars->add_child($useravatar);

        // Define sources.
        $avatar->set_source_table('avatar', ['id' => backup::VAR_ACTIVITYID]);

        // Get only avatars used in this module.
        $avataritem->set_source_sql(
            "SELECT al.*
             FROM {avatar_list} al WHERE al.archived = 0",
            [backup::VAR_ACTIVITYID]
        );

        // Add tags for each avatar.
        // Final list of select columns, convert to sql mode.
        $select = implode(', ', array_values($select));
        $avatartag->set_source_sql(
            "SELECT $select
             FROM {tag_instance} ti
             JOIN {tag} t ON ti.tagid = t.id
             WHERE ti.itemtype = 'avatar'
             AND ti.component = 'mod_avatar'", []
        );

        // Include user avatars if user info is included.
        if ($userinfo) {
            $useravatar->set_source_sql(
                "SELECT * FROM {avatar_user}
                 WHERE avatarid = ? AND cmid = ?",
                [backup::VAR_PARENTID, backup::VAR_ACTIVITYID]
            );
        }

        // Define id annotations.
        $useravatar->annotate_ids('user', 'userid');
        $avataritem->annotate_ids('user', 'usermodified');

        // Define file annotations.
        $avatar->annotate_files('mod_avatar', 'intro', null);
        $avatar->annotate_files('mod_avatar', 'headercontent', null);
        $avatar->annotate_files('mod_avatar', 'footercontent', null);
        $avatar->annotate_files('mod_avatar', 'emptystate', null);

        // Annotate all avatar file areas.
        $syscontext = context_system::instance();
        $avataritem->annotate_files('mod_avatar', 'previewimage', null, $syscontext->id);
        $avataritem->annotate_files('mod_avatar', 'additionalmedia', null, $syscontext->id);
        $avataritem->annotate_files('mod_avatar', 'description', null, $syscontext->id);
        $avataritem->annotate_files('mod_avatar', 'secretinfo', null, $syscontext->id);

        // Annotate all variant-related file areas.
        for ($i = 1; $i <= 10; $i++) {
            $avataritem->annotate_files('mod_avatar', "avatarimage{$i}", null, $syscontext->id);
            $avataritem->annotate_files('mod_avatar', "avatarthumbnail{$i}", null, $syscontext->id);
            $avataritem->annotate_files('mod_avatar', "animationstates{$i}", null, $syscontext->id);
        }

        // Return the root element (avatar), wrapped into standard activity structure.
        return $this->prepare_activity_structure($avatar);
    }

    /**
     * Get the list of avatar tags fields.
     *
     * @return array
     */
    public static function get_avatar_tags_fields() {
        global $DB;

        // Get the list of alias tags for each avatar.
        $tables = [
            'ti' => $DB->get_columns('tag_instance'),
            't' => $DB->get_columns('tag'),
        ];

        $select['ti_id'] = 'ti.id AS id'; // Set the schdule id as unique column.
        foreach ($tables as $prefix => $table) {
            $columns = array_keys($table);
            foreach ($columns as $key => $value) {
                $key = $prefix.'_'.$value;
                $value = "$prefix.$value AS ".$prefix."_$value";
                $select[$key] = $value;
            }
        }

        return $select;
    }
}
