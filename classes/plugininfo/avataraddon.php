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
 * Avatar addon subplugin definitions.
 *
 * @package   mod_avatar
 * @copyright 2025 bdecent GmbH <https://bdecent.de>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\plugininfo;

use core\plugininfo\base;

/**
 * Subplugin type avataraddon: addons that extend the avatar activity (in mod/avatar/addon).
 *
 * Addons extend mod_avatar through the hooks in mod_avatar\hook; mod_avatar does not know them.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class avataraddon extends base {
    /**
     * Addons are always enabled once installed.
     *
     * @return bool
     */
    public function is_enabled() {
        return true;
    }

    /**
     * Addons can be uninstalled.
     *
     * @return bool
     */
    public function is_uninstall_allowed() {
        return true;
    }

    /**
     * The name of the settings page of the addon.
     *
     * @return string
     */
    public function get_settings_section_name() {
        return 'avataraddon_' . $this->name;
    }

    /**
     * Adds the settings page of the addon (its settings.php) to the avatar category of the admin tree.
     *
     * The settings.php of an addon fills $settings (an admin_settingpage named after the addon). It may also
     * add its own pages to $ADMIN below $parentnodename and set $settings to null.
     *
     * @param \part_of_admin_tree $adminroot
     * @param string $parentnodename
     * @param bool $hassiteconfig
     */
    public function load_settings(\part_of_admin_tree $adminroot, $parentnodename, $hassiteconfig) {
        global $CFG, $USER, $DB, $OUTPUT, $PAGE; // In case settings.php wants to refer to them.
        $ADMIN = $adminroot; // May be used in settings.php.
        $plugininfo = $this; // Also can be used inside settings.php.

        if (!$this->is_installed_and_upgraded() || !$hassiteconfig || !file_exists($this->full_path('settings.php'))) {
            return;
        }

        $section = $this->get_settings_section_name();
        $settings = new \admin_settingpage($section, $this->displayname, 'moodle/site:config', $this->is_enabled() === false);
        include($this->full_path('settings.php'));

        if ($settings) {
            $ADMIN->add($parentnodename, $settings);
        }
    }
}
