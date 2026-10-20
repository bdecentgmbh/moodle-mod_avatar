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

require_once('../../config.php');

require_login(null, false);

use mod_avatar\hook\extend_manage_avatars;

$tab = optional_param('tab', 'active', PARAM_ALPHA);
$tab = ($tab === 'archive') ? 'archive' : 'active';

$pageurl = new moodle_url('/mod/avatar/manage.php');
$context = \mod_avatar\util::setup_library_page(
    new moodle_url($pageurl, ['tab' => $tab]),
    get_string('manageavatars', 'mod_avatar')
);
require_capability('mod/avatar:overview', $context);

echo $OUTPUT->header();

// Create avatar and the buttons of the addons (e.g. import).
$buttons = [];
if (has_capability('mod/avatar:create', $context)) {
    $buttons[] = new single_button(
        new moodle_url('/mod/avatar/edit.php', ['action' => 'create']),
        get_string('createavatar', 'mod_avatar'),
        'get',
        single_button::BUTTON_PRIMARY
    );
}
$hook = new extend_manage_avatars($context);
\core\di::get(\core\hook\manager::class)->dispatch($hook);
$buttons = array_merge($buttons, $hook->get_buttons());
if ($buttons) {
    echo html_writer::div(implode('', array_map([$OUTPUT, 'render'], $buttons)), 'manage-avatar-buttons mb-3');
}

// Active and archive tabs.
$tabs = [
    new tabobject('active', new moodle_url($pageurl, ['tab' => 'active']), get_string('activeavatars', 'mod_avatar')),
    new tabobject('archive', new moodle_url($pageurl, ['tab' => 'archive']), get_string('archivedavatars', 'mod_avatar')),
];
echo $OUTPUT->tabtree($tabs, $tab);

if ($tab === 'archive') {
    $table = new \mod_avatar\table\archived_avatars_table('mod_avatar_archived_avatars');
} else {
    $table = new \mod_avatar\table\active_avatars_table('mod_avatar_active_avatars');
}
$table->define_baseurl(new moodle_url($pageurl, ['tab' => $tab]));
$table->out(50, true);

$PAGE->requires->js_call_amd('mod_avatar/avatar_manage', 'init', [$context->id]);

echo $OUTPUT->footer();
