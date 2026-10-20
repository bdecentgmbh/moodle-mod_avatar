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
 * Creates and edits an avatar of the avatar library, and changes its status (enable, archive, restore, delete).
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login(null, false);

use mod_avatar\avatar_manager;
use mod_avatar\util;

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$manageurl = new moodle_url('/mod/avatar/manage.php');

// Actions on an existing avatar, from the action icons of the Manage avatars page.
if (in_array($action, ['togglestatus', 'archive', 'restore', 'delete'])) {
    $context = util::setup_library_page(
        new moodle_url('/mod/avatar/edit.php', ['id' => $id, 'action' => $action]),
        get_string('manageavatars', 'mod_avatar')
    );
    require_sesskey();

    try {
        $avatar = avatar_manager::get_avatar($id);
    } catch (\dml_missing_record_exception $e) {
        throw new moodle_exception('invalidavatarid', 'mod_avatar');
    }

    switch ($action) {
        case 'togglestatus':
            require_capability('mod/avatar:changestatus', $context);
            $avatar->toggle_status();
            redirect($manageurl, get_string('statusupdated', 'mod_avatar'));
            break;
        case 'archive':
            require_capability('mod/avatar:archive', $context);
            $avatar->archive();
            redirect($manageurl, get_string('avatararchived', 'mod_avatar'));
            break;
        case 'restore':
            require_capability('mod/avatar:archive', $context);
            $avatar->restore();
            redirect(new moodle_url($manageurl, ['tab' => 'archive']), get_string('avatarrestored', 'mod_avatar'));
            break;
        case 'delete':
            require_capability('mod/avatar:delete', $context);
            if (!$avatar->archived) {
                throw new moodle_exception('error:deletearchivedonly', 'mod_avatar');
            }
            $avatar->delete();
            redirect(new moodle_url($manageurl, ['tab' => 'archive']), get_string('avatardeleted', 'mod_avatar'));
            break;
    }
}

// The avatar form.
if ($id) {
    $url = new moodle_url('/mod/avatar/edit.php', ['id' => $id]);
    $context = util::setup_library_page($url, get_string('editavatar', 'mod_avatar'));
    require_capability('mod/avatar:edit', $context);
    try {
        $avatar = avatar_manager::get_avatar($id);
    } catch (\dml_missing_record_exception $e) {
        throw new moodle_exception('invalidavatarid', 'mod_avatar');
    }
} else {
    $url = new moodle_url('/mod/avatar/edit.php', ['action' => 'create']);
    $context = util::setup_library_page($url, get_string('createavatar', 'mod_avatar'));
    require_capability('mod/avatar:create', $context);
    $avatar = null;
}

$mform = new \mod_avatar\form\avatar_form($url, [
    'avatarid' => $avatar ? $avatar->id : 0,
    'context' => $context,
    'maxbytes' => $CFG->maxbytes,
]);

if ($mform->is_cancelled()) {
    redirect($manageurl);
} else if ($data = $mform->get_data()) {
    if ($avatar) {
        $data->id = $avatar->id;
        $avatar->update($data);
    } else {
        unset($data->id);
        $avatar = avatar_manager::get_avatar(avatar_manager::create_avatar($data));
    }
    $avatar->save_form_files($data);
    redirect($manageurl, get_string('avatarsaved', 'mod_avatar'));
} else if ($avatar && !optional_param('updatevariants', false, PARAM_BOOL)) {
    $mform->set_data($avatar->prepare_form_data());
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
