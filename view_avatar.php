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

$cmid = optional_param('cmid', 0, PARAM_INT);
$avatarid = required_param('avatarid', PARAM_INT);

$record = $DB->get_record('avatar_list', ['id' => $avatarid], '*', MUST_EXIST);
$useravatar = \mod_avatar\collection::get_user_avatar($USER->id, $avatarid);

if ($cmid) {
    // The avatar within an activity: only the avatars the activity offers.
    [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'avatar');
    require_login($course, true, $cm);
    $context = \core\context\module::instance($cm->id);
    require_capability('mod/avatar:view', $context);
    $instance = $DB->get_record('avatar', ['id' => $cm->instance], '*', MUST_EXIST);
    if (!\mod_avatar\activity::offers_avatar($instance, $avatarid) && !$useravatar) {
        throw new moodle_exception('avatarnotavailable', 'mod_avatar');
    }
    $PAGE->set_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatarid, 'cmid' => $cmid]);
} else {
    // The avatar outside of activities (My avatars, tag page): active avatars, or avatars the user owns.
    require_login(null, false);
    $context = \core\context\system::instance();
    require_capability('mod/avatar:view', $context);
    $active = $record->status && !$record->archived;
    if (!$active && !$useravatar && !has_capability('mod/avatar:overview', $context)) {
        throw new moodle_exception('avatarnotavailable', 'mod_avatar');
    }
    $PAGE->set_context($context);
    $PAGE->set_url('/mod/avatar/view_avatar.php', ['avatarid' => $avatarid]);
    $PAGE->set_pagelayout('standard');
}

$name = format_string($record->name, true, ['context' => \core\context\system::instance()]);
$PAGE->set_title($name);
$PAGE->set_heading($cmid ? format_string($course->fullname, true, ['context' => $context]) : $name);
$PAGE->add_body_class('limitedwidth');

\mod_avatar\event\avatar_viewed::create([
    'objectid' => $record->id,
    'context' => $context,
])->trigger();

echo $OUTPUT->header();
echo $OUTPUT->heading($name);

$avatar = new \mod_avatar\avatar($avatarid, $record);
echo $OUTPUT->render(new \mod_avatar\output\avatar_info($avatar, $useravatar, $cmid));

$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);

echo $OUTPUT->footer();
