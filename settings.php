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
 * Avatar module admin settings and defaults
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_avatar\task\collectavatar;

defined('MOODLE_INTERNAL') || die;

$ADMIN->add('modsettings', new admin_category('modavatar', new lang_string('pluginname', 'mod_avatar')));
$settings = new admin_settingpage('modsettingavatar', get_string('generalsettings', 'mod_avatar'), 'moodle/site:config', false);

if ($ADMIN->fulltree) {
    // Profile image sync settings.
    $settings->add(new admin_setting_configcheckbox('mod_avatar/profileimagesync',
        get_string('profileimagesync', 'mod_avatar'),
        get_string('profileimagesync_desc', 'mod_avatar'),
        0));

    // Add tag images filemanager.
    $settings->add(new admin_setting_configstoredfile('mod_avatar/tagimages',
        get_string('tagimages', 'mod_avatar'),
        get_string('tagimages_desc', 'mod_avatar'),
        'tagimages',
        0,
        ['maxfiles' => -1, 'accepted_types' => ['image']]
    ));
}

$ADMIN->add('modavatar', $settings);

$settings = null; // Reset the settings.

// Add link to manage avatars page.
$ADMIN->add('modavatar', new admin_externalpage('manageavatars',
    get_string('manageavatars', 'mod_avatar'), new \moodle_url('/mod/avatar/manage.php')));
