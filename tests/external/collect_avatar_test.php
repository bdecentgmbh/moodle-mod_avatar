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

namespace mod_avatar\external;

use core_external\external_api;
use mod_avatar\collection;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Tests of the web services that collect and upgrade avatars.
 *
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(collect_avatar::class)]
#[CoversClass(upgrade_avatar::class)]
final class collect_avatar_test extends \externallib_advanced_testcase {
    /** @var \mod_avatar_generator */
    protected $generator;

    /** @var \stdClass */
    protected $course;

    /** @var \stdClass */
    protected $student;

    /**
     * Set up.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Calls a web service like the AJAX request does.
     *
     * @param string $class The external class.
     * @param int $avatarid The avatar.
     * @param int $cmid The activity.
     * @return array
     */
    protected function call(string $class, int $avatarid, int $cmid): array {
        $result = $class::execute($avatarid, $cmid);
        return external_api::clean_returnvalue($class::execute_returns(), $result);
    }

    /**
     * A student collects an avatar the activity offers, once.
     */
    public function test_collect(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);
        $avatar = $this->generator->create_avatar();

        $this->setUser($this->student);
        $result = $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
        $this->assertTrue($result['success']);
        $this->assertTrue(collection::user_has_avatar($this->student->id, $avatar->id));

        $result = $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('alreadycollected', 'mod_avatar'), $result['message']);
    }

    /**
     * Avatars the activity does not offer (other tags, disabled, archived) cannot be collected.
     */
    public function test_collect_only_offered_avatars(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id,
            'avatarselection' => \mod_avatar\avatar::SELECTION_SPECIFIC, 'specifictags' => 'Dragons']);
        $dragon = $this->generator->create_avatar(['tags' => ['dragons']]);
        $troll = $this->generator->create_avatar(['tags' => ['trolls']]);
        $disabled = $this->generator->create_avatar(['tags' => ['dragons'], 'status' => 0]);
        $archived = $this->generator->create_avatar(['tags' => ['dragons'], 'archived' => 1]);

        $this->setUser($this->student);
        $this->assertTrue($this->call(collect_avatar::class, $dragon->id, $instance->cmid)['success']);
        foreach ([$troll, $disabled, $archived] as $avatar) {
            try {
                $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
                $this->fail('An avatar the activity does not offer was collected: ' . $avatar->name);
            } catch (\moodle_exception $e) {
                $this->assertEquals('avatarnotavailable', $e->errorcode);
            }
        }
        $this->assertFalse(collection::user_has_avatar($this->student->id, $troll->id));
    }

    /**
     * Users who cannot access the activity cannot collect in it.
     */
    public function test_collect_requires_access(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);
        $avatar = $this->generator->create_avatar();

        // Not enrolled.
        $this->setUser($this->getDataGenerator()->create_user());
        try {
            $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
            $this->fail('A user who is not enrolled collected an avatar.');
        } catch (\require_login_exception $e) {
            $this->assertInstanceOf(\require_login_exception::class, $e);
        }

        // Hidden activity.
        set_coursemodule_visible($instance->cmid, 0);
        $this->setUser($this->student);
        try {
            $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
            $this->fail('A student collected an avatar in a hidden activity.');
        } catch (\require_login_exception $e) {
            $this->assertInstanceOf(\require_login_exception::class, $e);
        }
        set_coursemodule_visible($instance->cmid, 1);

        // Without the capability.
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $roleid);
        assign_capability('mod/avatar:collect', CAP_PROHIBIT, $roleid, \core\context\module::instance($instance->cmid));
        $this->expectException(\required_capability_exception::class);
        $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
    }

    /**
     * Plugins can deny collecting through the hooks.
     */
    public function test_collect_denied_by_hook(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);
        $avatar = $this->generator->create_avatar();
        $this->redirectHook(
            \mod_avatar\hook\activity_availability::class,
            fn(\mod_avatar\hook\activity_availability $hook) => $hook->deny('Limit reached')
        );

        $this->setUser($this->student);
        $result = $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
        $this->assertFalse($result['success']);
        $this->assertFalse(collection::user_has_avatar($this->student->id, $avatar->id));
    }

    /**
     * A student upgrades an own avatar to its next variants, up to the last one.
     */
    public function test_upgrade(): void {
        $instance = $this->generator->create_instance(['course' => $this->course->id]);
        $avatar = $this->generator->create_avatar(['variants' => 2]);
        $other = $this->generator->create_avatar(['variants' => 2]);

        $this->setUser($this->student);
        $result = $this->call(upgrade_avatar::class, $avatar->id, $instance->cmid);
        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('avatarnotcollected', 'mod_avatar'), $result['message']);

        $this->call(collect_avatar::class, $avatar->id, $instance->cmid);
        $this->assertTrue($this->call(upgrade_avatar::class, $avatar->id, $instance->cmid)['success']);
        $this->assertTrue(collection::user_has_avatar($this->student->id, $avatar->id, 2));

        $result = $this->call(upgrade_avatar::class, $avatar->id, $instance->cmid);
        $this->assertFalse($result['success']);
        $this->assertEquals(get_string('alreadyfullyupgraded', 'mod_avatar'), $result['message']);

        // Another user's avatars are never touched: there is no user parameter.
        $classmate = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator->create_user_avatar(['userid' => $classmate->id, 'avatarid' => $other->id]);
        $this->call(collect_avatar::class, $other->id, $instance->cmid);
        $this->call(upgrade_avatar::class, $other->id, $instance->cmid);
        $this->assertFalse(collection::user_has_avatar($classmate->id, $other->id, 2));
        $this->assertArrayNotHasKey('userid', upgrade_avatar::execute_parameters()->keys);
        $this->assertArrayNotHasKey('userid', collect_avatar::execute_parameters()->keys);
    }
}
