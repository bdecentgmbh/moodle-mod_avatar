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
 * Archived avatars table contents.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\table;

/**
 * The archived avatars of the library, on the Manage avatars page.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archived_avatars_table extends avatars_table {
    /**
     * The tab of the table.
     *
     * @return string
     */
    protected function get_tab(): string {
        return 'archive';
    }

    /**
     * The columns of the table.
     *
     * @return string[]
     */
    protected function get_table_columns(): array {
        return [
            'name' => get_string('name'),
            'idnumber' => get_string('idnumber'),
            'description' => get_string('description'),
            'timearchived' => get_string('timearchived', 'mod_avatar'),
            'actions' => get_string('actions'),
        ];
    }

    /**
     * Set up the SQL for the table.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $this->set_sql('*', '{avatar_list}', 'archived = 1');
        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * The time the avatar was archived.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_timearchived($row) {
        return $row->timearchived ? userdate($row->timearchived) : '';
    }

    /**
     * Actions: restore, delete, and those of the addons.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_actions($row) {
        $actions = [];
        if (has_capability('mod/avatar:archive', $this->context)) {
            $actions[] = $this->action_icon($row, 'restore', 't/restore', get_string('restore', 'mod_avatar'));
        }
        if (has_capability('mod/avatar:delete', $this->context)) {
            $actions[] = $this->action_icon(
                $row,
                'delete',
                't/delete',
                get_string('delete'),
                get_string('confirmdelete', 'mod_avatar')
            );
        }
        return implode(' ', array_merge($actions, $this->get_addon_actions($row)));
    }
}
