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
 * Displays information about a specific avatar.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(dirname(__FILE__).'/lib.php');

$cmid = optional_param('cmid', 0, PARAM_INT);  // Course module ID.
$avatarid = required_param('avatarid', PARAM_INT); // Avatar ID.

$avatar = $DB->get_record('avatar_list', ['id' => $avatarid], '*', MUST_EXIST);

if ($cmid) {
    $cm = get_coursemodule_from_id('avatar', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $avatarinstance = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    $PAGE->set_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatarid, 'cmid' => $cmid]);
} else {
    // If no course module ID is provided, we're viewing the avatar outside of a course context.
    $cm = null;
    $course = null;
    $avatarinstance = null;
    require_login();
    $context = context_system::instance();
    $PAGE->set_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatarid]);
}

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_title(format_string($avatar->name));
// Limit the width of the avatar page.
$PAGE->add_body_class('limitedwidth');

// Avatar info viewed, trigger the event.
$event = \mod_avatar\event\avatar_viewed::create([
    'objectid' => $avatar->id,
    'context' => $context,
]);
$event->trigger();

// Get the users avatar list.
$useravatar = $DB->get_record('avatar_user', ['userid' => $USER->id, 'avatarid' => $avatarid]);

echo $OUTPUT->header();
// Display avatar name.
echo $OUTPUT->heading(format_string($avatar->name));

// Display avatar information.
$avatar = new \mod_avatar\avatar($avatarid);
$avatarinfo = new \mod_avatar\output\avatar_info($avatar, $useravatar, $cmid);
echo $OUTPUT->render($avatarinfo);

$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);

echo $OUTPUT->footer();
