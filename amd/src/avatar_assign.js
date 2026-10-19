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
 * Pick (collect) and upgrade avatars: the buttons of the activity, the avatar page and My avatars.
 *
 * @module     mod_avatar/avatar_assign
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

const SELECTOR = '.collect-avatar[data-avatarid], .upgrade-avatar[data-avatarid]';

let initialised = false;

/**
 * Collects or upgrades the avatar of the clicked button, then reloads the page.
 *
 * @param {HTMLElement} button The clicked button.
 */
const submit = (button) => {
    const methodname = button.classList.contains('collect-avatar') ? 'mod_avatar_collect_avatar' : 'mod_avatar_upgrade_avatar';
    button.disabled = true;
    Ajax.call([{
        methodname: methodname,
        args: {avatarid: parseInt(button.dataset.avatarid), cmid: parseInt(button.dataset.cmid)},
    }])[0].then((response) => {
        if (response.success) {
            // The confirmation is shown as notification after the reload.
            window.location.reload();
        } else {
            button.disabled = false;
            Notification.addNotification({message: response.message, type: 'error'});
        }
        return response;
    }).catch((error) => {
        button.disabled = false;
        Notification.exception(error);
    });
};

/**
 * Listens to the pick and upgrade buttons of the page (once per page).
 */
export const pickAvatar = () => {
    if (initialised) {
        return;
    }
    initialised = true;
    document.addEventListener('click', (e) => {
        const button = e.target.closest(SELECTOR);
        if (button) {
            e.preventDefault();
            submit(button);
        }
    });
};
