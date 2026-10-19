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

namespace mod_avatar;

/**
 * The avatars an avatar activity offers.
 *
 * An activity offers all active avatars of the library, or only those with one of its specific tags.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity {
    /**
     * The tag names an activity is limited to.
     *
     * @param \stdClass $instance The activity instance (record of avatar).
     * @return string[] Normalised (lower case) tag names; empty when the activity offers all avatars.
     */
    public static function get_tag_names(\stdClass $instance): array {
        if ((int) $instance->avatarselection !== avatar::SELECTION_SPECIFIC) {
            return [];
        }
        $names = array_map(fn($name) => \core_text::strtolower(trim($name)), explode(',', (string) $instance->specifictags));
        return array_values(array_unique(array_filter($names, fn($name) => $name !== '')));
    }

    /**
     * SQL that selects the avatars (records of avatar_list, alias al) an activity offers.
     *
     * @param \stdClass $instance The activity instance (record of avatar).
     * @return array [$sql, $params]
     */
    public static function get_avatars_sql(\stdClass $instance): array {
        global $DB;

        $sql = "SELECT al.*
                  FROM {avatar_list} al
                 WHERE al.archived = 0 AND al.status = 1";
        $params = [];

        if ((int) $instance->avatarselection === avatar::SELECTION_SPECIFIC) {
            $tags = self::get_tag_names($instance);
            if (!$tags) {
                // Limited to tags, but none given: the activity offers nothing.
                return [$sql . ' AND 1 = 0', $params];
            }
            [$insql, $params] = $DB->get_in_or_equal($tags, SQL_PARAMS_NAMED, 'tag');
            $sql .= " AND al.id IN (
                          SELECT ti.itemid
                            FROM {tag_instance} ti
                            JOIN {tag} t ON t.id = ti.tagid
                           WHERE ti.component = :component AND ti.itemtype = :itemtype AND t.name $insql
                      )";
            $params += ['component' => 'mod_avatar', 'itemtype' => 'avatar'];
        }

        return [$sql . ' ORDER BY al.timecreated, al.id', $params];
    }

    /**
     * The avatars an activity offers.
     *
     * @param \stdClass $instance The activity instance (record of avatar).
     * @return \stdClass[] Records of avatar_list, by id, in display order.
     */
    public static function get_avatars(\stdClass $instance): array {
        global $DB;
        [$sql, $params] = self::get_avatars_sql($instance);
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Whether an activity offers an avatar.
     *
     * @param \stdClass $instance The activity instance (record of avatar).
     * @param int $avatarid The avatar.
     * @return bool
     */
    public static function offers_avatar(\stdClass $instance, int $avatarid): bool {
        return array_key_exists($avatarid, self::get_avatars($instance));
    }
}
