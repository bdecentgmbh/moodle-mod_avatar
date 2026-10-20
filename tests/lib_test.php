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

use mod_avatar\hook\after_instance_saved;
use mod_avatar\hook\before_instance_deleted;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/avatar/lib.php');

/**
 * Tests of the library functions of the avatar activity.
 *
 * @covers     ::avatar_add_instance
 * @covers     ::avatar_update_instance
 * @covers     ::avatar_delete_instance
 * @covers     ::mod_avatar_output_fragment_avatar_usage
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lib_test extends \advanced_testcase {
    /**
     * Saving and deleting an activity dispatches the hooks; collected avatars stay with the users.
     */
    public function test_instance_lifecycle(): void {
        global $DB;
        $this->resetAfterTest();

        $saved = [];
        $deleted = [];
        $this->redirectHook(after_instance_saved::class, function (after_instance_saved $hook) use (&$saved) {
            $saved[] = $hook->created;
        });
        $this->redirectHook(before_instance_deleted::class, function (before_instance_deleted $hook) use (&$deleted) {
            $deleted[] = $hook->instance->id;
        });

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $instance = $generator->create_instance(['course' => $course->id, 'avatarselection' => avatar::SELECTION_SPECIFIC,
            'specifictags' => '  dragons ']);
        $this->assertSame([true], $saved);
        $this->assertEquals('dragons', $DB->get_field('avatar', 'specifictags', ['id' => $instance->id]));

        $cm = get_coursemodule_from_id('avatar', $instance->cmid);
        $update = (object) ['instance' => $instance->id, 'coursemodule' => $cm->id, 'name' => 'Renamed',
            'avatarselection' => avatar::SELECTION_ALL, 'specifictags' => 'dragons', 'intro' => '', 'introformat' => FORMAT_HTML];
        avatar_update_instance($update);
        $this->assertSame([true, false], $saved);
        $record = $DB->get_record('avatar', ['id' => $instance->id]);
        $this->assertEquals('Renamed', $record->name);
        $this->assertSame('', $record->specifictags);

        $avatar = $generator->create_avatar();
        $user = $this->getDataGenerator()->create_user();
        $generator->create_user_avatar(['userid' => $user->id, 'avatarid' => $avatar->id, 'cmid' => $cm->id]);

        if (method_exists(\core_courseformat\local\cmactions::class, 'delete')) {
            // Moodle 5.2 and later.
            \core_courseformat\formatactions::cm($course->id)->delete($cm->id);
        } else {
            course_delete_module($cm->id);
        }
        $this->assertSame([$instance->id], $deleted);
        $this->assertFalse($DB->record_exists('avatar', ['id' => $instance->id]));
        $this->assertEquals(0, $DB->get_field('avatar_user', 'cmid', ['userid' => $user->id, 'avatarid' => $avatar->id]));
    }

    /**
     * Where avatars are used is only shown to users with mod/avatar:overview.
     */
    public function test_fragment_avatar_usage_requires_capability(): void {
        $this->resetAfterTest();
        $avatar = $this->getDataGenerator()->get_plugin_generator('mod_avatar')->create_avatar();
        $args = ['avatarid' => $avatar->id, 'type' => 'users', 'context' => \core\context\system::instance()];

        $this->setAdminUser();
        $this->assertStringContainsString('avatar_usage_users', mod_avatar_output_fragment_avatar_usage($args));

        $this->setUser($this->getDataGenerator()->create_user());
        $table = new table\avatar_usage_table('avatar_usage_users');
        $this->assertFalse($table->has_capability());
        $this->expectException(\required_capability_exception::class);
        mod_avatar_output_fragment_avatar_usage($args);
    }
}
