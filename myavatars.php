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
require_once($CFG->dirroot.'/mod/avatar/lib.php');

require_login();

$userid = optional_param('userid', $USER->id, PARAM_INT);

$PAGE->set_context(context_user::instance($userid));
$PAGE->set_url('/mod/avatar/myavatars.php');
$PAGE->set_title(get_string('myavatars', 'mod_avatar'));
$PAGE->set_heading(get_string('myavatars', 'mod_avatar'));
$PAGE->add_body_class('limitedwidth');

$isprimary = optional_param('setasprimary', null, PARAM_INT);

if ($isprimary) {
    $avatarid = required_param('avatarid', PARAM_INT);
    $useravatar = $DB->get_record('avatar_user', ['userid' => $userid, 'avatarid' => $avatarid], '*', IGNORE_MISSING);

    if ($useravatar) {
        $transaction = $DB->start_delegated_transaction();
        try {

            $DB->set_field('avatar_user', 'isprimary', 0, ['userid' => $userid]);
            $useravatar->isprimary = 1;
            $DB->update_record('avatar_user', $useravatar);

            // Profile image sync is enalbed then update the profile picture.
            if (get_config('mod_avatar', 'profileimagesync')) {
                util::update_user_profile_picture($userid, $avatarid, $useravatar->variant);
            }

            $transaction->allow_commit();

        } catch (\Exception $e) {
            $transaction->rollback($e);
        }
    } else {
        \core_notifications::add_notification(get_string('avatarnotcollected', 'mod_avatar'));
    }

    redirect($PAGE->url);
}

echo $OUTPUT->header();

$renderer = $PAGE->get_renderer('mod_avatar');
echo $renderer->display_my_avatars($userid);

$PAGE->requires->js_call_amd('mod_avatar/avatar_assign', 'pickAvatar', []);

echo $OUTPUT->footer();
