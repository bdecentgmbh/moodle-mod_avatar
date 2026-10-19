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
 * Avatar module upgrade script
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade script for the avatar module
 *
 * @param int $oldversion The version we are upgrading from
 * @return bool Always true
 */
function xmldb_avatar_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025041300) {

        // Define table avatar to add fields.
        $protable = new xmldb_table('avataraddon_pro_avatar');

        // Define table avatar_list to remove fields.
        $table = new xmldb_table('avatar_list');
        $fields = ['coursecategories', 'includesubcategories', 'cohorts', 'totalcapacity'];

        if ($dbman->table_exists($protable)) {
            $field = new xmldb_field('coursecategories');
            if ($dbman->field_exists($table,  $field)) {
                $sql = "SELECT al.*
                        FROM {avatar_list} al
                        WHERE al.id NOT IN (SELECT avatarid FROM {avataraddon_pro_avatar})";
                $records = $DB->get_records_sql($sql);
                foreach ($records as $record) {
                    $data = new stdClass();
                    $data->avatarid = $record->id;
                    $data->coursecategories = $record->coursecategories ?? '';
                    $data->includesubcategories = $record->includesubcategories ?? 0;
                    $data->cohorts = $record->cohorts ?? '';
                    $data->totalcapacity = $record->totalcapacity ?? 0;
                    $DB->insert_record('avataraddon_pro_avatar', $data);
                }
            }
        }

        // Drop fields coursecategories, cohorts, and totalcapacity if they exist.
        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // Migrate the pro data to addon table.
        $protable = new xmldb_table('avataraddon_pro');

        $table = new xmldb_table('avatar');
        $fields = ['collectiontotallimit', 'collectionlimitperuser', 'collectionlimitperinterval', 'collectioninterval'];

        if ($dbman->table_exists($protable)) {
            $field = new xmldb_field('collectiontotallimit');
            if ($dbman->field_exists($table,  $field)) {
                $sql = "SELECT al.*
                        FROM {avatar} al
                        WHERE al.id NOT IN (SELECT cmavatarid FROM {avataraddon_pro})";
                $records = $DB->get_records_sql($sql);
                foreach ($records as $record) {
                    $data = new stdClass();
                    $data->cmavatarid = $record->id;
                    $data->collectiontotallimit = $record->collectiontotallimit ?? 0;
                    $data->collectionlimitperuser = $record->collectionlimitperuser ?? 0;
                    $data->collectionlimitperinterval = $record->collectionlimitperinterval ?? 0;
                    $data->collectioninterval = $record->collectioninterval ?? 0;
                    $DB->insert_record('avataraddon_pro', $data);
                }
            }
        }

        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        // Upgrade savepoint reached.
        upgrade_mod_savepoint(true, 2025041300, 'avatar');
    }

    return true;
}
