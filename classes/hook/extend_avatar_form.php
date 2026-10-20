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

namespace mod_avatar\hook;

/**
 * Lets plugins add fields to the form that creates and edits an avatar of the avatar library.
 *
 * Dispatched in definition_after_data(), after the variant fields were added. Plugins add their elements
 * (they may insert them before 'tags') and set the defaults of an existing avatar from their own storage;
 * they save them in {@see after_avatar_saved}.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add fields to the avatar form of the avatar library.')]
#[\core\attribute\tags('mod_avatar', 'form')]
final class extend_avatar_form {
    /**
     * Creates the hook.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param int $avatarid The id of the avatar, 0 for a new avatar.
     */
    public function __construct(
        /** @var \MoodleQuickForm The form */
        public readonly \MoodleQuickForm $mform,
        /** @var int The id of the avatar, 0 for a new avatar */
        public readonly int $avatarid,
    ) {
    }
}
