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

use mod_avatar\hook\activity_availability;
use mod_avatar\hook\avatar_availability;

/**
 * Tests of the avatar collections of the users.
 *
 * @covers     \mod_avatar\collection
 * @covers     \mod_avatar\hook\avatar_availability
 * @covers     \mod_avatar\hook\activity_availability
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class collection_test extends \advanced_testcase {
    /** @var \mod_avatar_generator */
    protected $generator;

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
    }

    /**
     * Collecting adds the first variant, makes it primary, triggers the event and completes the activity.
     */
    public function test_collect(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $this->generator->create_instance(['course' => $course->id, 'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completioncollect' => 1]);
        [, $cm] = get_course_and_cm_from_cmid($instance->cmid, 'avatar');
        $first = $this->generator->create_avatar(['variants' => 3]);
        $second = $this->generator->create_avatar();

        $this->setUser($user);
        $sink = $this->redirectEvents();
        $record = collection::collect($first, $user->id, $cm);
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(1, $record->variant);
        $this->assertEquals($cm->id, $record->cmid);
        $this->assertTrue(collection::user_has_avatar($user->id, $first->id));
        $this->assertFalse(collection::user_has_avatar($user->id, $first->id, 2));
        $this->assertEquals($first->id, collection::get_primary_avatar($user->id)->avatarid);
        $collected = array_filter($events, fn($e) => $e instanceof event\avatar_collected);
        $this->assertCount(1, $collected);
        $this->assertEquals($user->id, reset($collected)->relateduserid);

        $completion = new \completion_info($course);
        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_data($cm, false, $user->id)->completionstate);

        // The second avatar becomes the primary one.
        collection::collect($second, $user->id, $cm);
        $this->assertEquals($second->id, collection::get_primary_avatar($user->id)->avatarid);
        $this->assertEquals(1, $DB->count_records('avatar_user', ['userid' => $user->id, 'isprimary' => 1]));

        // Collecting twice is not possible.
        $this->expectException(\moodle_exception::class);
        collection::collect($first, $user->id, $cm);
    }

    /**
     * Collecting for another user (assignment) triggers the assigned event, also outside of activities.
     */
    public function test_collect_for_other_user(): void {
        $user = $this->getDataGenerator()->create_user();
        $avatar = $this->generator->create_avatar();

        $this->setAdminUser();
        $sink = $this->redirectEvents();
        $record = collection::collect($avatar, $user->id);
        $events = $sink->get_events();
        $sink->close();

        $this->assertEquals(0, $record->cmid);
        $this->assertInstanceOf(event\avatar_assigned::class, reset($events));
        $this->assertEquals(\core\context\system::instance()->id, reset($events)->contextid);
    }

    /**
     * Upgrading goes through the variants and triggers the completed event at the last one.
     */
    public function test_upgrade(): void {
        $user = $this->getDataGenerator()->create_user();
        $avatar = $this->generator->create_avatar(['variants' => 2]);
        $this->setUser($user);

        try {
            collection::upgrade($avatar, $user->id);
            $this->fail('Upgrading an avatar that is not collected must fail.');
        } catch (\moodle_exception $e) {
            $this->assertEquals('avatarnotcollected', $e->errorcode);
        }

        collection::collect($avatar, $user->id);
        $sink = $this->redirectEvents();
        $record = collection::upgrade($avatar, $user->id);
        $events = array_map(fn($e) => get_class($e), $sink->get_events());
        $sink->close();

        $this->assertEquals(2, $record->variant);
        $this->assertContains(event\avatar_upgraded::class, $events);
        $this->assertContains(event\avatar_completed::class, $events);
        $this->assertTrue(collection::user_has_avatar($user->id, $avatar->id, 2));

        $this->expectExceptionMessage(get_string('alreadyfullyupgraded', 'mod_avatar'));
        collection::upgrade($avatar, $user->id);
    }

    /**
     * Upgrading in another activity keeps the avatar linked to the activity it was collected in.
     */
    public function test_upgrade_keeps_activity(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $first = $this->generator->create_instance(['course' => $course->id]);
        $second = $this->generator->create_instance(['course' => $course->id]);
        [, $cm1] = get_course_and_cm_from_cmid($first->cmid, 'avatar');
        [, $cm2] = get_course_and_cm_from_cmid($second->cmid, 'avatar');
        $avatar = $this->generator->create_avatar(['variants' => 2]);

        collection::collect($avatar, $user->id, $cm1);
        $record = collection::upgrade($avatar, $user->id, $cm2);
        $this->assertEquals($cm1->id, $record->cmid);
        $this->assertEquals($cm1->id, collection::get_user_avatar($user->id, $avatar->id)->cmid);
    }

    /**
     * Only own avatars can become primary.
     */
    public function test_set_primary(): void {
        $user = $this->getDataGenerator()->create_user();
        $first = $this->generator->create_avatar();
        $second = $this->generator->create_avatar();
        $third = $this->generator->create_avatar();
        collection::collect($first, $user->id);
        collection::collect($second, $user->id);

        collection::set_primary($user->id, $first->id);
        $this->assertEquals($first->id, collection::get_primary_avatar($user->id)->avatarid);

        $this->expectException(\moodle_exception::class);
        collection::set_primary($user->id, $third->id);
    }

    /**
     * Disabled and archived avatars are not available; plugins can deny avatars through the hook.
     */
    public function test_is_avatar_available(): void {
        $user = $this->getDataGenerator()->create_user();
        $active = $this->generator->create_avatar();
        $disabled = $this->generator->create_avatar(['status' => 0]);
        $archived = $this->generator->create_avatar(['archived' => 1]);

        $this->assertTrue(collection::is_avatar_available($active, $user->id));
        $this->assertFalse(collection::is_avatar_available($disabled, $user->id));
        $this->assertFalse(collection::is_avatar_available($archived, $user->id));

        $this->redirectHook(avatar_availability::class, function (avatar_availability $hook) use ($active) {
            if ($hook->avatar->id == $active->id) {
                $hook->deny('Not for you');
            }
        });
        $this->assertFalse(collection::is_avatar_available($active, $user->id));
    }

    /**
     * Plugins can restrict an activity and add messages.
     */
    public function test_get_activity_availability(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->generator->create_instance(['course' => $course->id]);
        [, $cm] = get_course_and_cm_from_cmid($instance->cmid, 'avatar');
        $record = $DB->get_record('avatar', ['id' => $instance->id]);

        $hook = collection::get_activity_availability($cm, $record, $user->id);
        $this->assertTrue($hook->is_available());
        $this->assertSame([], $hook->get_messages());

        $this->redirectHook(activity_availability::class, function (activity_availability $hook) {
            $hook->add_message('Two more');
            $hook->deny('Limit reached');
        });
        $hook = collection::get_activity_availability($cm, $record, $user->id);
        $this->assertFalse($hook->is_available());
        $this->assertSame(['Two more', 'Limit reached'], $hook->get_messages());
    }
}
