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

use core\plugininfo\format;
use mod_avatar\util;
use html_writer;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

use core_table\dynamic as dynamic_table;
use mod_avatar\avatar;

require_once($CFG->libdir.'/tablelib.php');

/**
 * Avatar usage table.
 */
class avatar_usage_table extends \table_sql implements dynamic_table {

    /**
     * @var int Course module ID
     */
    protected $cmid;

    /**
     * @var int User ID
     */
    protected $avatarid;

    /**
     * Type of the usage.
     *
     * @var string User ID
     */
    protected $type;

    /**
     * Course module instance.
     *
     * @var cm_info
     */
    protected $cmavatar;

    /**
     * User avatar.
     *
     * @var array
     */
    protected $useravatar;

    /**
     * Constructor
     *
     * @param string $uniqueid Unique id of table
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);

        // Define columns.
        $columns = [
            'name' => get_string('name'),
            'view' => get_string('view'),
        ];
        $this->define_columns(array_keys($columns));
        $this->define_headers(array_values($columns));

        $this->collapsible(false);

        $this->pageable(true);
        $this->is_downloadable(false);

        $this->no_sorting('view');

    }

    /**
     * Base url for the table.
     *
     * @return void
     */
    public function guess_base_url(): void {
        $this->baseurl = new \moodle_url('/mod/avatar/report.php', ['cmid' => $this->cmid, 'userid' => $this->userid]);
    }

    /**
     * Context of thet table.
     *
     * @return \core\context
     */
    public function get_context(): \core\context {
        return \context_system::instance();
    }

    /**
     * Is user has capability.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return true;
    }

    /**
     * SEtup the table records.
     *
     * @param int $pagesize
     * @param bool $useinitialsbar
     * @return void
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        if ($this->filterset->has_filter('avatarid')) {
            $values = $this->filterset->get_filter('avatarid')->get_filter_values();
            $avatarid = isset($values[0]) ? current($values) : '';
            $this->avatarid = $avatarid;
        }

        if ($this->filterset->has_filter('type')) {
            $values = $this->filterset->get_filter('type')->get_filter_values();
            $type = isset($values[0]) ? current($values) : '';
            $this->type = $type;
        }

        if ($this->type == 'users') {

            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $this->set_sql("u.*, au.avatarid, $fullname as name", '{avatar_user} au JOIN {user} u ON u.id = au.userid',
                'au.avatarid = :avatarid', ['avatarid' => $this->avatarid]);

        } else if ($this->type == 'activities') {

            $tags = \core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $this->avatarid);
            $inparams = [];

            if (!empty($tags)) {

                foreach ($tags as $k => $tag) {
                    $like[] = $DB->sql_like('sav.specifictags', ':tag' . $k);
                    $inparams['tag' . $k] = '%' . $tag . '%';
                }

                $where = 'AND av.id IN (
                    SELECT sav.id FROM {avatar} sav
                    WHERE (' . implode(' OR ', $like) . ')
                )';

            }

            $this->set_sql('cm.*, av.id as cmavatarid, av.name as name', '{course_modules} cm
                JOIN {modules} md ON md.name=:modname and md.id = cm.module
                JOIN {avatar} av ON av.id = cm.instance', "av.avatarselection = :avatarselection
                    OR (av.avatarselection = 1 $where)",
                ['modname' => 'avatar', 'avatarselection' => 0] + $inparams);

        } else if ($this->type == 'courses') {

            $tags = \core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $this->avatarid);
            $inparams = [];

            if (!empty($tags)) {

                foreach ($tags as $k => $tag) {
                    $like[] = $DB->sql_like('sav.specifictags', ':tag' . $k);
                    $inparams['tag' . $k] = '%' . $tag . '%';
                }

                $where = 'AND av.id IN (
                    SELECT sav.id FROM {avatar} sav
                    WHERE (' . implode(' OR ', $like) . ')
                )';
            }

            $this->set_sql('c.*, c.fullname as name', '{course} c',
                "c.id IN (
                    SELECT cm.course FROM {course_modules} cm
                    JOIN {modules} md ON md.name=:modname and md.id = cm.module
                    JOIN {avatar} av ON av.id = cm.instance
                    WHERE av.avatarselection = :avatarselection OR (av.avatarselection = 1 $where)
                )",
                ['modname' => 'avatar', 'avatarselection' => 0] + $inparams
            );
        }

        parent::query_db($pagesize, false);

    }

    /**
     * Format preview column.
     *
     * @param object $row Table row
     * @return string HTML
     */
    public function col_name($row) {
        return format_string($row->name);
    }

    /**
     * Format description column.
     *
     * @param object $row Table row.
     * @return string HTML
     */
    public function col_view($row) {

        if ($this->type == 'users') {
            $url = new \moodle_url('/user/profile.php', ['id' => $row->id]);
        } else if ($this->type == 'activities') {
            $url = new \moodle_url('/mod/avatar/view.php', ['id' => $row->id]);
        } else if ($this->type == 'courses') {
            $url = new \moodle_url('/course/view.php', ['id' => $row->id]);
        }

        return \html_writer::link($url, get_string('view'));
    }

}
