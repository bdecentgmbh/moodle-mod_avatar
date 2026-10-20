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
 * Lets plugins decide whether a user may collect avatars in an avatar activity at all.
 *
 * Plugins can deny collecting (e.g. a collection limit is reached) and add messages that the activity
 * shows above its avatars (e.g. how many avatars the user can still collect).
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to restrict collecting avatars in an avatar activity and to explain the restriction.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class activity_availability {
    /** @var string[] Messages shown in the activity. */
    private array $messages = [];

    /** @var bool Whether the user may collect avatars. */
    private bool $available = true;

    /**
     * Creates the hook.
     *
     * @param \cm_info $cm The avatar activity.
     * @param \stdClass $instance The activity instance (record of avatar).
     * @param int $userid The user.
     */
    public function __construct(
        /** @var \cm_info The avatar activity */
        public readonly \cm_info $cm,
        /** @var \stdClass The activity instance (record of avatar) */
        public readonly \stdClass $instance,
        /** @var int The user */
        public readonly int $userid,
    ) {
    }

    /**
     * Prevents the user from collecting avatars in this activity.
     *
     * @param string $message Optional message, shown to the user (already formatted, plain text).
     */
    public function deny(string $message = ''): void {
        $this->available = false;
        $this->add_message($message);
    }

    /**
     * Adds an informational message, shown above the avatars.
     *
     * @param string $message The message (already formatted, plain text).
     */
    public function add_message(string $message): void {
        if ($message !== '') {
            $this->messages[] = $message;
        }
    }

    /**
     * Whether the user may collect avatars in the activity.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->available;
    }

    /**
     * The messages added by the plugins.
     *
     * @return string[]
     */
    public function get_messages(): array {
        return $this->messages;
    }
}
