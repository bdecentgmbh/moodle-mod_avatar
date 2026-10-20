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
 * Table to display the list of avatar usage records.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\table;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/tablelib.php');

use core\output\html_writer;
use core_table\dynamic as dynamic_table;
use mod_avatar\local\usage;

/**
 * Where an avatar of the library is used: activities and courses that offer it, or users who collected it.
 *
 * Shown in a modal on the Manage avatars page (fragment avatar_usage); only for mod/avatar:overview.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class avatar_usage_table extends \table_sql implements dynamic_table {
    /** @var int The avatar. */
    protected $avatarid = 0;

    /** @var string The usage: activities, courses or users. */
    protected $type = '';

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique id of the table.
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        $this->define_columns(['name', 'view']);
        $this->define_headers([get_string('name'), get_string('view')]);
        $this->collapsible(false);
        $this->pageable(true);
        $this->is_downloadable(false);
        $this->no_sorting('view');
    }

    /**
     * Base URL of the table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new \moodle_url('/mod/avatar/manage.php');
    }

    /**
     * Context of the table.
     *
     * @return \core\context
     */
    public function get_context(): \core\context {
        return \core\context\system::instance();
    }

    /**
     * Only users who may see the avatar overview see where avatars are used.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('mod/avatar:overview', $this->get_context());
    }

    /**
     * Query the records.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        if ($this->filterset->has_filter('avatarid')) {
            $this->avatarid = (int) ($this->filterset->get_filter('avatarid')->get_filter_values()[0] ?? 0);
        }
        if ($this->filterset->has_filter('type')) {
            $this->type = (string) ($this->filterset->get_filter('type')->get_filter_values()[0] ?? '');
        }

        if ($this->type === 'users') {
            $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
            $this->set_sql(
                "u.id, $userfields",
                '{avatar_user} au JOIN {user} u ON u.id = au.userid',
                'au.avatarid = :avatarid AND u.deleted = 0',
                ['avatarid' => $this->avatarid]
            );
            $this->sortable(true, 'lastname');
        } else {
            $instances = usage::get_instances_offering($this->avatarid);
            if ($this->type === 'courses') {
                $ids = array_unique(array_column($instances, 'course')) ?: [0];
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $this->set_sql('c.id, c.fullname AS name', '{course} c', "c.id $insql", $params);
            } else {
                $ids = array_column($instances, 'cmid') ?: [0];
                [$insql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
                $this->set_sql(
                    'cm.id, a.name',
                    '{course_modules} cm JOIN {avatar} a ON a.id = cm.instance',
                    "cm.id $insql",
                    $params
                );
            }
        }

        parent::query_db($pagesize, false);
    }

    /**
     * The sort columns: the name of a user is sorted by its name fields.
     *
     * @return array
     */
    public function get_sort_columns() {
        $columns = parent::get_sort_columns();
        if ($this->type === 'users' && isset($columns['name'])) {
            $direction = $columns['name'];
            unset($columns['name']);
            $columns = ['lastname' => $direction, 'firstname' => $direction] + $columns;
        }
        return $columns;
    }

    /**
     * Name of the activity, course or user.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_name($row) {
        if ($this->type === 'users') {
            return s(fullname($row));
        }
        return format_string($row->name, true, ['context' => $this->get_context()]);
    }

    /**
     * Link to the activity, course or user profile.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_view($row) {
        $url = match ($this->type) {
            'users' => new \moodle_url('/user/profile.php', ['id' => $row->id]),
            'courses' => new \moodle_url('/course/view.php', ['id' => $row->id]),
            default => new \moodle_url('/mod/avatar/view.php', ['id' => $row->id]),
        };
        return html_writer::link($url, get_string('view'));
    }
}
