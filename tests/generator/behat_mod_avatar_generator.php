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

/**
 * Behat data generator of mod_avatar.
 *
 * @package    mod_avatar
 * @category   test
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_avatar_generator extends behat_generator_base {
    /**
     * The entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'avatars' => [
                'singular' => 'avatar',
                'datagenerator' => 'avatar',
                'required' => ['name'],
            ],
            'collected avatars' => [
                'singular' => 'collected avatar',
                'datagenerator' => 'user_avatar',
                'required' => ['user', 'avatar'],
                'switchids' => ['user' => 'userid', 'avatar' => 'avatarid', 'activity' => 'cmid'],
            ],
        ];
    }

    /**
     * The id of an avatar of the library, by name.
     *
     * @param string $name The name of the avatar.
     * @return int
     */
    protected function get_avatar_id(string $name): int {
        global $DB;
        return (int) $DB->get_field('avatar_list', 'id', ['name' => $name], MUST_EXIST);
    }
}
