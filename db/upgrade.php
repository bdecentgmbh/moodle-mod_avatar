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

    if ($oldversion < 2023051221) {
        // Define table avatar_list to remove fields.
        $table = new xmldb_table('avatar_list');

        // Drop fields coursecategories, cohorts, and totalcapacity if they exist.
        $fields = ['coursecategories', 'includesubcategories', 'cohorts', 'totalcapacity'];
        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        $table = new xmldb_table('avatar');
        $fields = ['collectiontotallimit', 'collectionlimitperuser', 'collectionlimitperinterval', 'collectioninterval'];
        foreach ($fields as $fieldname) {
            $field = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        // Upgrade savepoint reached.
        upgrade_mod_savepoint(true, 2023051221, 'avatar');
    }

    return true;
}
