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
 * Provides the restore activity task class
 *
 * @package     mod_avatar
 * @copyright   2025 bdecent GmbH <https://bdecent.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/avatar/backup/moodle2/restore_avatar_stepslib.php');

/**
 * Provides all the settings and steps to perform complete restore of the avatar activity.
 */
class restore_avatar_activity_task extends restore_activity_task {

    /**
     * Define particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No custom settings for this activity.
    }

    /**
     * Define steps for avatar restore.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new restore_avatar_activity_structure_step('avatar_structure', 'avatar.xml'));
    }

    /**
     * Define the decode contents in the avatar.
     *
     * @return array Decoded Contents.
     */
    public static function define_decode_contents() {
        $contents = [];

        $contents[] = new restore_decode_content('avatar', ['intro', 'headercontent', 'footercontent', 'emptystate'], 'avatar');
        $contents[] = new restore_decode_content('avatar_list', ['description', 'secretinfo'], null);

        return $contents;
    }

    /**
     * Define the decoding rules.
     */
    public static function define_decode_rules() {
        $rules = [];

        $rules[] = new restore_decode_rule('AVATARVIEWBYID', '/mod/avatar/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('AVATARINDEX', '/mod/avatar/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('AVATARVIEWBYAVATARID', '/mod/avatar/view_avatar.php?avatarid=$1', 'avatar_item');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the restore_logs_processor when restoring
     * avatar logs. It must return one array
     * of restore_log_rule objects
     */
    public static function define_restore_log_rules() {
        $rules = [];

        $rules[] = new restore_log_rule('avatar', 'add', 'view.php?id={course_module}', '{avatar}');
        $rules[] = new restore_log_rule('avatar', 'update', 'view.php?id={course_module}', '{avatar}');
        $rules[] = new restore_log_rule('avatar', 'view', 'view.php?id={course_module}', '{avatar}');
        $rules[] = new restore_log_rule('avatar', 'collected', 'view.php?id={course_module}', '{avatar}');
        $rules[] = new restore_log_rule('avatar', 'upgraded', 'view.php?id={course_module}', '{avatar}');
        $rules[] = new restore_log_rule('avatar', 'completed', 'view.php?id={course_module}', '{avatar}');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied when restoring
     * course logs. It must return one array
     * of restore_log_rule objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    public static function define_restore_log_rules_for_course() {
        $rules = [];
        $rules[] = new restore_log_rule('avatar', 'view all', 'index.php?id={course}', null);
        return $rules;
    }
}
