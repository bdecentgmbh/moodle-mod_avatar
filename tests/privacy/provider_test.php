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

namespace mod_avatar\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests of the privacy provider.
 *
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends provider_testcase {
    /** @var \stdClass */
    protected $user1;

    /** @var \stdClass */
    protected $user2;

    /** @var \stdClass */
    protected $avatar;

    /**
     * Two users with collected avatars; user 1 also changed an avatar of the library.
     */
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $instance = $generator->create_instance(['course' => $course->id, 'name' => 'Pick one']);
        $this->user1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->user2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->avatar = $generator->create_avatar(['name' => 'Fiona', 'variants' => 2]);
        $other = $generator->create_avatar(['name' => 'Lucas']);
        $DB->set_field('avatar_list', 'usermodified', $this->user1->id, ['id' => $other->id]);

        $generator->create_user_avatar(['userid' => $this->user1->id, 'avatarid' => $this->avatar->id,
            'cmid' => $instance->cmid, 'variant' => 2]);
        $generator->create_user_avatar(['userid' => $this->user2->id, 'avatarid' => $this->avatar->id]);
    }

    /**
     * The collection is in the user context, the author of avatars in the system context.
     */
    public function test_get_contexts_and_users(): void {
        $contexts = provider::get_contexts_for_userid($this->user1->id)->get_contextids();
        $this->assertEqualsCanonicalizing([\core\context\user::instance($this->user1->id)->id,
            \core\context\system::instance()->id], $contexts);
        $this->assertEquals(
            [\core\context\user::instance($this->user2->id)->id],
            provider::get_contexts_for_userid($this->user2->id)->get_contextids()
        );

        $userlist = new userlist(\core\context\system::instance(), 'mod_avatar');
        provider::get_users_in_context($userlist);
        // The generator creates avatars as admin.
        $this->assertEqualsCanonicalizing([get_admin()->id, $this->user1->id], $userlist->get_userids());

        $userlist = new userlist(\core\context\user::instance($this->user2->id), 'mod_avatar');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->user2->id], $userlist->get_userids());
    }

    /**
     * The export contains the collected avatars with the activity they were collected in.
     */
    public function test_export_user_data(): void {
        $context = \core\context\user::instance($this->user1->id);
        $this->export_context_data_for_user($this->user1->id, $context, 'mod_avatar');
        $data = writer::with_context($context)->get_data([get_string('myavatars', 'mod_avatar')]);
        $this->assertCount(1, $data->avatars);
        $this->assertEquals('Fiona', $data->avatars[0]['name']);
        $this->assertEquals(2, $data->avatars[0]['variant']);
        $this->assertEquals('Pick one', $data->avatars[0]['collectedin']);

        $system = \core\context\system::instance();
        $this->export_context_data_for_user($this->user1->id, $system, 'mod_avatar');
        $data = writer::with_context($system)->get_data([get_string('manageavatars', 'mod_avatar')]);
        $this->assertEquals('Lucas', $data->avatars[0]['name']);
    }

    /**
     * Deleting removes the collection of the user and the author reference, not the avatars.
     */
    public function test_delete(): void {
        global $DB;

        $contexts = provider::get_contexts_for_userid($this->user1->id)->get_contextids();
        provider::delete_data_for_user(new approved_contextlist($this->user1, 'mod_avatar', $contexts));
        $this->assertFalse($DB->record_exists('avatar_user', ['userid' => $this->user1->id]));
        $this->assertTrue($DB->record_exists('avatar_user', ['userid' => $this->user2->id]));
        $this->assertFalse($DB->record_exists('avatar_list', ['usermodified' => $this->user1->id]));
        $this->assertEquals(2, $DB->count_records('avatar_list'));

        $context = \core\context\user::instance($this->user2->id);
        provider::delete_data_for_users(new approved_userlist($context, 'mod_avatar', [$this->user2->id]));
        $this->assertFalse($DB->record_exists('avatar_user', ['userid' => $this->user2->id]));
    }

    /**
     * Deleting all data of a user context only affects that user.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        provider::delete_data_for_all_users_in_context(\core\context\user::instance($this->user2->id));
        $this->assertFalse($DB->record_exists('avatar_user', ['userid' => $this->user2->id]));
        $this->assertTrue($DB->record_exists('avatar_user', ['userid' => $this->user1->id]));
    }
}
