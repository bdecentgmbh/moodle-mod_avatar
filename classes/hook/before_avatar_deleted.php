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
 * Dispatched before an avatar is deleted from the avatar library, so plugins can delete their data of it.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to delete their data of an avatar of the avatar library.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class before_avatar_deleted {
    /**
     * Creates the hook.
     *
     * @param \stdClass $avatar The avatar (record of avatar_list).
     */
    public function __construct(
        /** @var \stdClass The avatar (record of avatar_list) */
        public readonly \stdClass $avatar,
    ) {
    }
}
