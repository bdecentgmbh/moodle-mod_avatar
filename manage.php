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
 * Manage avatars table view.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_avatar\table\avatars_filterset;

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();

if (is_siteadmin()) {
    admin_externalpage_setup('manageavatars');
}

$context = context_system::instance();
$pageurl = new moodle_url('/mod/avatar/manage.php');

$tab = optional_param('tab', 'active', PARAM_ALPHA);

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageavatars', 'mod_avatar'));
$PAGE->set_heading(get_string('manageavatars', 'mod_avatar'));

// Check if the user has the capability to manage avatars.
if (!has_capability('mod/avatar:overview', $context)) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('manageavatars', 'mod_avatar'));
}

// Page renderer.
$output = $PAGE->get_renderer('mod_avatar');

// Print header.
echo $OUTPUT->header();

// Button to create a new avatar.
$buttons = '';
if (has_capability('mod/avatar:create', $context)) {
    $createurl = new moodle_url('/mod/avatar/edit.php', ['action' => 'create']);
    $buttons = $OUTPUT->single_button($createurl, get_string('createavatar', 'mod_avatar'), 'get');
}

// Load addtional buttons from the avatar addon pro renderer.
// Buttons for create, import, export, and manage categories.
$avatarrenderer = $PAGE->get_renderer('avataraddon_pro');
$buttons = $avatarrenderer->render_additional_manage_avatar_buttons($context, $buttons);

echo $OUTPUT->box($buttons, 'generalbox manage-avatar-buttons text-right');

// Active and archive tabs.
$tabs = [
    new tabobject('active', new moodle_url($pageurl, ['tab' => 'active']), get_string('activeavatars', 'mod_avatar')),
    new tabobject('archive', new moodle_url($pageurl, ['tab' => 'archive']), get_string('archivedavatars', 'mod_avatar')),
];

echo $OUTPUT->tabtree($tabs, $tab);

$filterset = new avatars_filterset();

if ($categoryid = optional_param('category', null, PARAM_INT)) {
    $category = new \core_table\local\filter\integer_filter('category');
    $category->add_filter_value($categoryid);
    $filterset->add_filter($category);
}

// Archive table.
if ($tab == 'archive') {
    $table = new \mod_avatar\table\archived_avatars_table($context->id);
} else {
    // Active table.
    $table = new \mod_avatar\table\active_avatars_table($context->id);
}

$table->define_baseurl($pageurl);
$table->set_filterset($filterset);
$table->out(50, true);

$PAGE->requires->js_call_amd('mod_avatar/avatar_manage', 'init', []);

echo $OUTPUT->footer();
