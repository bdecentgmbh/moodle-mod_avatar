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

use core\output\actions\confirm_action;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

/**
 * Archived avatars table contents.
 */
class archived_avatars_table extends \table_sql {

    /**
     * Context.
     *
     * @var context
     */
    protected $context;

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        // List of columns to show.
        $columns = ['name', 'idnumber', 'description', 'timearchived', 'actions'];
        $this->define_columns($columns);

        // Titles of columns to show in header.
        $headers = [
            get_string('name'),
            get_string('idnumber'),
            get_string('description'),
            get_string('timearchived', 'mod_avatar'),
            get_string('actions'),
        ];
        $this->define_headers($headers);
        $this->set_attribute('class', 'generaltable archived-avatars');
        $this->no_sorting('actions');

        // Set up the SQL for the table.
        $this->context = context_system::instance();
    }

    /**
     * SEtup the sql to fetch the list of archived avatars table.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        // Set up the SQL for the table.
        $this->set_sql('*', '{avatar_list}', 'archived = 1');

        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * Name of the avatar.
     *
     * @param object $values
     * @return string Return name.
     */
    public function col_name($values) {
        return format_string($values->name);
    }

    /**
     * Format the description of avatar.
     *
     * @param object $values Avatar Record.
     * @return string Return description.
     */
    public function col_description($values) {
        $description = file_rewrite_pluginfile_urls(
            $values->description, 'pluginfile.php', $this->context->id, 'mod_avatar', 'description', $values->id);
        return \html_writer::div(format_text($description, $values->descriptionformat), 'avatar-description-table');
    }

    /**
     * Time of the avatar archived in userdate format.
     *
     * @param object $values
     * @return string Return formatted time archived.
     */
    public function col_timearchived($values) {
        return userdate($values->timearchived);
    }

    /**
     * Actions column, options to restore and delete the avatar.
     *
     * @param object $values REcord.
     * @return string Return actions HTML.
     */
    public function col_actions($values) {
        global $OUTPUT;

        $actions = [];

        $url = new \moodle_url('/mod/avatar/edit.php', ['id' => $values->id, 'sesskey' => sesskey()]);

        if (has_capability('mod/avatar:archive', $this->context)) {
            $url->param('action', 'restore');
            $actions[] = $OUTPUT->action_icon(
                $url, new \pix_icon('t/restore', get_string('restore', 'mod_avatar')),
                null, ['class' => 'action-restore action-icon']);
        }

        if (has_capability('mod/avatar:delete', $this->context)) {
            $url->param('action', 'delete');
            $confirmaction = new confirm_action(get_string('confirmdelete', 'mod_avatar'));
            $actions[] = $OUTPUT->action_icon(
                $url, new \pix_icon('t/delete', get_string('delete')), $confirmaction, ['class' => 'action-delete action-icon']);
        }

        return implode(' ', $actions);
    }
}
