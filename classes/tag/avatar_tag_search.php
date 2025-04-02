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
     * Get content for the tag index page.
     *
     * @return array
     */
    public function get_content() {
        global $DB;

        $tagid = $this->tag->id;
        $query = "SELECT a.*, ti.tagid
                   FROM {avatar_list} a
                   JOIN {tag_instance} ti ON ti.itemid = a.id
                  WHERE ti.tagid = :tagid
                    AND ti.itemtype = :itemtype
                    AND ti.component = :component";

        if ($this->exclusivemode) {
            $query .= " AND NOT EXISTS (
                         SELECT 1
                           FROM {tag_instance}
                          WHERE itemid = a.id
                            AND tagid != :tagid2
                            AND itemtype = :itemtype2
                            AND component = :component2
                       )";
        }

        $params = [
            'tagid' => $tagid,
            'itemtype' => 'avatar',
            'component' => 'mod_avatar',
        ];

        if ($this->exclusivemode) {
            $params['tagid2'] = $tagid;
            $params['itemtype2'] = 'avatar';
            $params['component2'] = 'mod_avatar';
        }

        $avatars = $DB->get_records_sql($query, $params, $this->page * $this->perpage, $this->perpage);

        $content = [
            'avatars' => array_values($avatars),
            'tagid' => $tagid,
            'exclusivemode' => $this->exclusivemode,
        ];

        return $content;
    }

    /**
     * Get total count of items.
     *
     * @return int
     */
    public function get_count() {
        global $DB;

        $tagid = $this->tag->id;
        $query = "SELECT COUNT(*)
                   FROM {avatar_list} a
                   JOIN {tag_instance} ti ON ti.itemid = a.id
                  WHERE ti.tagid = :tagid
                    AND ti.itemtype = :itemtype
                    AND ti.component = :component";

        if ($this->exclusivemode) {
            $query .= " AND NOT EXISTS (
                            SELECT 1
                            FROM {tag_instance}
                            WHERE itemid = a.id AND tagid != :tagid2 AND itemtype = :itemtype2 AND component = :component2
                        )";
        }

        $params = [
            'tagid' => $tagid,
            'itemtype' => 'avatar',
            'component' => 'mod_avatar',
        ];

        if ($this->exclusivemode) {
            $params['tagid2'] = $tagid;
            $params['itemtype2'] = 'avatar';
            $params['component2'] = 'mod_avatar';
        }

        return $DB->count_records_sql($query, $params);
    }
}
