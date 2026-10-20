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
 * My Avatars page for mod_avatar
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_avatar\util;

require_once('../../config.php');

require_login(null, false);
if (isguestuser()) {
    throw new \moodle_exception('noguest');
}

$context = \core\context\user::instance($USER->id);
$url = new moodle_url('/mod/avatar/myavatars.php');

$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('myavatars', 'mod_avatar'));
$PAGE->set_heading(get_string('myavatars', 'mod_avatar'));
$PAGE->add_body_class('limitedwidth');

// Make an avatar of the own collection the primary avatar.
if (optional_param('setasprimary', 0, PARAM_BOOL) && confirm_sesskey()) {
    $avatarid = required_param('avatarid', PARAM_INT);
    if (\mod_avatar\collection::get_user_avatar($USER->id, $avatarid)) {
        \mod_avatar\collection::set_primary($USER->id, $avatarid);
        redirect($url, get_string('primaryavatarupdated', 'mod_avatar'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
    redirect($url, get_string('avatarnotcollected', 'mod_avatar'), null, \core\output\notification::NOTIFY_ERROR);
}

echo $OUTPUT->header();
echo $PAGE->get_renderer('mod_avatar')->display_my_avatars($USER->id);
$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);
echo $OUTPUT->footer();
