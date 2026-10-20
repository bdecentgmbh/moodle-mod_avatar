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
 * Privacy implementation for avatar module.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\privacy;

use core\context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider of the avatar activity.
 *
 * The avatar collection of a user (table avatar_user) belongs to the user, not to the activity in which an
 * avatar was collected, so it is exported and deleted in the user context (like issued badges). The user who
 * last changed an avatar of the library (avatar_list.usermodified) is stored in the system context; on
 * deletion the reference is removed, the avatar stays.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * The stored personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('avatar_user', [
            'userid' => 'privacy:metadata:avatar_user:userid',
            'avatarid' => 'privacy:metadata:avatar_user:avatarid',
            'variant' => 'privacy:metadata:avatar_user:variant',
            'cmid' => 'privacy:metadata:avatar_user:cmid',
            'isprimary' => 'privacy:metadata:avatar_user:isprimary',
            'timecollected' => 'privacy:metadata:avatar_user:timecollected',
            'timemodified' => 'privacy:metadata:avatar_user:timemodified',
        ], 'privacy:metadata:avatar_user');

        $collection->add_database_table('avatar_list', [
            'usermodified' => 'privacy:metadata:avatar_list:usermodified',
            'timemodified' => 'privacy:metadata:avatar_list:timemodified',
        ], 'privacy:metadata:avatar_list');

        // With profile picture sync, the image of the primary avatar becomes the profile picture of the user.
        $collection->add_subsystem_link('core_user', [], 'privacy:metadata:profilepicture');

        return $collection;
    }

    /**
     * The contexts with data of a user.
     *
     * @param int $userid The user.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if ($DB->record_exists('avatar_user', ['userid' => $userid])) {
            $contextlist->add_user_context($userid);
        }
        if ($DB->record_exists('avatar_list', ['usermodified' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * The users with data in a context.
     *
     * @param userlist $userlist The userlist with the context.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context\user) {
            $userlist->add_from_sql(
                'userid',
                "SELECT userid FROM {avatar_user} WHERE userid = :userid",
                ['userid' => $context->instanceid]
            );
        } else if ($context instanceof context\system) {
            $userlist->add_from_sql('usermodified', "SELECT usermodified FROM {avatar_list} WHERE usermodified > 0", []);
        }
    }

    /**
     * Exports the data of a user.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context\user && $context->instanceid == $userid) {
                $records = $DB->get_records_sql(
                    "SELECT au.*, al.name
                       FROM {avatar_user} au
                       JOIN {avatar_list} al ON al.id = au.avatarid
                      WHERE au.userid = :userid
                   ORDER BY au.timecollected, au.id",
                    ['userid' => $userid]
                );
                $avatars = [];
                foreach ($records as $record) {
                    $avatars[] = [
                        'name' => format_string($record->name, true, ['context' => context\system::instance()]),
                        'variant' => $record->variant,
                        'isprimary' => transform::yesno($record->isprimary),
                        'collectedin' => self::get_activity_name($record->cmid),
                        'timecollected' => transform::datetime($record->timecollected),
                        'timemodified' => transform::datetime($record->timemodified),
                    ];
                }
                if ($avatars) {
                    writer::with_context($context)->export_data(
                        [get_string('myavatars', 'mod_avatar')],
                        (object) ['avatars' => $avatars]
                    );
                }
            } else if ($context instanceof context\system) {
                $records = $DB->get_records('avatar_list', ['usermodified' => $userid], 'id', 'id, name, timemodified');
                $avatars = array_map(fn($record) => [
                    'name' => format_string($record->name, true, ['context' => $context]),
                    'timemodified' => transform::datetime($record->timemodified),
                ], array_values($records));
                if ($avatars) {
                    writer::with_context($context)->export_data(
                        [get_string('manageavatars', 'mod_avatar')],
                        (object) ['avatars' => $avatars]
                    );
                }
            }
        }
    }

    /**
     * Deletes the data of all users in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof context\user) {
            $DB->delete_records('avatar_user', ['userid' => $context->instanceid]);
        } else if ($context instanceof context\system) {
            $DB->set_field_select('avatar_list', 'usermodified', 0, 'usermodified > 0');
        }
    }

    /**
     * Deletes the data of a user in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context\user && $context->instanceid == $userid) {
                $DB->delete_records('avatar_user', ['userid' => $userid]);
            } else if ($context instanceof context\system) {
                $DB->set_field('avatar_list', 'usermodified', 0, ['usermodified' => $userid]);
            }
        }
    }

    /**
     * Deletes the data of several users in a context.
     *
     * @param approved_userlist $userlist The approved users and context.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        if ($context instanceof context\user) {
            if (in_array($context->instanceid, $userids)) {
                $DB->delete_records('avatar_user', ['userid' => $context->instanceid]);
            }
        } else if ($context instanceof context\system) {
            [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->set_field_select('avatar_list', 'usermodified', 0, "usermodified $insql", $params);
        }
    }

    /**
     * The name of the activity in which an avatar was collected.
     *
     * @param int|null $cmid The course module id.
     * @return string
     */
    protected static function get_activity_name(?int $cmid): string {
        global $DB;

        if (!$cmid) {
            return '';
        }
        $cm = get_coursemodule_from_id('avatar', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return '';
        }
        return format_string($cm->name, true, ['context' => context\module::instance($cm->id)]);
    }
}
