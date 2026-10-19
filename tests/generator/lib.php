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
 * Data generator of mod_avatar: avatar activities, avatars of the library and collected avatars.
 *
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_avatar_generator extends testing_module_generator {
    /** @var int Number of created avatars. */
    protected $avatarcount = 0;

    /**
     * Resets the counters.
     */
    public function reset() {
        $this->avatarcount = 0;
        parent::reset();
    }

    /**
     * Creates an avatar activity.
     *
     * @param array|stdClass|null $record
     * @param array|null $options
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (array) $record + [
            'avatarselection' => \mod_avatar\avatar::SELECTION_ALL,
            'specifictags' => '',
            'displaymode' => \mod_avatar\activity::DISPLAY_PAGE,
            'headercontent' => '',
            'headercontentformat' => FORMAT_HTML,
            'footercontent' => '',
            'footercontentformat' => FORMAT_HTML,
            'emptystate' => '',
            'emptystateformat' => FORMAT_HTML,
            'completioncollect' => 0,
        ];
        return parent::create_instance($record, (array) $options);
    }

    /**
     * Creates an avatar of the library, with a preview image and an image for each variant.
     *
     * @param array|stdClass $record name, idnumber, variants, status, archived, description, secretinfo, tags
     *     (array or comma separated)
     * @return stdClass The record of avatar_list.
     */
    public function create_avatar($record = []): stdClass {
        global $CFG, $DB;

        $this->avatarcount++;
        $record = (array) $record + [
            'name' => 'Avatar ' . $this->avatarcount,
            'idnumber' => 'avatar' . $this->avatarcount,
            'description' => '',
            'descriptionformat' => FORMAT_HTML,
            'internalnotes' => '',
            'secretinfo' => '',
            'secretinfoformat' => FORMAT_HTML,
            'variants' => 1,
            'status' => 1,
            'archived' => 0,
            'tags' => [],
        ];
        $tags = is_array($record['tags']) ? $record['tags'] : array_map('trim', explode(',', $record['tags']));
        unset($record['tags']);
        $record['timecreated'] = $record['timemodified'] = time();
        $record['usermodified'] = get_admin()->id;
        $record['id'] = $DB->insert_record('avatar_list', (object) $record);

        $context = \core\context\system::instance();
        $fs = get_file_storage();
        $image = $CFG->dirroot . '/mod/avatar/tests/fixtures/test_avatar.png';
        $areas = ['previewimage'];
        for ($i = 1; $i <= $record['variants']; $i++) {
            $areas[] = 'avatarimage' . $i;
        }
        foreach ($areas as $area) {
            $fs->create_file_from_pathname(['contextid' => $context->id, 'component' => 'mod_avatar',
                'filearea' => $area, 'itemid' => $record['id'], 'filepath' => '/', 'filename' => 'avatar.png'], $image);
        }

        if ($tags = array_filter($tags)) {
            \core_tag_tag::set_item_tags('mod_avatar', 'avatar', $record['id'], $context, $tags);
        }

        return $DB->get_record('avatar_list', ['id' => $record['id']], '*', MUST_EXIST);
    }

    /**
     * Adds an avatar to the collection of a user (and upgrades it to a variant).
     *
     * @param array|stdClass $record userid, avatarid, variant (default 1), cmid (default none)
     * @return stdClass The record of avatar_user.
     */
    public function create_user_avatar($record): stdClass {
        global $DB;

        $record = (array) $record;
        $avatar = $DB->get_record('avatar_list', ['id' => $record['avatarid']], '*', MUST_EXIST);
        $cm = null;
        if (!empty($record['cmid'])) {
            [, $cm] = get_course_and_cm_from_cmid($record['cmid'], 'avatar');
        }
        $useravatar = \mod_avatar\collection::collect($avatar, $record['userid'], $cm);
        for ($i = 1; $i < ($record['variant'] ?? 1); $i++) {
            $useravatar = \mod_avatar\collection::upgrade($avatar, $record['userid'], $cm);
        }
        return $useravatar;
    }
}
