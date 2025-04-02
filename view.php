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
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course module ID.

if ($id) {
    $cm = get_coursemodule_from_id('avatar', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $avatarinstance = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new moodle_exception('emptycoursemodule', 'mod_avatar');
}

require_login($course, true, $cm);

// Capability checking.
$modulecontext = context_module::instance($cm->id);
require_capability('mod/avatar:view', $modulecontext);

// Setup the page content.
$PAGE->set_url('/mod/avatar/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($avatarinstance->name));
$PAGE->set_context($modulecontext);

// Limit the width of the avatar page.
$PAGE->add_body_class('limitedwidth');

// Trigger the avatar viewed event and completion view.
avatar_view($avatarinstance, $course, $cm, $modulecontext);

// Output starts here.
echo $OUTPUT->header();

// Display the avatar activity content.
$renderer = $PAGE->get_renderer('mod_avatar');
echo $renderer->display_avatar_activity($avatarinstance, $cm, $course);

$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);

// Footer of the page.
echo $OUTPUT->footer();
