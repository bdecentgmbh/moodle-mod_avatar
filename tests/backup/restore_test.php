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

namespace mod_avatar\backup;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Tests of the backup and restore of avatar activities.
 *
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\backup_avatar_activity_structure_step::class)]
#[CoversClass(\restore_avatar_activity_structure_step::class)]
final class restore_test extends \advanced_testcase {
    /**
     * Backs up an activity and extracts it for a restore.
     *
     * @param int $cmid The activity.
     * @param int $userid The user who makes the backup.
     * @return string The backup id (folder in the backup temp directory).
     */
    protected function backup(int $cmid, int $userid): string {
        $bc = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid
        );
        $bc->execute_plan();
        $file = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $backupid = 'avatar_test_' . random_string(10);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), make_backup_temp_directory($backupid));
        $file->delete();
        return $backupid;
    }

    /**
     * Restores a backup into a course.
     *
     * @param string $backupid The backup id.
     * @param int $courseid The course.
     * @param int $userid The user who restores.
     */
    protected function restore(string $backupid, int $courseid, int $userid): void {
        $rc = new \restore_controller(
            $backupid,
            $courseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();
    }

    /**
     * On the same site, the avatars of the backup are mapped to the existing ones, which are not changed.
     */
    public function test_restore_maps_existing_avatars(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $instance = $generator->create_instance(['course' => $course->id, 'name' => 'Dragons',
            'avatarselection' => \mod_avatar\avatar::SELECTION_SPECIFIC, 'specifictags' => 'dragons']);
        $dragon = $generator->create_avatar(['name' => 'Ignar', 'tags' => ['dragons']]);
        $generator->create_avatar(['name' => 'Lulu', 'tags' => ['trolls']]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $generator->create_user_avatar(['userid' => $student->id, 'avatarid' => $dragon->id, 'cmid' => $instance->cmid]);

        $backupid = $this->backup($instance->cmid, get_admin()->id);
        $xml = file_get_contents(make_backup_temp_directory($backupid) . '/activities/avatar_' . $instance->cmid . '/avatar.xml');
        $this->assertStringContainsString('<name>Ignar</name>', $xml);
        $this->assertStringNotContainsString('<name>Lulu</name>', $xml, 'Only the avatars of the activity are backed up.');

        $DB->set_field('avatar_list', 'description', 'Changed here', ['id' => $dragon->id]);
        $target = $this->getDataGenerator()->create_course();
        $this->restore($backupid, $target->id, get_admin()->id);

        $this->assertEquals(2, $DB->count_records('avatar_list'));
        $this->assertEquals('Changed here', $DB->get_field('avatar_list', 'description', ['id' => $dragon->id]));
        $this->assertEquals(1, $DB->count_records('avatar', ['course' => $target->id]));
    }

    /**
     * Missing avatars are recreated (with images and tags) only when the restoring user may create avatars.
     */
    public function test_restore_creates_missing_avatars_only_with_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $instance = $generator->create_instance(['course' => $course->id]);
        $avatar = $generator->create_avatar(['name' => 'Ignar', 'idnumber' => 'ignar', 'variants' => 2, 'tags' => ['dragons']]);
        $backupid = $this->backup($instance->cmid, get_admin()->id);

        // Another site: the avatar does not exist.
        (new \mod_avatar\avatar($avatar->id))->delete();

        // A teacher may restore the activity, but not add avatars to the library.
        $target = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($target, 'editingteacher');
        $teacherbackup = 'avatar_test_' . random_string(10);
        check_dir_exists(make_backup_temp_directory($teacherbackup));
        $this->copy_dir(make_backup_temp_directory($backupid), make_backup_temp_directory($teacherbackup));
        $this->setUser($teacher);
        $this->restore($teacherbackup, $target->id, $teacher->id);
        $this->assertFalse($DB->record_exists('avatar_list', ['idnumber' => 'ignar']));

        // An administrator restores it with the avatar.
        $this->setAdminUser();
        $this->restore($backupid, $target->id, get_admin()->id);
        $restored = $DB->get_record('avatar_list', ['idnumber' => 'ignar'], '*', MUST_EXIST);
        $this->assertEquals('Ignar', $restored->name);
        $this->assertEquals(2, $restored->variants);
        $this->assertEquals(['dragons'], array_values(\core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $restored->id)));
        $fs = get_file_storage();
        $context = \core\context\system::instance();
        $this->assertFalse($fs->is_area_empty($context->id, 'mod_avatar', 'previewimage', $restored->id));
        $this->assertFalse($fs->is_area_empty($context->id, 'mod_avatar', 'avatarimage2', $restored->id));
    }

    /**
     * Copies a directory recursively.
     *
     * @param string $from Source.
     * @param string $to Target (exists).
     */
    protected function copy_dir(string $from, string $to): void {
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            ) as $item
        ) {
            $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
            $item->isDir() ? check_dir_exists($target) : copy($item->getPathname(), $target);
        }
    }
}
