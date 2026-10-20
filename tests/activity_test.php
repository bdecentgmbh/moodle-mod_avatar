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

/**
 * Tests of the avatars an activity offers, and of their usage.
 *
 * @covers     \mod_avatar\activity
 * @covers     \mod_avatar\local\usage
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activity_test extends \advanced_testcase {
    /**
     * An activity offers all active avatars, or those with one of its tags (exact, case insensitive).
     */
    public function test_get_avatars(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $dragon = $generator->create_avatar(['tags' => ['Dragons']]);
        $princess = $generator->create_avatar(['tags' => ['Troll princess']]);
        $troll = $generator->create_avatar(['tags' => ['Troll']]);
        $generator->create_avatar(['status' => 0, 'tags' => ['Troll']]);
        $generator->create_avatar(['archived' => 1, 'tags' => ['Troll']]);

        $all = (object) ['avatarselection' => avatar::SELECTION_ALL, 'specifictags' => 'ignored'];
        $this->assertEquals([$dragon->id, $princess->id, $troll->id], array_keys(activity::get_avatars($all)));

        $trolls = (object) ['avatarselection' => avatar::SELECTION_SPECIFIC, 'specifictags' => ' troll , DRAGONS '];
        $this->assertEquals(['troll', 'dragons'], activity::get_tag_names($trolls));
        $this->assertEquals([$dragon->id, $troll->id], array_keys(activity::get_avatars($trolls)));
        $this->assertTrue(activity::offers_avatar($trolls, $troll->id));
        $this->assertFalse(activity::offers_avatar($trolls, $princess->id));

        $none = (object) ['avatarselection' => avatar::SELECTION_SPECIFIC, 'specifictags' => ' , '];
        $this->assertSame([], activity::get_avatars($none));
    }

    /**
     * The usage counts activities and courses offering an avatar, and users who collected it.
     */
    public function test_usage(): void {
        global $DB;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $dragon = $generator->create_avatar(['tags' => ['dragons']]);
        $troll = $generator->create_avatar(['tags' => ['trolls']]);
        $generator->create_instance(['course' => $course1->id]);
        $generator->create_instance(['course' => $course1->id, 'avatarselection' => avatar::SELECTION_SPECIFIC,
            'specifictags' => 'dragons']);
        $generator->create_instance(['course' => $course2->id, 'avatarselection' => avatar::SELECTION_SPECIFIC,
            'specifictags' => 'trolls']);
        $user = $this->getDataGenerator()->create_user();
        $generator->create_user_avatar(['userid' => $user->id, 'avatarid' => $dragon->id]);

        local\usage::reset_caches();
        $this->assertEquals(['activities' => 2, 'courses' => 1, 'users' => 1], local\usage::get_counts($dragon->id));
        $this->assertEquals(['activities' => 2, 'courses' => 2, 'users' => 0], local\usage::get_counts($troll->id));

        // The counts of all avatars are loaded together and reloaded after collections change.
        $queries = $DB->perf_get_queries();
        local\usage::get_counts($dragon->id);
        local\usage::get_counts($troll->id);
        $this->assertEquals(0, $DB->perf_get_queries() - $queries);
        collection::collect($troll, $user->id);
        $this->assertEquals(1, local\usage::get_counts($troll->id)['users']);
    }
}
