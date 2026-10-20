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
 * Manage avatars: shows where an avatar is used (activities, courses, users) in a modal.
 *
 * @module     mod_avatar/avatar_manage
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from 'core/fragment';
import Modal from 'core/modal';
import Notification from 'core/notification';

/**
 * Opens the usage of the clicked item in a modal.
 *
 * @param {HTMLElement} item The clicked usage item.
 * @param {number} contextid The system context id.
 */
const showUsage = (item, contextid) => {
    Modal.create({
        title: item.closest('ul').dataset.title,
        body: Fragment.loadFragment('mod_avatar', 'avatar_usage', contextid,
            {type: item.dataset.type, avatarid: item.dataset.avatarid}),
        large: true,
        show: true,
        removeOnClose: true,
    }).catch(Notification.exception);
};

/**
 * Listens to the usage items of the avatar table.
 *
 * @param {number} contextid The system context id.
 */
export const init = (contextid) => {
    document.addEventListener('click', (e) => {
        const item = e.target.closest('.usage-item[data-avatarid]');
        if (item) {
            e.preventDefault();
            showUsage(item, contextid);
        }
    });
    document.addEventListener('keydown', (e) => {
        const item = e.target.closest('.usage-item[data-avatarid]');
        if (item && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            showUsage(item, contextid);
        }
    });
};
