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
 * Display information about all the mod_avatar modules in the requested course.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/avatar/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'mod_avatar');
echo $OUTPUT->heading($modulenameplural);

$avatars = get_all_instances_in_course('avatar', $course);

if (empty($avatars)) {
    notice(get_string('noavatars', 'mod_avatar'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

$table->head  = [get_string('name')];
$table->align = ['left', 'left', 'left'];

foreach ($avatars as $avatar) {

    if (!$avatar->visible) {
        $link = html_writer::link(
            new moodle_url('/mod/avatar/view.php', ['id' => $avatar->coursemodule]),
            format_string($avatar->name, true),
            ['class' => 'dimmed']);
    } else {
        $link = html_writer::link(
            new moodle_url('/mod/avatar/view.php', ['id' => $avatar->coursemodule]),
            format_string($avatar->name, true));
    }

    $table->data[] = [$link];

}

echo html_writer::table($table);
echo $OUTPUT->footer();
