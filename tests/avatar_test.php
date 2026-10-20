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

use mod_avatar\hook\after_avatar_saved;
use mod_avatar\hook\before_avatar_deleted;

/**
 * Tests of the avatars of the library.
 *
 * @covers     \mod_avatar\avatar
 * @covers     \mod_avatar\avatar_manager
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class avatar_test extends \advanced_testcase {
    /**
     * Creating, updating and deleting avatars dispatches the hooks and cleans up files, tags and collections.
     */
    public function test_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $saved = [];
        $deleted = [];
        $this->redirectHook(after_avatar_saved::class, function (after_avatar_saved $hook) use (&$saved) {
            $saved[] = [$hook->data->id, $hook->created];
        });
        $this->redirectHook(before_avatar_deleted::class, function (before_avatar_deleted $hook) use (&$deleted) {
            $deleted[] = $hook->avatar->id;
        });

        $sink = $this->redirectEvents();
        $id = avatar_manager::create_avatar((object) ['name' => 'Fiona', 'idnumber' => 'fiona', 'variants' => 1,
            'tags' => ['explorer']]);
        $avatar = avatar_manager::get_avatar($id);
        $avatar->update((object) ['name' => 'Fiona the brave']);
        $events = array_values(array_filter(
            array_map(fn($e) => get_class($e), $sink->get_events()),
            fn($class) => str_starts_with($class, 'mod_avatar\\')
        ));
        $sink->close();

        $this->assertSame([[$id, true], [$id, false]], $saved);
        $this->assertEquals([event\avatar_created::class, event\avatar_changed::class], $events);
        $this->assertEquals('Fiona the brave', $avatar->name);
        $this->assertEquals(['explorer'], array_values(\core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $id)));

        $fs = get_file_storage();
        $context = \core\context\system::instance();
        $fs->create_file_from_string(['contextid' => $context->id, 'component' => 'mod_avatar', 'filearea' => 'avatarimage1',
            'itemid' => $id, 'filepath' => '/', 'filename' => 'a.png'], 'x');
        $user = $this->getDataGenerator()->create_user();
        collection::collect($avatar->get_data(), $user->id);

        $avatar->archive();
        $this->assertEquals(1, $DB->get_field('avatar_list', 'archived', ['id' => $id]));
        $this->assertFalse(collection::is_avatar_available($avatar->get_data(), $user->id));
        $avatar->restore();
        $this->assertEquals(0, $DB->get_field('avatar_list', 'archived', ['id' => $id]));

        $avatar->delete();
        $this->assertEquals([$id], $deleted);
        $this->assertFalse($DB->record_exists('avatar_list', ['id' => $id]));
        $this->assertFalse($DB->record_exists('avatar_user', ['avatarid' => $id]));
        $this->assertTrue($fs->is_area_empty($context->id, 'mod_avatar', 'avatarimage1', $id));
        $this->assertSame([], \core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $id));
    }

    /**
     * Names are escaped and secrets are only exported for users who collected the avatar.
     */
    public function test_output_escapes_and_hides_secrets(): void {
        global $PAGE;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $instance = $generator->create_instance(['course' => $course->id]);
        $record = $generator->create_avatar(['name' => '<b onmouseover="x">Fiona</b>', 'secretinfo' => 'Hidden treasure']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        [$course, $cm] = get_course_and_cm_from_cmid($instance->cmid, 'avatar');
        $PAGE->set_url('/mod/avatar/view.php', ['id' => $cm->id]);
        $renderer = $PAGE->get_renderer('mod_avatar');
        $html = $renderer->display_avatar_activity($instance, $cm, $course);
        $this->assertStringNotContainsString('<b onmouseover', $html);
        $this->assertStringContainsString('collect-avatar', $html);

        $info = new output\avatar_info(new avatar($record->id, $record), null, $cm->id);
        $data = $info->export_for_template($renderer);
        $this->assertFalse($data['viewsecretinfo']);
        $this->assertSame('', $data['secretinfo']);

        $useravatar = collection::collect($record, $student->id, $cm);
        $info = new output\avatar_info(new avatar($record->id, $record), $useravatar, $cm->id);
        $data = $info->export_for_template($renderer);
        $this->assertTrue($data['viewsecretinfo']);
        $this->assertStringContainsString('Hidden treasure', $data['secretinfo']);
    }
}
