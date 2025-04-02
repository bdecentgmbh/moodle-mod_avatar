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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\deletion_criteria;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;
use context_module;
use context_system;

/**
 * Implementation of the privacy subsystem plugin provider for the avatar activity module.
 */
class provider implements
    // This plugin stores personal data.
    \core_privacy\local\metadata\provider,
    // This plugin is a core_user_data_provider.
    \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'avatar_user',
            [
                'userid' => 'privacy:metadata:avatar_user:userid',
                'avatarid' => 'privacy:metadata:avatar_user:avatarid',
                'variant' => 'privacy:metadata:avatar_user:variant',
                'cmid' => 'privacy:metadata:avatar_user:cmid',
                'isprimary' => 'privacy:metadata:avatar_user:isprimary',
                'timecollected' => 'privacy:metadata:avatar_user:timecollected',
                'timemodified' => 'privacy:metadata:avatar_user:timemodified',
            ],
            'privacy:metadata:avatar_user'
        );

        // Add information about file storage.
        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:filepurpose');

        // Avatar uses tags.
        $collection->add_subsystem_link('core_tag', [], 'privacy:metadata:tagpurpose');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist A list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new \core_privacy\local\request\contextlist();

        // The avatar_user data is related to the course module context level, so retrieve the users avatar contexts.
        $sql = "SELECT c.id
                FROM {context} c
                INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                INNER JOIN {avatar_user} au ON au.cmid = cm.id
                WHERE au.userid = :userid";

        $params = [
            'modname'       => 'avatar',
            'contextlevel'  => CONTEXT_MODULE,
            'userid'        => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        // Check for system-level avatar data.
        $sql = "SELECT COUNT(au.id)
                FROM {avatar_user} au
                WHERE au.userid = :userid AND (au.cmid = 0 OR au.cmid IS NULL)";

        if ($DB->count_records_sql($sql, ['userid' => $userid]) > 0) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist)) {
            return;
        }

        $user = $contextlist->get_user();
        $userid = $user->id;

        // Export module context data.
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                self::export_avatar_data_for_module_context($context, $user);
            } else if ($context instanceof \context_system) {
                self::export_avatar_data_for_system_context($user);
            }
        }
    }

    /**
     * Export avatar data for a specific module context.
     *
     * @param \context_module $context The context to export data for
     * @param \stdClass $user The user to export data for
     */
    protected static function export_avatar_data_for_module_context(\context_module $context, \stdClass $user) {
        global $DB;

        $userid = $user->id;
        $cmid = $context->instanceid;

        // Get user's avatar data for this instance.
        $sql = "SELECT au.*, a.name as avatarname
                FROM {avatar_user} au
                JOIN {avatar} a ON a.id = au.avatarid
                WHERE au.userid = :userid
                AND au.cmid = :cmid";

        $params = [
            'userid' => $userid,
            'cmid' => $cmid,
        ];

        $avatardata = $DB->get_records_sql($sql, $params);

        if (!empty($avatardata)) {
            $contextdata = helper::get_context_data($context, $user);

            // Add avatar activity data.
            $contextdata->avatars = [];
            foreach ($avatardata as $record) {
                $contextdata->avatars[] = [
                    'name' => $record->avatarname,
                    'variant' => $record->variant,
                    'isprimary' => $record->isprimary ? true : false,
                    'timecollected' => \core_privacy\local\request\transform::datetime($record->timecollected),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data([], $contextdata);

            // Export associated files.
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Export system-level avatar data for a user.
     *
     * @param \stdClass $user The user to export data
     */
    protected static function export_avatar_data_for_system_context(\stdClass $user) {
        global $DB;

        $userid = $user->id;
        $systemcontext = \context_system::instance();

        // Get all user's system-level avatar data (where cmid is 0 or NULL).
        $sql = "SELECT au.*, a.name as avatarname
                FROM {avatar_user} au
                JOIN {avatar} a ON a.id = au.avatarid
                WHERE au.userid = :userid
                AND (au.cmid = 0 OR au.cmid IS NULL)";

        $params = ['userid' => $userid];

        $avatardata = $DB->get_records_sql($sql, $params);

        if (!empty($avatardata)) {
            $avatars = [];

            foreach ($avatardata as $record) {
                $avatars[] = [
                    'name' => $record->avatarname,
                    'variant' => $record->variant,
                    'isprimary' => $record->isprimary ? true : false,
                    'timecollected' => \core_privacy\local\request\transform::datetime($record->timecollected),
                    'timemodified' => \core_privacy\local\request\transform::datetime($record->timemodified),
                ];
            }

            $systemdata = [
                'avatars' => $avatars,
            ];

            writer::with_context($systemcontext)->export_data(['mod_avatar'], $systemdata);

            // Export any system-level files.
            $fs = get_file_storage();
            $systemfiles = $fs->get_area_files(
                $systemcontext->id,
                'mod_avatar',
                'user_avatar',
                $userid
            );

            if (!empty($systemfiles)) {
                writer::with_context($systemcontext)
                    ->export_area_files(['mod_avatar'], 'user_avatar', $userid);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context instanceof \context_system) {
            $DB->delete_records('avatar_user', ['cmid' => 0]);

            // Delete system-level files.
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_avatar', 'user_avatar');

            return;
        }

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id('avatar', $context->instanceid);
        if (!$cm) {
            return;
        }

        $DB->delete_records('avatar_user', ['cmid' => $cm->id]);

        // Delete module-level files.
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'mod_avatar');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_module) {
                $DB->delete_records('avatar_user', ['cmid' => $context->instanceid, 'userid' => $userid]);

                // Delete user files in this context.
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_avatar', 'user_avatar', $userid);
            } else if ($context instanceof \context_system) {
                $DB->delete_records('avatar_user', [
                    'userid' => $userid,
                    'cmid' => 0,
                ]);

                // Delete system-level user files.
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_avatar', 'user_avatar', $userid);
            }
        }
    }
}
