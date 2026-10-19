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
 * Active avatars table class
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\table;

use core\output\html_writer;

/**
 * The active (not archived) avatars of the library, on the Manage avatars page.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class active_avatars_table extends avatars_table {
    /**
     * The tab of the table.
     *
     * @return string
     */
    protected function get_tab(): string {
        return 'active';
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
            'timecreated' => get_string('timecreated'),
            'usermodified' => get_string('createdby', 'mod_avatar'),
            'usage' => get_string('usage', 'mod_avatar'),
            'actions' => get_string('actions'),
        ];
    }

    /**
     * Usage and actions stay after the columns of the addons.
     *
     * @return int
     */
    protected function get_trailing_columns(): int {
        return 2;
    }

    /**
     * Set up the SQL for the table.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $userfields = \core_user\fields::for_name()->get_sql('u', false, 'creator', '', false)->selects;
        $this->set_sql(
            "al.*, $userfields",
            '{avatar_list} al LEFT JOIN {user} u ON u.id = al.usermodified',
            'al.archived = 0'
        );
        $this->no_sorting('usage');
        $this->no_sorting('usermodified');
        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * The time the avatar was created.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated);
    }

    /**
     * The user who last changed the avatar.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_usermodified($row) {
        if (empty($row->usermodified)) {
            return '';
        }
        $user = username_load_fields_from_object(new \stdClass(), $row, 'creator');
        return s(fullname($user));
    }

    /**
     * Where the avatar is used; the counts open the details in a modal.
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_usage($row) {
        $usage = \mod_avatar\local\usage::get_counts($row->id);
        $items = '';
        foreach (
            ['activities' => get_string('activities'), 'courses' => get_string('courses'),
                'users' => get_string('users')] as $type => $label
        ) {
            $items .= html_writer::tag('li', html_writer::span(
                $label . ': ' . $usage[$type],
                'usage-item',
                ['data-type' => $type, 'data-avatarid' => $row->id, 'role' => 'button', 'tabindex' => 0]
            ));
        }
        return html_writer::tag('ul', $items, ['class' => 'avatar-usage-list list-unstyled mb-0',
            'data-title' => format_string($row->name, true, ['context' => $this->context])]);
    }

    /**
     * Actions: edit, enable/disable, archive, and those of the addons (e.g. export).
     *
     * @param \stdClass $row The avatar.
     * @return string
     */
    public function col_actions($row) {
        global $OUTPUT;

        $actions = [];
        if (has_capability('mod/avatar:edit', $this->context)) {
            $actions[] = $OUTPUT->action_icon(
                new \moodle_url('/mod/avatar/edit.php', ['id' => $row->id]),
                new \core\output\pix_icon('i/settings', get_string('edit')),
                null,
                ['class' => 'action-edit action-icon']
            );
        }
        if (has_capability('mod/avatar:changestatus', $this->context)) {
            $actions[] = $this->action_icon(
                $row,
                'togglestatus',
                $row->status ? 't/hide' : 't/show',
                get_string($row->status ? 'disable' : 'enable')
            );
        }
        if (has_capability('mod/avatar:archive', $this->context)) {
            $actions[] = $this->action_icon(
                $row,
                'archive',
                'f/archive',
                get_string('archive', 'mod_avatar'),
                get_string('confirmarchive', 'mod_avatar')
            );
        }
        return implode(' ', array_merge($actions, $this->get_addon_actions($row)));
    }
}
