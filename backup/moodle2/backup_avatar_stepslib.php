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
     * Defines the structure of the backup of an avatar activity.
     *
     * The backup contains the avatars of the library the activity offers, so that a restore on another site can
     * recreate them. Their texts and files are only included when the user who makes the backup may create
     * avatars (manage the library); otherwise only their names and idnumbers, to map them on restore.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/avatar/lib.php');

        $userinfo = $this->get_setting_value('userinfo');
        $syscontext = \core\context\system::instance();
        $backupuserid = (int) $DB->get_field('backup_controllers', 'userid', ['backupid' => $this->get_backupid()]);
        $withlibrary = $backupuserid && has_capability('mod/avatar:create', $syscontext, $backupuserid);

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

        $avatartags = new backup_nested_element('avatar_tags');
        $avatartag = new backup_nested_element('avatar_tag', ['ti_id'], ['ti_itemid', 't_name', 't_rawname']);

        $useravatars = new backup_nested_element('user_avatars');
        $useravatar = new backup_nested_element('user_avatar', ['id'], [
            'userid', 'avatarid', 'variant', 'cmid', 'isprimary', 'timecollected', 'timemodified']);

        $avatar->add_child($avatars);
        $avatars->add_child($avataritem);
        $avatar->add_child($avatartags);
        $avatartags->add_child($avatartag);
        $avatar->add_child($useravatars);
        $useravatars->add_child($useravatar);

        $avatar->set_source_table('avatar', ['id' => backup::VAR_ACTIVITYID]);

        // The avatars the activity offers, and those collected in it.
        $instance = $DB->get_record('avatar', ['id' => $this->task->get_activityid()], '*', MUST_EXIST);
        $avatarids = array_keys(\mod_avatar\activity::get_avatars($instance));
        $avatarids = array_merge($avatarids, $DB->get_fieldset_select(
            'avatar_user',
            'DISTINCT avatarid',
            'cmid = :cmid',
            ['cmid' => $this->task->get_moduleid()]
        ));
        $avatarids = array_values(array_unique(array_map('intval', $avatarids))) ?: [0];
        [$insql, $inparams] = $DB->get_in_or_equal($avatarids, SQL_PARAMS_QM);
        $inparams = array_map([backup_helper::class, 'is_sqlparam'], $inparams);

        if ($withlibrary) {
            $avataritem->set_source_sql("SELECT * FROM {avatar_list} WHERE id $insql", $inparams);
        } else {
            $avataritem->set_source_sql("SELECT id, name, idnumber, variants, status, archived, timecreated, timemodified
                                           FROM {avatar_list} WHERE id $insql", $inparams);
        }

        $avatartag->set_source_sql(
            "SELECT ti.id AS ti_id, ti.itemid AS ti_itemid, t.name AS t_name, t.rawname AS t_rawname
               FROM {tag_instance} ti
               JOIN {tag} t ON t.id = ti.tagid
              WHERE ti.component = ? AND ti.itemtype = ? AND ti.itemid $insql",
            array_merge([backup_helper::is_sqlparam('mod_avatar'), backup_helper::is_sqlparam('avatar')], $inparams)
        );

        if ($userinfo) {
            $useravatar->set_source_sql("SELECT * FROM {avatar_user} WHERE cmid = ?", [backup::VAR_MODID]);
        }

        $useravatar->annotate_ids('user', 'userid');
        if ($withlibrary) {
            $avataritem->annotate_ids('user', 'usermodified');
        }

        $avatar->annotate_files('mod_avatar', 'intro', null);
        $avatar->annotate_files('mod_avatar', 'headercontent', null);
        $avatar->annotate_files('mod_avatar', 'footercontent', null);
        $avatar->annotate_files('mod_avatar', 'emptystate', null);

        if ($withlibrary) {
            foreach (avatar_get_library_fileareas() as $area) {
                if ($area !== 'tagimages') {
                    $avataritem->annotate_files('mod_avatar', $area, 'id', $syscontext->id);
                }
            }
            $avataritem->annotate_files('mod_avatar', 'secretinfo', 'id', $syscontext->id);
        }

        // Data of the addons (activity and avatars).
        $this->add_subplugin_structure('avataraddon', $avatar, true);
        $this->add_subplugin_structure('avataraddon', $avataritem, true);

        return $this->prepare_activity_structure($avatar);
    }
}
