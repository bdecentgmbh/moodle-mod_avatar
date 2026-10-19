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
 * Avatar module - view instance.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/avatar/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID.

[$course, $cm] = get_course_and_cm_from_cmid($id, 'avatar');
$avatarinstance = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$modulecontext = \core\context\module::instance($cm->id);
require_capability('mod/avatar:view', $modulecontext);

$PAGE->set_url('/mod/avatar/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($avatarinstance->name, true, ['context' => $modulecontext]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $modulecontext]));
$PAGE->add_body_class('limitedwidth');

avatar_view($avatarinstance, $course, $cm, $modulecontext);

echo $OUTPUT->header();
echo $PAGE->get_renderer('mod_avatar')->display_avatar_activity($avatarinstance, $cm, $course);
$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);
echo $OUTPUT->footer();
