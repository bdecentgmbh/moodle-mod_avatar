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
 * Dispatched after an avatar of the avatar library was created or updated, so plugins can save their data.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to save their data of an avatar of the avatar library.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class after_avatar_saved {
    /**
     * Creates the hook.
     *
     * @param \stdClass $data The submitted data (form or import), with the avatar id in 'id'.
     * @param bool $created True when the avatar was created, false when it was updated.
     */
    public function __construct(
        /** @var \stdClass The submitted data, with the avatar id in 'id' */
        public readonly \stdClass $data,
        /** @var bool True when the avatar was created */
        public readonly bool $created,
    ) {
    }
}
