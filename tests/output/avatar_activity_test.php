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

namespace mod_avatar\output;

use mod_avatar\avatar;

/**
 * Tests of the gallery of an avatar activity.
 *
 * @covers     \mod_avatar\output\avatar_activity
 * @covers     \mod_avatar\output\avatar_info
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class avatar_activity_test extends \advanced_testcase {
    /**
     * The gallery shows the preview of avatars the user has not collected, and the collected variant of the others.
     */
    public function test_gallery_images(): void {
        global $CFG;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $context = \core\context\system::instance();
        $fs = get_file_storage();

        $withthumbnail = $generator->create_avatar(['variants' => 2]);
        $thumbnail = ['contextid' => $context->id, 'component' => 'mod_avatar', 'filearea' => 'avatarthumbnail2',
            'itemid' => $withthumbnail->id, 'filepath' => '/', 'filename' => 'thumb.png'];
        $fs->create_file_from_pathname($thumbnail, $CFG->dirroot . '/mod/avatar/tests/fixtures/test_avatar.png');
        $plain = $generator->create_avatar();
        $nopreview = $generator->create_avatar();
        $fs->delete_area_files($context->id, 'mod_avatar', 'previewimage', $nopreview->id);

        $images = avatar_info::get_gallery_images(
            [$withthumbnail->id, $plain->id, $nopreview->id],
            [$withthumbnail->id => 2, $plain->id => 1]
        );
        $this->assertStringContainsString("/avatarthumbnail2/{$withthumbnail->id}/thumb.png", $images[$withthumbnail->id]);
        $this->assertStringContainsString("/avatarimage1/{$plain->id}/avatar.png", $images[$plain->id]);
        $this->assertStringContainsString('default_avatar', $images[$nopreview->id]);

        // The same images as those of a single avatar.
        $collected = fn($record, $variant) => new avatar($record->id, $record, (object) ['variant' => $variant]);
        $this->assertEquals($collected($withthumbnail, 2)->info->get_thumbnail_image_url(), $images[$withthumbnail->id]);
        $this->assertEquals($collected($plain, 1)->info->get_thumbnail_image_url(), $images[$plain->id]);
        $this->assertEquals((new avatar($nopreview->id))->info->get_preview_image(), $images[$nopreview->id]);
        $this->assertEquals(
            (new avatar($plain->id))->info->get_preview_image(),
            avatar_info::get_gallery_images([$plain->id], [])[$plain->id]
        );
    }

    /**
     * Rendering the gallery takes as many queries for a few avatars as for many.
     */
    public function test_gallery_queries(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_avatar');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $instance = $generator->create_instance(['course' => $course->id]);
        [, $cm] = get_course_and_cm_from_cmid($instance->cmid, 'avatar');
        $record = $DB->get_record('avatar', ['id' => $instance->id]);
        $generator->create_user_avatar(['userid' => $user->id, 'avatarid' => $generator->create_avatar()->id]);
        $generator->create_avatar();

        $this->setUser($user);
        $output = $PAGE->get_renderer('mod_avatar');
        $render = function () use ($record, $cm, $course, $output, $DB): int {
            $queries = $DB->perf_get_queries();
            (new avatar_activity($record, $cm, $course))->export_for_template($output);
            return $DB->perf_get_queries() - $queries;
        };
        $render();
        $few = $render();

        for ($i = 0; $i < 6; $i++) {
            $avatar = $generator->create_avatar(['variants' => 2]);
            if ($i % 2) {
                $generator->create_user_avatar(['userid' => $user->id, 'avatarid' => $avatar->id, 'variant' => 2]);
            }
        }
        $this->assertCount(8, (new avatar_activity($record, $cm, $course))->export_for_template($output)['avatars']);
        $this->assertLessThanOrEqual($few, $render());
    }
}
