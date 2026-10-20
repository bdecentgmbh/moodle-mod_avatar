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
 * Lets plugins add columns and row actions to the tables of the Manage avatars page.
 *
 * @package    mod_avatar
 * @copyright  2026 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to add columns and actions to the avatar tables of the Manage avatars page.')]
#[\core\attribute\tags('mod_avatar', 'avatar')]
final class extend_avatars_table {
    /** @var array[] Added columns: name => [header, renderer]. */
    private array $columns = [];

    /** @var callable[] Added row actions. */
    private array $actions = [];

    /**
     * Creates the hook.
     *
     * @param string $tab The table: 'active' or 'archive'.
     * @param \context $context The context of the page (system).
     */
    public function __construct(
        /** @var string The table: 'active' or 'archive' */
        public readonly string $tab,
        /** @var \context The context of the page */
        public readonly \context $context,
    ) {
    }

    /**
     * Adds a column, shown before the usage column.
     *
     * The renderer gets the avatar record of the row and returns the (escaped) HTML of the cell.
     *
     * @param string $name Unique column name, prefixed with the plugin name (e.g. 'availability_cohorts').
     * @param string $header The column header.
     * @param callable $renderer function(\stdClass $avatar): string
     */
    public function add_column(string $name, string $header, callable $renderer): void {
        $this->columns[$name] = [$header, $renderer];
    }

    /**
     * Adds a row action.
     *
     * The callback gets the avatar record of the row and returns the HTML of an action icon, or '' to add
     * nothing (it must check the capability itself).
     *
     * @param callable $callback function(\stdClass $avatar): string
     */
    public function add_action(callable $callback): void {
        $this->actions[] = $callback;
    }

    /**
     * The added columns.
     *
     * @return array[] name => [header, renderer]
     */
    public function get_columns(): array {
        return $this->columns;
    }

    /**
     * The added row actions.
     *
     * @return callable[]
     */
    public function get_actions(): array {
        return $this->actions;
    }
}
