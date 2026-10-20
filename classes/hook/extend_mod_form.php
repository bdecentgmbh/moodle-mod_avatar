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
 * Lets plugins add settings to the avatar activity form.
 *
 * Dispatched at the end of the avatar specific part of the form, before the standard course module
 * settings. Plugins add their elements (they may insert them before 'appearancehdr') and set the
 * defaults of an existing activity from their own storage; they save them in {@see after_instance_saved}.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add settings to the avatar activity form.')]
#[\core\attribute\tags('mod_avatar', 'form')]
final class extend_mod_form {
    /**
     * Creates the hook.
     *
     * @param \MoodleQuickForm $mform The form.
     * @param int $instanceid The id of the activity instance, 0 for a new activity.
     * @param \context $context The context of the form (course, or module when editing).
     */
    public function __construct(
        /** @var \MoodleQuickForm The form */
        public readonly \MoodleQuickForm $mform,
        /** @var int The id of the activity instance, 0 for a new activity */
        public readonly int $instanceid,
        /** @var \context The context of the form */
        public readonly \context $context,
    ) {
    }
}
