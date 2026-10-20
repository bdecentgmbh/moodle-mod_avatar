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
 * Lets plugins decide whether a user may collect or upgrade an avatar.
 *
 * Dispatched for every avatar shown in an activity and before an avatar is collected or upgraded.
 * Without listeners every active avatar is available.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to restrict who may collect or upgrade an avatar.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class avatar_availability {
    /** @var string[] Reasons why the avatar is not available. */
    private array $reasons = [];

    /** @var bool Whether the avatar is available. */
    private bool $available = true;

    /**
     * Creates the hook.
     *
     * @param \stdClass $avatar The avatar (record of avatar_list).
     * @param int $userid The user who would collect or upgrade it.
     * @param \cm_info|null $cm The avatar activity, null outside of an activity (e.g. automatic assignment).
     */
    public function __construct(
        /** @var \stdClass The avatar (record of avatar_list) */
        public readonly \stdClass $avatar,
        /** @var int The user who would collect or upgrade the avatar */
        public readonly int $userid,
        /** @var \cm_info|null The avatar activity */
        public readonly ?\cm_info $cm = null,
    ) {
    }

    /**
     * Marks the avatar as not available for the user.
     *
     * @param string $reason Optional reason, shown to the user (already formatted, plain text).
     */
    public function deny(string $reason = ''): void {
        $this->available = false;
        if ($reason !== '') {
            $this->reasons[] = $reason;
        }
    }

    /**
     * Whether the avatar is available.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->available;
    }

    /**
     * The reasons given by the plugins that denied the avatar.
     *
     * @return string[]
     */
    public function get_reasons(): array {
        return $this->reasons;
    }
}
