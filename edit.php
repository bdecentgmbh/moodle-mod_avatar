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
 * Edit avatar page.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\exception\moodle_exception;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);

admin_externalpage_setup('manageavatars');

$context = context_system::instance();
$PAGE->set_context($context);

// Check if the user has the capability to manage avatars.
if ($action === 'create') {
    $PAGE->set_url(new moodle_url('/mod/avatar/edit.php', ['action' => 'create']));
    $PAGE->set_title(get_string('createavatar', 'mod_avatar'));
    $PAGE->set_heading(get_string('createavatar', 'mod_avatar'));
    // Require create capability.
    require_capability('mod/avatar:create', $context);
} else {
    $PAGE->set_url(new moodle_url('/mod/avatar/edit.php', ['id' => $id]));
    $PAGE->set_title(get_string('editavatar', 'mod_avatar'));
    $PAGE->set_heading(get_string('editavatar', 'mod_avatar'));
    // Require edit capability.
    require_capability('mod/avatar:edit', $context);
}

if ($id) {
    try {
        $avatar = \mod_avatar\avatar_manager::get_avatar($id);
    } catch (\dml_missing_record_exception $e) {
        throw new moodle_exception('invalidavatarid', 'mod_avatar');
    }
} else {
    $avatar = null;
}

$filemanageroptions = ['maxbytes' => $CFG->maxbytes, 'subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']];

$customdata = [
    'persistent' => $avatar ?? new stdClass(),
    'userid' => $USER->id,
    'context' => $context,
    'maxbytes' => $CFG->maxbytes,
];

$mform = new \mod_avatar\form\avatar_form($PAGE->url, $customdata);

if ($mform->is_cancelled()) {

    redirect(new moodle_url('/mod/avatar/manage.php'));

} else if ($data = $mform->get_data()) {
    // Prepare editor field data.
    $data = file_postupdate_standard_editor(
        $data, 'description', $mform->get_description_editor_options(), $context, 'mod_avatar', 'description', $data->id);

    $data = file_postupdate_standard_editor(
        $data, 'secretinfo', $mform->get_description_editor_options(0), $context, 'mod_avatar', 'secretinfo', $data->id);

    if (empty($data->id)) {
        $avatarid = \mod_avatar\avatar_manager::create_avatar($data);
        $avatar = \mod_avatar\avatar_manager::get_avatar($avatarid);
        $data->id = $avatarid;
    } else {
        $avatar->update($data);
    }

    // Handle file uploads.
    $avatar->handle_file_uploads($data, $context);

    redirect(new moodle_url('/mod/avatar/manage.php'), get_string('avatarsaved', 'mod_avatar'));

} else {

    if (!empty($avatar)) {

        $avatardata = $avatar->prepare_fileareas($context);
        $avatardata->tags = \core_tag_tag::get_item_tags_array('mod_avatar', 'avatar', $id);

        $avatardata = file_prepare_standard_editor($avatardata, 'description',
            $mform->get_description_editor_options(), $context, 'mod_avatar', 'description', $avatardata->id);

        $avatardata = file_prepare_standard_editor($avatardata, 'secretinfo',
            $mform->get_description_editor_options(0), $context, 'mod_avatar', 'secretinfo', $avatardata->id);

        \mod_avatar\plugininfo\avataraddon::pro_util()?->prepare_avatar_edit_data($avatardata, $context);

        $mform->set_data($avatardata);
    }
}

if (optional_param('updatevariants', false, PARAM_BOOL)) {
    $formdata = $mform->get_data();
    if ($formdata) {
        $mform->set_data($formdata);
    }
}

$id = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

if ($action && $id) {
    require_sesskey();
    $avatar = \mod_avatar\avatar_manager::get_avatar($id);
    if (!$avatar) {
        throw new moodle_exception('invalidavatarid', 'mod_avatar');
    }

    switch ($action) {
        case 'togglestatus':
            require_capability('mod/avatar:changestatus', $context);
            $avatar->toggle_status();
            redirect(new moodle_url('/mod/avatar/manage.php'), get_string('statusupdated', 'mod_avatar'));
            break;
        case 'archive':
            require_capability('mod/avatar:archive', $context);
            $avatar->archive();
            redirect(new moodle_url('/mod/avatar/manage.php'), get_string('avatararchived', 'mod_avatar'));
            break;
        case 'restore':
            require_capability('mod/avatar:archive', $context);
            $avatar->restore();
            redirect(new moodle_url('/mod/avatar/manage.php'), get_string('avatarrestored', 'mod_avatar'));
            break;
        case 'delete':
            require_capability('mod/avatar:delete', $context);
            $avatar->delete();
            redirect(new moodle_url('/mod/avatar/manage.php'), get_string('avatardeleted', 'mod_avatar'));
            break;
        default:
            // Pro addon action.
            mod_avatar\plugininfo\avataraddon::pro_util()?->manage_avatar_edit_action($action, $avatar, $context);
    }
}

echo $OUTPUT->header();

$mform->display();

echo $OUTPUT->footer();
