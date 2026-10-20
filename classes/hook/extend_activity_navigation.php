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
 * Lets plugins add pages to the navigation of an avatar activity (the "More" menu of the activity).
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add pages to the navigation of an avatar activity.')]
#[\core\attribute\tags('mod_avatar', 'navigation')]
final class extend_activity_navigation {
    /**
     * Creates the hook.
     *
     * @param \navigation_node $node The settings node of the activity; plugins add their nodes to it.
     * @param \cm_info $cm The avatar activity.
     */
    public function __construct(
        /** @var \navigation_node The settings node of the activity */
        public readonly \navigation_node $node,
        /** @var \cm_info The avatar activity */
        public readonly \cm_info $cm,
    ) {
    }
}
