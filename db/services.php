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
 * Avatar external functions and service definitions.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_avatar_collect_avatar' => [
        'classname'     => 'mod_avatar\external',
        'methodname'    => 'collect_avatar',
        'description'   => 'Collect an avatar for the current user',
        'type'          => 'write',
        'capabilities'  => 'mod/avatar:collect',
        'ajax'          => true,
    ],
    'mod_avatar_upgrade_avatar' => [
        'classname'     => 'mod_avatar\external',
        'methodname'    => 'upgrade_avatar',
        'description'   => 'Upgrade an avatar for the current user',
        'type'          => 'write',
        'capabilities'  => 'mod/avatar:upgrade',
        'ajax'          => true,
    ],
];
