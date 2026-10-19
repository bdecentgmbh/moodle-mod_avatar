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

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use html_writer;
use context_system;
use confirm_action;
use mod_avatar\plugininfo\avataraddon;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Active avatars table class.
 */
class active_avatars_table extends \table_sql {

    use \avataraddon_pro\table\trait_active_avatars_table;

    /**
     * @var context_system system context.
     */
    protected $context;

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        // Define the list of columns to show.
        $columns = ['name', 'idnumber', 'description', 'timecreated', 'usermodified', 'usage', 'actions'];
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $headers = [
            get_string('name'),
            get_string('idnumber'),
            get_string('description'),
            get_string('timecreated'),
            get_string('createdby', 'mod_avatar'),
            get_string('usage', 'mod_avatar'),
            get_string('actions'),
        ];
        $this->define_headers($headers);

        $this->set_attribute('class', 'generaltable avatars');

        $this->no_sorting('actions');
        $this->no_sorting('usage');

        $this->context = context_system::instance();
    }

    /**
     * Set up the SQL for the table.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;
        // Set up the SQL for the table.
        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

        $this->set_sql("al.*, $fullname as usermodified",
            '{avatar_list} al LEFT JOIN {user} u ON u.id = al.usermodified', 'archived = 0');

        parent::query_db($pagesize, $useinitialsbar);
    }

    /**
     * Name of the avatar.
     *
     * @param object $values Records.
     * @return string Return name.
     */
    public function col_name($values) {
        return format_string($values->name);
    }

    /**
     * Description of the avatar.
     *
     * @param object $values
     * @return string Return description.
     */
    public function col_description($values) {

        // Format the description.
        $description = file_rewrite_pluginfile_urls(
            $values->description, 'pluginfile.php', $this->context->id, 'mod_avatar', 'description', $values->id);

        return html_writer::div(format_text($description, $values->descriptionformat), 'avatar-description-table');
    }

    /**
     * The avatar time created in userdate format.
     *
     * @param object $values
     * @return string Return formatted time created.
     */
    public function col_timecreated($values) {
        return userdate($values->timecreated);
    }

    /**
     * Actions column, Options to edit, togglestatus, export, archive.
     *
     * @param object $row
     * @return string Return actions HTML.
     */
    public function col_actions($row) {
        global $OUTPUT, $CFG;

        $actions = [];

        $url = new \moodle_url('/mod/avatar/edit.php', ['id' => $row->id, 'sesskey' => sesskey()]);

        if (has_capability('mod/avatar:edit', $this->context)) {
            $url->param('action', 'edit');
            $editicon = ($CFG->branch >= 405) ? 'i/settings' : 't/edit';
            $actions[] = $OUTPUT->action_icon(
                $url, new \pix_icon($editicon, get_string('edit')), null, ['class' => 'action-edit action-icon']);
        }

        if (has_capability('mod/avatar:changestatus', $this->context)) {
            $url->param('action', 'togglestatus');
            $icon = $row->status ? 't/hide' : 't/show';
            $actions[] = $OUTPUT->action_icon(
                $url, new \pix_icon($icon, get_string('togglestatus', 'mod_avatar')), null,
                ['class' => 'action-status action-icon']);
        }

        if (has_capability('mod/avatar:archive', $this->context)) {
            $url->param('action', 'archive');
            $confirmaction = new confirm_action(get_string('confirmarchive', 'mod_avatar'));
            $actions[] = $OUTPUT->action_icon(
                $url, new \pix_icon('f/archive', get_string('archive', 'mod_avatar')),
                $confirmaction, ['class' => 'action-archive action-icon']);
        }

        // Include pro actions.
        $actions[] = $this->avataraddon_avatar_table_action($row, $this->context, $url);

        return implode(' ', array_filter($actions));
    }

    /**
     * Usage column, which lists the avatar usage in courses, activites, and users.
     *
     * @param object $values
     * @return string
     */
    public function col_usage($values) {

        $avatar = new \mod_avatar\avatar($values->id, $values);
        $usage = $avatar->info->get_avatar_usage();

        $html = html_writer::tag('li', html_writer::span(get_string('activities') . ': ' . $usage['activities'], 'usage-item',
            ['data-type' => 'activities', 'data-avatarid' => $values->id]));

        $html .= html_writer::tag('li', html_writer::span(get_string('courses') . ': ' . $usage['courses'], 'usage-item',
            ['data-type' => 'courses', 'data-avatarid' => $values->id]));

        $html .= html_writer::tag('li', html_writer::span(get_string('users') . ': ' . $usage['users'], 'usage-item',
            ['data-type' => 'users', 'data-avatarid' => $values->id]));

        return html_writer::tag('ul', $html,
            ['data-title' => format_text($values->name, FORMAT_HTML), 'class' => 'avatar-usage-list']);
    }

    /**
     * Override the message if the table contains no entries.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        // Show notification as html element.
        $notification = new \core\output\notification(
                get_string('avatarsnothingtodisplay', 'mod_avatar'),
                    \core\output\notification::NOTIFY_INFO);
        $notification->set_show_closebutton(false);
        echo $OUTPUT->render($notification);
    }
}
