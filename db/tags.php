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
 * Tag areas definitions for mod_avatar
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$tagareas = [

    [
        'itemtype' => 'avatar',     // Name of the avatar table.
        'component' => 'mod_avatar', // Component.
        'callback' => 'mod_avatar_get_tagged_avatars', // Callback function to search for tagged items.
        'callbackfile' => '/mod/avatar/lib.php',       // File containing the callback function.
        'showstandard' => core_tag_tag::STANDARD_ONLY, // Show only standard tags.
    ],
];
