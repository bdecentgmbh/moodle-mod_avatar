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
 * Avatars filter set.
 *
 * @package    mod_avatar
 * @copyright  2025 bdecent GmbH <https://bdecent.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_avatar\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;
use core_table\local\filter\string_filter;

/**
 * Avatars filter set class.
 */
class avatars_filterset extends filterset {

    /**
     * Get the required filters.
     *
     * @return array
     */
    public function get_required_filters(): array {
        return [
            'id' => integer_filter::class,
            'name' => string_filter::class,
            'idnumber' => string_filter::class,
            'coursecategories' => integer_filter::class,
            'cohorts' => integer_filter::class,
            'timecreated' => integer_filter::class,
            'timemodified' => integer_filter::class,
        ];
    }

    /**
     * Add all required filters.
     */
    protected function add_all_required_filters(): void {
        $required = $this->get_required_filters();

        foreach ($required as $name => $classname) {
            $filter = new $classname($name);
            $this->add_filter($filter);
        }
    }
}
