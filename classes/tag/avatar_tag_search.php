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
 * Tag area class for mod_avatar
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\tag;

/**
 * Tag area class for mod_avatar
 */
class avatar_tag_search {
    /**
     * @var \core_tag_tag
     */
    protected $tag;

    /**
     * @var bool
     */
    protected $exclusivemode;

    /**
     * @var int
     */
    protected $fromctx;

    /**
     * @var int
     */
    protected $ctx;

    /**
     * @var bool
     */
    protected $rec;

    /**
     * @var int
     */
    protected $page;

    /**
     * @var int
     */
    protected $perpage;

    /**
     * Constructor
     *
     * @param \core_tag_tag $tag
     * @param bool $exclusivemode
     * @param int $fromctx
     * @param int $ctx
     * @param bool $rec
     * @param int $page
     * @param int $perpage
     */
    public function __construct($tag, $exclusivemode, $fromctx, $ctx, $rec, $page, $perpage) {
        $this->tag = $tag;
        $this->exclusivemode = $exclusivemode;
        $this->fromctx = $fromctx;
        $this->ctx = $ctx;
        $this->rec = $rec;
        $this->page = $page;
        $this->perpage = $perpage;
    }

    /**
     * The SQL (FROM and WHERE) of the active avatars with the tag.
     *
     * @return array [$sql, $params]
     */
    protected function get_sql(): array {
        $sql = "FROM {avatar_list} a
                JOIN {tag_instance} ti ON ti.itemid = a.id
               WHERE ti.tagid = :tagid AND ti.itemtype = :itemtype AND ti.component = :component
                     AND a.status = 1 AND a.archived = 0";
        $params = ['tagid' => $this->tag->id, 'itemtype' => 'avatar', 'component' => 'mod_avatar'];

        if ($this->exclusivemode) {
            $sql .= " AND NOT EXISTS (
                          SELECT 1
                            FROM {tag_instance} ti2
                           WHERE ti2.itemid = a.id AND ti2.tagid <> :tagid2
                                 AND ti2.itemtype = :itemtype2 AND ti2.component = :component2
                      )";
            $params += ['tagid2' => $this->tag->id, 'itemtype2' => 'avatar', 'component2' => 'mod_avatar'];
        }
        return [$sql, $params];
    }

    /**
     * Data for the template mod_avatar/tagindex.
     *
     * @return array
     */
    public function get_content() {
        global $DB;

        [$sql, $params] = $this->get_sql();
        $records = $DB->get_records_sql(
            "SELECT a.id, a.name, a.description, a.descriptionformat $sql ORDER BY a.name, a.id",
            $params,
            $this->page * $this->perpage,
            $this->perpage
        );

        $context = \core\context\system::instance();
        $avatars = [];
        foreach ($records as $record) {
            $description = file_rewrite_pluginfile_urls(
                (string) $record->description,
                'pluginfile.php',
                $context->id,
                'mod_avatar',
                'description',
                $record->id
            );
            $avatars[] = [
                'id' => $record->id,
                'name' => format_string($record->name, true, ['context' => $context]),
                'description' => format_text($description, $record->descriptionformat, ['context' => $context]),
                'viewurl' => (new \moodle_url('/mod/avatar/view_avatar.php', ['avatarid' => $record->id]))->out(false),
            ];
        }

        return [
            'avatars' => $avatars,
            'tagid' => $this->tag->id,
            'exclusivemode' => $this->exclusivemode,
        ];
    }

    /**
     * Total count of items.
     *
     * @return int
     */
    public function get_count() {
        global $DB;
        [$sql, $params] = $this->get_sql();
        return $DB->count_records_sql("SELECT COUNT(1) $sql", $params);
    }
}
