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
 * Lets plugins add buttons next to "Create avatar" on the Manage avatars page.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add buttons to the Manage avatars page.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class extend_manage_avatars {
    /** @var \single_button[] Added buttons. */
    private array $buttons = [];

    /**
     * Creates the hook.
     *
     * @param \context $context The context of the page (system).
     */
    public function __construct(
        /** @var \context The context of the page */
        public readonly \context $context,
    ) {
    }

    /**
     * Adds a button (the plugin checks the capability before adding it).
     *
     * @param \moodle_url $url Target of the button.
     * @param string $label The label.
     */
    public function add_button(\moodle_url $url, string $label): void {
        $this->buttons[] = new \single_button($url, $label, 'get');
    }

    /**
     * The added buttons.
     *
     * @return \single_button[]
     */
    public function get_buttons(): array {
        return $this->buttons;
    }
}
