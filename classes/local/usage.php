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

namespace mod_avatar\local;

use mod_avatar\activity;
use mod_avatar\avatar;

/**
 * Where avatars of the library are used: in which activities and courses, and by how many users.
 *
 * The avatar activities of the site are loaded once per request.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage {
    /** @var \stdClass[]|null The avatar activities of the site (id, course, avatarselection, specifictags, cmid). */
    private static ?array $instances = null;

    /** @var array|null The tag names of all avatars, by avatar id. */
    private static ?array $tags = null;

    /** @var int[]|null How many users collected each avatar, by avatar id. */
    private static ?array $users = null;

    /**
     * Forgets the loaded activities, tags and counts (for tests and after collections changed).
     */
    public static function reset_caches(): void {
        self::$instances = null;
        self::$tags = null;
        self::$users = null;
    }

    /**
     * The avatar activities of the site.
     *
     * @return \stdClass[] By instance id.
     */
    private static function get_instances(): array {
        global $DB;

        if (self::$instances === null) {
            self::$instances = $DB->get_records_sql(
                "SELECT a.id, a.course, a.avatarselection, a.specifictags, cm.id AS cmid
                   FROM {avatar} a
                   JOIN {modules} m ON m.name = :modname
                   JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = a.id
                  WHERE cm.deletioninprogress = 0",
                ['modname' => 'avatar']
            );
        }
        return self::$instances;
    }

    /**
     * The avatar activities that offer an avatar (all avatars, or one of the avatar's tags).
     *
     * @param int $avatarid The avatar.
     * @return \stdClass[] Instances (id, course, avatarselection, specifictags, cmid) by instance id.
     */
    public static function get_instances_offering(int $avatarid): array {
        global $DB;

        // Tables and reports show the usage of many avatars: load the tags of all avatars at once.
        if (self::$tags === null) {
            self::$tags = [];
            $records = $DB->get_recordset_sql(
                "SELECT ti.id, ti.itemid, t.name
                   FROM {tag_instance} ti
                   JOIN {tag} t ON t.id = ti.tagid
                  WHERE ti.component = :component AND ti.itemtype = :itemtype",
                ['component' => 'mod_avatar', 'itemtype' => 'avatar']
            );
            foreach ($records as $record) {
                self::$tags[$record->itemid][] = $record->name;
            }
            $records->close();
        }
        $tags = self::$tags[$avatarid] ?? [];

        return array_filter(self::get_instances(), function ($instance) use ($tags) {
            if ((int) $instance->avatarselection !== avatar::SELECTION_SPECIFIC) {
                return true;
            }
            return (bool) array_intersect(activity::get_tag_names($instance), $tags);
        });
    }

    /**
     * How many activities and courses offer an avatar and how many users collected it.
     *
     * @param int $avatarid The avatar.
     * @return int[] ['activities' => int, 'courses' => int, 'users' => int]
     */
    public static function get_counts(int $avatarid): array {
        global $DB;

        if (self::$users === null) {
            self::$users = $DB->get_records_sql_menu(
                "SELECT au.avatarid, COUNT(1)
                   FROM {avatar_user} au
                   JOIN {user} u ON u.id = au.userid AND u.deleted = 0
               GROUP BY au.avatarid"
            );
        }

        $instances = self::get_instances_offering($avatarid);
        return [
            'activities' => count($instances),
            'courses' => count(array_unique(array_column($instances, 'course'))),
            'users' => (int) (self::$users[$avatarid] ?? 0),
        ];
    }
}
